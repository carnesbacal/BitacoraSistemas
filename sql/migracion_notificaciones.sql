-- ============================================================================
-- migracion_notificaciones.sql
-- Sistema de notificaciones externas: Email + Telegram
-- Ejecutar UNA sola vez en la BD.
-- ============================================================================

-- Tabla de configuración global (1 sola fila)
CREATE TABLE IF NOT EXISTS `configuracion_notificaciones` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `smtp_host` VARCHAR(255) DEFAULT NULL,
    `smtp_port` SMALLINT UNSIGNED DEFAULT 587,
    `smtp_seguridad` ENUM('tls','ssl','none') DEFAULT 'tls',
    `smtp_usuario` VARCHAR(255) DEFAULT NULL,
    `smtp_password` VARCHAR(255) DEFAULT NULL,
    `smtp_from_email` VARCHAR(255) DEFAULT NULL,
    `smtp_from_nombre` VARCHAR(150) DEFAULT 'Bitácora Sistemas',
    `smtp_activo` TINYINT(1) NOT NULL DEFAULT 0,
    `telegram_bot_token` VARCHAR(255) DEFAULT NULL,
    `telegram_activo` TINYINT(1) NOT NULL DEFAULT 0,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `actualizado_por` INT DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar la fila única si no existe
INSERT IGNORE INTO `configuracion_notificaciones` (`id`) VALUES (1);

-- Telegram Chat ID por usuario
-- (usa IF NOT EXISTS equivalente: ALTER IGNORE no existe en versiones modernas,
--  por lo que se usa un bloque de procedimiento condicional)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'telegram_chat_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `usuarios` ADD COLUMN `telegram_chat_id` VARCHAR(50) DEFAULT NULL',
    'SELECT ''columna telegram_chat_id ya existe, sin cambios'''
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Preferencias de notificación por usuario y tipo de evento
CREATE TABLE IF NOT EXISTS `notificacion_preferencias` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `usuario_id` INT NOT NULL,
    `tipo` VARCHAR(60) NOT NULL,
    `canal_inapp` TINYINT(1) NOT NULL DEFAULT 1,
    `canal_email` TINYINT(1) NOT NULL DEFAULT 0,
    `canal_telegram` TINYINT(1) NOT NULL DEFAULT 0,
    `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_usuario_tipo` (`usuario_id`, `tipo`),
    KEY `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de envíos externos
CREATE TABLE IF NOT EXISTS `notificacion_envios` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `notificacion_id` INT DEFAULT NULL,
    `usuario_id` INT NOT NULL,
    `canal` ENUM('email','telegram') NOT NULL,
    `tipo` VARCHAR(60) DEFAULT NULL,
    `asunto` VARCHAR(255) DEFAULT NULL,
    `estado` ENUM('ok','error') NOT NULL DEFAULT 'ok',
    `error_detalle` TEXT DEFAULT NULL,
    `enviado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_usuario_canal` (`usuario_id`, `canal`),
    KEY `idx_enviado_en` (`enviado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- FIN DE LA MIGRACIÓN
-- ============================================================================
