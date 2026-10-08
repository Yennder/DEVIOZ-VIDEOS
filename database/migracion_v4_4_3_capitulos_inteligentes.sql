-- TECHFLIX V4.4.3 - Capitulos inteligentes y navegacion por escenas
-- Ejecutar una sola vez sobre la base devioz_videos.

CREATE TABLE IF NOT EXISTS ai_capitulos_generaciones (
    id_generacion INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_video INT NOT NULL,
    fuente_hash CHAR(64) NOT NULL,
    fuente_segmentos INT UNSIGNED NOT NULL DEFAULT 0,
    duracion_segundos DECIMAL(10,3) NOT NULL DEFAULT 0,
    estado ENUM('borrador','publicada','archivada') NOT NULL DEFAULT 'borrador',
    proveedor VARCHAR(40) NULL,
    modelo VARCHAR(120) NULL,
    generado_por INT NULL,
    publicado_por INT NULL,
    fecha_generacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_publicacion DATETIME NULL,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_generacion),
    KEY idx_ai_capitulos_video_estado (id_video, estado, id_generacion),
    KEY idx_ai_capitulos_generado_por (generado_por),
    KEY idx_ai_capitulos_publicado_por (publicado_por),
    CONSTRAINT fk_ai_capitulos_generacion_video
        FOREIGN KEY (id_video) REFERENCES videos(id_video)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ai_capitulos_generado_usuario
        FOREIGN KEY (generado_por) REFERENCES usuarios(id_usuario)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_ai_capitulos_publicado_usuario
        FOREIGN KEY (publicado_por) REFERENCES usuarios(id_usuario)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_capitulos_video (
    id_capitulo INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_generacion INT UNSIGNED NOT NULL,
    orden INT UNSIGNED NOT NULL DEFAULT 1,
    inicio_segundos DECIMAL(10,3) NOT NULL DEFAULT 0,
    fin_segundos DECIMAL(10,3) NOT NULL DEFAULT 0,
    titulo VARCHAR(180) NOT NULL,
    resumen TEXT NULL,
    conceptos LONGTEXT NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_capitulo),
    UNIQUE KEY uq_ai_capitulo_orden (id_generacion, orden),
    KEY idx_ai_capitulo_tiempo (id_generacion, inicio_segundos),
    CONSTRAINT fk_ai_capitulo_generacion
        FOREIGN KEY (id_generacion) REFERENCES ai_capitulos_generaciones(id_generacion)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
