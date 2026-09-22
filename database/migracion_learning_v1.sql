-- ============================================================
-- TECHFLIX LEARNING LAB - MIGRACION V1
-- Agrega cursos, capacitaciones, evaluaciones, progreso y logros
-- SIN eliminar ni modificar contenido audiovisual existente.
-- Compatible con MariaDB 10.4 / PHP 8.2 / XAMPP.
-- ============================================================

USE `devioz_videos`;

CREATE TABLE IF NOT EXISTS `learning_cursos` (
  `id_curso` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `nivel` enum('principiante','intermedio','avanzado') NOT NULL DEFAULT 'principiante',
  `duracion_estimada_minutos` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `orden_secuencial` tinyint(1) NOT NULL DEFAULT 1,
  `estado` enum('borrador','publicado','archivado') NOT NULL DEFAULT 'borrador',
  `creado_por` int(11) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_curso`),
  KEY `idx_learning_cursos_estado` (`estado`),
  KEY `idx_learning_cursos_nivel` (`nivel`),
  KEY `fk_learning_curso_creador` (`creado_por`),
  CONSTRAINT `fk_learning_curso_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `learning_curso_lecciones` (
  `id_leccion` int(11) NOT NULL AUTO_INCREMENT,
  `id_curso` int(11) NOT NULL,
  `id_video` int(11) NOT NULL,
  `titulo_personalizado` varchar(180) DEFAULT NULL,
  `descripcion` varchar(700) DEFAULT NULL,
  `orden` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `obligatoria` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_leccion`),
  UNIQUE KEY `uk_learning_curso_video` (`id_curso`,`id_video`),
  KEY `idx_learning_lecciones_orden` (`id_curso`,`orden`),
  KEY `fk_learning_leccion_video` (`id_video`),
  CONSTRAINT `fk_learning_leccion_curso` FOREIGN KEY (`id_curso`) REFERENCES `learning_cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_learning_leccion_video` FOREIGN KEY (`id_video`) REFERENCES `videos` (`id_video`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `learning_capacitaciones` (
  `id_capacitacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_curso` int(11) NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_limite` date NOT NULL,
  `estado` enum('planificada','activa','cerrada') NOT NULL DEFAULT 'activa',
  `creado_por` int(11) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_capacitacion`),
  KEY `idx_learning_cap_fecha` (`estado`,`fecha_limite`),
  KEY `fk_learning_cap_curso` (`id_curso`),
  KEY `fk_learning_cap_creador` (`creado_por`),
  CONSTRAINT `fk_learning_cap_curso` FOREIGN KEY (`id_curso`) REFERENCES `learning_cursos` (`id_curso`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_learning_cap_creador` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `learning_asignaciones` (
  `id_asignacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_capacitacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `estado` enum('pendiente','en_progreso','completada','vencida') NOT NULL DEFAULT 'pendiente',
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_inicio_real` datetime DEFAULT NULL,
  `fecha_completado` datetime DEFAULT NULL,
  PRIMARY KEY (`id_asignacion`),
  UNIQUE KEY `uk_learning_cap_usuario` (`id_capacitacion`,`id_usuario`),
  KEY `idx_learning_asig_usuario_estado` (`id_usuario`,`estado`),
  KEY `idx_learning_asig_cap` (`id_capacitacion`),
  CONSTRAINT `fk_learning_asig_cap` FOREIGN KEY (`id_capacitacion`) REFERENCES `learning_capacitaciones` (`id_capacitacion`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_learning_asig_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `learning_progreso_lecciones` (
  `id_progreso_leccion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_asignacion` int(11) NOT NULL,
  `id_leccion` int(11) NOT NULL,
  `estado` enum('pendiente','en_progreso','completada') NOT NULL DEFAULT 'pendiente',
  `porcentaje` decimal(5,2) NOT NULL DEFAULT 0.00,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_completado` datetime DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_progreso_leccion`),
  UNIQUE KEY `uk_learning_progreso_asig_leccion` (`id_asignacion`,`id_leccion`),
  KEY `idx_learning_progreso_estado` (`id_asignacion`,`estado`),
  KEY `fk_learning_progreso_leccion` (`id_leccion`),
  CONSTRAINT `fk_learning_progreso_asig` FOREIGN KEY (`id_asignacion`) REFERENCES `learning_asignaciones` (`id_asignacion`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_learning_progreso_leccion` FOREIGN KEY (`id_leccion`) REFERENCES `learning_curso_lecciones` (`id_leccion`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `learning_evaluaciones` (
  `id_evaluacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_curso` int(11) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `nota_minima` decimal(5,2) NOT NULL DEFAULT 70.00,
  `intentos_permitidos` int(11) UNSIGNED NOT NULL DEFAULT 3,
  `estado` enum('borrador','publicada') NOT NULL DEFAULT 'borrador',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_evaluacion`),
  UNIQUE KEY `uk_learning_eval_curso` (`id_curso`),
  KEY `idx_learning_eval_estado` (`estado`),
  CONSTRAINT `fk_learning_eval_curso` FOREIGN KEY (`id_curso`) REFERENCES `learning_cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `learning_preguntas` (
  `id_pregunta` int(11) NOT NULL AUTO_INCREMENT,
  `id_evaluacion` int(11) NOT NULL,
  `pregunta` varchar(800) NOT NULL,
  `tipo` enum('opcion_multiple','verdadero_falso') NOT NULL DEFAULT 'opcion_multiple',
  `puntos` decimal(7,2) NOT NULL DEFAULT 1.00,
  `orden` int(11) UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_pregunta`),
  KEY `idx_learning_preg_eval_orden` (`id_evaluacion`,`orden`),
  CONSTRAINT `fk_learning_preg_eval` FOREIGN KEY (`id_evaluacion`) REFERENCES `learning_evaluaciones` (`id_evaluacion`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `learning_opciones` (
  `id_opcion` int(11) NOT NULL AUTO_INCREMENT,
  `id_pregunta` int(11) NOT NULL,
  `texto` varchar(500) NOT NULL,
  `es_correcta` tinyint(1) NOT NULL DEFAULT 0,
  `orden` int(11) UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_opcion`),
  KEY `idx_learning_opc_preg_orden` (`id_pregunta`,`orden`),
  CONSTRAINT `fk_learning_opc_preg` FOREIGN KEY (`id_pregunta`) REFERENCES `learning_preguntas` (`id_pregunta`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `learning_intentos` (
  `id_intento` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_asignacion` int(11) NOT NULL,
  `id_evaluacion` int(11) NOT NULL,
  `numero_intento` int(11) UNSIGNED NOT NULL,
  `puntos_obtenidos` decimal(9,2) NOT NULL DEFAULT 0.00,
  `puntos_totales` decimal(9,2) NOT NULL DEFAULT 0.00,
  `porcentaje` decimal(5,2) NOT NULL DEFAULT 0.00,
  `aprobado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_fin` datetime DEFAULT NULL,
  PRIMARY KEY (`id_intento`),
  UNIQUE KEY `uk_learning_intento_num` (`id_asignacion`,`id_evaluacion`,`numero_intento`),
  KEY `idx_learning_intentos_asig_fecha` (`id_asignacion`,`fecha_inicio`),
  KEY `fk_learning_intento_eval` (`id_evaluacion`),
  CONSTRAINT `fk_learning_intento_asig` FOREIGN KEY (`id_asignacion`) REFERENCES `learning_asignaciones` (`id_asignacion`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_learning_intento_eval` FOREIGN KEY (`id_evaluacion`) REFERENCES `learning_evaluaciones` (`id_evaluacion`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `learning_respuestas` (
  `id_respuesta` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_intento` bigint(20) UNSIGNED NOT NULL,
  `id_pregunta` int(11) NOT NULL,
  `id_opcion` int(11) DEFAULT NULL,
  `es_correcta` tinyint(1) NOT NULL DEFAULT 0,
  `puntos_obtenidos` decimal(7,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_respuesta`),
  UNIQUE KEY `uk_learning_resp_intento_preg` (`id_intento`,`id_pregunta`),
  KEY `fk_learning_resp_preg` (`id_pregunta`),
  KEY `fk_learning_resp_opc` (`id_opcion`),
  CONSTRAINT `fk_learning_resp_intento` FOREIGN KEY (`id_intento`) REFERENCES `learning_intentos` (`id_intento`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_learning_resp_preg` FOREIGN KEY (`id_pregunta`) REFERENCES `learning_preguntas` (`id_pregunta`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_learning_resp_opc` FOREIGN KEY (`id_opcion`) REFERENCES `learning_opciones` (`id_opcion`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `learning_logros` (
  `id_logro` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(60) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(500) NOT NULL,
  `icono` varchar(20) NOT NULL DEFAULT '🏅',
  `criterio` enum('primer_curso','cursos_completados','nota_perfecta','curso_especifico') NOT NULL,
  `valor_objetivo` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `id_curso` int(11) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_logro`),
  UNIQUE KEY `uk_learning_logro_codigo` (`codigo`),
  KEY `idx_learning_logro_estado` (`estado`),
  KEY `fk_learning_logro_curso` (`id_curso`),
  CONSTRAINT `fk_learning_logro_curso` FOREIGN KEY (`id_curso`) REFERENCES `learning_cursos` (`id_curso`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `learning_usuario_logros` (
  `id_usuario_logro` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_logro` int(11) NOT NULL,
  `id_asignacion` int(11) DEFAULT NULL,
  `fecha_obtenido` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario_logro`),
  UNIQUE KEY `uk_learning_usuario_logro` (`id_usuario`,`id_logro`),
  KEY `idx_learning_ul_usuario_fecha` (`id_usuario`,`fecha_obtenido`),
  KEY `fk_learning_ul_logro` (`id_logro`),
  KEY `fk_learning_ul_asig` (`id_asignacion`),
  CONSTRAINT `fk_learning_ul_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_learning_ul_logro` FOREIGN KEY (`id_logro`) REFERENCES `learning_logros` (`id_logro`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_learning_ul_asig` FOREIGN KEY (`id_asignacion`) REFERENCES `learning_asignaciones` (`id_asignacion`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `learning_logros`
(`codigo`,`nombre`,`descripcion`,`icono`,`criterio`,`valor_objetivo`,`estado`) VALUES
('PRIMER_CURSO','Primer curso','Completa tu primera capacitación o curso asignado.','🏅','primer_curso',1,1),
('RUTA_5','Constancia','Completa cinco capacitaciones asignadas.','🔥','cursos_completados',5,1),
('PERFECT_SCORE','Nota perfecta','Obtén 100% en una evaluación final.','🎯','nota_perfecta',100,1);
