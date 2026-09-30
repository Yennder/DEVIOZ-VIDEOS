-- ============================================================
-- TECHFLIX V4.4.1 - RESUMENES AUTOMATICOS CON IA
-- Ejecutar UNA sola vez sobre la base existente devioz_videos.
-- No elimina ni reemplaza informacion existente.
-- ============================================================

USE `devioz_videos`;

CREATE TABLE IF NOT EXISTS `ai_resumenes` (
  `id_resumen` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo` enum('video','curso') NOT NULL,
  `id_video` int(11) DEFAULT NULL,
  `id_curso` int(11) DEFAULT NULL,
  `resumen` text NOT NULL,
  `puntos_clave` longtext DEFAULT NULL,
  `conceptos` longtext DEFAULT NULL,
  `tecnologias` longtext DEFAULT NULL,
  `fuente_hash` char(64) NOT NULL,
  `fuente_items` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `proveedor` varchar(40) DEFAULT NULL,
  `modelo` varchar(120) DEFAULT NULL,
  `generado_por` int(11) DEFAULT NULL,
  `fecha_generacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_resumen`),
  UNIQUE KEY `uk_ai_resumen_video` (`id_video`),
  UNIQUE KEY `uk_ai_resumen_curso` (`id_curso`),
  KEY `idx_ai_resumen_tipo_fecha` (`tipo`,`fecha_actualizacion`),
  KEY `fk_ai_resumen_generado_por` (`generado_por`),
  CONSTRAINT `fk_ai_resumen_video` FOREIGN KEY (`id_video`) REFERENCES `videos` (`id_video`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ai_resumen_curso` FOREIGN KEY (`id_curso`) REFERENCES `learning_cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ai_resumen_generado_por` FOREIGN KEY (`generado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
