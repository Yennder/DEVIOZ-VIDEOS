-- TECHFLIX V3.2.4 - Certificados verificables
-- Ejecutar UNA sola vez sobre la base existente devioz_videos.
-- No elimina ni reemplaza información existente.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `learning_certificados` (
  `id_certificado` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_asignacion` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_curso` int(11) DEFAULT NULL,
  `id_capacitacion` int(11) DEFAULT NULL,
  `codigo` varchar(64) NOT NULL,
  `nombre_participante` varchar(150) NOT NULL,
  `curso_titulo` varchar(180) NOT NULL,
  `capacitacion_nombre` varchar(180) NOT NULL,
  `duracion_minutos` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `nota_final` decimal(5,2) NOT NULL DEFAULT 0.00,
  `fecha_finalizacion` datetime NOT NULL,
  `fecha_emision` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('valido','anulado') NOT NULL DEFAULT 'valido',
  `motivo_anulacion` varchar(500) DEFAULT NULL,
  `fecha_anulacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_certificado`),
  UNIQUE KEY `uk_learning_cert_asignacion` (`id_asignacion`),
  UNIQUE KEY `uk_learning_cert_codigo` (`codigo`),
  KEY `idx_learning_cert_usuario_fecha` (`id_usuario`,`fecha_emision`),
  KEY `idx_learning_cert_estado` (`estado`),
  CONSTRAINT `fk_learning_cert_asignacion` FOREIGN KEY (`id_asignacion`) REFERENCES `learning_asignaciones` (`id_asignacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_learning_cert_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_learning_cert_curso` FOREIGN KEY (`id_curso`) REFERENCES `learning_cursos` (`id_curso`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_learning_cert_capacitacion` FOREIGN KEY (`id_capacitacion`) REFERENCES `learning_capacitaciones` (`id_capacitacion`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Emite certificados para capacitaciones ya completadas antes de instalar V3.2.4.
INSERT IGNORE INTO `learning_certificados`
(`id_asignacion`,`id_usuario`,`id_curso`,`id_capacitacion`,`codigo`,`nombre_participante`,`curso_titulo`,`capacitacion_nombre`,`duracion_minutos`,`nota_final`,`fecha_finalizacion`)
SELECT a.id_asignacion,a.id_usuario,c.id_curso,cap.id_capacitacion,
       CONCAT('DEVIOZ-',YEAR(COALESCE(a.fecha_completado,NOW())),'-',LPAD(a.id_asignacion,6,'0'),'-',UPPER(SUBSTRING(MD5(CONCAT(a.id_asignacion,':',a.id_usuario,':',c.id_curso)),1,8))),
       u.nombre,c.titulo,cap.nombre,c.duracion_estimada_minutos,
       COALESCE(MAX(CASE WHEN i.aprobado=1 THEN i.porcentaje END),0),
       COALESCE(a.fecha_completado,NOW())
FROM learning_asignaciones a
INNER JOIN usuarios u ON u.id_usuario=a.id_usuario
INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion=a.id_capacitacion
INNER JOIN learning_cursos c ON c.id_curso=cap.id_curso
LEFT JOIN learning_evaluaciones e ON e.id_curso=c.id_curso AND e.estado='publicada'
LEFT JOIN learning_intentos i ON i.id_asignacion=a.id_asignacion AND i.id_evaluacion=e.id_evaluacion
WHERE a.estado='completada'
GROUP BY a.id_asignacion;

-- Notifica a usuarios que ya tenían una capacitación completada.
INSERT IGNORE INTO `notificaciones`
(`id_usuario`,`tipo`,`titulo`,`mensaje`,`url`,`icono`,`clave_unica`)
SELECT lc.id_usuario,'certificado_disponible','Certificado disponible',
       CONCAT('Tu certificado de ',lc.curso_titulo,' ya está disponible para descargar.'),
       CONCAT('/DEVIOZ-VIDEOS/public/certificados.php?codigo=',lc.codigo),
       '🎓',CONCAT('certificado:',lc.id_asignacion)
FROM learning_certificados lc
WHERE lc.id_usuario IS NOT NULL;

COMMIT;
