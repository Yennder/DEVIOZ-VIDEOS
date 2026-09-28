from __future__ import annotations

import argparse
import json
import platform
import sys

from common import db_connect, get_model_name, table_exists


def check_import(name: str):
    try:
        module = __import__(name)
        return True, str(getattr(module, "__version__", "OK"))
    except Exception as exc:
        return False, str(exc)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args()

    pymysql_ok, pymysql_version = check_import("pymysql")
    numpy_ok, numpy_version = check_import("numpy")
    st_ok, st_version = check_import("sentence_transformers")

    result = {
        "python": True,
        "python_version": platform.python_version(),
        "pymysql": pymysql_ok,
        "pymysql_version": pymysql_version,
        "numpy": numpy_ok,
        "numpy_version": numpy_version,
        "sentence_transformers": st_ok,
        "sentence_transformers_version": st_version,
        "mysql": False,
        "migration_v41": False,
        "transcripciones_completadas": 0,
        "documentos_indexados": 0,
        "chunks": 0,
        "model_name": "",
    }

    try:
        conn = db_connect(autocommit=True)
        try:
            result["mysql"] = True
            result["migration_v41"] = table_exists(conn, "rag_documentos") and table_exists(conn, "rag_chunks")
            result["model_name"] = get_model_name(conn)
            with conn.cursor() as cur:
                cur.execute("SELECT COUNT(*) total FROM video_transcripciones WHERE estado='completada'")
                result["transcripciones_completadas"] = int(cur.fetchone()["total"])
                if result["migration_v41"]:
                    cur.execute("SELECT COUNT(*) total FROM rag_documentos WHERE estado='indexado'")
                    result["documentos_indexados"] = int(cur.fetchone()["total"])
                    cur.execute("SELECT COUNT(*) total FROM rag_chunks")
                    result["chunks"] = int(cur.fetchone()["total"])
        finally:
            conn.close()
    except Exception as exc:
        result["mysql_error"] = str(exc)

    ok = all([pymysql_ok, numpy_ok, st_ok, result["mysql"], result["migration_v41"]])
    result["ok"] = ok
    if args.json:
        print(json.dumps(result, ensure_ascii=False))
    else:
        for key, value in result.items():
            print(f"{key}: {value}")
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
