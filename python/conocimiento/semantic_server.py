from __future__ import annotations

import argparse
import json
import os
import sys
import threading
import time
from collections import OrderedDict
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Any

import numpy as np

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

HOST = "127.0.0.1"
PORT = int(os.getenv("DEVIOZ_SEMANTIC_PORT", "8765"))
CACHE_TTL = 300.0
CACHE_MAX = 200
CHECK_INTERVAL = 5.0


def seconds_to_label(value: float) -> str:
    total = max(0, int(round(float(value))))
    hours, remainder = divmod(total, 3600)
    minutes, seconds = divmod(remainder, 60)
    if hours:
        return f"{hours:02d}:{minutes:02d}:{seconds:02d}"
    return f"{minutes:02d}:{seconds:02d}"


class SemanticEngine:
    def __init__(self) -> None:
        self._lock = threading.RLock()
        self.model_name = ""
        self.model = None
        self.matrix = np.empty((0, 0), dtype=np.float32)
        self.rows: list[dict[str, Any]] = []
        self.signature = ""
        self.last_check = 0.0
        self.loaded_at = 0.0
        self.cache: OrderedDict[tuple[Any, ...], tuple[float, list[dict[str, Any]]]] = OrderedDict()
        self.load_all()

    def _db_signature(self) -> tuple[str, str]:
        conn = db_connect(autocommit=True)
        try:
            if not table_exists(conn, "rag_chunks"):
                raise RuntimeError("Primero importa la migracion V4.1.")
            model_name = get_model_name(conn)
            with conn.cursor() as cur:
                cur.execute(
                    """
                    SELECT COUNT(*) AS total_chunks,
                           COALESCE(MAX(rd.fecha_actualizacion),'') AS ultima_actualizacion
                    FROM rag_chunks rc
                    INNER JOIN rag_documentos rd ON rd.id_documento=rc.id_documento
                    WHERE rd.estado='indexado' AND rd.modelo_embedding=%s
                    """,
                    (model_name,),
                )
                row = cur.fetchone() or {}
            signature = f"{model_name}|{int(row.get('total_chunks') or 0)}|{row.get('ultima_actualizacion') or ''}"
            return model_name, signature
        finally:
            conn.close()

    def load_all(self) -> None:
        started = time.perf_counter()
        conn = db_connect(autocommit=True)
        try:
            if not table_exists(conn, "rag_chunks"):
                raise RuntimeError("Primero importa la migracion V4.1.")
            model_name = get_model_name(conn)
            with conn.cursor() as cur:
                cur.execute(
                    """
                    SELECT rc.id_chunk,rc.texto,rc.embedding,rc.dimension,
                           rc.inicio_segundos,rc.fin_segundos,
                           v.id_video,v.titulo,v.id_serie,
                           c.nombre AS categoria,s.titulo AS serie
                    FROM rag_chunks rc
                    INNER JOIN rag_documentos rd ON rd.id_documento=rc.id_documento
                    INNER JOIN videos v ON v.id_video=rd.id_video
                    INNER JOIN categorias c ON c.id_categoria=v.id_categoria
                    LEFT JOIN series s ON s.id_serie=v.id_serie
                    WHERE rd.estado='indexado' AND rd.modelo_embedding=%s
                    ORDER BY rc.id_chunk
                    """,
                    (model_name,),
                )
                raw_rows = list(cur.fetchall())

            vectors: list[np.ndarray] = []
            rows: list[dict[str, Any]] = []
            dimension = 0
            for row in raw_rows:
                vector = np.frombuffer(row["embedding"], dtype=np.float32)
                expected = int(row["dimension"] or 0)
                if vector.size != expected or expected <= 0:
                    continue
                if dimension == 0:
                    dimension = expected
                if expected != dimension:
                    continue
                vectors.append(vector.copy())
                rows.append(
                    {
                        "id_chunk": int(row["id_chunk"]),
                        "id_video": int(row["id_video"]),
                        "id_serie": int(row["id_serie"] or 0),
                        "titulo": str(row["titulo"] or ""),
                        "categoria": str(row.get("categoria") or ""),
                        "serie": str(row.get("serie") or ""),
                        "inicio_segundos": float(row["inicio_segundos"] or 0),
                        "fin_segundos": float(row["fin_segundos"] or 0),
                        "texto": str(row["texto"] or ""),
                    }
                )

            matrix = np.vstack(vectors).astype(np.float32, copy=False) if vectors else np.empty((0, dimension or 0), dtype=np.float32)
            with self._lock:
                self.model_name = model_name
                if self.model is None or getattr(self.model, "_devioz_model_name", None) != model_name:
                    self.model = load_embedding_model(model_name)
                    try:
                        setattr(self.model, "_devioz_model_name", model_name)
                    except Exception:
                        pass
                self.rows = rows
                self.matrix = matrix
                _, self.signature = self._db_signature()
                self.last_check = time.monotonic()
                self.loaded_at = time.time()
                self.cache.clear()
        finally:
            conn.close()
        elapsed = int((time.perf_counter() - started) * 1000)
        print(f"[SEMANTIC] Motor listo: {len(self.rows)} chunks en {elapsed} ms", flush=True)

    def refresh_if_needed(self) -> None:
        now = time.monotonic()
        if now - self.last_check < CHECK_INTERVAL:
            return
        self.last_check = now
        try:
            model_name, signature = self._db_signature()
        except Exception as exc:
            print(f"[SEMANTIC] No se pudo verificar el indice: {exc}", flush=True)
            return
        if signature != self.signature or model_name != self.model_name:
            print("[SEMANTIC] Cambio detectado en el indice; recargando memoria...", flush=True)
            self.load_all()

    def _course_video_ids(self, course_id: int) -> set[int]:
        conn = db_connect(autocommit=True)
        try:
            if not table_exists(conn, "learning_curso_lecciones"):
                return set()
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT DISTINCT id_video FROM learning_curso_lecciones WHERE id_curso=%s",
                    (course_id,),
                )
                return {int(row["id_video"]) for row in cur.fetchall()}
        finally:
            conn.close()

    def _indices_for_scope(self, scope: str, scope_id: int) -> np.ndarray:
        if scope == "global" or scope_id <= 0:
            return np.arange(len(self.rows), dtype=np.int64)
        if scope == "video":
            return np.fromiter((i for i, row in enumerate(self.rows) if row["id_video"] == scope_id), dtype=np.int64)
        if scope == "serie":
            return np.fromiter((i for i, row in enumerate(self.rows) if row["id_serie"] == scope_id), dtype=np.int64)
        if scope == "curso":
            video_ids = self._course_video_ids(scope_id)
            return np.fromiter((i for i, row in enumerate(self.rows) if row["id_video"] in video_ids), dtype=np.int64)
        return np.arange(len(self.rows), dtype=np.int64)

    def search(self, query: str, top_k: int, scope: str, scope_id: int) -> tuple[list[dict[str, Any]], bool, float]:
        self.refresh_if_needed()
        query = query.strip()
        top_k = max(1, min(20, int(top_k)))
        scope = scope if scope in {"global", "video", "curso", "serie"} else "global"
        scope_id = max(0, int(scope_id))
        cache_key = (self.signature, query.casefold(), top_k, scope, scope_id)
        now = time.time()

        with self._lock:
            cached = self.cache.get(cache_key)
            if cached and now - cached[0] <= CACHE_TTL:
                self.cache.move_to_end(cache_key)
                return cached[1], True, 0.0

        started = time.perf_counter()
        with self._lock:
            if self.model is None or self.matrix.size == 0:
                return [], False, (time.perf_counter() - started) * 1000
            query_vector = self.model.encode(
                [query],
                normalize_embeddings=True,
                convert_to_numpy=True,
                show_progress_bar=False,
            )[0].astype(np.float32)
            indices = self._indices_for_scope(scope, scope_id)
            if indices.size == 0:
                return [], False, (time.perf_counter() - started) * 1000
            scoped_matrix = self.matrix[indices]
            scores = scoped_matrix @ query_vector
            take = min(top_k, scores.size)
            if take <= 0:
                return [], False, (time.perf_counter() - started) * 1000
            if take < scores.size:
                local = np.argpartition(scores, -take)[-take:]
                local = local[np.argsort(scores[local])[::-1]]
            else:
                local = np.argsort(scores)[::-1]

            results: list[dict[str, Any]] = []
            for local_idx in local[:take]:
                row = self.rows[int(indices[int(local_idx)])]
                score = float(scores[int(local_idx)])
                start = float(row["inicio_segundos"])
                end = float(row["fin_segundos"])
                results.append(
                    {
                        "score": round(score, 4),
                        "score_percent": round(max(0.0, min(1.0, score)) * 100, 1),
                        "id_video": int(row["id_video"]),
                        "titulo": row["titulo"],
                        "categoria": row["categoria"],
                        "serie": row["serie"],
                        "inicio_segundos": start,
                        "fin_segundos": end,
                        "inicio": seconds_to_label(start),
                        "fin": seconds_to_label(end),
                        "texto": row["texto"],
                    }
                )

            self.cache[cache_key] = (now, results)
            self.cache.move_to_end(cache_key)
            while len(self.cache) > CACHE_MAX:
                self.cache.popitem(last=False)

        return results, False, (time.perf_counter() - started) * 1000

    def health(self) -> dict[str, Any]:
        self.refresh_if_needed()
        return {
            "ok": True,
            "ready": self.model is not None,
            "model": self.model_name,
            "chunks": len(self.rows),
            "pid": os.getpid(),
            "loaded_at": self.loaded_at,
            "signature": self.signature,
        }


ENGINE: SemanticEngine | None = None


class Handler(BaseHTTPRequestHandler):
    server_version = "TechFlixSemantic/1.0"

    def log_message(self, fmt: str, *args: Any) -> None:
        return

    def _json(self, payload: dict[str, Any], status: int = 200) -> None:
        raw = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(raw)))
        self.send_header("Cache-Control", "no-store")
        self.send_header("Connection", "close")
        self.end_headers()
        self.wfile.write(raw)

    def do_GET(self) -> None:
        try:
            if self.path == "/health":
                self._json(ENGINE.health() if ENGINE else {"ok": False, "ready": False}, 200)
                return
            self._json({"ok": False, "error": "Ruta no disponible."}, 404)
        except Exception as exc:
            self._json({"ok": False, "error": str(exc)}, 500)

    def do_POST(self) -> None:
        try:
            if self.path != "/search":
                self._json({"ok": False, "error": "Ruta no disponible."}, 404)
                return
            length = min(65536, max(0, int(self.headers.get("Content-Length", "0") or 0)))
            body = self.rfile.read(length)
            data = json.loads(body.decode("utf-8")) if body else {}
            query = str(data.get("query") or "").strip()
            if not query:
                self._json({"ok": True, "results": [], "cached": False, "elapsed_ms": 0.0})
                return
            if len(query) > 500:
                raise RuntimeError("La consulta es demasiado larga.")
            results, cached, elapsed_ms = ENGINE.search(
                query=query,
                top_k=int(data.get("top_k") or 6),
                scope=str(data.get("scope") or "global"),
                scope_id=int(data.get("scope_id") or 0),
            )
            self._json(
                {
                    "ok": True,
                    "query": query,
                    "model": ENGINE.model_name,
                    "results": results,
                    "cached": cached,
                    "elapsed_ms": round(elapsed_ms, 1),
                    "chunks": len(ENGINE.rows),
                }
            )
        except Exception as exc:
            self._json({"ok": False, "error": str(exc)}, 500)


def main() -> int:
    global ENGINE
    parser = argparse.ArgumentParser(description="Motor semantico persistente de TechFlix")
    parser.add_argument("--port", type=int, default=PORT)
    args = parser.parse_args()
    port = args.port if 1024 <= int(args.port) <= 65535 else PORT
    try:
        ENGINE = SemanticEngine()
        server = ThreadingHTTPServer((HOST, port), Handler)
        print(f"[SEMANTIC] Escuchando en http://{HOST}:{port}", flush=True)
        server.serve_forever(poll_interval=0.25)
        return 0
    except KeyboardInterrupt:
        return 0
    except Exception as exc:
        print(f"[SEMANTIC] ERROR: {exc}", file=sys.stderr, flush=True)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
