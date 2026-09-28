@echo off
setlocal
cd /d "%~dp0"

echo ===============================================
echo TECHFLIX V4.1 - INSTALACION RAG LOCAL
echo ===============================================
echo.

where py >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    py -3.11 -m venv .venv 2>nul
    if %ERRORLEVEL% NEQ 0 py -m venv .venv
) else (
    python -m venv .venv
)

if not exist ".venv\Scripts\python.exe" (
    echo ERROR: No se pudo crear el entorno virtual.
    pause
    exit /b 1
)

call .venv\Scripts\activate.bat
python -m pip install --upgrade pip
pip install -r requirements.txt

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ERROR: No se pudieron instalar las dependencias RAG.
    pause
    exit /b 1
)

echo.
echo Instalacion completada.
echo El modelo de embeddings se descargara automaticamente al primer indice o busqueda.
echo Antes de indexar, importa database\migracion_v4_1_rag.sql.
echo.
python diagnostico.py
pause
