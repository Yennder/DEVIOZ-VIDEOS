# TECHFLIX V3.2.1 - Control de aprendizaje

## Incluido
- El progreso de capacitaciones deja de depender de `video_progreso` y se registra en `learning_progreso_lecciones`.
- Ver un video desde una capacitacion ya no aumenta el contador publico de vistas ni el historial/progreso publico.
- En una leccion obligatoria no se puede adelantar a una parte que aun no fue vista.
- El usuario si puede retroceder libremente dentro del tramo ya reproducido.
- Al volver a entrar, la leccion continua desde la ultima posicion academica guardada.
- Al alcanzar 90% la leccion queda completada, conservando la regla de examen final aprobado para completar la capacitacion.
- Una leccion ya completada permite desplazamiento libre por el video.
- Se agrega validacion de servidor para limitar saltos artificiales enviados directamente al API.

## Migracion
Importar `database/migracion_v3_2_1_learning_progress.sql` sobre la base existente.
No reemplazar la BD completa.
