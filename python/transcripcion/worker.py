from __future__ import annotations

import argparse
import atexit
import json
import os
import shutil
import signal
import subprocess
import sys
import tempfile
import threading
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, List, Tuple

import pymysql
from faster_whisper import WhisperModel


def configure_utf8_streams() -> None:
    """Force UTF-8 for redirected logs on Windows.

    Titles may contain emoji or other Unicode characters. Windows can otherwise
    create stdout/stderr using a legacy code page (for example cp1252), which
    raises UnicodeEncodeError before transcription even starts.
    """
    for stream in (sys.stdout, sys.stderr):
        try:
            if hasattr(stream, "reconfigure"):
                stream.reconfigure(encoding="utf-8", errors="replace", line_buffering=True)
        except Exception:
            pass


configure_utf8_streams()

ROOT = Path(__file__).resolve().parents[2]
UPLOADS = ROOT / "uploads" / "videos"
SUBTITULOS = ROOT / "uploads" / "subtitulos"
RUNTIME = Path(__file__).resolve().parent / "runtime"
STATUS_FILE = RUNTIME / "worker_status.json"

DB_HOST = os.getenv("DEVIOZ_DB_HOST", "localhost")
DB_NAME = os.getenv("DEVIOZ_DB_NAME", "devioz_videos")
DB_USER = os.getenv("DEVIOZ_DB_USER", "root")
DB_PASSWORD = os.getenv("DEVIOZ_DB_PASSWORD", "")

DEFAULT_DEVICE = os.getenv("DEVIOZ_WHISPER_DEVICE", "cpu")
DEFAULT_COMPUTE = os.getenv("DEVIOZ_WHISPER_COMPUTE_TYPE", "int8")
FFMPEG_BIN = os.getenv("DEVIOZ_FFMPEG_BIN", "") or shutil.which("ffmpeg") or ""

_model_cache: Dict[Tuple[str, str, str], WhisperModel] = {}
STOP_REQUESTED = False


def now_iso() -> str:
    return datetime.now(timezone.utc).astimezone().isoformat(timespec="seconds")


def pid_alive(pid: int) -> bool:
    if pid <= 0:
        return False
    try:
        os.kill(pid, 0)
        return True
    except PermissionError:
        return True
    except OSError:
        return False


def read_status() -> dict:
    try:
        if not STATUS_FILE.is_file():
            return {}
        return json.loads(STATUS_FILE.read_text(encoding="utf-8"))
    except Exception:
        return {}


def another_worker_active() -> bool:
    status = read_status()
    pid = int(status.get("pid") or 0)
    heartbeat = int(status.get("heartbeat_ts") or 0)
    state = str(status.get("state") or "")
    if pid == os.getpid():
        return False
    return state in {"active", "processing"} and (time.time() - heartbeat) <= 15 and pid_alive(pid)


class WorkerRuntime:
    def __init__(self, device: str, compute_type: str):
        self.device = device
        self.compute_type = compute_type
        self.started_at = now_iso()
        self._lock = threading.Lock()
        self._stop = threading.Event()
        self._thread: threading.Thread | None = None
        self._state = {
            "state": "active",
            "message": "Esperando trabajos pendientes.",
            "current_job": None,
            "current_video": None,
            "current_title": "",
            "model": "",
        }

    def start(self):
        RUNTIME.mkdir(parents=True, exist_ok=True)
        self.write()
        self._thread = threading.Thread(target=self._loop, name="techflix-heartbeat", daemon=True)
        self._thread.start()

    def _loop(self):
        while not self._stop.wait(4.0):
            self.write()

    def set(self, **kwargs):
        with self._lock:
            self._state.update(kwargs)
        self.write()

    def write(self):
        RUNTIME.mkdir(parents=True, exist_ok=True)
        with self._lock:
            data = dict(self._state)
        data.update(
            {
                "pid": os.getpid(),
                "heartbeat_ts": int(time.time()),
                "heartbeat_at": now_iso(),
                "started_at": self.started_at,
                "device": self.device,
                "compute_type": self.compute_type,
                "platform": sys.platform,
            }
        )
        temp = STATUS_FILE.with_suffix(".tmp")
        try:
            temp.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
            temp.replace(STATUS_FILE)
        except Exception:
            pass

    def stop(self, message: str = "Worker detenido."):
        self._stop.set()
        with self._lock:
            self._state.update(
                {
                    "state": "stopped",
                    "message": message,
                    "current_job": None,
                    "current_video": None,
                    "current_title": "",
                }
            )
        self.write()


runtime: WorkerRuntime | None = None


def request_stop(signum=None, frame=None):
    global STOP_REQUESTED
    STOP_REQUESTED = True
    if runtime is not None:
        runtime.set(message="Detencion solicitada. Finalizando el ciclo actual...")


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


def recover_interrupted_jobs(conn) -> int:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id_trabajo, id_transcripcion
            FROM transcripcion_trabajos
            WHERE estado='procesando'
            FOR UPDATE
            """
        )
        rows = cur.fetchall()
        if not rows:
            conn.rollback()
            return 0

        job_ids = [int(row["id_trabajo"]) for row in rows]
        trans_ids = sorted({int(row["id_transcripcion"]) for row in rows})
        job_marks = ",".join(["%s"] * len(job_ids))
        trans_marks = ",".join(["%s"] * len(trans_ids))
        cur.execute(
            f"""
            UPDATE transcripcion_trabajos
            SET estado='pendiente', progreso=0, mensaje_error=NULL,
                fecha_inicio=NULL, fecha_fin=NULL
            WHERE id_trabajo IN ({job_marks})
            """,
            job_ids,
        )
        cur.execute(
            f"""
            UPDATE video_transcripciones
            SET estado='pendiente', progreso=0, mensaje_error=NULL,
                fecha_inicio_proceso=NULL, fecha_fin_proceso=NULL
            WHERE id_transcripcion IN ({trans_marks})
            """,
            trans_ids,
        )
    conn.commit()
    return len(job_ids)


def get_model(model_name: str, device: str, compute_type: str) -> WhisperModel:
    key = (model_name, device, compute_type)
    if key not in _model_cache:
        if runtime is not None:
            runtime.set(model=model_name, message=f"Cargando modelo {model_name}...")
        print(f"[TECHFLIX] Cargando modelo {model_name} ({device}/{compute_type})...", flush=True)
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
    if runtime is not None:
        runtime.set(message=f"Transcribiendo... {progress}%")


def requeue_job(conn, job_id: int, trans_id: int):
    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE transcripcion_trabajos
            SET estado='pendiente', progreso=0, mensaje_error=NULL, fecha_inicio=NULL, fecha_fin=NULL
            WHERE id_trabajo=%s
            """,
            (job_id,),
        )
        cur.execute(
            """
            UPDATE video_transcripciones
            SET estado='pendiente', progreso=0, mensaje_error=NULL,
                fecha_inicio_proceso=NULL, fecha_fin_proceso=NULL
            WHERE id_transcripcion=%s
            """,
            (trans_id,),
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

    if runtime is not None:
        runtime.set(
            state="processing",
            current_job=job_id,
            current_video=video_id,
            current_title=str(job.get("titulo") or ""),
            model=model_name,
            message="Preparando el video...",
        )

    if not video_path.is_file():
        raise FileNotFoundError(f"No se encontro el video: {video_path}")

    print(f"[TECHFLIX] Procesando #{video_id}: {job['titulo']}", flush=True)
    update_progress(conn, job_id, trans_id, 3)

    input_path, temp_dir = maybe_extract_audio(video_path)
    if input_path != video_path:
        update_progress(conn, job_id, trans_id, 8)

    model = get_model(model_name, device, compute_type)
    update_progress(conn, job_id, trans_id, 10)
    if runtime is not None:
        runtime.set(message="Modelo cargado. Decodificando audio; el primer segmento puede tardar unos segundos...")

    try:
        generated, info = model.transcribe(
            str(input_path),
            language=language if language else None,
            vad_filter=True,
            beam_size=5,
        )

        duration = float(getattr(info, "duration", 0.0) or 0.0)
        update_progress(conn, job_id, trans_id, 12)
        segments: List[dict] = []
        full_text: List[str] = []

        for index, segment in enumerate(generated, start=1):
            if STOP_REQUESTED:
                raise RuntimeError("Proceso detenido por el administrador. El trabajo sera recuperado al reiniciar.")
            text = str(segment.text or "").strip()
            if not text:
                continue
            start = float(segment.start or 0.0)
            end = float(segment.end or start)
            segments.append({"orden": index, "inicio": start, "fin": end, "texto": text})
            full_text.append(text)

            if duration > 0:
                progress = 12 + int(min(1.0, end / duration) * 80)
            else:
                progress = min(92, 12 + len(segments))
            # Actualiza cada segmento: evita la sensacion de que el proceso se congelo
            # durante varios minutos en 10% aunque Whisper siga trabajando.
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
        print(f"[TECHFLIX] OK #{video_id}: {len(segments)} segmentos", flush=True)
    finally:
        if temp_dir is not None:
            temp_dir.cleanup()


def run_once(device: str, compute_type: str) -> bool:
    conn = db_connect()
    try:
        job = next_job(conn)
        if not job:
            if runtime is not None:
                runtime.set(
                    state="active",
                    message="Esperando trabajos pendientes.",
                    current_job=None,
                    current_video=None,
                    current_title="",
                )
            return False
        try:
            process_job(conn, job, device, compute_type)
        except Exception as exc:
            print(f"[TECHFLIX] ERROR #{job['id_video']}: {exc}", file=sys.stderr, flush=True)
            if STOP_REQUESTED:
                requeue_job(conn, int(job["id_trabajo"]), int(job["id_transcripcion"]))
            else:
                fail_job(conn, int(job["id_trabajo"]), int(job["id_transcripcion"]), str(exc))
        finally:
            if runtime is not None:
                runtime.set(
                    state="active",
                    message="Esperando trabajos pendientes.",
                    current_job=None,
                    current_video=None,
                    current_title="",
                )
        return True
    finally:
        conn.close()


def recover_on_startup() -> int:
    conn = db_connect()
    try:
        return recover_interrupted_jobs(conn)
    finally:
        conn.close()


def main():
    global runtime
    parser = argparse.ArgumentParser(description="Worker local de transcripcion para TechFlix Learning Lab")
    parser.add_argument("--once", action="store_true", help="Procesa como maximo un trabajo y termina")
    parser.add_argument("--daemon", action="store_true", help="Mantiene el worker esperando nuevos trabajos")
    parser.add_argument("--device", default=DEFAULT_DEVICE, help="cpu o cuda")
    parser.add_argument("--compute-type", default=DEFAULT_COMPUTE, help="int8, float16, etc.")
    parser.add_argument("--interval", type=float, default=3.0, help="Segundos entre consultas a la cola")
    args = parser.parse_args()

    if not args.once and not args.daemon:
        args.once = True

    if args.daemon and another_worker_active():
        print("[TECHFLIX] Ya existe un worker activo. No se iniciara una segunda instancia.", flush=True)
        return

    signal.signal(signal.SIGINT, request_stop)
    if hasattr(signal, "SIGTERM"):
        signal.signal(signal.SIGTERM, request_stop)

    runtime = WorkerRuntime(args.device, args.compute_type)
    runtime.start()
    atexit.register(lambda: runtime.stop("Worker finalizado.") if runtime is not None else None)

    print("[TECHFLIX] Worker local de transcripcion V3.3", flush=True)
    print(f"[TECHFLIX] Proyecto: {ROOT}", flush=True)
    print(f"[TECHFLIX] BD: {DB_HOST}/{DB_NAME}", flush=True)
    print(f"[TECHFLIX] Dispositivo: {args.device} / {args.compute_type}", flush=True)
    print(f"[TECHFLIX] FFmpeg: {FFMPEG_BIN if FFMPEG_BIN else 'no detectado (se usara PyAV)'}", flush=True)

    try:
        recovered = recover_on_startup()
        if recovered:
            print(f"[TECHFLIX] Recuperados {recovered} trabajo(s) interrumpidos.", flush=True)
            runtime.set(message=f"Se recuperaron {recovered} trabajo(s) interrumpidos.")

        if args.once:
            if not run_once(args.device, args.compute_type):
                print("[TECHFLIX] No hay trabajos pendientes.", flush=True)
            return

        while not STOP_REQUESTED:
            try:
                processed = run_once(args.device, args.compute_type)
                if not processed:
                    time.sleep(max(1.0, args.interval))
            except KeyboardInterrupt:
                request_stop()
            except Exception as exc:
                print(f"[TECHFLIX] Error del worker: {exc}", file=sys.stderr, flush=True)
                runtime.set(state="active", message=f"Error temporal: {str(exc)[:180]}")
                time.sleep(max(2.0, args.interval))
    finally:
        if runtime is not None:
            runtime.stop("Worker detenido.")


if __name__ == "__main__":
    main()
