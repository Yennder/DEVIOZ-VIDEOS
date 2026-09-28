from __future__ import annotations

import json
import os
import time
import threading
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import pymysql

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[1]
RUNTIME = HERE / "runtime"
STATUS_FILE = RUNTIME / "index_status.json"

DB_HOST = os.getenv("DEVIOZ_DB_HOST", "localhost")
DB_NAME = os.getenv("DEVIOZ_DB_NAME", "devioz_videos")
DB_USER = os.getenv("DEVIOZ_DB_USER", "root")
DB_PASSWORD = os.getenv("DEVIOZ_DB_PASSWORD", "")

DEFAULT_MODEL = os.getenv(
    "DEVIOZ_EMBEDDING_MODEL",
    "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2",
)


def now_iso() -> str:
    return datetime.now(timezone.utc).astimezone().isoformat(timespec="seconds")


def db_connect(autocommit: bool = False):
    return pymysql.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME,
        charset="utf8mb4",
        autocommit=autocommit,
        cursorclass=pymysql.cursors.DictCursor,
    )


def table_exists(conn, table: str) -> bool:
    with conn.cursor() as cur:
        cur.execute("SHOW TABLES LIKE %s", (table,))
        return cur.fetchone() is not None


def config_value(conn, key: str, default: str) -> str:
    if not table_exists(conn, "rag_configuracion"):
        return default
    with conn.cursor() as cur:
        cur.execute("SELECT valor FROM rag_configuracion WHERE clave=%s LIMIT 1", (key,))
        row = cur.fetchone()
    return str(row["valor"]) if row and row.get("valor") is not None else default


def get_model_name(conn=None) -> str:
    env_model = os.getenv("DEVIOZ_EMBEDDING_MODEL")
    if env_model:
        return env_model
    if conn is None:
        try:
            conn = db_connect(autocommit=True)
            try:
                return config_value(conn, "embedding_model", DEFAULT_MODEL)
            finally:
                conn.close()
        except Exception:
            return DEFAULT_MODEL
    return config_value(conn, "embedding_model", DEFAULT_MODEL)


def load_embedding_model(model_name: str):
    from sentence_transformers import SentenceTransformer

    return SentenceTransformer(model_name, device="cpu")


class IndexRuntime:
    def __init__(self, model: str):
        self.model = model
        self._lock = threading.Lock()
        self._stop = threading.Event()
        self.state: dict[str, Any] = {
            "state": "loading",
            "message": "Preparando el modelo de embeddings...",
            "current_video": None,
            "current_title": "",
            "processed": 0,
            "total": 0,
            "chunks": 0,
            "model": model,
        }
        self.started_at = now_iso()
        RUNTIME.mkdir(parents=True, exist_ok=True)
        self.write()
        self._thread = threading.Thread(target=self._heartbeat_loop, name="techflix-rag-heartbeat", daemon=True)
        self._thread.start()

    def _heartbeat_loop(self):
        while not self._stop.wait(4.0):
            self.write()

    def update(self, **kwargs):
        with self._lock:
            self.state.update(kwargs)
        self.write()

    def write(self):
        with self._lock:
            payload = dict(self.state)
        payload.update(
            {
                "pid": os.getpid(),
                "heartbeat_ts": int(time.time()),
                "heartbeat_at": now_iso(),
                "started_at": self.started_at,
            }
        )
        RUNTIME.mkdir(parents=True, exist_ok=True)
        temp = STATUS_FILE.with_suffix(".tmp")
        temp.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
        temp.replace(STATUS_FILE)

    def complete(self, message: str, processed: int, total: int, chunks: int):
        self.update(
            state="completed",
            message=message,
            current_video=None,
            current_title="",
            processed=processed,
            total=total,
            chunks=chunks,
        )
        self._stop.set()
