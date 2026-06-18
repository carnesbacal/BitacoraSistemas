<?php
/**
 * ============================================================================
 * config/notificaciones_helpers.php
 * ============================================================================
 * Funciones para crear y gestionar notificaciones in-app.
 * Las notificaciones se disparan desde PHP cuando ocurren eventos relevantes
 * (asignación, cambio de estado, reincidencia detectada, etc.).
 * ============================================================================
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/notificaciones_canales.php';

// ============================================================================
// Tipos de notificación (para íconos y colores en la UI)
// ============================================================================
const NOTIF_TIPOS = [
    'asignacion'          => ['icono' => 'user-plus',  'color' => '#2563EB'],
    'cambio_estado'       => ['icono' => 'flag',       'color' => '#7C3AED'],
    'comentario'          => ['icono' => 'message-square', 'color' => '#0EA5E9'],
    'mencion'             => ['icono' => 'at-sign',    'color' => '#DB2777'],
    'reincidencia'        => ['icono' => 'rotate-ccw', 'color' => '#A855F7'],
    'sla_vencido'         => ['icono' => 'flame',      'color' => '#DC2626'],
    'sla_riesgo'          => ['icono' => 'clock-alert','color' => '#D97706'],
    'incidencia_creada'   => ['icono' => 'file-plus',  'color' => '#16A34A'],
    'incidencia_resuelta' => ['icono' => 'check-circle-2', 'color' => '#16A34A'],
    'mantenimiento_proximo' => ['icono' => 'calendar-clock', 'color' => '#D97706'],
    'mantenimiento_vencido' => ['icono' => 'alert-triangle', 'color' => '#DC2626'],
    'mantenimiento_completado' => ['icono' => 'wrench', 'color' => '#16A34A'],
    'sistema'             => ['icono' => 'bell',       'color' => '#6B7280'],
];

// ============================================================================
// Crear una notificación
// ============================================================================

/**
 * Crea una notificación para un usuario específico.
 * Evita duplicados recientes del mismo tipo+entidad+usuario en los últimos 5 min.
 *
 * @param int    $usuario_id    Destinatario
 * @param string $tipo          Uno de los tipos en NOTIF_TIPOS
 * @param string $titulo        Título corto
 * @param string $mensaje       Texto descriptivo
 * @param string|null $url      Enlace a donde lleva la notificación
 * @param string|null $entidad  Nombre de la tabla relacionada (ej. 'incidencias')
 * @param int|null $entidad_id  ID del registro relacionado
 */
function crear_notificacion(
    int $usuario_id,
    string $tipo,
    string $titulo,
    string $mensaje,
    ?string $url = null,
    ?string $entidad = null,
    ?int $entidad_id = null
): bool {
    if ($usuario_id <= 0) return false;
    if (!isset(NOTIF_TIPOS[$tipo])) $tipo = 'sistema';

    // Detectar columnas reales de la tabla (cache estático)
    static $cols_cache = null;
    if ($cols_cache === null) {
        try {
            $rows = db_all("SHOW COLUMNS FROM notificaciones");
            $cols_cache = array_column($rows, 'Field');
        } catch (Throwable $e) {
            $cols_cache = [];
        }
    }

    $tiene_entidad = in_array('entidad', $cols_cache, true);
    $col_enlace = in_array('enlace', $cols_cache, true) ? 'enlace' :
                  (in_array('url', $cols_cache, true) ? 'url' : null);
    $col_fecha = in_array('creada_en', $cols_cache, true) ? 'creada_en' : 'creado_en';

    // Evitar duplicados muy seguidos (solo si la columna entidad existe)
    if ($tiene_entidad && $entidad && $entidad_id) {
        try {
            $existe = db_one(
                "SELECT id FROM notificaciones
                 WHERE usuario_id = :uid AND tipo = :tipo
                   AND entidad = :ent AND entidad_id = :eid
                   AND $col_fecha >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                 LIMIT 1",
                ['uid' => $usuario_id, 'tipo' => $tipo, 'ent' => $entidad, 'eid' => $entidad_id]
            );
            if ($existe) return false;
        } catch (Throwable $e) {
            // Si falla, seguimos al INSERT
        }
    }

    try {
        // Construir INSERT dinámicamente según columnas disponibles
        $columnas = ['usuario_id', 'tipo', 'titulo', 'mensaje', 'leida'];
        $valores  = [':uid', ':tipo', ':tit', ':msg', '0'];
        $params = [
            'uid'  => $usuario_id,
            'tipo' => $tipo,
            'tit'  => mb_substr($titulo, 0, 150),
            'msg'  => mb_substr($mensaje, 0, 500),
        ];

        if ($col_enlace) {
            $columnas[] = $col_enlace;
            $valores[]  = ':url';
            $params['url'] = $url;
        }

        if ($tiene_entidad) {
            $columnas[] = 'entidad';
            $valores[]  = ':ent';
            $columnas[] = 'entidad_id';
            $valores[]  = ':eid';
            $params['ent'] = $entidad;
            $params['eid'] = $entidad_id;
        }

        $sql = "INSERT INTO notificaciones (" . implode(',', $columnas) . ") VALUES (" . implode(',', $valores) . ")";
        db_exec($sql, $params);
        $notif_id = db_last_id() ?: null;

        // Despachar canales externos (email / Telegram) — no bloquea aunque falle
        try {
            dispatch_notificacion($usuario_id, $tipo, $titulo, $mensaje, $url, $notif_id);
        } catch (Throwable $de) {
            error_log('dispatch_notificacion fallida: ' . $de->getMessage());
        }

        return true;
    } catch (Throwable $e) {
        error_log('crear_notificacion fallida: ' . $e->getMessage());
        return false;
    }
}

/**
 * Crea la misma notificación para múltiples usuarios.
 */
function crear_notificacion_multiple(array $usuario_ids, string $tipo, string $titulo, string $mensaje, ?string $url = null, ?string $entidad = null, ?int $entidad_id = null): int {
    $count = 0;
    foreach (array_unique($usuario_ids) as $uid) {
        if (crear_notificacion((int) $uid, $tipo, $titulo, $mensaje, $url, $entidad, $entidad_id)) {
            $count++;
        }
    }
    return $count;
}

// ============================================================================
// Disparadores específicos por evento
// ============================================================================

/**
 * Cuando una incidencia se asigna a un técnico, notificarle.
 */
function notificar_asignacion(int $incidencia_id, int $tecnico_id, ?int $asignador_id = null): void {
    if ($tecnico_id === $asignador_id) return; // No se autonotifica

    $inc = db_one(
        "SELECT i.folio, i.titulo, sev.nombre severidad, sev.color severidad_color
         FROM incidencias i
         INNER JOIN severidades sev ON i.severidad_id = sev.id
         WHERE i.id = :id",
        ['id' => $incidencia_id]
    );
    if (!$inc) return;

    crear_notificacion(
        $tecnico_id,
        'asignacion',
        "Se te asignó {$inc['folio']}",
        "{$inc['titulo']} · Severidad: {$inc['severidad']}",
        url_relativa('incidencia_ver.php?id=' . $incidencia_id),
        'incidencias',
        $incidencia_id
    );
}

/**
 * Cuando una incidencia cambia de estado, notificar al reportante y al asignado.
 */
function notificar_cambio_estado(int $incidencia_id, int $nuevo_estado_id, int $actor_id): void {
    $inc = db_one(
        "SELECT i.folio, i.titulo, i.reportado_por_id, i.asignado_a_id,
                est.nombre estado_nombre, est.es_final
         FROM incidencias i
         INNER JOIN estados est ON i.estado_id = est.id
         WHERE i.id = :id",
        ['id' => $incidencia_id]
    );
    if (!$inc) return;

    $destinatarios = [];
    if ($inc['reportado_por_id'] && (int) $inc['reportado_por_id'] !== $actor_id) {
        $destinatarios[] = (int) $inc['reportado_por_id'];
    }
    if ($inc['asignado_a_id'] && (int) $inc['asignado_a_id'] !== $actor_id) {
        $destinatarios[] = (int) $inc['asignado_a_id'];
    }

    $tipo = (int) $inc['es_final'] === 1 ? 'incidencia_resuelta' : 'cambio_estado';
    $titulo_notif = (int) $inc['es_final'] === 1
        ? "{$inc['folio']} resuelta"
        : "{$inc['folio']}: {$inc['estado_nombre']}";

    crear_notificacion_multiple(
        $destinatarios,
        $tipo,
        $titulo_notif,
        $inc['titulo'],
        url_relativa('incidencia_ver.php?id=' . $incidencia_id),
        'incidencias',
        $incidencia_id
    );
}

/**
 * Cuando alguien comenta una incidencia, notificar al reportante y al asignado.
 */
function notificar_comentario(int $incidencia_id, int $autor_id, string $texto_comentario): void {
    $inc = db_one(
        "SELECT i.folio, i.titulo, i.reportado_por_id, i.asignado_a_id, u.nombre_completo autor_nombre
         FROM incidencias i
         LEFT JOIN usuarios u ON u.id = :aid
         WHERE i.id = :id",
        ['id' => $incidencia_id, 'aid' => $autor_id]
    );
    if (!$inc) return;

    $destinatarios = [];
    if ($inc['reportado_por_id'] && (int) $inc['reportado_por_id'] !== $autor_id) {
        $destinatarios[] = (int) $inc['reportado_por_id'];
    }
    if ($inc['asignado_a_id'] && (int) $inc['asignado_a_id'] !== $autor_id) {
        $destinatarios[] = (int) $inc['asignado_a_id'];
    }

    $preview = mb_substr(trim($texto_comentario), 0, 100);
    if (mb_strlen($texto_comentario) > 100) $preview .= '…';

    crear_notificacion_multiple(
        $destinatarios,
        'comentario',
        "Nuevo comentario en {$inc['folio']}",
        ($inc['autor_nombre'] ?? 'Alguien') . ': "' . $preview . '"',
        url_relativa('incidencia_ver.php?id=' . $incidencia_id),
        'incidencias',
        $incidencia_id
    );
}

/**
 * Cuando se detecta una reincidencia, notificar al reportante de la padre y a los ingenieros.
 */
function notificar_reincidencia(int $incidencia_nueva_id, int $padre_id): void {
    $nueva = db_one(
        "SELECT folio, titulo, reportado_por_id, sucursal_id FROM incidencias WHERE id = :id",
        ['id' => $incidencia_nueva_id]
    );
    $padre = db_one(
        "SELECT folio, reportado_por_id, asignado_a_id FROM incidencias WHERE id = :id",
        ['id' => $padre_id]
    );
    if (!$nueva || !$padre) return;

    $destinatarios = [];

    // Notificar al reportante y asignado de la incidencia padre
    if ($padre['reportado_por_id']) $destinatarios[] = (int) $padre['reportado_por_id'];
    if ($padre['asignado_a_id'])    $destinatarios[] = (int) $padre['asignado_a_id'];

    // Notificar a los ingenieros con acceso a la sucursal (solo si la padre no tenía asignado)
    if (!$padre['asignado_a_id']) {
        $ingenieros = db_all(
            "SELECT u.id FROM usuarios u
             INNER JOIN roles r ON u.rol_id = r.id
             WHERE r.puede_resolver = 1 AND u.activo = 1
               AND (u.sucursal_id IS NULL OR u.sucursal_id = :sid)
             LIMIT 5",
            ['sid' => $nueva['sucursal_id']]
        );
        foreach ($ingenieros as $ing) $destinatarios[] = (int) $ing['id'];
    }

    // Quitar al reportante de la nueva (no se autonotifica)
    $destinatarios = array_filter($destinatarios, fn($id) => $id !== (int) $nueva['reportado_por_id']);

    crear_notificacion_multiple(
        $destinatarios,
        'reincidencia',
        "Reincidencia detectada: {$nueva['folio']}",
        "Es reincidencia de {$padre['folio']}: {$nueva['titulo']}",
        url_relativa('incidencia_ver.php?id=' . $incidencia_nueva_id),
        'incidencias',
        $incidencia_nueva_id
    );
}

/**
 * Notifica críticas nuevas a todos los ingenieros con acceso.
 */
function notificar_critica_nueva(int $incidencia_id): void {
    $inc = db_one(
        "SELECT i.folio, i.titulo, i.sucursal_id, s.nombre sucursal_nombre, sev.nivel
         FROM incidencias i
         INNER JOIN sucursales s ON i.sucursal_id = s.id
         INNER JOIN severidades sev ON i.severidad_id = sev.id
         WHERE i.id = :id",
        ['id' => $incidencia_id]
    );
    if (!$inc || (int) $inc['nivel'] !== 1) return; // Solo críticas

    $ingenieros = db_all(
        "SELECT u.id FROM usuarios u
         INNER JOIN roles r ON u.rol_id = r.id
         WHERE r.puede_resolver = 1 AND u.activo = 1
           AND (u.sucursal_id IS NULL OR u.sucursal_id = :sid)",
        ['sid' => $inc['sucursal_id']]
    );

    crear_notificacion_multiple(
        array_column($ingenieros, 'id'),
        'sistema',
        "⚠ CRÍTICA: {$inc['folio']}",
        "Nueva incidencia crítica en {$inc['sucursal_nombre']}: {$inc['titulo']}",
        url_relativa('incidencia_ver.php?id=' . $incidencia_id),
        'incidencias',
        $incidencia_id
    );
}

// ============================================================================
// Lectura y consulta
// ============================================================================

function contar_no_leidas(int $usuario_id): int {
    $row = db_one(
        "SELECT COUNT(*) c FROM notificaciones WHERE usuario_id = :uid AND leida = 0",
        ['uid' => $usuario_id]
    );
    return (int) ($row['c'] ?? 0);
}

function listar_notificaciones(int $usuario_id, int $limite = 20, bool $solo_no_leidas = false): array {
    $where = "WHERE usuario_id = :uid";
    if ($solo_no_leidas) $where .= " AND leida = 0";

    // Detectar columna de fecha (creada_en o creado_en) y de enlace (enlace o url)
    static $col_fecha = null, $col_enlace = null;
    if ($col_fecha === null) {
        try {
            $rows = db_all("SHOW COLUMNS FROM notificaciones");
            $cols = array_column($rows, 'Field');
            $col_fecha = in_array('creada_en', $cols, true) ? 'creada_en' : 'creado_en';
            $col_enlace = in_array('enlace', $cols, true) ? 'enlace' :
                          (in_array('url', $cols, true) ? 'url' : null);
        } catch (Throwable $e) {
            $col_fecha = 'creada_en';
            $col_enlace = 'enlace';
        }
    }

    $rows = db_all(
        "SELECT * FROM notificaciones $where ORDER BY $col_fecha DESC LIMIT $limite",
        ['uid' => $usuario_id]
    );

    // Normalizar campos: el código de la UI espera 'url' y 'creado_en'
    foreach ($rows as &$r) {
        if ($col_enlace === 'enlace' && isset($r['enlace'])) $r['url'] = $r['enlace'];
        if ($col_fecha === 'creada_en' && isset($r['creada_en'])) $r['creado_en'] = $r['creada_en'];
    }
    unset($r);

    return $rows;
}

function marcar_notificacion_leida(int $notificacion_id, int $usuario_id): bool {
    db_exec(
        "UPDATE notificaciones SET leida = 1, leida_en = NOW()
         WHERE id = :id AND usuario_id = :uid",
        ['id' => $notificacion_id, 'uid' => $usuario_id]
    );
    return true;
}

function marcar_todas_leidas(int $usuario_id): int {
    db_exec(
        "UPDATE notificaciones SET leida = 1, leida_en = NOW()
         WHERE usuario_id = :uid AND leida = 0",
        ['uid' => $usuario_id]
    );
    return 1;
}
