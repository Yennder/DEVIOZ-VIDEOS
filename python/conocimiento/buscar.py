from __future__ import annotations

import argparse
import io
import json
import os
import sys
from contextlib import redirect_stderr, redirect_stdout
from pathlib import Path

import numpy as np

# Windows/XAMPP puede iniciar Python con una pagina de codigos que no soporta
# tildes, n ni emojis presentes en titulos de videos. Forzamos UTF-8 para que
# la salida JSON sea siempre valida para PHP.
for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass

os.environ.setdefault("PYTHONUTF8", "1")
os.environ.setdefault("TOKENIZERS_PARALLELISM", "false")
os.environ.setdefault("HF_HUB_DISABLE_PROGRESS_BARS", "1")
os.environ.setdefault("TRANSFORMERS_VERBOSITY", "error")

from common import db_connect, get_model_name, load_embedding_model, table_exists


def seconds_to_label(value: float) -> str:
    total = max(0, int(round(float(value))))
    hours, remainder = divmod(total, 3600)
    minutes, seconds = divmod(remainder, 60)
    if hours:
        return f"{hours:02d}:{minutes:02d}:{seconds:02d}"
    return f"{minutes:02d}:{seconds:02d}"


def fetch_chunks(conn, model_name: str, scope: str, scope_id: int) -> list[dict]:
    sql = """
        SELECT rc.id_chunk,rc.texto,rc.embedding,rc.dimension,
               rc.inicio_segundos,rc.fin_segundos,
               v.id_video,v.titulo,c.nombre AS categoria,s.titulo AS serie
        FROM rag_chunks rc
        INNER JOIN rag_documentos rd ON rd.id_documento=rc.id_documento
        INNER JOIN videos v ON v.id_video=rd.id_video
        INNER JOIN categorias c ON c.id_categoria=v.id_categoria
        LEFT JOIN series s ON s.id_serie=v.id_serie
        WHERE rd.estado='indexado' AND rd.modelo_embedding=%s
    """
    params: list[object] = [model_name]
    if scope == "video" and scope_id > 0:
        sql += " AND v.id_video=%s "
        params.append(scope_id)
    elif scope == "curso" and scope_id > 0:
        sql += " AND EXISTS (SELECT 1 FROM learning_curso_lecciones l WHERE l.id_video=v.id_video AND l.id_curso=%s) "
        params.append(scope_id)
    elif scope == "serie" and scope_id > 0:
        sql += " AND v.id_serie=%s "
        params.append(scope_id)
    with conn.cursor() as cur:
        cur.execute(sql, params)
        return list(cur.fetchall())


def main() -> int:
    parser = argparse.ArgumentParser(description="Busqueda semantica local en TechFlix")
    parser.add_argument("--query", default="")
    parser.add_argument("--query-file", default="")
    parser.add_argument("--top-k", type=int, default=6)
    parser.add_argument("--scope", choices=["global", "video", "curso", "serie"], default="global")
    parser.add_argument("--scope-id", type=int, default=0)
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args()

    try:
        query = args.query.strip()
        if args.query_file:
            query = Path(args.query_file).read_text(encoding="utf-8").strip()
        if not query:
            raise RuntimeError("La consulta esta vacia.")

        conn = db_connect(autocommit=True)
        try:
            if not table_exists(conn, "rag_chunks"):
                raise RuntimeError("Primero importa la migracion V4.1.")
            model_name = get_model_name(conn)
            rows = fetch_chunks(conn, model_name, args.scope, args.scope_id)
        finally:
            conn.close()

        if not rows:
            payload = {"ok": True, "query": query, "model": model_name, "results": []}
            print(json.dumps(payload, ensure_ascii=False))
            return 0

        # sentence-transformers/Hugging Face pueden escribir avisos o barras de
        # progreso. La respuesta de este script debe contener SOLO JSON en stdout.
        _library_output = io.StringIO()
        with redirect_stdout(_library_output), redirect_stderr(_library_output):
            model = load_embedding_model(model_name)
            query_vector = model.encode(
                [query],
                normalize_embeddings=True,
                convert_to_numpy=True,
                show_progress_bar=False,
            )[0].astype(np.float32)

        scored: list[tuple[float, dict]] = []
        for row in rows:
            vector = np.frombuffer(row["embedding"], dtype=np.float32)
            if vector.size != int(row["dimension"]) or vector.size != query_vector.size:
                continue
            score = float(np.dot(query_vector, vector))
            scored.append((score, row))

        scored.sort(key=lambda item: item[0], reverse=True)
        results = []
        for score, row in scored[: max(1, min(20, args.top_k))]:
            start = float(row["inicio_segundos"] or 0)
            end = float(row["fin_segundos"] or start)
            results.append(
                {
                    "score": round(score, 4),
                    "score_percent": round(max(0.0, min(1.0, score)) * 100, 1),
                    "id_video": int(row["id_video"]),
                    "titulo": str(row["titulo"]),
                    "categoria": str(row.get("categoria") or ""),
                    "serie": str(row.get("serie") or ""),
                    "inicio_segundos": start,
                    "fin_segundos": end,
                    "inicio": seconds_to_label(start),
                    "fin": seconds_to_label(end),
                    "texto": str(row["texto"]),
                }
            )

        payload = {"ok": True, "query": query, "model": model_name, "results": results}
        print(json.dumps(payload, ensure_ascii=False))
        return 0
    except Exception as exc:
        payload = {"ok": False, "error": str(exc)}
        print(json.dumps(payload, ensure_ascii=False))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
