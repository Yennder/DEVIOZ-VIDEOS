@echo off
cd /d "%~dp0"
if not exist ".venv\Scripts\python.exe" (
  echo No existe el entorno RAG. Ejecuta INSTALAR_RAG_LOCAL.bat.
  pause
  exit /b 1
)
.venv\Scripts\python.exe diagnostico.py
pause
