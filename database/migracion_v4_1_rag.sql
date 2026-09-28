-- TECHFLIX V4.1 - Base de conocimiento + indice semantico local
-- Ejecutar UNA sola vez sobre la base existente devioz_videos.
-- No elimina tablas ni contenido existente.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `rag_documentos` (
  `id_documento` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_video` int(11) NOT NULL,
  `id_transcripcion` bigint(20) UNSIGNED NOT NULL,
  `modelo_embedding` varchar(190) NOT NULL,
  `dimension` smallint(5) UNSIGNED DEFAULT NULL,
  `estado` enum('pendiente','indexando','indexado','error') NOT NULL DEFAULT 'pendiente',
  `hash_contenido` char(64) DEFAULT NULL,
  `total_chunks` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `mensaje_error` text DEFAULT NULL,
  `fecha_fuente` datetime DEFAULT NULL,
  `fecha_indexado` datetime DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_documento`),
  UNIQUE KEY `uk_rag_documento_video` (`id_video`),
  UNIQUE KEY `uk_rag_documento_transcripcion` (`id_transcripcion`),
  KEY `idx_rag_documento_estado` (`estado`,`fecha_actualizacion`),
  CONSTRAINT `fk_rag_documento_video`
    FOREIGN KEY (`id_video`) REFERENCES `videos` (`id_video`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rag_documento_transcripcion`
    FOREIGN KEY (`id_transcripcion`) REFERENCES `video_transcripciones` (`id_transcripcion`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `rag_chunks` (
  `id_chunk` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_documento` bigint(20) UNSIGNED NOT NULL,
  `orden` int(11) UNSIGNED NOT NULL,
  `id_segmento_inicio` bigint(20) UNSIGNED DEFAULT NULL,
  `id_segmento_fin` bigint(20) UNSIGNED DEFAULT NULL,
  `inicio_segundos` decimal(12,3) NOT NULL DEFAULT 0.000,
  `fin_segundos` decimal(12,3) NOT NULL DEFAULT 0.000,
  `texto` mediumtext NOT NULL,
  `embedding` mediumblob NOT NULL,
  `dimension` smallint(5) UNSIGNED NOT NULL,
  `hash_chunk` char(64) NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_chunk`),
  UNIQUE KEY `uk_rag_chunk_documento_orden` (`id_documento`,`orden`),
  KEY `idx_rag_chunk_tiempo` (`id_documento`,`inicio_segundos`),
  CONSTRAINT `fk_rag_chunk_documento`
    FOREIGN KEY (`id_documento`) REFERENCES `rag_documentos` (`id_documento`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `rag_configuracion` (
  `clave` varchar(100) NOT NULL,
  `valor` text NOT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `rag_configuracion` (`clave`,`valor`) VALUES
('embedding_model','sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2'),
('chunk_max_chars','900'),
('chunk_max_segments','8'),
('chunk_overlap_segments','1')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);

COMMIT;
