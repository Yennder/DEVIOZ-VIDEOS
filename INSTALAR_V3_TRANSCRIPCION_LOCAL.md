# Instalar TECHFLIX V3 sin perder datos

## 1. Respaldo

Conserva una copia de tu proyecto actual, de `uploads` y de tu base `devioz_videos`.

## 2. Proyecto

Extrae la V3 en `C:\xampp\htdocs\DEVIOZ-VIDEOS` y fusiona tu carpeta `uploads` para conservar videos, miniaturas, portadas y logo.

## 3. Base de datos

No reemplaces tu base actual. En phpMyAdmin selecciona `devioz_videos` e importa solamente:

`database/migracion_transcripcion_v3.sql`

## 4. Python

Instala Python para Windows con la opcion `Add Python to PATH`.

Despues ejecuta:

`python\transcripcion\INSTALAR_TRANSCRIPCION.bat`

El script crea un entorno virtual local dentro del proyecto e instala las dependencias Python.

## 5. FFmpeg

Es recomendado para normalizar el audio antes de transcribir. Si `ffmpeg` esta disponible en PATH el worker lo usara automaticamente. Si no esta disponible, faster-whisper intentara leer el MP4 directamente.

## 6. Diagnostico

Ejecuta:

`python\transcripcion\PROBAR_ENTORNO.bat`

Comprueba Python, paquetes, MySQL y la migracion V3.

## 7. Worker

Ejecuta:

`python\transcripcion\INICIAR_WORKER.bat`

Deja la ventana abierta mientras quieras procesar la cola.

## 8. Primera prueba

En el administrador entra a `Transcripciones` y genera primero la transcripcion de un video corto de Docker.

No empieces con todas las temporadas hasta comprobar:

1. Estado completada.
2. Subtitulos CC.
3. Panel de transcripcion.
4. Saltos por timestamp.
5. DEVIOZ AI en modo `Este video`.

## 9. Modelo

El modelo inicial es `small`, apropiado para una primera prueba en CPU.

Puede cambiarse en:

`config/transcripcion.php`

Para GPU NVIDIA compatible, el worker puede ejecutarse posteriormente con `--device cuda --compute-type float16`.
