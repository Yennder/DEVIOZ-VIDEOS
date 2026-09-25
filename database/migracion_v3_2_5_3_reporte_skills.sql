-- TECHFLIX V3.2.5.3 - Reporte administrativo y evaluacion manual de Skills
-- Ejecutar UNA sola vez sobre devioz_videos.
-- No elimina ni modifica registros existentes.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `learning_skill_evaluaciones` (
  `id_skill_evaluacion` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_skill` int(11) NOT NULL,
  `id_evaluador` int(11) NOT NULL,
  `puntaje` decimal(5,2) NOT NULL DEFAULT 0.00,
  `nivel` enum('basico','intermedio','avanzado') NOT NULL DEFAULT 'basico',
  `comentario` varchar(1000) NOT NULL,
  `fecha_evaluacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_skill_evaluacion`),
  KEY `idx_skill_eval_usuario_skill` (`id_usuario`,`id_skill`,`fecha_evaluacion`),
  KEY `idx_skill_eval_skill` (`id_skill`),
  KEY `idx_skill_eval_evaluador` (`id_evaluador`),
  CONSTRAINT `fk_skill_eval_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_skill_eval_skill` FOREIGN KEY (`id_skill`) REFERENCES `learning_skills` (`id_skill`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_skill_eval_evaluador` FOREIGN KEY (`id_evaluador`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
