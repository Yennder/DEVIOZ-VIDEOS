-- TECHFLIX V3.2.5.1 - Skills manuales + deteccion automatica con DEVIOZ AI
-- Ejecutar UNA sola vez sobre la base existente devioz_videos.
-- No elimina tablas ni contenido existente.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `learning_skills` (
  `id_skill` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(60) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `categoria` varchar(80) NOT NULL DEFAULT 'General',
  `descripcion` varchar(500) DEFAULT NULL,
  `icono` varchar(20) NOT NULL DEFAULT '🧩',
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_skill`),
  UNIQUE KEY `uk_learning_skill_codigo` (`codigo`),
  UNIQUE KEY `uk_learning_skill_nombre` (`nombre`),
  KEY `idx_learning_skill_categoria_estado` (`categoria`,`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `learning_curso_skills` (
  `id_curso_skill` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_curso` int(11) NOT NULL,
  `id_skill` int(11) NOT NULL,
  `peso` decimal(5,2) NOT NULL DEFAULT 0.00,
  `nivel_objetivo` enum('basico','intermedio','avanzado') NOT NULL DEFAULT 'basico',
  `origen` enum('manual','ia') NOT NULL DEFAULT 'manual',
  `confianza_ia` decimal(5,2) DEFAULT NULL,
  `justificacion_ia` varchar(700) DEFAULT NULL,
  `proveedor_ia` varchar(30) DEFAULT NULL,
  `modelo_ia` varchar(80) DEFAULT NULL,
  `fecha_analisis_ia` datetime DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_curso_skill`),
  UNIQUE KEY `uk_learning_curso_skill` (`id_curso`,`id_skill`),
  KEY `idx_learning_curso_skill_skill` (`id_skill`),
  KEY `idx_learning_curso_skill_origen` (`origen`),
  CONSTRAINT `fk_learning_curso_skill_curso` FOREIGN KEY (`id_curso`) REFERENCES `learning_cursos` (`id_curso`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_learning_curso_skill_skill` FOREIGN KEY (`id_skill`) REFERENCES `learning_skills` (`id_skill`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Compatibilidad por si se llego a crear la tabla de una version preliminar.
ALTER TABLE `learning_curso_skills` ADD COLUMN IF NOT EXISTS `origen` enum('manual','ia') NOT NULL DEFAULT 'manual' AFTER `nivel_objetivo`;
ALTER TABLE `learning_curso_skills` ADD COLUMN IF NOT EXISTS `confianza_ia` decimal(5,2) DEFAULT NULL AFTER `origen`;
ALTER TABLE `learning_curso_skills` ADD COLUMN IF NOT EXISTS `justificacion_ia` varchar(700) DEFAULT NULL AFTER `confianza_ia`;
ALTER TABLE `learning_curso_skills` ADD COLUMN IF NOT EXISTS `proveedor_ia` varchar(30) DEFAULT NULL AFTER `justificacion_ia`;
ALTER TABLE `learning_curso_skills` ADD COLUMN IF NOT EXISTS `modelo_ia` varchar(80) DEFAULT NULL AFTER `proveedor_ia`;
ALTER TABLE `learning_curso_skills` ADD COLUMN IF NOT EXISTS `fecha_analisis_ia` datetime DEFAULT NULL AFTER `modelo_ia`;

INSERT IGNORE INTO `learning_skills` (`codigo`,`nombre`,`categoria`,`descripcion`,`icono`,`estado`) VALUES
('DOCKER','Docker','DevOps','Contenedores, imágenes, registros y operación de aplicaciones con Docker.','🐳',1),
('LINUX','Linux','Sistemas','Fundamentos y administración de sistemas Linux.','🐧',1),
('GIT','Git','Desarrollo','Control de versiones y colaboración sobre repositorios de código.','🌿',1),
('PYTHON','Python','Desarrollo','Programación y automatización con Python.','🐍',1),
('SQL','SQL','Datos','Consulta, manipulación y validación de datos relacionales.','🗄️',1),
('AZURE','Azure','Cloud','Servicios y fundamentos de Microsoft Azure.','☁️',1),
('DEVOPS','DevOps','DevOps','Prácticas de automatización, integración, entrega y operación de software.','⚙️',1),
('KUBERNETES','Kubernetes','DevOps','Orquestación y administración de cargas contenerizadas.','☸️',1),
('IA_GENERATIVA','IA Generativa','Inteligencia Artificial','Uso responsable de modelos generativos, asistentes y automatización con IA.','✨',1),
('CIBERSEGURIDAD','Ciberseguridad','Seguridad','Fundamentos de seguridad, protección de activos y buenas prácticas.','🛡️',1);

COMMIT;
