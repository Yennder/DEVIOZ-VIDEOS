-- TECHFLIX V4.2.1 - Simplificacion de solicitudes de descarga
-- Ejecutar UNA sola vez despues de haber instalado las migraciones V4.2.1 anteriores.
-- No elimina solicitudes, codigos, logs ni mensajes historicos.

START TRANSACTION;

ALTER TABLE `descarga_solicitudes`
  ADD COLUMN IF NOT EXISTS `codigo_visible` varchar(20) DEFAULT NULL AFTER `id_codigo`;

-- Recupera codigos completos de solicitudes ya aprobadas en la version anterior,
-- donde el codigo se habia guardado dentro del mensaje automatico del sistema.
UPDATE `descarga_solicitudes` s
INNER JOIN (
    SELECT m1.id_solicitud, m1.mensaje
    FROM `descarga_solicitud_mensajes` m1
    INNER JOIN (
        SELECT id_solicitud, MAX(id_mensaje) AS id_mensaje
        FROM `descarga_solicitud_mensajes`
        WHERE rol='sistema' AND mensaje LIKE 'Solicitud aprobada. Codigo: DEV-%'
        GROUP BY id_solicitud
    ) ult ON ult.id_mensaje=m1.id_mensaje
) msg ON msg.id_solicitud=s.id_solicitud
SET s.codigo_visible = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(msg.mensaje, 'Codigo: ', -1), '. Valido', 1))
WHERE s.codigo_visible IS NULL
  AND s.estado='aprobada';

COMMIT;
