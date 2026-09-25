@echo off
setlocal
cd /d "%~dp0"

if not exist ".venv\Scripts\python.exe" (
  echo No existe el entorno local de TechFlix.
  echo Ejecuta primero INSTALAR_TRANSCRIPCION.bat
  pause
  exit /b 1
)

echo =============================================
echo TECHFLIX V3.3 - WORKER LOCAL
echo Los trabajos interrumpidos se recuperan solos.
echo Cierra esta ventana para detenerlo.
echo =============================================
".venv\Scripts\python.exe" worker.py --daemon --device cpu --compute-type int8
pause
