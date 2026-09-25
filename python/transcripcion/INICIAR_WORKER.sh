#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")"
if [ ! -x ".venv/bin/python" ]; then
  echo "No existe .venv/bin/python. Crea primero el entorno virtual."
  exit 1
fi
exec .venv/bin/python worker.py --daemon --device "${DEVIOZ_WHISPER_DEVICE:-cpu}" --compute-type "${DEVIOZ_WHISPER_COMPUTE_TYPE:-int8}"
