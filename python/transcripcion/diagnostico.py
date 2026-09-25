from pathlib import Path
import argparse
import importlib.metadata
import json
import os
import shutil
import sys

ROOT = Path(__file__).resolve().parents[2]


def model_cache_exists(model_name: str) -> bool:
    home = Path.home()
    cache_root = Path(os.getenv("HF_HOME", home / ".cache" / "huggingface"))
    hub = cache_root / "hub"
    candidates = [
        hub / f"models--Systran--faster-whisper-{model_name}",
        hub / f"models--guillaumekln--faster-whisper-{model_name}",
    ]
    return any(path.exists() for path in candidates)


def run() -> dict:
    model_name = os.getenv("DEVIOZ_WHISPER_MODEL", "small")
    result = {
        "python": True,
        "python_version": sys.version.split()[0],
        "project": str(ROOT),
        "videos_path": str(ROOT / "uploads" / "videos"),
        "ffmpeg": False,
        "ffmpeg_path": shutil.which("ffmpeg") or "",
        "pymysql": False,
        "pymysql_version": "",
        "faster_whisper": False,
        "faster_whisper_version": "",
        "pyav": False,
        "pyav_version": "",
        "mysql": False,
        "video_count": None,
        "migration_v3": False,
        "model_name": model_name,
        "model_cached": model_cache_exists(model_name),
        "errors": [],
    }
    result["ffmpeg"] = bool(result["ffmpeg_path"])

    try:
        import pymysql
        result["pymysql"] = True
        try:
            result["pymysql_version"] = importlib.metadata.version("PyMySQL")
        except Exception:
            pass
    except Exception as exc:
        result["errors"].append(f"PyMySQL: {exc}")

    try:
        import faster_whisper
        result["faster_whisper"] = True
        try:
            result["faster_whisper_version"] = importlib.metadata.version("faster-whisper")
        except Exception:
            pass
    except Exception as exc:
        result["errors"].append(f"faster-whisper: {exc}")

    try:
        import av
        result["pyav"] = True
        result["pyav_version"] = getattr(av, "__version__", "")
    except Exception as exc:
        result["errors"].append(f"PyAV: {exc}")

    if result["pymysql"]:
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
                result["video_count"] = int(cur.fetchone()[0])
                result["mysql"] = True
                cur.execute("SHOW TABLES LIKE 'video_transcripciones'")
                result["migration_v3"] = bool(cur.fetchone())
            conn.close()
        except Exception as exc:
            result["errors"].append(f"MySQL: {exc}")

    return result


def print_human(result: dict):
    print("TECHFLIX V3.3 - Diagnostico de transcripcion local")
    print("Python:", result["python_version"])
    print("Proyecto:", result["project"])
    print("Videos:", result["videos_path"])
    print("FFmpeg:", result["ffmpeg_path"] or "No detectado; faster-whisper puede usar PyAV")
    print("PyMySQL:", "OK" if result["pymysql"] else "ERROR")
    print("faster-whisper:", "OK" if result["faster_whisper"] else "ERROR")
    print("PyAV:", "OK" if result["pyav"] else "ERROR")
    print("MySQL:", f"OK - videos: {result['video_count']}" if result["mysql"] else "ERROR")
    print("Migracion V3:", "OK" if result["migration_v3"] else "PENDIENTE")
    print("Modelo:", result["model_name"], "- cache local OK" if result["model_cached"] else "- se descargara al primer uso")
    if result["errors"]:
        print("Detalles:")
        for error in result["errors"]:
            print(" -", error)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args()
    result = run()
    if args.json:
        print(json.dumps(result, ensure_ascii=False))
    else:
        print_human(result)


if __name__ == "__main__":
    main()
