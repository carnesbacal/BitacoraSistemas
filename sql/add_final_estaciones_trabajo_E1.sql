-- ============================================================================
-- estaciones_trabajo_E1.sql
-- ============================================================================
-- BLOQUE E1 (SISTEMAS): Estaciones de trabajo
--
-- Una estación de trabajo agrupa N equipos (PC + monitor + teclado + mouse,
-- por ejemplo) bajo un nombre lógico (ej. "Caja 1", "Puesto Beatriz").
--
-- Crea:
--   - Tabla estaciones_trabajo
--   - Columna estaciones_trabajo.id referenciada desde equipos
--   - Columna estacion_id en incidencias para filtrado por estación
-- ============================================================================

USE carnes_bacal;

-- ============================================================================
-- Tabla: estaciones_trabajo
-- ============================================================================
CREATE TABLE estaciones_trabajo (
    id              INT NOT NULL AUTO_INCREMENT,

    -- Identificación
    codigo          VARCHAR(50) NOT NULL COMMENT 'Código corto único, ej. EST-CAJA-1',
    nombre          VARCHAR(150) NOT NULL COMMENT 'Nombre descriptivo, ej. Caja 1, Puesto Beatriz',
    descripcion     TEXT DEFAULT NULL,

    -- Ubicación
    sucursal_id     INT NOT NULL,
    area_id         INT DEFAULT NULL,
    ubicacion       VARCHAR(255) DEFAULT NULL COMMENT 'Ubicación física específica dentro del área',

    -- Responsable principal
    responsable_id  INT DEFAULT NULL COMMENT 'Usuario o empleado responsable habitual',
    responsable_nombre VARCHAR(150) DEFAULT NULL COMMENT 'Nombre libre si no es un usuario del sistema',

    -- Posición en mapa
    pos_x           DECIMAL(5,2) DEFAULT NULL,
    pos_y           DECIMAL(5,2) DEFAULT NULL,
    planta_id       INT DEFAULT NULL,

    -- Notas
    notas           TEXT DEFAULT NULL,

    -- Control
    activo          TINYINT(1) NOT NULL DEFAULT 1,
    creado_por_id   INT DEFAULT NULL,
    creado_en       DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_codigo (codigo),
    KEY idx_sucursal (sucursal_id),
    KEY idx_area (area_id),
    KEY idx_responsable (responsable_id),
    KEY idx_planta (planta_id),

    CONSTRAINT fk_est_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE RESTRICT,
    CONSTRAINT fk_est_area FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL,
    CONSTRAINT fk_est_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_est_planta FOREIGN KEY (planta_id) REFERENCES sucursal_plantas(id) ON DELETE SET NULL,
    CONSTRAINT fk_est_creador FOREIGN KEY (creado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Agregar columna estacion_id a equipos
-- ============================================================================
ALTER TABLE equipos
    ADD COLUMN estacion_id INT DEFAULT NULL COMMENT 'Estación a la que pertenece (opcional)' AFTER area_id,
    ADD KEY idx_estacion (estacion_id),
    ADD CONSTRAINT fk_equipo_estacion FOREIGN KEY (estacion_id) REFERENCES estaciones_trabajo(id) ON DELETE SET NULL;


-- ============================================================================
-- Agregar columna estacion_id a incidencias
-- ============================================================================
-- Permite que una incidencia pueda asociarse opcionalmente a una estación
-- (además del equipo específico que ya tiene).
-- ============================================================================
ALTER TABLE incidencias
    ADD COLUMN estacion_id INT DEFAULT NULL COMMENT 'Estación afectada (opcional)' AFTER equipo_id,
    ADD KEY idx_estacion (estacion_id),
    ADD CONSTRAINT fk_inc_estacion FOREIGN KEY (estacion_id) REFERENCES estaciones_trabajo(id) ON DELETE SET NULL;


-- ============================================================================
-- Verificación
-- ============================================================================
SELECT 'Bloque E1 instalado' AS estado;
SELECT 'estaciones_trabajo' tabla, COUNT(*) total FROM estaciones_trabajo
UNION ALL SELECT 'equipos.estacion_id', COUNT(*) FROM equipos WHERE estacion_id IS NOT NULL
UNION ALL SELECT 'incidencias.estacion_id', COUNT(*) FROM incidencias WHERE estacion_id IS NOT NULL;
