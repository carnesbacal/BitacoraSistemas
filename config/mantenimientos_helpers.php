<?php
/**
 * ============================================================================
 * config/mantenimientos_helpers.php
 * ============================================================================
 * Funciones para gestionar mantenimientos:
 *   - Estados y sus colores
 *   - Generación automática del siguiente mantenimiento recurrente
 *   - Actualización de estados (programado → próximo → vencido)
 *   - Permisos
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

// ============================================================================
// Estados de mantenimiento
// ============================================================================

const MANTENIMIENTO_ESTADOS = [
    'programado'   => ['nombre' => 'Programado',  'color' => '#0EA5E9', 'icono' => 'calendar-clock'],
    'proximo'      => ['nombre' => 'Próximo',     'color' => '#D97706', 'icono' => 'clock-alert'],
    'en_progreso' => ['nombre' => 'En progreso', 'color' => '#7C3AED', 'icono' => 'wrench'],
    'completado'   => ['nombre' => 'Completado',  'color' => '#16A34A', 'icono' => 'check-circle-2'],
    'cancelado'    => ['nombre' => 'Cancelado',   'color' => '#6B7280', 'icono' => 'x-circle'],
    'vencido'      => ['nombre' => 'Vencido',     'color' => '#DC2626', 'icono' => 'alert-circle'],
];

function badge_estado_mant(string $estado): string {
    $cfg = MANTENIMIENTO_ESTADOS[$estado] ?? MANTENIMIENTO_ESTADOS['programado'];
    return sprintf(
        '<span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded" style="background-color: %s15; color: %s">' .
        '<i data-lucide="%s" class="w-3 h-3"></i> %s</span>',
        htmlspecialchars($cfg['color'], ENT_QUOTES),
        htmlspecialchars($cfg['color'], ENT_QUOTES),
        htmlspecialchars($cfg['icono'], ENT_QUOTES),
        htmlspecialchars($cfg['nombre'], ENT_QUOTES)
    );
}


// ============================================================================
// Tipos de recurrencia
// ============================================================================

const RECURRENCIA_TIPOS = [
    'dias'    => 'días',
    'semanas' => 'semanas',
    'meses'   => 'meses',
    'anios'   => 'años',
];

function fmt_recurrencia(?string $tipo, ?int $valor): string {
    if (!$tipo || !$valor || $valor < 1) return '';
    $unidad = RECURRENCIA_TIPOS[$tipo] ?? $tipo;
    if ($valor === 1) {
        $singular = ['dias' => 'día', 'semanas' => 'semana', 'meses' => 'mes', 'anios' => 'año'];
        return 'Cada ' . ($singular[$tipo] ?? $unidad);
    }
    return "Cada $valor $unidad";
}


// ============================================================================
// Cálculo de próxima fecha según recurrencia
// ============================================================================

function calcular_proxima_fecha(string $fecha_base, string $tipo, int $valor): string {
    $dt = new DateTime($fecha_base);
    $intervalo_str = '';

    switch ($tipo) {
        case 'dias':    $intervalo_str = "P{$valor}D"; break;
        case 'semanas': $intervalo_str = "P" . ($valor * 7) . "D"; break;
        case 'meses':   $intervalo_str = "P{$valor}M"; break;
        case 'anios':   $intervalo_str = "P{$valor}Y"; break;
        default: return $fecha_base;
    }

    $dt->add(new DateInterval($intervalo_str));
    return $dt->format('Y-m-d');
}


// ============================================================================
// Equipos de un mantenimiento (tabla puente mantenimiento_equipos)
// ----------------------------------------------------------------------------
// Un mantenimiento puede cubrir varios equipos. `mantenimientos.equipo_id`
// se conserva como "equipo principal"; la tabla puente guarda TODOS.
// ============================================================================

/**
 * IDs de los equipos asociados a un mantenimiento.
 * Si por alguna razón aún no hay filas en la puente, cae al equipo principal.
 */
function mantenimiento_equipos_ids(int $mantenimiento_id): array {
    $rows = db_all(
        "SELECT equipo_id FROM mantenimiento_equipos WHERE mantenimiento_id = :id ORDER BY equipo_id",
        ['id' => $mantenimiento_id]
    );
    $ids = array_map(fn($r) => (int) $r['equipo_id'], $rows);
    if (!$ids) {
        $m = db_one("SELECT equipo_id FROM mantenimientos WHERE id = :id", ['id' => $mantenimiento_id]);
        if ($m && $m['equipo_id']) $ids = [(int) $m['equipo_id']];
    }
    return $ids;
}

/**
 * Datos completos (para mostrar) de los equipos de un mantenimiento.
 */
function mantenimiento_equipos(int $mantenimiento_id): array {
    return db_all(
        "SELECT e.id, e.codigo_inventario, e.nombre, e.tipo, e.sucursal_id,
                s.nombre sucursal_nombre
         FROM mantenimiento_equipos me
         INNER JOIN equipos e ON me.equipo_id = e.id
         INNER JOIN sucursales s ON e.sucursal_id = s.id
         WHERE me.mantenimiento_id = :id
         ORDER BY s.nombre, e.codigo_inventario",
        ['id' => $mantenimiento_id]
    );
}

/**
 * Reemplaza el conjunto de equipos de un mantenimiento por el indicado.
 * Mantiene el primer equipo como `mantenimientos.equipo_id` (equipo principal).
 * Los IDs se normalizan (enteros únicos > 0).
 */
function sincronizar_mantenimiento_equipos(int $mantenimiento_id, array $equipo_ids): void {
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $equipo_ids),
        fn($v) => $v > 0
    )));
    if (!$ids) return;

    db_exec("DELETE FROM mantenimiento_equipos WHERE mantenimiento_id = :id", ['id' => $mantenimiento_id]);
    foreach ($ids as $eid) {
        db_exec(
            "INSERT IGNORE INTO mantenimiento_equipos (mantenimiento_id, equipo_id) VALUES (:m, :e)",
            ['m' => $mantenimiento_id, 'e' => $eid]
        );
    }
    db_exec(
        "UPDATE mantenimientos SET equipo_id = :e WHERE id = :id",
        ['e' => $ids[0], 'id' => $mantenimiento_id]
    );
}


// ============================================================================
// Generar siguiente mantenimiento recurrente
// ============================================================================

/**
 * Cuando se completa un mantenimiento recurrente, crea automáticamente el siguiente.
 * Retorna el ID del nuevo mantenimiento creado, o null si no era recurrente.
 */
function generar_siguiente_recurrente(int $mantenimiento_id): ?int {
    $m = db_one("SELECT * FROM mantenimientos WHERE id = :id", ['id' => $mantenimiento_id]);
    if (!$m) return null;
    if ((int) $m['es_recurrente'] !== 1) return null;
    if (!$m['recurrencia_tipo'] || !$m['recurrencia_valor']) return null;

    // Verificar que no exista ya un siguiente para este (evitar duplicados)
    $existe = db_one(
        "SELECT id FROM mantenimientos
         WHERE mantenimiento_padre_id = :pid AND estado IN ('programado','proximo','en_progreso')
         LIMIT 1",
        ['pid' => $mantenimiento_id]
    );
    if ($existe) return null;

    // Calcular nueva fecha basada en la fecha programada original (no la de completado)
    // así no se va corriendo si se completa tarde
    $proxima_fecha = calcular_proxima_fecha(
        $m['fecha_programada'],
        (string) $m['recurrencia_tipo'],
        (int) $m['recurrencia_valor']
    );

    // El padre real es el padre del actual (si lo tiene) o el actual mismo
    $padre_real = $m['mantenimiento_padre_id'] ?: $m['id'];

    db_exec(
        "INSERT INTO mantenimientos
         (equipo_id, titulo, descripcion, fecha_programada, hora_programada,
          asignado_a_id, proveedor_id, estado,
          es_recurrente, recurrencia_tipo, recurrencia_valor, mantenimiento_padre_id,
          creado_por_id)
         VALUES (:eid, :tit, :desc, :fp, :hp, :aid, :pid, 'programado',
                 1, :rt, :rv, :prv, :cid)",
        [
            'eid'  => $m['equipo_id'],
            'tit'  => $m['titulo'],
            'desc' => $m['descripcion'],
            'fp'   => $proxima_fecha,
            'hp'   => $m['hora_programada'],
            'aid'  => $m['asignado_a_id'],
            'pid'  => $m['proveedor_id'],
            'rt'   => $m['recurrencia_tipo'],
            'rv'   => $m['recurrencia_valor'],
            'prv'  => $padre_real,
            'cid'  => $m['creado_por_id'],
        ]
    );

    $nuevo_id = (int) db_last_id();

    // Copiar el conjunto de equipos del mantenimiento original a la nueva ocurrencia
    $equipos = mantenimiento_equipos_ids($mantenimiento_id);
    if ($equipos) {
        sincronizar_mantenimiento_equipos($nuevo_id, $equipos);
    }

    return $nuevo_id;
}


// ============================================================================
// Actualizar estados automáticamente
// ============================================================================

/**
 * Recorre mantenimientos en estado 'programado' y los pasa a:
 *   - 'proximo': si faltan <= 3 días
 *   - 'vencido': si la fecha ya pasó y siguen sin completar
 *
 * Se llama desde cron o cada vez que se carga el listado.
 */
function actualizar_estados_mantenimientos(): array {
    $hoy = date('Y-m-d');
    $en_3_dias = date('Y-m-d', strtotime('+3 days'));

    // Programados → próximos (faltan ≤ 3 días)
    db_exec(
        "UPDATE mantenimientos
         SET estado = 'proximo'
         WHERE estado = 'programado'
           AND fecha_programada <= :en3
           AND fecha_programada >= :hoy",
        ['en3' => $en_3_dias, 'hoy' => $hoy]
    );

    // Programados / próximos / en_progreso → vencidos (la fecha ya pasó)
    db_exec(
        "UPDATE mantenimientos
         SET estado = 'vencido'
         WHERE estado IN ('programado','proximo','en_progreso')
           AND fecha_programada < :hoy",
        ['hoy' => $hoy]
    );

    return [
        'actualizados_proximos' => 0, // No regresamos conteo para simplicidad
        'actualizados_vencidos' => 0,
    ];
}


// ============================================================================
// Permisos
// ============================================================================

/**
 * ¿Puede el usuario crear/editar/eliminar mantenimientos?
 * Admin + ingenieros (puede_resolver)
 */
function puede_administrar_mantenimientos(): bool {
    return tiene_permiso('administrar') || tiene_permiso('resolver');
}


// ============================================================================
// Consultas comunes
// ============================================================================

/**
 * Próximos mantenimientos en los siguientes N días (para widget de dashboard).
 */
function proximos_mantenimientos(int $dias = 14, ?int $sucursal_id = null): array {
    $hoy = date('Y-m-d');
    $hasta = date('Y-m-d', strtotime("+$dias days"));

    $params = ['h' => $hoy, 'f' => $hasta];
    $where_suc = '';
    if ($sucursal_id) {
        $where_suc = " AND e.sucursal_id = :sid ";
        $params['sid'] = $sucursal_id;
    }

    return db_all(
        "SELECT m.*, e.codigo_inventario equipo_codigo, e.nombre equipo_nombre,
                s.nombre sucursal_nombre, u.nombre_completo asignado_nombre,
                p.nombre proveedor_nombre
         FROM mantenimientos m
         INNER JOIN equipos e ON m.equipo_id = e.id
         INNER JOIN sucursales s ON e.sucursal_id = s.id
         LEFT JOIN usuarios u ON m.asignado_a_id = u.id
         LEFT JOIN proveedores p ON m.proveedor_id = p.id
         WHERE m.estado IN ('programado','proximo','en_progreso','vencido')
           AND m.fecha_programada BETWEEN :h AND :f
           $where_suc
         ORDER BY m.fecha_programada ASC, m.hora_programada ASC
         LIMIT 30",
        $params
    );
}


/**
 * Mantenimientos vencidos (fecha ya pasó y sin completar) que pueden requerir atención.
 */
function mantenimientos_vencidos(?int $sucursal_id = null): array {
    $params = [];
    $where_suc = '';
    if ($sucursal_id) {
        $where_suc = " AND e.sucursal_id = :sid ";
        $params['sid'] = $sucursal_id;
    }

    return db_all(
        "SELECT m.*, e.codigo_inventario equipo_codigo, e.nombre equipo_nombre,
                s.nombre sucursal_nombre
         FROM mantenimientos m
         INNER JOIN equipos e ON m.equipo_id = e.id
         INNER JOIN sucursales s ON e.sucursal_id = s.id
         WHERE m.estado = 'vencido' $where_suc
         ORDER BY m.fecha_programada ASC
         LIMIT 20",
        $params
    );
}
