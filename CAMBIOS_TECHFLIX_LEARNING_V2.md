# TECHFLIX LEARNING LAB V2 - Correcciones

Esta versión parte de TECHFLIX LEARNING V1 y no requiere migración de base de datos.

## Cambios principales

- Se eliminó el completado manual de lecciones. Una lección solo se completa al alcanzar al menos 90% de progreso real del video.
- En una capacitación, la reproducción automática continúa con la siguiente lección del curso, no con un video relacionado al azar.
- Al terminar la última lección, el reproductor vuelve al curso para mostrar la evaluación final.
- Una capacitación ya no se considera completada solo por terminar sus videos. Requiere una evaluación final publicada y aprobada.
- Si un examen se publica después de haber visto los videos, el estado se reconcilia y queda En progreso hasta aprobarlo.
- Tipografía global cambiada a Manrope (con Segoe UI/Arial como respaldo).
- Corregidos los botones de Acciones en Cursos, Capacitaciones y Evaluaciones; permanecen visibles en tablas anchas.
- El tema claro/oscuro del administrador ahora se carga en todas las páginas, incluidas Usuarios y Configuración.
- Eliminado el scroll independiente del menú lateral del administrador; ahora usa el scroll general de la página.
- Eliminado el scroll independiente de la lista lateral del reproductor.
- No se modificaron las integraciones de IA (Groq/Gemini/AIManager).

## Base de datos

No hay cambios de esquema en esta V2. Conserva tu base `devioz_videos` actual. No importes una base nueva.

## Videos

El ZIP no contiene tus MP4. Antes de reemplazar el proyecto, respalda `uploads` y luego fusiona tus carpetas de videos/miniaturas/portadas/configuración con esta versión.
