from __future__ import annotations

import argparse
import os
import shutil
import subprocess
import sys
import tempfile
import time
from pathlib import Path
from typing import Dict, List, Tuple

import pymysql
from faster_whisper import WhisperModel

ROOT = Path(__file__).resolve().parents[2]
UPLOADS = ROOT / "uploads" / "videos"
SUBTITULOS = ROOT / "uploads" / "subtitulos"

DB_HOST = os.getenv("DEVIOZ_DB_HOST", "localhost")
DB_NAME = os.getenv("DEVIOZ_DB_NAME", "devioz_videos")
DB_USER = os.getenv("DEVIOZ_DB_USER", "root")
DB_PASSWORD = os.getenv("DEVIOZ_DB_PASSWORD", "")

DEFAULT_DEVICE = os.getenv("DEVIOZ_WHISPER_DEVICE", "cpu")
DEFAULT_COMPUTE = os.getenv("DEVIOZ_WHISPER_COMPUTE_TYPE", "int8")
FFMPEG_BIN = os.getenv("DEVIOZ_FFMPEG_BIN", "") or shutil.which("ffmpeg") or ""

_model_cache: Dict[Tuple[str, str, str], WhisperModel] = {}


def db_connect():
    return pymysql.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME,
        charset="utf8mb4",
        autocommit=False,
        cursorclass=pymysql.cursors.DictCursor,
    )


def get_model(model_name: str, device: str, compute_type: str) -> WhisperModel:
    key = (model_name, device, compute_type)
    if key not in _model_cache:
        print(f"[TECHFLIX] Cargando modelo {model_name} ({device}/{compute_type})...")
        _model_cache[key] = WhisperModel(model_name, device=device, compute_type=compute_type)
    return _model_cache[key]


def next_job(conn):
    with conn.cursor() as cur:
        cur.execute("START TRANSACTION")
        cur.execute(
            """
            SELECT j.*, vt.idioma, vt.modelo, v.archivo_video, v.titulo
            FROM transcripcion_trabajos j
            INNER JOIN video_transcripciones vt ON vt.id_transcripcion = j.id_transcripcion
            INNER JOIN videos v ON v.id_video = j.id_video
            WHERE j.estado = 'pendiente'
            ORDER BY j.fecha_creacion ASC, j.id_trabajo ASC
            LIMIT 1
            FOR UPDATE
            """
        )
        job = cur.fetchone()
        if not job:
            conn.rollback()
            return None

        cur.execute(
            """
            UPDATE transcripcion_trabajos
            SET estado='procesando', progreso=1, intentos=intentos+1,
                fecha_inicio=NOW(), mensaje_error=NULL
            WHERE id_trabajo=%s
            """,
            (job["id_trabajo"],),
        )
        cur.execute(
            """
            UPDATE video_transcripciones
            SET estado='procesando', progreso=1, mensaje_error=NULL,
                fecha_inicio_proceso=NOW(), fecha_fin_proceso=NULL
            WHERE id_transcripcion=%s
            """,
            (job["id_transcripcion"],),
        )
        conn.commit()
        return job


def update_progress(conn, job_id: int, trans_id: int, progress: int):
    progress = max(1, min(99, int(progress)))
    with conn.cursor() as cur:
        cur.execute(
            "UPDATE transcripcion_trabajos SET progreso=%s WHERE id_trabajo=%s",
            (progress, job_id),
        )
        cur.execute(
            "UPDATE video_transcripciones SET progreso=%s WHERE id_transcripcion=%s",
            (progress, trans_id),
        )
    conn.commit()


def fail_job(conn, job_id: int, trans_id: int, message: str):
    message = (message or "Error desconocido")[:4000]
    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE transcripcion_trabajos
            SET estado='error', mensaje_error=%s, fecha_fin=NOW()
            WHERE id_trabajo=%s
            """,
            (message, job_id),
        )
        cur.execute(
            """
            UPDATE video_transcripciones
            SET estado='error', progreso=0, mensaje_error=%s, fecha_fin_proceso=NOW()
            WHERE id_transcripcion=%s
            """,
            (message, trans_id),
        )
    conn.commit()


def vtt_time(seconds: float) -> str:
    seconds = max(0.0, float(seconds))
    hours = int(seconds // 3600)
    seconds -= hours * 3600
    minutes = int(seconds // 60)
    seconds -= minutes * 60
    return f"{hours:02d}:{minutes:02d}:{seconds:06.3f}"


def write_vtt(video_id: int, segments: List[dict]) -> str:
    SUBTITULOS.mkdir(parents=True, exist_ok=True)
    filename = f"video_{video_id}_es.vtt"
    path = SUBTITULOS / filename
    with path.open("w", encoding="utf-8", newline="\n") as fh:
        fh.write("WEBVTT\n\n")
        index = 1
        for segment in segments:
            text = str(segment["texto"]).strip()
            if not text:
                continue
            fh.write(f"{index}\n")
            fh.write(f"{vtt_time(segment['inicio'])} --> {vtt_time(segment['fin'])}\n")
            fh.write(text.replace("\r\n", "\n").replace("\r", "\n") + "\n\n")
            index += 1
    return filename


def maybe_extract_audio(video_path: Path) -> Tuple[Path, tempfile.TemporaryDirectory | None]:
    if not FFMPEG_BIN:
        return video_path, None

    temp_dir = tempfile.TemporaryDirectory(prefix="techflix_whisper_")
    audio_path = Path(temp_dir.name) / "audio.wav"
    command = [
        FFMPEG_BIN,
        "-y",
        "-i",
        str(video_path),
        "-vn",
        "-ac",
        "1",
        "-ar",
        "16000",
        "-c:a",
        "pcm_s16le",
        str(audio_path),
    ]
    try:
        result = subprocess.run(command, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        if result.returncode == 0 and audio_path.is_file():
            return audio_path, temp_dir
    except Exception:
        pass

    temp_dir.cleanup()
    return video_path, None


def process_job(conn, job, device: str, compute_type: str):
    job_id = int(job["id_trabajo"])
    trans_id = int(job["id_transcripcion"])
    video_id = int(job["id_video"])
    model_name = str(job.get("modelo") or "small")
    language = str(job.get("idioma") or "es")
    filename = os.path.basename(str(job["archivo_video"]))
    video_path = UPLOADS / filename

    if not video_path.is_file():
        raise FileNotFoundError(f"No se encontro el video: {video_path}")

    print(f"[TECHFLIX] Procesando #{video_id}: {job['titulo']}")
    update_progress(conn, job_id, trans_id, 3)

    input_path, temp_dir = maybe_extract_audio(video_path)
    if input_path != video_path:
        update_progress(conn, job_id, trans_id, 8)

    model = get_model(model_name, device, compute_type)
    update_progress(conn, job_id, trans_id, 10)

    try:
        generated, info = model.transcribe(
            str(input_path),
            language=language if language else None,
            vad_filter=True,
            beam_size=5,
        )

        duration = float(getattr(info, "duration", 0.0) or 0.0)
        segments: List[dict] = []
        full_text: List[str] = []

        for index, segment in enumerate(generated, start=1):
            text = str(segment.text or "").strip()
            if not text:
                continue
            start = float(segment.start or 0.0)
            end = float(segment.end or start)
            segments.append({"orden": index, "inicio": start, "fin": end, "texto": text})
            full_text.append(text)

            if duration > 0:
                progress = 10 + int(min(1.0, end / duration) * 82)
            else:
                progress = min(92, 10 + len(segments))
            if len(segments) % 4 == 0:
                update_progress(conn, job_id, trans_id, progress)

        if not segments:
            raise RuntimeError("El modelo no genero segmentos de voz para este video.")

        update_progress(conn, job_id, trans_id, 94)
        vtt_filename = write_vtt(video_id, segments)

        with conn.cursor() as cur:
            cur.execute("DELETE FROM video_transcripcion_segmentos WHERE id_transcripcion=%s", (trans_id,))
            cur.executemany(
                """
                INSERT INTO video_transcripcion_segmentos
                (id_transcripcion, orden, inicio_segundos, fin_segundos, texto)
                VALUES (%s,%s,%s,%s,%s)
                """,
                [
                    (trans_id, seg["orden"], seg["inicio"], seg["fin"], seg["texto"])
                    for seg in segments
                ],
            )
            cur.execute(
                """
                UPDATE video_transcripciones
                SET estado='completada', progreso=100, duracion_segundos=%s,
                    texto_completo=%s, vtt_archivo=%s, mensaje_error=NULL,
                    fecha_fin_proceso=NOW()
                WHERE id_transcripcion=%s
                """,
                (duration if duration > 0 else segments[-1]["fin"], "\n".join(full_text), vtt_filename, trans_id),
            )
            cur.execute(
                """
                UPDATE transcripcion_trabajos
                SET estado='completado', progreso=100, mensaje_error=NULL, fecha_fin=NOW()
                WHERE id_trabajo=%s
                """,
                (job_id,),
            )
        conn.commit()
        print(f"[TECHFLIX] OK #{video_id}: {len(segments)} segmentos")
    finally:
        if temp_dir is not None:
            temp_dir.cleanup()


def run_once(device: str, compute_type: str) -> bool:
    conn = db_connect()
    try:
        job = next_job(conn)
        if not job:
            return False
        try:
            process_job(conn, job, device, compute_type)
        except Exception as exc:
            print(f"[TECHFLIX] ERROR #{job['id_video']}: {exc}", file=sys.stderr)
            fail_job(conn, int(job["id_trabajo"]), int(job["id_transcripcion"]), str(exc))
        return True
    finally:
        conn.close()


def main():
    parser = argparse.ArgumentParser(description="Worker local de transcripcion para TechFlix Learning Lab")
    parser.add_argument("--once", action="store_true", help="Procesa como maximo un trabajo y termina")
    parser.add_argument("--daemon", action="store_true", help="Mantiene el worker esperando nuevos trabajos")
    parser.add_argument("--device", default=DEFAULT_DEVICE, help="cpu o cuda")
    parser.add_argument("--compute-type", default=DEFAULT_COMPUTE, help="int8, float16, etc.")
    parser.add_argument("--interval", type=float, default=3.0, help="Segundos entre consultas a la cola")
    args = parser.parse_args()

    if not args.once and not args.daemon:
        args.once = True

    print("[TECHFLIX] Worker local de transcripcion")
    print(f"[TECHFLIX] Proyecto: {ROOT}")
    print(f"[TECHFLIX] BD: {DB_HOST}/{DB_NAME}")
    print(f"[TECHFLIX] Dispositivo: {args.device} / {args.compute_type}")
    print(f"[TECHFLIX] FFmpeg: {FFMPEG_BIN if FFMPEG_BIN else 'no detectado (se usara PyAV)'}")

    if args.once:
        if not run_once(args.device, args.compute_type):
            print("[TECHFLIX] No hay trabajos pendientes.")
        return

    while True:
        try:
            processed = run_once(args.device, args.compute_type)
            if not processed:
                time.sleep(max(1.0, args.interval))
        except KeyboardInterrupt:
            print("\n[TECHFLIX] Worker detenido por el usuario.")
            break
        except Exception as exc:
            print(f"[TECHFLIX] Error del worker: {exc}", file=sys.stderr)
            time.sleep(max(2.0, args.interval))


if __name__ == "__main__":
    main()
