# TECHFLIX V3 - Transcripcion local, subtitulos y DEVIOZ AI contextual

Esta version conserva las funciones de la V2 y agrega una capa local de inteligencia sobre el contenido audiovisual.

## Nuevo modulo de transcripciones

- Panel administrador: `Administracion > Transcripciones`.
- Cola de procesamiento local por video.
- Opcion para encolar todos los videos que aun no tienen transcripcion.
- Estados: no generada, pendiente, procesando, completada y error.
- Progreso de procesamiento visible en el administrador.
- Edicion manual de cada segmento despues de la transcripcion automatica.
- Regeneracion de transcripciones.

## Motor local

- Python.
- `faster-whisper`.
- PyMySQL.
- FFmpeg recomendado; si no esta disponible el worker intenta decodificar el MP4 mediante PyAV/faster-whisper.
- Modelo inicial configurado: `small`.
- Perfil inicial: CPU + int8.

## Subtitulos

Al completar una transcripcion se genera automaticamente un archivo WebVTT en:

`uploads/subtitulos/video_ID_es.vtt`

El reproductor HTML5 agrega la pista como subtitulo en Espanol.

## Transcripcion en el reproductor

- Panel de transcripcion debajo de la informacion del video.
- Busqueda dentro de la transcripcion.
- Click en un segmento para saltar al momento exacto.
- El segmento actual se resalta durante la reproduccion.

## DEVIOZ AI contextual

Cuando el video tiene una transcripcion completada, DEVIOZ AI muestra dos modos:

- General: comportamiento normal de DEVIOZ AI.
- Este video: usa fragmentos relevantes de la transcripcion como fuente principal.

Las respuestas pueden incluir timestamps como `[03:12]`. Esos timestamps se convierten en botones para saltar al momento del video.

Groq sigue siendo el proveedor principal y Gemini el respaldo. La transcripcion local no reemplaza esas integraciones.

## Base de datos

Ejecutar una vez:

`database/migracion_transcripcion_v3.sql`

La migracion agrega:

- `video_transcripciones`
- `video_transcripcion_segmentos`
- `transcripcion_trabajos`

No elimina tablas anteriores.
