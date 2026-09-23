# Instalación V3.2.3

Esta versión es un parche incremental sobre V3.2.2.

1. Haz `git status` y confirma que tu V3.2.2 ya está guardada en GitHub.
2. Copia los archivos del parche sobre `C:\xampp\htdocs\DEVIOZ-VIDEOS`, respetando las carpetas.
3. No reemplaces ni borres `uploads`.
4. En phpMyAdmin abre la base `devioz_videos`.
5. Importa solamente `database/migracion_v3_2_3_notificaciones.sql`.
6. No vuelvas a importar tu SQL completo.
7. Presiona `Ctrl + F5` en el navegador.

## Prueba mínima

- Inicia sesión con un usuario que tenga una capacitación activa: debe ver la campana y una notificación.
- Desde Admin asigna una capacitación a otro usuario: debe generarse una nueva notificación.
- Completa todas las lecciones: debe aparecer `Evaluación disponible`.
- Responde un comentario de otro usuario: el autor debe recibir una notificación.
- Abre una notificación: debe marcarse como leída y llevar al contenido correspondiente.
- Usa `Marcar todas como leídas` y verifica que el contador llegue a 0.
