-- TECHFLIX V3.2.1 - Progreso academico independiente y control de avance
-- Ejecutar UNA sola vez sobre la BD existente devioz_videos.
-- No elimina datos ni tablas.

START TRANSACTION;

ALTER TABLE learning_progreso_lecciones
    ADD COLUMN IF NOT EXISTS posicion_segundos INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER porcentaje,
    ADD COLUMN IF NOT EXISTS duracion_segundos INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER posicion_segundos,
    ADD COLUMN IF NOT EXISTS max_posicion_segundos INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER duracion_segundos;

-- Compatibilidad con el progreso ya registrado antes de V3.2.1.
-- Se copia una sola vez como punto inicial para no perder avances existentes.
UPDATE learning_progreso_lecciones pl
INNER JOIN learning_asignaciones a ON a.id_asignacion = pl.id_asignacion
INNER JOIN learning_curso_lecciones l ON l.id_leccion = pl.id_leccion
LEFT JOIN video_progreso vp
    ON vp.id_usuario = a.id_usuario
   AND vp.id_video = l.id_video
SET
    pl.posicion_segundos = CASE
        WHEN pl.posicion_segundos = 0 THEN COALESCE(vp.posicion_segundos, 0)
        ELSE pl.posicion_segundos
    END,
    pl.duracion_segundos = CASE
        WHEN pl.duracion_segundos = 0 THEN COALESCE(vp.duracion_segundos, 0)
        ELSE pl.duracion_segundos
    END,
    pl.max_posicion_segundos = CASE
        WHEN pl.max_posicion_segundos = 0 THEN COALESCE(vp.posicion_segundos, 0)
        ELSE pl.max_posicion_segundos
    END
WHERE pl.posicion_segundos = 0
   OR pl.duracion_segundos = 0
   OR pl.max_posicion_segundos = 0;

COMMIT;
