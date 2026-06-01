-- ============================================================================
-- proyectos_PR1.sql
-- ============================================================================
-- BLOQUE PR1 (SISTEMAS): Módulo de Proyectos
--
-- Crea:
--   - Tabla proyectos (info principal)
--   - Tabla proyecto_participantes (equipo del proyecto)
--   - Tabla proyecto_tareas (hitos opcionales)
--   - Tabla proyecto_comentarios (bitácora de actualizaciones)
--   - Tabla proyecto_adjuntos (documentos del proyecto)
--
-- NO modifica permisos del rol Ingeniero todavía — eso lo aplica el bloque PR3
-- ============================================================================

USE carnes_bacal;


-- ============================================================================
-- Tabla principal: proyectos
-- ============================================================================
CREATE TABLE proyectos (
    id              INT NOT NULL AUTO_INCREMENT,

    -- Identificación
    codigo          VARCHAR(50) NOT NULL COMMENT 'Código único, ej. PROY-001',
    nombre          VARCHAR(200) NOT NULL,
    descripcion     TEXT DEFAULT NULL COMMENT 'Objetivo o resumen del proyecto',

    -- Clasificación
    tipo            VARCHAR(80) NOT NULL DEFAULT 'Otro' COMMENT 'Texto libre con autocompletar',
    estado          ENUM('propuesto','aprobado','en_curso','pausado','completado','cancelado')
                    NOT NULL DEFAULT 'propuesto',
    prioridad       ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',

    -- Ámbito
    sucursal_id     INT DEFAULT NULL COMMENT 'NULL = aplica a todas',
    area_id         INT DEFAULT NULL,

    -- Equipo
    lider_id        INT DEFAULT NULL COMMENT 'Usuario responsable del proyecto',
    sugerido_por_id INT DEFAULT NULL COMMENT 'Quién lo propuso',
    aprobado_por_id INT DEFAULT NULL COMMENT 'Quién lo aprobó (admin/líder)',
    aprobado_en     DATETIME DEFAULT NULL,

    -- Fechas (todas opcionales)
    fecha_inicio_plan   DATE DEFAULT NULL,
    fecha_fin_plan      DATE DEFAULT NULL,
    fecha_inicio_real   DATE DEFAULT NULL,
    fecha_fin_real      DATE DEFAULT NULL,

    -- Métricas (opcional)
    avance          TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Porcentaje 0-100',
    presupuesto     DECIMAL(12,2) DEFAULT NULL,
    costo_real      DECIMAL(12,2) DEFAULT NULL,

    -- Información adicional opcional
    cliente_interno VARCHAR(150) DEFAULT NULL COMMENT 'Quién solicitó / quién se beneficia',
    proveedor_externo VARCHAR(150) DEFAULT NULL COMMENT 'Si involucra a terceros',
    tecnologias     VARCHAR(255) DEFAULT NULL COMMENT 'Stack o herramientas usadas',
    enlaces         TEXT DEFAULT NULL COMMENT 'URLs relacionadas, repositorios, docs',
    riesgos         TEXT DEFAULT NULL,
    notas           TEXT DEFAULT NULL,

    -- Control
    activo          TINYINT(1) NOT NULL DEFAULT 1,
    creado_por_id   INT DEFAULT NULL,
    creado_en       DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_codigo (codigo),
    KEY idx_estado (estado),
    KEY idx_lider (lider_id),
    KEY idx_sucursal (sucursal_id),
    KEY idx_creado_en (creado_en),

    CONSTRAINT fk_proy_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE SET NULL,
    CONSTRAINT fk_proy_area FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL,
    CONSTRAINT fk_proy_lider FOREIGN KEY (lider_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_proy_sugerido FOREIGN KEY (sugerido_por_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_proy_aprobado FOREIGN KEY (aprobado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_proy_creador FOREIGN KEY (creado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Participantes del proyecto (relación N:M con usuarios)
-- ============================================================================
CREATE TABLE proyecto_participantes (
    id              INT NOT NULL AUTO_INCREMENT,
    proyecto_id     INT NOT NULL,
    usuario_id      INT NOT NULL,
    rol_en_proyecto VARCHAR(80) DEFAULT NULL COMMENT 'Ej. Desarrollador, QA, Stakeholder',
    asignado_en     DATETIME DEFAULT CURRENT_TIMESTAMP,
    asignado_por_id INT DEFAULT NULL,
    notas           VARCHAR(255) DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uk_proy_usr (proyecto_id, usuario_id),
    KEY idx_usuario (usuario_id),

    CONSTRAINT fk_part_proy FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    CONSTRAINT fk_part_usr FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_part_asignador FOREIGN KEY (asignado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Tareas/hitos del proyecto (opcional, fechas todas opcionales)
-- ============================================================================
CREATE TABLE proyecto_tareas (
    id              INT NOT NULL AUTO_INCREMENT,
    proyecto_id     INT NOT NULL,

    titulo          VARCHAR(200) NOT NULL,
    descripcion     TEXT DEFAULT NULL,

    estado          ENUM('pendiente','en_progreso','bloqueada','completada','cancelada')
                    NOT NULL DEFAULT 'pendiente',
    es_hito         TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Marca hito importante vs tarea normal',
    orden           INT NOT NULL DEFAULT 0,

    -- Asignación
    asignada_a_id   INT DEFAULT NULL,

    -- Fechas (opcionales)
    fecha_inicio    DATE DEFAULT NULL,
    fecha_fin_plan  DATE DEFAULT NULL,
    fecha_completada DATETIME DEFAULT NULL,

    -- Auditoría
    creado_por_id   INT DEFAULT NULL,
    creado_en       DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_proyecto (proyecto_id),
    KEY idx_estado (estado),
    KEY idx_asignada (asignada_a_id),

    CONSTRAINT fk_tarea_proy FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    CONSTRAINT fk_tarea_asig FOREIGN KEY (asignada_a_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_tarea_creador FOREIGN KEY (creado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Comentarios (timeline de actualizaciones)
-- ============================================================================
CREATE TABLE proyecto_comentarios (
    id              INT NOT NULL AUTO_INCREMENT,
    proyecto_id     INT NOT NULL,
    usuario_id      INT NOT NULL,
    contenido       TEXT NOT NULL,
    tipo            ENUM('comentario','cambio_estado','hito','nota_admin') NOT NULL DEFAULT 'comentario',
    creado_en       DATETIME DEFAULT CURRENT_TIMESTAMP,
    editado_en      DATETIME DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_proyecto (proyecto_id),
    KEY idx_creado (creado_en),

    CONSTRAINT fk_com_proy FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    CONSTRAINT fk_com_usr FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Adjuntos del proyecto
-- ============================================================================
CREATE TABLE proyecto_adjuntos (
    id              INT NOT NULL AUTO_INCREMENT,
    proyecto_id     INT NOT NULL,
    comentario_id   INT DEFAULT NULL COMMENT 'NULL = adjunto al proyecto; lleno = a un comentario',

    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo  VARCHAR(255) NOT NULL COMMENT 'Nombre real en uploads/proyectos/',
    tipo_mime       VARCHAR(100) DEFAULT NULL,
    tamano_bytes    INT DEFAULT NULL,

    subido_por_id   INT DEFAULT NULL,
    subido_en       DATETIME DEFAULT CURRENT_TIMESTAMP,
    descripcion     VARCHAR(255) DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_proyecto (proyecto_id),
    KEY idx_comentario (comentario_id),

    CONSTRAINT fk_adj_proy FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    CONSTRAINT fk_adj_com FOREIGN KEY (comentario_id) REFERENCES proyecto_comentarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_adj_usr FOREIGN KEY (subido_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Verificación
-- ============================================================================
SELECT 'Bloque PR1 instalado' AS estado;
SELECT 'proyectos' tabla, COUNT(*) total FROM proyectos
UNION ALL SELECT 'proyecto_participantes', COUNT(*) FROM proyecto_participantes
UNION ALL SELECT 'proyecto_tareas', COUNT(*) FROM proyecto_tareas
UNION ALL SELECT 'proyecto_comentarios', COUNT(*) FROM proyecto_comentarios
UNION ALL SELECT 'proyecto_adjuntos', COUNT(*) FROM proyecto_adjuntos;
