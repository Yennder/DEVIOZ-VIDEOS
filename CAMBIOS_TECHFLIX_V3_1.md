# TECHFLIX V3.1 - Reproductor, Inicio, Playlists y Comunidad

## Reproductor
- Se corrige la secuencia de reproducción automática para videos independientes.
- El siguiente video se obtiene de forma determinista y no vuelve al primero al terminar la categoría.
- La barra lateral identifica visualmente cuál será el siguiente video.
- Se mantiene sin cambios el autoplay de series y Learning Lab.

## Inicio
- `Videos recientes` pasa a llamarse `Videos subidos recientemente`.
- Se agrega `Continuar viendo` para usuarios autenticados.
- Se agrega `Vistos recientemente` basado en el historial real.
- Las recomendaciones intentan priorizar contenido no visto.
- Toda la tarjeta de video puede abrir el detalle, no solo la miniatura o el título.

## Favoritos y playlists
- El reproductor unifica el botón como `♡ Favoritos`.
- Se mantiene el selector de playlists existentes.
- Se puede crear una nueva playlist directamente desde el reproductor.
- Al crearla desde el reproductor, el video actual se guarda automáticamente.

## Comentarios
- Se agregan respuestas a comentarios en un nivel de conversación.
- Los usuarios pueden editar/eliminar sus propias respuestas.
- Los administradores conservan la capacidad de eliminar comentarios/respuestas.
- Las respuestas se eliminan en cascada si se elimina el comentario principal.

## Series
- El indicador `Temporadas y capítulos` deja de parecer un botón sin acción.
- Se muestra cantidad real de temporadas y capítulos.
- Se agrega `Reproducir desde el inicio` cuando la serie tiene capítulos.

## Base de datos
- Nueva migración: `database/migracion_v3_1_interacciones.sql`.
- Solo agrega `comentarios.id_comentario_padre`, índice y clave foránea.
- No borra datos existentes.

## Fuera de alcance de V3.1
- Bloqueo de adelantar videos en cursos.
- Notificaciones de capacitaciones.
- Certificados.
- Skills de trabajadores.
- Worker robusto/recuperación automática.
- RAG, embeddings y búsqueda semántica.
- Generación automática de evaluaciones con IA.
