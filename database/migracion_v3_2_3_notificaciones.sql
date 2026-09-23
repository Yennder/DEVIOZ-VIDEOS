-- TECHFLIX V3.2.3 - Sistema de notificaciones
-- Ejecutar UNA sola vez sobre la base existente devioz_videos.
-- No elimina ni reemplaza información existente.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id_notificacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `mensaje` varchar(500) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `icono` varchar(20) NOT NULL DEFAULT '🔔',
  `clave_unica` varchar(191) DEFAULT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_leida` datetime DEFAULT NULL,
  PRIMARY KEY (`id_notificacion`),
  UNIQUE KEY `uk_notificacion_usuario_clave` (`id_usuario`,`clave_unica`),
  KEY `idx_notificaciones_usuario_estado_fecha` (`id_usuario`,`leida`,`fecha_creacion`),
  CONSTRAINT `fk_notificacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Genera un aviso inicial para asignaciones activas ya existentes, de modo que
-- los usuarios actuales también puedan probar el centro de notificaciones.
INSERT IGNORE INTO `notificaciones`
(`id_usuario`,`tipo`,`titulo`,`mensaje`,`url`,`icono`,`clave_unica`)
SELECT a.id_usuario,
       'capacitacion_asignada',
       'Capacitación asignada',
       CONCAT('Tienes asignada ',cap.nombre,'. Fecha límite: ',DATE_FORMAT(cap.fecha_limite,'%d/%m/%Y'),'.'),
       CONCAT('/DEVIOZ-VIDEOS/public/curso.php?id_asignacion=',a.id_asignacion),
       '🎓',
       CONCAT('cap_asignada:',a.id_asignacion)
FROM learning_asignaciones a
INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion=a.id_capacitacion
WHERE a.estado IN ('pendiente','en_progreso');

COMMIT;
