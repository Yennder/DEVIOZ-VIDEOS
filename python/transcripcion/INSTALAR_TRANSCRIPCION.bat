@echo off
setlocal
cd /d "%~dp0"

echo =============================================
echo TECHFLIX V3 - INSTALADOR DE TRANSCRIPCION
echo =============================================

where py >nul 2>&1
if %errorlevel%==0 (
  set "PY=py"
) else (
  where python >nul 2>&1
  if %errorlevel%==0 (
    set "PY=python"
  ) else (
    echo ERROR: Python no esta instalado o no esta en PATH.
    echo Instala Python y vuelve a ejecutar este archivo.
    pause
    exit /b 1
  )
)

if not exist ".venv\Scripts\python.exe" (
  echo Creando entorno virtual...
  %PY% -m venv .venv
  if errorlevel 1 goto :error
)

call ".venv\Scripts\activate.bat"
python -m pip install --upgrade pip
if errorlevel 1 goto :error
pip install -r requirements.txt
if errorlevel 1 goto :error

echo.
echo Instalacion completada.
echo Ahora importa database\migracion_transcripcion_v3.sql y ejecuta INICIAR_WORKER.bat.
pause
exit /b 0

:error
echo.
echo La instalacion no pudo completarse. Revisa el mensaje anterior.
pause
exit /b 1
