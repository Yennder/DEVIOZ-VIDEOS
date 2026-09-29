-- TECHFLIX V4.2.1 - Descargas autorizadas mediante codigo
-- Ejecutar UNA sola vez sobre la base devioz_videos existente.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `descarga_codigos` (
  `id_codigo` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo_hash` char(64) NOT NULL,
  `codigo_mascara` varchar(20) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_video` int(11) NOT NULL,
  `creado_por` int(11) NOT NULL,
  `expira_en` datetime NOT NULL,
  `max_usos` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `usos` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `estado` enum('activo','revocado') NOT NULL DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_uso` datetime DEFAULT NULL,
  PRIMARY KEY (`id_codigo`),
  UNIQUE KEY `uk_descarga_codigo_hash` (`codigo_hash`),
  KEY `idx_descarga_usuario` (`id_usuario`),
  KEY `idx_descarga_video` (`id_video`),
  KEY `idx_descarga_estado_expira` (`estado`,`expira_en`),
  CONSTRAINT `fk_descarga_codigo_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_descarga_codigo_video` FOREIGN KEY (`id_video`) REFERENCES `videos` (`id_video`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_descarga_codigo_admin` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `descarga_logs` (
  `id_log` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_codigo` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_video` int(11) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `fecha_descarga` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `idx_descarga_log_codigo` (`id_codigo`),
  KEY `idx_descarga_log_usuario` (`id_usuario`),
  KEY `idx_descarga_log_video` (`id_video`),
  CONSTRAINT `fk_descarga_log_codigo` FOREIGN KEY (`id_codigo`) REFERENCES `descarga_codigos` (`id_codigo`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_descarga_log_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_descarga_log_video` FOREIGN KEY (`id_video`) REFERENCES `videos` (`id_video`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
