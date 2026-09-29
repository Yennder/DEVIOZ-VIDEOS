-- TECHFLIX V4.2.1 - Flujo de solicitudes de descarga
-- Requiere que migracion_v4_2_1_descargas.sql ya haya sido ejecutada.
-- No elimina codigos ni historial existentes.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `descarga_solicitudes` (
  `id_solicitud` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_video` int(11) NOT NULL,
  `motivo` varchar(500) DEFAULT NULL,
  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `id_codigo` bigint(20) UNSIGNED DEFAULT NULL,
  `revisado_por` int(11) DEFAULT NULL,
  `motivo_rechazo` varchar(500) DEFAULT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_revision` datetime DEFAULT NULL,
  PRIMARY KEY (`id_solicitud`),
  KEY `idx_descarga_solicitud_usuario` (`id_usuario`),
  KEY `idx_descarga_solicitud_video` (`id_video`),
  KEY `idx_descarga_solicitud_estado_fecha` (`estado`,`fecha_solicitud`),
  KEY `idx_descarga_solicitud_codigo` (`id_codigo`),
  KEY `idx_descarga_solicitud_revisor` (`revisado_por`),
  CONSTRAINT `fk_descarga_solicitud_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_descarga_solicitud_video` FOREIGN KEY (`id_video`) REFERENCES `videos` (`id_video`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_descarga_solicitud_codigo` FOREIGN KEY (`id_codigo`) REFERENCES `descarga_codigos` (`id_codigo`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_descarga_solicitud_revisor` FOREIGN KEY (`revisado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `descarga_solicitud_mensajes` (
  `id_mensaje` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_solicitud` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `rol` enum('usuario','admin','sistema') NOT NULL DEFAULT 'usuario',
  `mensaje` varchar(1200) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_mensaje`),
  KEY `idx_descarga_mensaje_solicitud_fecha` (`id_solicitud`,`fecha_creacion`),
  KEY `idx_descarga_mensaje_usuario` (`id_usuario`),
  CONSTRAINT `fk_descarga_mensaje_solicitud` FOREIGN KEY (`id_solicitud`) REFERENCES `descarga_solicitudes` (`id_solicitud`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_descarga_mensaje_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
