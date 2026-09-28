from __future__ import annotations

import argparse
import hashlib
import sys
import traceback
from typing import Iterable

import numpy as np

from common import IndexRuntime, config_value, db_connect, get_model_name, load_embedding_model, table_exists


def content_hash(segments: list[dict]) -> str:
    digest = hashlib.sha256()
    for row in segments:
        digest.update(str(row.get("id_segmento") or "").encode("utf-8"))
        digest.update(b"|")
        digest.update(str(row.get("inicio_segundos") or "0").encode("utf-8"))
        digest.update(b"|")
        digest.update(str(row.get("fin_segundos") or "0").encode("utf-8"))
        digest.update(b"|")
        digest.update(str(row.get("texto") or "").strip().encode("utf-8"))
        digest.update(b"\n")
    return digest.hexdigest()


def make_chunks(segments: list[dict], max_chars: int, max_segments: int, overlap: int) -> list[dict]:
    clean = [s for s in segments if str(s.get("texto") or "").strip()]
    chunks: list[dict] = []
    if not clean:
        return chunks

    i = 0
    order = 1
    while i < len(clean):
        j = i
        texts: list[str] = []
        char_count = 0
        while j < len(clean) and len(texts) < max_segments:
            text = str(clean[j]["texto"]).strip()
            if texts and char_count + len(text) > max_chars:
                break
            texts.append(text)
            char_count += len(text) + 1
            j += 1
        if j == i:
            j = i + 1
            texts = [str(clean[i]["texto"]).strip()]

        first = clean[i]
        last = clean[j - 1]
        text = " ".join(texts).strip()
        chunks.append(
            {
                "orden": order,
                "id_segmento_inicio": int(first["id_segmento"]),
                "id_segmento_fin": int(last["id_segmento"]),
                "inicio_segundos": float(first["inicio_segundos"]),
                "fin_segundos": float(last["fin_segundos"]),
                "texto": text,
                "hash_chunk": hashlib.sha256(text.encode("utf-8")).hexdigest(),
            }
        )
        order += 1
        if j >= len(clean):
            break
        i = max(i + 1, j - max(0, overlap))
    return chunks


def fetch_candidates(conn, video_id: int | None = None) -> list[dict]:
    sql = """
        SELECT vt.id_transcripcion, vt.id_video, vt.fecha_actualizacion,
               v.titulo, c.nombre AS categoria, s.titulo AS serie,
               rd.id_documento, rd.estado AS rag_estado,
               rd.hash_contenido, rd.modelo_embedding, rd.fecha_fuente
        FROM video_transcripciones vt
        INNER JOIN videos v ON v.id_video=vt.id_video
        INNER JOIN categorias c ON c.id_categoria=v.id_categoria
        LEFT JOIN series s ON s.id_serie=v.id_serie
        LEFT JOIN rag_documentos rd ON rd.id_transcripcion=vt.id_transcripcion
        WHERE vt.estado='completada'
    """
    params: list[object] = []
    if video_id:
        sql += " AND vt.id_video=%s "
        params.append(video_id)
    sql += " ORDER BY vt.fecha_actualizacion ASC, vt.id_video ASC "
    with conn.cursor() as cur:
        cur.execute(sql, params)
        return list(cur.fetchall())


def fetch_segments(conn, trans_id: int) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id_segmento,orden,inicio_segundos,fin_segundos,texto
            FROM video_transcripcion_segmentos
            WHERE id_transcripcion=%s
            ORDER BY orden,id_segmento
            """,
            (trans_id,),
        )
        return list(cur.fetchall())


def mark_document(conn, candidate: dict, model_name: str, state: str, message: str | None = None) -> int:
    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO rag_documentos
            (id_video,id_transcripcion,modelo_embedding,estado,mensaje_error)
            VALUES (%s,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE
                id_transcripcion=VALUES(id_transcripcion),
                modelo_embedding=VALUES(modelo_embedding),
                estado=VALUES(estado),
                mensaje_error=VALUES(mensaje_error)
            """,
            (
                int(candidate["id_video"]),
                int(candidate["id_transcripcion"]),
                model_name,
                state,
                message,
            ),
        )
        cur.execute("SELECT id_documento FROM rag_documentos WHERE id_video=%s", (int(candidate["id_video"]),))
        row = cur.fetchone()
    conn.commit()
    return int(row["id_documento"])


def index_one(conn, model, model_name: str, candidate: dict, rebuild: bool, max_chars: int, max_segments: int, overlap: int) -> int:
    trans_id = int(candidate["id_transcripcion"])
    video_id = int(candidate["id_video"])
    segments = fetch_segments(conn, trans_id)
    if not segments:
        raise RuntimeError("La transcripcion no contiene segmentos.")

    source_hash = content_hash(segments)
    same_source = (
        not rebuild
        and candidate.get("rag_estado") == "indexado"
        and candidate.get("hash_contenido") == source_hash
        and candidate.get("modelo_embedding") == model_name
    )
    if same_source:
        return -1

    document_id = mark_document(conn, candidate, model_name, "indexando")
    chunks = make_chunks(segments, max_chars=max_chars, max_segments=max_segments, overlap=overlap)
    if not chunks:
        raise RuntimeError("No fue posible construir fragmentos semanticos.")

    texts_for_embedding = [
        f"Video: {candidate['titulo']}\nCategoria: {candidate.get('categoria') or ''}\nContenido: {chunk['texto']}"
        for chunk in chunks
    ]
    embeddings = model.encode(
        texts_for_embedding,
        batch_size=16,
        show_progress_bar=False,
        normalize_embeddings=True,
        convert_to_numpy=True,
    )
    embeddings = np.asarray(embeddings, dtype=np.float32)
    dimension = int(embeddings.shape[1])

    try:
        with conn.cursor() as cur:
            cur.execute("DELETE FROM rag_chunks WHERE id_documento=%s", (document_id,))
            rows = []
            for chunk, vector in zip(chunks, embeddings):
                rows.append(
                    (
                        document_id,
                        chunk["orden"],
                        chunk["id_segmento_inicio"],
                        chunk["id_segmento_fin"],
                        chunk["inicio_segundos"],
                        chunk["fin_segundos"],
                        chunk["texto"],
                        vector.astype(np.float32).tobytes(),
                        dimension,
                        chunk["hash_chunk"],
                    )
                )
            cur.executemany(
                """
                INSERT INTO rag_chunks
                (id_documento,orden,id_segmento_inicio,id_segmento_fin,inicio_segundos,fin_segundos,texto,embedding,dimension,hash_chunk)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                """,
                rows,
            )
            cur.execute(
                """
                UPDATE rag_documentos
                SET estado='indexado', modelo_embedding=%s, dimension=%s,
                    hash_contenido=%s, total_chunks=%s, mensaje_error=NULL,
                    fecha_fuente=%s, fecha_indexado=NOW()
                WHERE id_documento=%s
                """,
                (
                    model_name,
                    dimension,
                    source_hash,
                    len(chunks),
                    candidate["fecha_actualizacion"],
                    document_id,
                ),
            )
        conn.commit()
        return len(chunks)
    except Exception:
        conn.rollback()
        raise


def main() -> int:
    parser = argparse.ArgumentParser(description="Indexador semantico local de TechFlix")
    parser.add_argument("--pending", action="store_true", help="Indexa solo contenido nuevo o modificado")
    parser.add_argument("--rebuild", action="store_true", help="Reconstruye todos los embeddings")
    parser.add_argument("--video-id", type=int, default=0, help="Indexa un video especifico")
    args = parser.parse_args()

    conn = db_connect()
    runtime: IndexRuntime | None = None
    try:
        if not table_exists(conn, "rag_documentos") or not table_exists(conn, "rag_chunks"):
            raise RuntimeError("Primero importa database/migracion_v4_1_rag.sql.")

        model_name = get_model_name(conn)
        runtime = IndexRuntime(model_name)

        max_chars = max(250, int(config_value(conn, "chunk_max_chars", "900")))
        max_segments = max(2, int(config_value(conn, "chunk_max_segments", "8")))
        overlap = max(0, min(max_segments - 1, int(config_value(conn, "chunk_overlap_segments", "1"))))

        with conn.cursor() as cur:
            cur.execute("UPDATE rag_documentos SET estado='pendiente', mensaje_error=NULL WHERE estado='indexando'")
        conn.commit()

        candidates = fetch_candidates(conn, args.video_id or None)
        runtime.update(total=len(candidates), message="Cargando modelo de embeddings...")
        if not candidates:
            runtime.complete("No hay transcripciones completadas para indexar.", 0, 0, 0)
            return 0

        model = load_embedding_model(model_name)
        runtime.update(state="indexing", message="Modelo listo. Revisando transcripciones...")

        processed = 0
        total_chunks = 0
        errors = 0
        for candidate in candidates:
            video_id = int(candidate["id_video"])
            title = str(candidate.get("titulo") or f"Video {video_id}")
            runtime.update(
                state="indexing",
                current_video=video_id,
                current_title=title,
                processed=processed,
                total=len(candidates),
                chunks=total_chunks,
                message=f"Indexando: {title}",
            )
            try:
                count = index_one(
                    conn,
                    model,
                    model_name,
                    candidate,
                    rebuild=args.rebuild,
                    max_chars=max_chars,
                    max_segments=max_segments,
                    overlap=overlap,
                )
                if count >= 0:
                    total_chunks += count
                    print(f"[TECHFLIX RAG] OK #{video_id}: {count} chunks", flush=True)
                else:
                    print(f"[TECHFLIX RAG] SIN CAMBIOS #{video_id}", flush=True)
            except Exception as exc:
                errors += 1
                print(f"[TECHFLIX RAG] ERROR #{video_id}: {exc}", file=sys.stderr, flush=True)
                try:
                    doc_id = mark_document(conn, candidate, model_name, "error", str(exc)[:4000])
                    with conn.cursor() as cur:
                        cur.execute("UPDATE rag_documentos SET estado='error',mensaje_error=%s WHERE id_documento=%s", (str(exc)[:4000], doc_id))
                    conn.commit()
                except Exception:
                    conn.rollback()
            processed += 1
            runtime.update(processed=processed, chunks=total_chunks)

        message = f"Indice actualizado: {processed - errors} videos revisados, {total_chunks} chunks generados"
        if errors:
            message += f", {errors} con error"
        runtime.complete(message + ".", processed, len(candidates), total_chunks)
        return 0 if errors == 0 else 2
    except Exception as exc:
        if runtime is not None:
            runtime.update(state="error", message=str(exc)[:500])
        print(str(exc), file=sys.stderr)
        traceback.print_exc(file=sys.stderr)
        return 1
    finally:
        conn.close()


if __name__ == "__main__":
    raise SystemExit(main())
