-- ============================================================================
-- mant_multi_equipos.sql
-- ----------------------------------------------------------------------------
-- Permite que UN mantenimiento cubra VARIOS equipos (p. ej. por zona o tipo).
--
-- Estrategia: tabla puente `mantenimiento_equipos`.
--   * `mantenimientos.equipo_id` se conserva como "equipo principal"
--     (compatibilidad con todas las consultas existentes).
--   * La tabla puente es la fuente de verdad de TODOS los equipos del
--     mantenimiento (incluye al principal).
--
-- Es IDEMPOTENTE: se puede ejecutar varias veces sin error.
-- Ejecutar en phpMyAdmin sobre la BD `carnes_bacal`.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `mantenimiento_equipos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mantenimiento_id` int(11) NOT NULL,
  `equipo_id` int(11) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mant_equipo` (`mantenimiento_id`,`equipo_id`),
  KEY `idx_me_mant` (`mantenimiento_id`),
  KEY `idx_me_equipo` (`equipo_id`),
  CONSTRAINT `fk_me_mant` FOREIGN KEY (`mantenimiento_id`)
    REFERENCES `mantenimientos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_me_equipo` FOREIGN KEY (`equipo_id`)
    REFERENCES `equipos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: cada mantenimiento existente -> su equipo principal en la puente.
-- INSERT IGNORE evita duplicados si se vuelve a correr.
INSERT IGNORE INTO `mantenimiento_equipos` (`mantenimiento_id`, `equipo_id`)
SELECT m.`id`, m.`equipo_id`
FROM `mantenimientos` m
WHERE m.`equipo_id` IS NOT NULL;
