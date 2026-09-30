-- ============================================================
-- TechFlix / DEVIOZ - V4.4.2
-- Generador inteligente de evaluaciones desde transcripciones
-- MariaDB 10.4+
-- ============================================================

CREATE TABLE IF NOT EXISTS `ai_evaluacion_borradores` (
  `id_borrador` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_curso` int(11) NOT NULL,
  `cantidad_solicitada` int(11) UNSIGNED NOT NULL DEFAULT 10,
  `dificultad` enum('basica','intermedia','avanzada') NOT NULL DEFAULT 'intermedia',
  `tipos` longtext NOT NULL,
  `alcance` enum('curso','lecciones') NOT NULL DEFAULT 'curso',
  `lecciones_ids` longtext DEFAULT NULL,
  `fuente_hash` char(64) NOT NULL,
  `fuente_items` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `proveedor` varchar(40) DEFAULT NULL,
  `modelo` varchar(120) DEFAULT NULL,
  `estado` enum('borrador','aprobado','descartado') NOT NULL DEFAULT 'borrador',
  `creado_por` int(11) DEFAULT NULL,
  `aprobado_por` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fecha_aprobacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_borrador`),
  KEY `idx_ai_eval_borrador_curso_estado` (`id_curso`,`estado`,`id_borrador`),
  KEY `fk_ai_eval_borrador_creador` (`creado_por`),
  KEY `fk_ai_eval_borrador_aprobador` (`aprobado_por`),
  CONSTRAINT `fk_ai_eval_borrador_curso` FOREIGN KEY (`id_curso`) REFERENCES `learning_cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ai_eval_borrador_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ai_eval_borrador_aprobador` FOREIGN KEY (`aprobado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ai_evaluacion_preguntas` (
  `id_pregunta_ia` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_borrador` bigint(20) UNSIGNED NOT NULL,
  `pregunta` varchar(800) NOT NULL,
  `tipo` enum('opcion_multiple','verdadero_falso') NOT NULL DEFAULT 'opcion_multiple',
  `dificultad` enum('basica','intermedia','avanzada') NOT NULL DEFAULT 'intermedia',
  `puntos` decimal(7,2) NOT NULL DEFAULT 1.00,
  `explicacion` text DEFAULT NULL,
  `orden` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pregunta_ia`),
  KEY `idx_ai_eval_preg_borrador_orden` (`id_borrador`,`orden`),
  CONSTRAINT `fk_ai_eval_preg_borrador` FOREIGN KEY (`id_borrador`) REFERENCES `ai_evaluacion_borradores` (`id_borrador`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ai_evaluacion_opciones` (
  `id_opcion_ia` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pregunta_ia` bigint(20) UNSIGNED NOT NULL,
  `texto` varchar(500) NOT NULL,
  `es_correcta` tinyint(1) NOT NULL DEFAULT 0,
  `orden` int(11) UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_opcion_ia`),
  KEY `idx_ai_eval_opc_preg_orden` (`id_pregunta_ia`,`orden`),
  CONSTRAINT `fk_ai_eval_opc_preg` FOREIGN KEY (`id_pregunta_ia`) REFERENCES `ai_evaluacion_preguntas` (`id_pregunta_ia`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `learning_preguntas`
  ADD COLUMN IF NOT EXISTS `origen` enum('manual','ia') NOT NULL DEFAULT 'manual' AFTER `orden`,
  ADD COLUMN IF NOT EXISTS `dificultad` enum('basica','intermedia','avanzada') DEFAULT NULL AFTER `origen`,
  ADD COLUMN IF NOT EXISTS `explicacion` text DEFAULT NULL AFTER `dificultad`,
  ADD COLUMN IF NOT EXISTS `id_borrador_ia` bigint(20) UNSIGNED DEFAULT NULL AFTER `explicacion`;

CREATE INDEX IF NOT EXISTS `idx_learning_preg_origen` ON `learning_preguntas` (`origen`);
CREATE INDEX IF NOT EXISTS `idx_learning_preg_borrador_ia` ON `learning_preguntas` (`id_borrador_ia`);
