@echo off
setlocal
cd /d "%~dp0"
if not exist ".venv\Scripts\python.exe" (
  echo Ejecuta primero INSTALAR_TRANSCRIPCION.bat
  pause
  exit /b 1
)
".venv\Scripts\python.exe" diagnostico.py
pause
