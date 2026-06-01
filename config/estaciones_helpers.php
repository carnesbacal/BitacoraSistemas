<?php
/**
 * ============================================================================
 * config/estaciones_helpers.php
 * ============================================================================
 * Gestión de estaciones de trabajo:
 *   - CRUD del catálogo
 *   - Asignación de equipos a estaciones
 *   - Listados con conteo de equipos
 *   - Estadísticas (incidencias por estación)
 * ============================================================================
 */

require_once __DIR__ . '/db.php';


// ============================================================================
// LISTADOS
// ============================================================================

/**
 * Lista las estaciones con filtros y conteos.
 */
function listar_estaciones(array $filtros = []): array {
    $where = ["e.activo = 1"];
    $params = [];

    if (!empty($filtros['busqueda'])) {
        $like = '%' . $filtros['busqueda'] . '%';
        $where[] = "(e.codigo LIKE :q1 OR e.nombre LIKE :q2 OR e.descripcion LIKE :q3)";
        $params['q1'] = $like;
        $params['q2'] = $like;
        $params['q3'] = $like;
    }

    if (!empty($filtros['sucursal_id'])) {
        $where[] = "e.sucursal_id = :sid";
        $params['sid'] = (int) $filtros['sucursal_id'];
    }

    if (!empty($filtros['area_id'])) {
        $where[] = "e.area_id = :aid";
        $params['aid'] = (int) $filtros['area_id'];
    }

    $where_sql = "WHERE " . implode(' AND ', $where);

    return db_all(
        "SELECT e.*,
                s.codigo AS sucursal_codigo, s.nombre AS sucursal_nombre,
                a.nombre AS area_nombre, a.color AS area_color,
                u.nombre_completo AS responsable_usuario,
                (SELECT COUNT(*) FROM equipos WHERE estacion_id = e.id AND activo = 1) AS num_equipos,
                (SELECT COUNT(*) FROM incidencias
                 WHERE estacion_id = e.id
                   AND estado_id NOT IN (6,7,8)) AS incidencias_abiertas,
                (SELECT COUNT(*) FROM incidencias
                 WHERE estacion_id = e.id
                   AND creado_en >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS incidencias_30d
         FROM estaciones_trabajo e
         INNER JOIN sucursales s ON e.sucursal_id = s.id
         LEFT JOIN areas a ON e.area_id = a.id
         LEFT JOIN usuarios u ON e.responsable_id = u.id
         $where_sql
         ORDER BY s.codigo, e.nombre ASC",
        $params
    );
}


/**
 * Obtiene una estación por ID con datos completos.
 */
function obtener_estacion(int $id): ?array {
    $r = db_one(
        "SELECT e.*,
                s.codigo AS sucursal_codigo, s.nombre AS sucursal_nombre,
                a.nombre AS area_nombre, a.color AS area_color,
                u.nombre_completo AS responsable_usuario,
                u_crea.nombre_completo AS creado_por_nombre,
                p.nombre AS planta_nombre
         FROM estaciones_trabajo e
         INNER JOIN sucursales s ON e.sucursal_id = s.id
         LEFT JOIN areas a ON e.area_id = a.id
         LEFT JOIN usuarios u ON e.responsable_id = u.id
         LEFT JOIN usuarios u_crea ON e.creado_por_id = u_crea.id
         LEFT JOIN sucursal_plantas p ON e.planta_id = p.id
         WHERE e.id = :id",
        ['id' => $id]
    );
    return $r ?: null;
}


/**
 * Equipos asignados a una estación.
 */
function listar_equipos_de_estacion(int $estacion_id): array {
    return db_all(
        "SELECT e.*,
                p.nombre AS proveedor_nombre,
                a.nombre AS area_nombre,
                resp.nombre_completo AS responsable_nombre
         FROM equipos e
         LEFT JOIN proveedores p ON e.proveedor_id = p.id
         LEFT JOIN areas a ON e.area_id = a.id
         LEFT JOIN usuarios resp ON e.responsable_id = resp.id
         WHERE e.estacion_id = :eid AND e.activo = 1
         ORDER BY e.tipo, e.nombre",
        ['eid' => $estacion_id]
    );
}


/**
 * Equipos NO asignados a ninguna estación (para selector "agregar a esta estación").
 * Limita a la misma sucursal que la estación destino.
 */
function listar_equipos_disponibles_para_estacion(int $sucursal_id, int $estacion_actual_id = 0): array {
    return db_all(
        "SELECT e.id, e.codigo_inventario, e.nombre, e.tipo, e.marca, e.modelo,
                e.estacion_id,
                est_actual.nombre AS estacion_actual_nombre
         FROM equipos e
         LEFT JOIN estaciones_trabajo est_actual ON e.estacion_id = est_actual.id
         WHERE e.sucursal_id = :sid
           AND e.activo = 1
           AND (e.estacion_id IS NULL OR e.estacion_id = :eid)
         ORDER BY e.tipo, e.nombre",
        ['sid' => $sucursal_id, 'eid' => $estacion_actual_id]
    );
}


/**
 * Incidencias relacionadas con una estación (vía equipos asignados O directamente).
 */
function listar_incidencias_de_estacion(int $estacion_id, int $limite = 30): array {
    return db_all(
        "SELECT DISTINCT i.id, i.folio, i.titulo, i.creado_en, i.fecha_resolucion,
                e.nombre_completo AS reportado_por_nombre,
                est.nombre AS estado_nombre, est.color AS estado_color, est.es_final,
                sev.nombre AS severidad_nombre, sev.color AS severidad_color,
                eq.codigo_inventario AS equipo_codigo, eq.nombre AS equipo_nombre,
                t.nombre_completo AS asignado_a_nombre,
                CASE
                    WHEN i.estacion_id = :eid1 THEN 'directa'
                    ELSE 'equipo'
                END AS relacion
         FROM incidencias i
         LEFT JOIN usuarios e ON i.reportado_por_id = e.id
         LEFT JOIN estados est ON i.estado_id = est.id
         LEFT JOIN severidades sev ON i.severidad_id = sev.id
         LEFT JOIN equipos eq ON i.equipo_id = eq.id
         LEFT JOIN usuarios t ON i.asignado_a_id = t.id
         WHERE i.estacion_id = :eid2
            OR i.equipo_id IN (SELECT id FROM equipos WHERE estacion_id = :eid3)
         ORDER BY i.creado_en DESC
         LIMIT $limite",
        ['eid1' => $estacion_id, 'eid2' => $estacion_id, 'eid3' => $estacion_id]
    );
}


// ============================================================================
// CRUD
// ============================================================================

function crear_estacion(array $datos, int $usuario_id): int {
    db_exec(
        "INSERT INTO estaciones_trabajo
         (codigo, nombre, descripcion, sucursal_id, area_id, ubicacion,
          responsable_id, responsable_nombre, planta_id, notas, creado_por_id)
         VALUES
         (:cod, :nom, :desc, :sid, :aid, :ubi,
          :rid, :rnom, :pid, :notas, :uid)",
        [
            'cod' => mb_substr($datos['codigo'], 0, 50),
            'nom' => mb_substr($datos['nombre'], 0, 150),
            'desc' => $datos['descripcion'] ?? null,
            'sid' => (int) $datos['sucursal_id'],
            'aid' => !empty($datos['area_id']) ? (int) $datos['area_id'] : null,
            'ubi' => $datos['ubicacion'] ?? null,
            'rid' => !empty($datos['responsable_id']) ? (int) $datos['responsable_id'] : null,
            'rnom' => $datos['responsable_nombre'] ?? null,
            'pid' => !empty($datos['planta_id']) ? (int) $datos['planta_id'] : null,
            'notas' => $datos['notas'] ?? null,
            'uid' => $usuario_id,
        ]
    );
    return (int) db_last_id();
}


function actualizar_estacion(int $id, array $datos): void {
    db_exec(
        "UPDATE estaciones_trabajo
         SET codigo = :cod, nombre = :nom, descripcion = :desc,
             sucursal_id = :sid, area_id = :aid, ubicacion = :ubi,
             responsable_id = :rid, responsable_nombre = :rnom,
             planta_id = :pid, notas = :notas
         WHERE id = :id",
        [
            'cod' => mb_substr($datos['codigo'], 0, 50),
            'nom' => mb_substr($datos['nombre'], 0, 150),
            'desc' => $datos['descripcion'] ?? null,
            'sid' => (int) $datos['sucursal_id'],
            'aid' => !empty($datos['area_id']) ? (int) $datos['area_id'] : null,
            'ubi' => $datos['ubicacion'] ?? null,
            'rid' => !empty($datos['responsable_id']) ? (int) $datos['responsable_id'] : null,
            'rnom' => $datos['responsable_nombre'] ?? null,
            'pid' => !empty($datos['planta_id']) ? (int) $datos['planta_id'] : null,
            'notas' => $datos['notas'] ?? null,
            'id' => $id,
        ]
    );
}


function eliminar_estacion(int $id): void {
    // No borra realmente: marca como inactiva. Los equipos quedan con estacion_id apuntando
    // a una estación inactiva. Mejor desvincularlos primero.
    db_exec("UPDATE equipos SET estacion_id = NULL WHERE estacion_id = :id", ['id' => $id]);
    db_exec("UPDATE estaciones_trabajo SET activo = 0 WHERE id = :id", ['id' => $id]);
}


/**
 * Asigna un equipo a una estación (o lo desasigna si $estacion_id es null).
 */
function asignar_equipo_a_estacion(int $equipo_id, ?int $estacion_id): void {
    db_exec(
        "UPDATE equipos SET estacion_id = :eid WHERE id = :id",
        ['eid' => $estacion_id, 'id' => $equipo_id]
    );
}


/**
 * Agrega varios equipos a una estación.
 */
function agregar_equipos_a_estacion(int $estacion_id, array $equipo_ids): int {
    $afectados = 0;
    foreach ($equipo_ids as $eid) {
        $eid = (int) $eid;
        if ($eid <= 0) continue;
        db_exec("UPDATE equipos SET estacion_id = :est WHERE id = :id AND activo = 1",
            ['est' => $estacion_id, 'id' => $eid]);
        $afectados++;
    }
    return $afectados;
}


// ============================================================================
// ESTADÍSTICAS
// ============================================================================

function stats_estaciones(?int $sucursal_id = null): array {
    $where_suc = $sucursal_id ? "AND sucursal_id = :sid" : "";
    $params = $sucursal_id ? ['sid' => $sucursal_id] : [];

    $totales = db_one(
        "SELECT
            COUNT(*) AS total,
            COUNT(DISTINCT area_id) AS num_areas
         FROM estaciones_trabajo
         WHERE activo = 1 $where_suc",
        $params
    );

    $equipos_asignados = db_one(
        "SELECT COUNT(*) c FROM equipos
         WHERE activo = 1 AND estacion_id IS NOT NULL
           " . ($sucursal_id ? "AND sucursal_id = :sid" : ""),
        $params
    );

    $equipos_sin_asignar = db_one(
        "SELECT COUNT(*) c FROM equipos
         WHERE activo = 1 AND estacion_id IS NULL
           " . ($sucursal_id ? "AND sucursal_id = :sid" : ""),
        $params
    );

    return [
        'total' => (int) ($totales['total'] ?? 0),
        'num_areas' => (int) ($totales['num_areas'] ?? 0),
        'equipos_asignados' => (int) ($equipos_asignados['c'] ?? 0),
        'equipos_sin_asignar' => (int) ($equipos_sin_asignar['c'] ?? 0),
    ];
}


/**
 * Top estaciones con más incidencias en los últimos N días.
 * Útil para dashboard y reportes.
 */
function top_estaciones_problematicas(int $dias = 30, int $limite = 10): array {
    return db_all(
        "SELECT e.id, e.codigo, e.nombre,
                s.codigo AS sucursal_codigo,
                a.nombre AS area_nombre,
                COUNT(DISTINCT i.id) AS num_incidencias
         FROM estaciones_trabajo e
         INNER JOIN sucursales s ON e.sucursal_id = s.id
         LEFT JOIN areas a ON e.area_id = a.id
         INNER JOIN incidencias i ON (
             i.estacion_id = e.id
             OR i.equipo_id IN (SELECT id FROM equipos WHERE estacion_id = e.id)
         )
         WHERE e.activo = 1
           AND i.creado_en >= DATE_SUB(NOW(), INTERVAL $dias DAY)
         GROUP BY e.id, e.codigo, e.nombre, s.codigo, a.nombre
         HAVING num_incidencias > 0
         ORDER BY num_incidencias DESC
         LIMIT $limite"
    );
}
