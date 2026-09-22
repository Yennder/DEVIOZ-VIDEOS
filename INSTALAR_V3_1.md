# TECHFLIX V3.1 - Instalación sin perder datos

1. Haz respaldo de tu carpeta actual `DEVIOZ-VIDEOS` y de tu base `devioz_videos`.
2. Conserva tu carpeta `uploads` actual (videos, miniaturas, series, subtítulos, logo, etc.).
3. Reemplaza el código por el contenido de este ZIP o renombra la carpeta anterior y extrae esta versión.
4. Fusiona tu `uploads` real dentro de la nueva carpeta `DEVIOZ-VIDEOS/uploads`.
5. En phpMyAdmin selecciona la base `devioz_videos`.
6. Importa **solo** `database/migracion_v3_1_interacciones.sql`.
7. No importes nuevamente un dump completo de la base.
8. Abre la plataforma y usa `Ctrl + F5` para limpiar caché de CSS/JS.

## Pruebas recomendadas

- Video independiente: activa reproducción automática y confirma que avanza por la categoría sin regresar al video anterior.
- Inicio: comprueba `Videos subidos recientemente`, `Continuar viendo` y `Vistos recientemente`.
- Tarjetas: haz clic en texto, espacios internos y miniatura; toda la tarjeta debe abrir el video.
- Favoritos: el reproductor debe usar el mismo concepto `♡ Favoritos`.
- Playlists: crea una playlist desde el reproductor y comprueba que el video queda agregado.
- Comentarios: publica un comentario y respóndelo con otro usuario.
- Serie: entra a una serie y prueba `Reproducir desde el inicio`.

## Importante

V3.1 no modifica el motor de transcripción, faster-whisper, DEVIOZ AI, Groq ni Gemini.
