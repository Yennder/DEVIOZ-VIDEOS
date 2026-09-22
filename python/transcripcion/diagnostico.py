from pathlib import Path
import os
import shutil
import sys

ROOT = Path(__file__).resolve().parents[2]

print("TECHFLIX V3 - Diagnostico de transcripcion local")
print("Python:", sys.version.split()[0])
print("Proyecto:", ROOT)
print("Videos:", ROOT / "uploads" / "videos")
print("FFmpeg:", shutil.which("ffmpeg") or "No detectado; faster-whisper puede usar PyAV")

try:
    import pymysql
    print("PyMySQL: OK")
except Exception as exc:
    print("PyMySQL: ERROR", exc)

try:
    import faster_whisper
    print("faster-whisper: OK")
except Exception as exc:
    print("faster-whisper: ERROR", exc)

try:
    import pymysql
    conn = pymysql.connect(
        host=os.getenv("DEVIOZ_DB_HOST", "localhost"),
        user=os.getenv("DEVIOZ_DB_USER", "root"),
        password=os.getenv("DEVIOZ_DB_PASSWORD", ""),
        database=os.getenv("DEVIOZ_DB_NAME", "devioz_videos"),
        charset="utf8mb4",
    )
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) FROM videos")
        print("MySQL: OK - videos:", cur.fetchone()[0])
        cur.execute("SHOW TABLES LIKE 'video_transcripciones'")
        print("Migracion V3:", "OK" if cur.fetchone() else "PENDIENTE")
    conn.close()
except Exception as exc:
    print("MySQL: ERROR", exc)
