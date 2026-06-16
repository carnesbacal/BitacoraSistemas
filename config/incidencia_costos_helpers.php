<?php
/**
 * ============================================================================
 * config/incidencia_costos_helpers.php  (BitacoraSistemas)
 * ============================================================================
 * Funciones para calcular y reportar costos de incidencias.
 *
 * Modelo de costo (Sistemas NO tiene refacciones de stock):
 *   EXTERNO = costo_mano_obra + costo_materiales_proveedor
 *   INTERNO = costo_materiales_comprados
 *           + horas_trabajadas * tarifa_hora_aplicada   (mano de obra interna)
 *
 * PRIVACIDAD: la mano de obra interna (salario del técnico) y la tarifa
 * solo son visibles para administradores.
 *
 * El total NUNCA se almacena: se calcula al vuelo.
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

// ============================================================================
// CONTROL DE VISIBILIDAD
// ============================================================================

/**
 * Solo los administradores pueden ver la mano de obra interna, la tarifa
 * por hora y cualquier total que las incluya (evita inferir salarios).
 */
function puede_ver_mano_obra_interna(): bool {
    return function_exists('tiene_permiso') && tiene_permiso('administrar');
}

// ============================================================================
// EXPRESIÓN SQL REUTILIZABLE
// ============================================================================

/**
 * Expresión SQL del costo de mano de obra interna (horas × tarifa congelada).
 */
function sql_mano_obra_interna(string $alias_incidencia = 'i'): string {
    return "(COALESCE({$alias_incidencia}.horas_trabajadas, 0)
             * COALESCE({$alias_incidencia}.tarifa_hora_aplicada, 0))";
}

// ============================================================================
// COSTO DE UNA INCIDENCIA INDIVIDUAL
// ============================================================================

/**
 * Calcula el desglose de costos de una incidencia.
 * Retorna:
 *   mano_obra, materiales_proveedor, externo,
 *   materiales_comprados, mano_obra_interna, interno,
 *   total           (incluye mano de obra interna — solo mostrar a admin),
 *   total_visible   (sin mano de obra interna — para no-admin),
 *   horas_trabajadas, tarifa_aplicada, tiene_costo, tiene_costo_visible.
 */
function costo_incidencia(int $incidencia_id): array {
    $row = db_one(
        "SELECT
            COALESCE(i.costo_mano_obra, 0)            AS mano_obra,
            COALESCE(i.costo_materiales_proveedor, 0) AS materiales_proveedor,
            COALESCE(i.costo_materiales_comprados, 0) AS materiales_comprados,
            COALESCE(i.horas_trabajadas, 0)           AS horas,
            COALESCE(i.tarifa_hora_aplicada, 0)       AS tarifa
         FROM incidencias i
         WHERE i.id = :id",
        ['id' => $incidencia_id]
    );

    $mano_obra     = (float) ($row['mano_obra'] ?? 0);
    $materiales    = (float) ($row['materiales_proveedor'] ?? 0);
    $mat_comprados = (float) ($row['materiales_comprados'] ?? 0);
    $horas         = (float) ($row['horas'] ?? 0);
    $tarifa        = (float) ($row['tarifa'] ?? 0);

    $mano_obra_interna = round($horas * $tarifa, 2);
    $externo = $mano_obra + $materiales;
    $interno = $mat_comprados + $mano_obra_interna;
    $total = $externo + $interno;
    $total_visible = $externo + $mat_comprados; // sin mano de obra interna

    return [
        'mano_obra'            => $mano_obra,
        'materiales_proveedor' => $materiales,
        'materiales_comprados' => $mat_comprados,
        'externo'              => $externo,
        'mano_obra_interna'    => $mano_obra_interna,
        'interno'              => $interno,
        'total'                => $total,
        'total_visible'        => $total_visible,
        'horas_trabajadas'     => $horas,
        'tarifa_aplicada'      => $tarifa,
        'tiene_costo'          => $total > 0,
        'tiene_costo_visible'  => $total_visible > 0,
    ];
}

// ============================================================================
// GUARDAR / ACTUALIZAR COSTOS Y PROVEEDOR
// ============================================================================

/**
 * Actualiza proveedor, costos y horas trabajadas de una incidencia.
 * Si se registran horas, CONGELA la tarifa del técnico asignado.
 */
function guardar_costos_incidencia(int $incidencia_id, array $datos): void {
    $horas = isset($datos['horas_trabajadas']) && $datos['horas_trabajadas'] !== ''
        ? (float) $datos['horas_trabajadas']
        : null;

    $tarifa_aplicada = null;
    if ($horas !== null && $horas > 0) {
        $row = db_one(
            "SELECT u.tarifa_hora
             FROM incidencias i
             LEFT JOIN usuarios u ON i.asignado_a_id = u.id
             WHERE i.id = :id",
            ['id' => $incidencia_id]
        );
        $tarifa_aplicada = ($row && $row['tarifa_hora'] !== null)
            ? (float) $row['tarifa_hora']
            : null;
    }

    db_exec(
        "UPDATE incidencias SET
            proveedor_escalado_id      = :pid,
            proveedor_externo_info     = :pinfo,
            costo_mano_obra            = :cmo,
            costo_materiales_proveedor = :cmp,
            costo_materiales_comprados = :cmc,
            costo_notas                = :cnotas,
            horas_trabajadas           = :horas,
            tarifa_hora_aplicada       = :tarifa
         WHERE id = :id",
        [
            'pid'    => !empty($datos['proveedor_escalado_id']) ? (int) $datos['proveedor_escalado_id'] : null,
            'pinfo'  => !empty($datos['proveedor_externo_info']) ? trim((string) $datos['proveedor_externo_info']) : null,
            'cmo'    => isset($datos['costo_mano_obra']) && $datos['costo_mano_obra'] !== '' ? (float) $datos['costo_mano_obra'] : null,
            'cmp'    => isset($datos['costo_materiales_proveedor']) && $datos['costo_materiales_proveedor'] !== '' ? (float) $datos['costo_materiales_proveedor'] : null,
            'cmc'    => isset($datos['costo_materiales_comprados']) && $datos['costo_materiales_comprados'] !== '' ? (float) $datos['costo_materiales_comprados'] : null,
            'cnotas' => !empty($datos['costo_notas']) ? trim((string) $datos['costo_notas']) : null,
            'horas'  => $horas,
            'tarifa' => $tarifa_aplicada,
            'id'     => $incidencia_id,
        ]
    );
}

// ============================================================================
// ALTA RÁPIDA DE PROVEEDOR
// ============================================================================

function crear_proveedor_rapido(array $datos, int $usuario_id): int {
    $nombre = trim((string) ($datos['nombre'] ?? ''));
    if ($nombre === '') {
        throw new RuntimeException('El nombre del proveedor es obligatorio.');
    }
    $existe = db_one("SELECT id FROM proveedores WHERE nombre = :n", ['n' => $nombre]);
    if ($existe) {
        return (int) $existe['id'];
    }
    db_exec(
        "INSERT INTO proveedores (nombre, servicio, telefono, email, notas, activo, creado_por_id)
         VALUES (:n, :serv, :tel, :email, :notas, 1, :uid)",
        [
            'n'     => $nombre,
            'serv'  => !empty($datos['servicio']) ? trim((string) $datos['servicio']) : null,
            'tel'   => !empty($datos['telefono']) ? trim((string) $datos['telefono']) : null,
            'email' => !empty($datos['email']) ? trim((string) $datos['email']) : null,
            'notas' => !empty($datos['notas']) ? trim((string) $datos['notas']) : null,
            'uid'   => $usuario_id,
        ]
    );
    return (int) db_last_id();
}

function listar_proveedores_activos(): array {
    return db_all(
        "SELECT id, nombre, servicio, telefono
         FROM proveedores
         WHERE activo = 1
         ORDER BY nombre ASC"
    );
}

// ============================================================================
// REPORTES DE COSTOS POR PERÍODO
// ============================================================================

function costos_resumen_periodo(string $desde, string $hasta, string $extra_where = '', array $extra_params = []): array {
    $params = array_merge(['d' => $desde, 'h' => $hasta], $extra_params);
    $moi = sql_mano_obra_interna('i');
    $mc  = "COALESCE(i.costo_materiales_comprados, 0)";

    $row = db_one(
        "SELECT
            COUNT(*) AS num_total,
            SUM(COALESCE(i.costo_mano_obra, 0))            AS mano_obra,
            SUM(COALESCE(i.costo_materiales_proveedor, 0)) AS materiales,
            SUM($mc)                                        AS materiales_comprados,
            SUM($moi)                                       AS mano_obra_interna,
            SUM(COALESCE(i.costo_mano_obra, 0) + COALESCE(i.costo_materiales_proveedor, 0)
                + $mc + $moi) AS total,
            SUM(CASE WHEN (COALESCE(i.costo_mano_obra,0) + COALESCE(i.costo_materiales_proveedor,0)
                          + $mc + $moi) > 0 THEN 1 ELSE 0 END) AS con_costo,
            SUM(CASE WHEN i.proveedor_escalado_id IS NOT NULL
                       OR i.proveedor_externo_info IS NOT NULL THEN 1 ELSE 0 END) AS con_proveedor
         FROM incidencias i
         WHERE DATE(i.creado_en) BETWEEN :d AND :h $extra_where",
        $params
    );

    $mano_obra = (float) ($row['mano_obra'] ?? 0);
    $materiales = (float) ($row['materiales'] ?? 0);
    $mat_comprados = (float) ($row['materiales_comprados'] ?? 0);
    $mano_obra_interna = (float) ($row['mano_obra_interna'] ?? 0);
    $externo = $mano_obra + $materiales;
    $interno = $mat_comprados + $mano_obra_interna;
    $total = (float) ($row['total'] ?? 0);
    $total_visible = $externo + $mat_comprados;
    $con_costo = (int) ($row['con_costo'] ?? 0);

    return [
        'num_total'            => (int) ($row['num_total'] ?? 0),
        'mano_obra'            => $mano_obra,
        'materiales'           => $materiales,
        'materiales_comprados' => $mat_comprados,
        'externo'              => $externo,
        'mano_obra_interna'    => $mano_obra_interna,
        'interno'              => $interno,
        'total'                => $total,
        'total_visible'        => $total_visible,
        'con_costo'            => $con_costo,
        'con_proveedor'        => (int) ($row['con_proveedor'] ?? 0),
        'promedio'             => $con_costo > 0 ? round($total / $con_costo, 2) : 0,
        'pct_externo'          => $total > 0 ? round(($externo / $total) * 100, 1) : 0,
        'pct_interno'          => $total > 0 ? round(($interno / $total) * 100, 1) : 0,
    ];
}

function costos_ranking_incidencias(string $desde, string $hasta, int $limite = 20, string $extra_where = '', array $extra_params = []): array {
    $params = array_merge(['d' => $desde, 'h' => $hasta], $extra_params);
    $moi = sql_mano_obra_interna('i');

    return db_all(
        "SELECT
            i.id, i.folio, i.titulo, i.fecha_evento,
            s.nombre AS sucursal_nombre,
            est.nombre AS estado_nombre, est.color AS estado_color,
            sev.nombre AS severidad_nombre, sev.color AS severidad_color,
            p.nombre AS proveedor_nombre,
            i.proveedor_externo_info,
            COALESCE(i.costo_mano_obra, 0)            AS mano_obra,
            COALESCE(i.costo_materiales_proveedor, 0) AS materiales,
            COALESCE(i.costo_materiales_comprados, 0) AS materiales_comprados,
            $moi AS mano_obra_interna,
            (COALESCE(i.costo_mano_obra, 0) + COALESCE(i.costo_materiales_proveedor, 0)
             + COALESCE(i.costo_materiales_comprados, 0) + $moi) AS total
         FROM incidencias i
         INNER JOIN sucursales s ON i.sucursal_id = s.id
         INNER JOIN estados est ON i.estado_id = est.id
         INNER JOIN severidades sev ON i.severidad_id = sev.id
         LEFT JOIN proveedores p ON i.proveedor_escalado_id = p.id
         WHERE DATE(i.creado_en) BETWEEN :d AND :h $extra_where
         HAVING total > 0
         ORDER BY total DESC
         LIMIT $limite",
        $params
    );
}

function costos_ranking_proveedores(string $desde, string $hasta, int $limite = 20, string $extra_where = '', array $extra_params = []): array {
    $params = array_merge(['d' => $desde, 'h' => $hasta], $extra_params);

    return db_all(
        "SELECT
            p.id, p.nombre, p.servicio,
            COUNT(i.id) AS num_incidencias,
            SUM(COALESCE(i.costo_mano_obra, 0))            AS mano_obra,
            SUM(COALESCE(i.costo_materiales_proveedor, 0)) AS materiales,
            SUM(COALESCE(i.costo_mano_obra, 0) + COALESCE(i.costo_materiales_proveedor, 0)) AS total
         FROM proveedores p
         INNER JOIN incidencias i ON i.proveedor_escalado_id = p.id
            AND DATE(i.creado_en) BETWEEN :d AND :h $extra_where
         GROUP BY p.id, p.nombre, p.servicio
         HAVING total > 0
         ORDER BY total DESC
         LIMIT $limite",
        $params
    );
}

function costos_tendencia(string $desde, string $hasta, string $agrupar = 'mes', string $extra_where = '', array $extra_params = []): array {
    $params = array_merge(['d' => $desde, 'h' => $hasta], $extra_params);
    $moi = sql_mano_obra_interna('i');

    $grupo_sql = match ($agrupar) {
        'dia'    => "DATE(i.creado_en)",
        'semana' => "DATE_FORMAT(i.creado_en, '%x-S%v')",
        default  => "DATE_FORMAT(i.creado_en, '%Y-%m')",
    };
    $label_sql = match ($agrupar) {
        'dia'    => "DATE_FORMAT(i.creado_en, '%d/%m/%Y')",
        'semana' => "CONCAT('Sem ', DATE_FORMAT(i.creado_en, '%v · %x'))",
        default  => "DATE_FORMAT(i.creado_en, '%m/%Y')",
    };

    return db_all(
        "SELECT
            $grupo_sql AS periodo,
            MIN($label_sql) AS label,
            COUNT(*) AS num_incidencias,
            SUM(COALESCE(i.costo_mano_obra, 0) + COALESCE(i.costo_materiales_proveedor, 0)) AS externo,
            SUM(COALESCE(i.costo_materiales_comprados, 0) + $moi) AS interno,
            SUM(COALESCE(i.costo_mano_obra, 0) + COALESCE(i.costo_materiales_proveedor, 0)
                + COALESCE(i.costo_materiales_comprados, 0) + $moi) AS total
         FROM incidencias i
         WHERE DATE(i.creado_en) BETWEEN :d AND :h $extra_where
         GROUP BY $grupo_sql
         ORDER BY periodo ASC",
        $params
    );
}

function costos_por_sucursal(string $desde, string $hasta): array {
    $moi = sql_mano_obra_interna('i');

    return db_all(
        "SELECT
            s.id, s.nombre, s.codigo,
            COUNT(i.id) AS num_incidencias,
            SUM(COALESCE(i.costo_mano_obra, 0) + COALESCE(i.costo_materiales_proveedor, 0)) AS externo,
            SUM(COALESCE(i.costo_materiales_comprados, 0) + $moi) AS interno,
            SUM(COALESCE(i.costo_mano_obra, 0) + COALESCE(i.costo_materiales_proveedor, 0)
                + COALESCE(i.costo_materiales_comprados, 0) + $moi) AS total
         FROM sucursales s
         LEFT JOIN incidencias i ON i.sucursal_id = s.id
            AND DATE(i.creado_en) BETWEEN :d AND :h
         WHERE s.activo = 1
         GROUP BY s.id, s.nombre, s.codigo
         ORDER BY total DESC",
        ['d' => $desde, 'h' => $hasta]
    );
}

// ============================================================================
// FORMATEO
// ============================================================================

function fmt_dinero(?float $monto): string {
    if ($monto === null) return '—';
    return '$' . number_format($monto, 2, '.', ',');
}

function fmt_dinero_corto(?float $monto): string {
    if ($monto === null || $monto == 0) return '$0';
    if ($monto >= 1000000) return '$' . number_format($monto / 1000000, 1) . 'M';
    if ($monto >= 10000)   return '$' . number_format($monto / 1000, 1) . 'k';
    return '$' . number_format($monto, 0, '.', ',');
}
