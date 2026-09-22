-- TECHFLIX V3 - Transcripcion local + subtitulos + contexto DEVIOZ AI
-- Ejecutar UNA sola vez sobre la base existente devioz_videos.
-- No elimina tablas ni contenido existente.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `video_transcripciones` (
  `id_transcripcion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_video` int(11) NOT NULL,
  `idioma` varchar(10) NOT NULL DEFAULT 'es',
  `proveedor` varchar(60) NOT NULL DEFAULT 'faster-whisper',
  `modelo` varchar(60) NOT NULL DEFAULT 'small',
  `estado` enum('pendiente','procesando','completada','error') NOT NULL DEFAULT 'pendiente',
  `progreso` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `duracion_segundos` decimal(12,3) DEFAULT NULL,
  `texto_completo` longtext DEFAULT NULL,
  `vtt_archivo` varchar(255) DEFAULT NULL,
  `mensaje_error` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_inicio_proceso` datetime DEFAULT NULL,
  `fecha_fin_proceso` datetime DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_transcripcion`),
  UNIQUE KEY `uk_video_transcripcion_video` (`id_video`),
  KEY `idx_video_transcripcion_estado` (`estado`,`fecha_actualizacion`),
  CONSTRAINT `fk_video_transcripcion_video`
    FOREIGN KEY (`id_video`) REFERENCES `videos` (`id_video`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `video_transcripcion_segmentos` (
  `id_segmento` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_transcripcion` bigint(20) UNSIGNED NOT NULL,
  `orden` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `inicio_segundos` decimal(12,3) NOT NULL DEFAULT 0.000,
  `fin_segundos` decimal(12,3) NOT NULL DEFAULT 0.000,
  `texto` text NOT NULL,
  PRIMARY KEY (`id_segmento`),
  KEY `idx_transcripcion_segmentos_orden` (`id_transcripcion`,`orden`),
  KEY `idx_transcripcion_segmentos_tiempo` (`id_transcripcion`,`inicio_segundos`),
  CONSTRAINT `fk_transcripcion_segmento_transcripcion`
    FOREIGN KEY (`id_transcripcion`) REFERENCES `video_transcripciones` (`id_transcripcion`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `transcripcion_trabajos` (
  `id_trabajo` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_video` int(11) NOT NULL,
  `id_transcripcion` bigint(20) UNSIGNED NOT NULL,
  `estado` enum('pendiente','procesando','completado','error','cancelado') NOT NULL DEFAULT 'pendiente',
  `progreso` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `intentos` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `mensaje_error` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_trabajo`),
  KEY `idx_transcripcion_trabajo_estado` (`estado`,`fecha_creacion`),
  KEY `idx_transcripcion_trabajo_video` (`id_video`,`estado`),
  KEY `fk_transcripcion_trabajo_transcripcion` (`id_transcripcion`),
  CONSTRAINT `fk_transcripcion_trabajo_video`
    FOREIGN KEY (`id_video`) REFERENCES `videos` (`id_video`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_transcripcion_trabajo_transcripcion`
    FOREIGN KEY (`id_transcripcion`) REFERENCES `video_transcripciones` (`id_transcripcion`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
