-- ============================================================
-- TECHFLIX V3.1 - RESPUESTAS EN COMENTARIOS
-- Ejecutar UNA sola vez sobre la base existente devioz_videos.
-- No elimina datos ni tablas existentes.
-- MariaDB 10.4+
-- ============================================================

USE `devioz_videos`;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'comentarios'
      AND COLUMN_NAME = 'id_comentario_padre'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE `comentarios` ADD COLUMN `id_comentario_padre` bigint(20) UNSIGNED NULL AFTER `id_video`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'comentarios'
      AND INDEX_NAME = 'idx_comentarios_padre'
);
SET @sql := IF(
    @idx_exists = 0,
    'ALTER TABLE `comentarios` ADD KEY `idx_comentarios_padre` (`id_comentario_padre`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'comentarios'
      AND CONSTRAINT_NAME = 'fk_comentario_padre'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE `comentarios` ADD CONSTRAINT `fk_comentario_padre` FOREIGN KEY (`id_comentario_padre`) REFERENCES `comentarios` (`id_comentario`) ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
