<?php
/**
 * ============================================================================
 * config/proyectos_helpers.php
 * ============================================================================
 * Gestión del módulo de proyectos:
 *   - CRUD de proyectos
 *   - Participantes
 *   - Tareas/hitos
 *   - Comentarios (timeline)
 *   - Adjuntos
 * ============================================================================
 */

require_once __DIR__ . '/db.php';


// ============================================================================
// CATÁLOGOS / CONSTANTES
// ============================================================================

const PROYECTO_TIPOS_SUGERIDOS = [
    'Desarrollo de software',
    'Migración de sistemas',
    'Implementación de software',
    'Implementación de hardware',
    'Configuración de red',
    'Investigación',
    'Capacitación',
    'Documentación',
    'Auditoría / seguridad',
    'Optimización / mejora',
    'Soporte estratégico',
    'Otro',
];

const PROYECTO_ESTADOS = [
    'propuesto' => ['label' => 'Propuesto',  'color' => '#71717A', 'icono' => 'lightbulb'],
    'aprobado'  => ['label' => 'Aprobado',   'color' => '#0EA5E9', 'icono' => 'check-circle'],
    'en_curso'  => ['label' => 'En curso',   'color' => '#7C3AED', 'icono' => 'play-circle'],
    'pausado'   => ['label' => 'Pausado',    'color' => '#F59E0B', 'icono' => 'pause-circle'],
    'completado'=> ['label' => 'Completado', 'color' => '#16A34A', 'icono' => 'check-circle-2'],
    'cancelado' => ['label' => 'Cancelado',  'color' => '#DC2626', 'icono' => 'x-circle'],
];

const PROYECTO_PRIORIDADES = [
    'baja'    => ['label' => 'Baja',     'color' => '#16A34A'],
    'media'   => ['label' => 'Media',    'color' => '#0EA5E9'],
    'alta'    => ['label' => 'Alta',     'color' => '#F59E0B'],
    'critica' => ['label' => 'Crítica',  'color' => '#DC2626'],
];

const PROYECTO_TAREA_ESTADOS = [
    'pendiente'    => ['label' => 'Pendiente',    'color' => '#71717A', 'icono' => 'circle'],
    'en_progreso'  => ['label' => 'En progreso',  'color' => '#0EA5E9', 'icono' => 'play-circle'],
    'bloqueada'    => ['label' => 'Bloqueada',    'color' => '#DC2626', 'icono' => 'alert-octagon'],
    'completada'   => ['label' => 'Completada',   'color' => '#16A34A', 'icono' => 'check-circle-2'],
    'cancelada'    => ['label' => 'Cancelada',    'color' => '#9CA3AF', 'icono' => 'x-circle'],
];


function etiqueta_estado_proyecto(string $estado): array {
    return PROYECTO_ESTADOS[$estado] ?? PROYECTO_ESTADOS['propuesto'];
}

function etiqueta_prioridad_proyecto(string $prioridad): array {
    return PROYECTO_PRIORIDADES[$prioridad] ?? PROYECTO_PRIORIDADES['media'];
}

function etiqueta_estado_tarea(string $estado): array {
    return PROYECTO_TAREA_ESTADOS[$estado] ?? PROYECTO_TAREA_ESTADOS['pendiente'];
}


// ============================================================================
// LISTADOS
// ============================================================================

/**
 * Lista proyectos con filtros opcionales.
 */
function listar_proyectos(array $filtros = []): array {
    $where = ["p.activo = 1"];
    $params = [];

    if (!empty($filtros['busqueda'])) {
        $like = '%' . $filtros['busqueda'] . '%';
        $where[] = "(p.codigo LIKE :q1 OR p.nombre LIKE :q2 OR p.descripcion LIKE :q3)";
        $params['q1'] = $like;
        $params['q2'] = $like;
        $params['q3'] = $like;
    }
    if (!empty($filtros['estado'])) {
        $where[] = "p.estado = :est";
        $params['est'] = $filtros['estado'];
    }
    if (!empty($filtros['prioridad'])) {
        $where[] = "p.prioridad = :pri";
        $params['pri'] = $filtros['prioridad'];
    }
    if (!empty($filtros['tipo'])) {
        $where[] = "p.tipo = :tp";
        $params['tp'] = $filtros['tipo'];
    }
    if (!empty($filtros['sucursal_id'])) {
        $where[] = "(p.sucursal_id = :sid OR p.sucursal_id IS NULL)";
        $params['sid'] = (int) $filtros['sucursal_id'];
    }
    if (!empty($filtros['lider_id'])) {
        $where[] = "p.lider_id = :lid";
        $params['lid'] = (int) $filtros['lider_id'];
    }
    if (!empty($filtros['participante_id'])) {
        $where[] = "(p.lider_id = :pid1
                     OR EXISTS (SELECT 1 FROM proyecto_participantes pp
                                WHERE pp.proyecto_id = p.id AND pp.usuario_id = :pid2))";
        $params['pid1'] = (int) $filtros['participante_id'];
        $params['pid2'] = (int) $filtros['participante_id'];
    }

    $where_sql = "WHERE " . implode(' AND ', $where);

    return db_all(
        "SELECT p.*,
                s.codigo AS sucursal_codigo, s.nombre AS sucursal_nombre,
                a.nombre AS area_nombre,
                u_lid.nombre_completo AS lider_nombre,
                u_sug.nombre_completo AS sugerido_por_nombre,
                (SELECT COUNT(*) FROM proyecto_participantes WHERE proyecto_id = p.id) AS num_participantes,
                (SELECT COUNT(*) FROM proyecto_tareas WHERE proyecto_id = p.id) AS num_tareas,
                (SELECT COUNT(*) FROM proyecto_tareas WHERE proyecto_id = p.id AND estado = 'completada') AS tareas_completadas,
                (SELECT COUNT(*) FROM proyecto_comentarios WHERE proyecto_id = p.id) AS num_comentarios,
                (SELECT COUNT(*) FROM proyecto_adjuntos WHERE proyecto_id = p.id) AS num_adjuntos
         FROM proyectos p
         LEFT JOIN sucursales s ON p.sucursal_id = s.id
         LEFT JOIN areas a ON p.area_id = a.id
         LEFT JOIN usuarios u_lid ON p.lider_id = u_lid.id
         LEFT JOIN usuarios u_sug ON p.sugerido_por_id = u_sug.id
         $where_sql
         ORDER BY
            FIELD(p.estado, 'en_curso', 'aprobado', 'propuesto', 'pausado', 'completado', 'cancelado'),
            FIELD(p.prioridad, 'critica', 'alta', 'media', 'baja'),
            p.creado_en DESC",
        $params
    );
}


function obtener_proyecto(int $id): ?array {
    $r = db_one(
        "SELECT p.*,
                s.codigo AS sucursal_codigo, s.nombre AS sucursal_nombre,
                a.nombre AS area_nombre, a.color AS area_color,
                u_lid.nombre_completo AS lider_nombre, u_lid.usuario AS lider_usuario,
                u_sug.nombre_completo AS sugerido_por_nombre,
                u_apr.nombre_completo AS aprobado_por_nombre,
                u_crea.nombre_completo AS creado_por_nombre
         FROM proyectos p
         LEFT JOIN sucursales s ON p.sucursal_id = s.id
         LEFT JOIN areas a ON p.area_id = a.id
         LEFT JOIN usuarios u_lid ON p.lider_id = u_lid.id
         LEFT JOIN usuarios u_sug ON p.sugerido_por_id = u_sug.id
         LEFT JOIN usuarios u_apr ON p.aprobado_por_id = u_apr.id
         LEFT JOIN usuarios u_crea ON p.creado_por_id = u_crea.id
         WHERE p.id = :id",
        ['id' => $id]
    );
    return $r ?: null;
}


// ============================================================================
// CRUD PROYECTO
// ============================================================================

function crear_proyecto(array $datos, int $usuario_id): int {
    db_exec(
        "INSERT INTO proyectos
         (codigo, nombre, descripcion, tipo, estado, prioridad,
          sucursal_id, area_id, lider_id, sugerido_por_id,
          fecha_inicio_plan, fecha_fin_plan,
          presupuesto, cliente_interno, proveedor_externo,
          tecnologias, enlaces, riesgos, notas, creado_por_id)
         VALUES
         (:cod, :nom, :desc, :tipo, :est, :pri,
          :sid, :aid, :lid, :sug,
          :fip, :ffp,
          :pres, :cli, :prov,
          :tec, :enl, :ries, :notas, :uid)",
        [
            'cod' => mb_substr($datos['codigo'], 0, 50),
            'nom' => mb_substr($datos['nombre'], 0, 200),
            'desc' => $datos['descripcion'] ?? null,
            'tipo' => mb_substr($datos['tipo'] ?? 'Otro', 0, 80),
            'est' => $datos['estado'] ?? 'propuesto',
            'pri' => $datos['prioridad'] ?? 'media',
            'sid' => !empty($datos['sucursal_id']) ? (int) $datos['sucursal_id'] : null,
            'aid' => !empty($datos['area_id']) ? (int) $datos['area_id'] : null,
            'lid' => !empty($datos['lider_id']) ? (int) $datos['lider_id'] : null,
            'sug' => $usuario_id,
            'fip' => !empty($datos['fecha_inicio_plan']) ? $datos['fecha_inicio_plan'] : null,
            'ffp' => !empty($datos['fecha_fin_plan']) ? $datos['fecha_fin_plan'] : null,
            'pres' => !empty($datos['presupuesto']) ? (float) $datos['presupuesto'] : null,
            'cli' => $datos['cliente_interno'] ?? null,
            'prov' => $datos['proveedor_externo'] ?? null,
            'tec' => $datos['tecnologias'] ?? null,
            'enl' => $datos['enlaces'] ?? null,
            'ries' => $datos['riesgos'] ?? null,
            'notas' => $datos['notas'] ?? null,
            'uid' => $usuario_id,
        ]
    );
    return (int) db_last_id();
}


function actualizar_proyecto(int $id, array $datos): void {
    db_exec(
        "UPDATE proyectos SET
            codigo = :cod, nombre = :nom, descripcion = :desc,
            tipo = :tipo, prioridad = :pri,
            sucursal_id = :sid, area_id = :aid, lider_id = :lid,
            fecha_inicio_plan = :fip, fecha_fin_plan = :ffp,
            fecha_inicio_real = :fir, fecha_fin_real = :ffr,
            avance = :av,
            presupuesto = :pres, costo_real = :cr,
            cliente_interno = :cli, proveedor_externo = :prov,
            tecnologias = :tec, enlaces = :enl,
            riesgos = :ries, notas = :notas
         WHERE id = :id",
        [
            'cod' => mb_substr($datos['codigo'], 0, 50),
            'nom' => mb_substr($datos['nombre'], 0, 200),
            'desc' => $datos['descripcion'] ?? null,
            'tipo' => mb_substr($datos['tipo'] ?? 'Otro', 0, 80),
            'pri' => $datos['prioridad'] ?? 'media',
            'sid' => !empty($datos['sucursal_id']) ? (int) $datos['sucursal_id'] : null,
            'aid' => !empty($datos['area_id']) ? (int) $datos['area_id'] : null,
            'lid' => !empty($datos['lider_id']) ? (int) $datos['lider_id'] : null,
            'fip' => !empty($datos['fecha_inicio_plan']) ? $datos['fecha_inicio_plan'] : null,
            'ffp' => !empty($datos['fecha_fin_plan']) ? $datos['fecha_fin_plan'] : null,
            'fir' => !empty($datos['fecha_inicio_real']) ? $datos['fecha_inicio_real'] : null,
            'ffr' => !empty($datos['fecha_fin_real']) ? $datos['fecha_fin_real'] : null,
            'av' => max(0, min(100, (int) ($datos['avance'] ?? 0))),
            'pres' => !empty($datos['presupuesto']) ? (float) $datos['presupuesto'] : null,
            'cr' => !empty($datos['costo_real']) ? (float) $datos['costo_real'] : null,
            'cli' => $datos['cliente_interno'] ?? null,
            'prov' => $datos['proveedor_externo'] ?? null,
            'tec' => $datos['tecnologias'] ?? null,
            'enl' => $datos['enlaces'] ?? null,
            'ries' => $datos['riesgos'] ?? null,
            'notas' => $datos['notas'] ?? null,
            'id' => $id,
        ]
    );
}


/**
 * Cambia el estado del proyecto y registra entrada automática en comentarios.
 */
function cambiar_estado_proyecto(int $id, string $nuevo_estado, int $usuario_id, ?string $nota = null): void {
    if (!isset(PROYECTO_ESTADOS[$nuevo_estado])) {
        throw new RuntimeException("Estado inválido: $nuevo_estado");
    }

    $p = db_one("SELECT estado FROM proyectos WHERE id = :id", ['id' => $id]);
    if (!$p) throw new RuntimeException('Proyecto no encontrado.');
    $anterior = $p['estado'];
    if ($anterior === $nuevo_estado) return;

    // Si se aprueba, registrar quién y cuándo
    $set_aprobacion = '';
    $params = ['est' => $nuevo_estado, 'id' => $id];
    if ($nuevo_estado === 'aprobado' || $nuevo_estado === 'en_curso') {
        $set_aprobacion = ', aprobado_por_id = :apr, aprobado_en = NOW()';
        $params['apr'] = $usuario_id;
    }
    // Si pasa a en_curso por primera vez, registrar fecha_inicio_real
    if ($nuevo_estado === 'en_curso') {
        $set_aprobacion .= ', fecha_inicio_real = COALESCE(fecha_inicio_real, CURDATE())';
    }
    // Si se completa, registrar fecha_fin_real
    if ($nuevo_estado === 'completado') {
        $set_aprobacion .= ', fecha_fin_real = COALESCE(fecha_fin_real, CURDATE()), avance = 100';
    }

    db_exec(
        "UPDATE proyectos SET estado = :est $set_aprobacion WHERE id = :id",
        $params
    );

    // Comentario automático
    $lbl_ant = etiqueta_estado_proyecto($anterior)['label'];
    $lbl_nue = etiqueta_estado_proyecto($nuevo_estado)['label'];
    $contenido = "Cambio de estado: **{$lbl_ant}** → **{$lbl_nue}**";
    if ($nota) $contenido .= "\n\n$nota";

    agregar_comentario_proyecto($id, $usuario_id, $contenido, 'cambio_estado');
}


function eliminar_proyecto(int $id): void {
    // Borrado lógico
    db_exec("UPDATE proyectos SET activo = 0 WHERE id = :id", ['id' => $id]);
}


// ============================================================================
// PARTICIPANTES
// ============================================================================

function listar_participantes(int $proyecto_id): array {
    return db_all(
        "SELECT pp.*, u.nombre_completo, u.usuario, u.email, r.nombre AS rol_sistema
         FROM proyecto_participantes pp
         INNER JOIN usuarios u ON pp.usuario_id = u.id
         LEFT JOIN roles r ON u.rol_id = r.id
         WHERE pp.proyecto_id = :pid
         ORDER BY pp.asignado_en ASC",
        ['pid' => $proyecto_id]
    );
}


function agregar_participante(int $proyecto_id, int $usuario_id, ?string $rol_en_proyecto, int $asignador_id): void {
    db_exec(
        "INSERT IGNORE INTO proyecto_participantes
         (proyecto_id, usuario_id, rol_en_proyecto, asignado_por_id)
         VALUES (:pid, :uid, :rol, :asig)",
        [
            'pid' => $proyecto_id,
            'uid' => $usuario_id,
            'rol' => $rol_en_proyecto ? mb_substr($rol_en_proyecto, 0, 80) : null,
            'asig' => $asignador_id,
        ]
    );
}


function quitar_participante(int $proyecto_id, int $usuario_id): void {
    db_exec(
        "DELETE FROM proyecto_participantes
         WHERE proyecto_id = :pid AND usuario_id = :uid",
        ['pid' => $proyecto_id, 'uid' => $usuario_id]
    );
}


// ============================================================================
// COMENTARIOS
// ============================================================================

function listar_comentarios_proyecto(int $proyecto_id): array {
    return db_all(
        "SELECT pc.*, u.nombre_completo, u.usuario,
                (SELECT COUNT(*) FROM proyecto_adjuntos WHERE comentario_id = pc.id) AS num_adjuntos
         FROM proyecto_comentarios pc
         INNER JOIN usuarios u ON pc.usuario_id = u.id
         WHERE pc.proyecto_id = :pid
         ORDER BY pc.creado_en DESC",
        ['pid' => $proyecto_id]
    );
}


function agregar_comentario_proyecto(int $proyecto_id, int $usuario_id, string $contenido, string $tipo = 'comentario'): int {
    db_exec(
        "INSERT INTO proyecto_comentarios (proyecto_id, usuario_id, contenido, tipo)
         VALUES (:pid, :uid, :cont, :tipo)",
        ['pid' => $proyecto_id, 'uid' => $usuario_id, 'cont' => $contenido, 'tipo' => $tipo]
    );
    return (int) db_last_id();
}


function editar_comentario_proyecto(int $comentario_id, string $nuevo_contenido): void {
    db_exec(
        "UPDATE proyecto_comentarios SET contenido = :c, editado_en = NOW() WHERE id = :id",
        ['c' => $nuevo_contenido, 'id' => $comentario_id]
    );
}


function eliminar_comentario_proyecto(int $comentario_id): void {
    db_exec("DELETE FROM proyecto_comentarios WHERE id = :id", ['id' => $comentario_id]);
}


// ============================================================================
// TAREAS
// ============================================================================

function listar_tareas_proyecto(int $proyecto_id): array {
    return db_all(
        "SELECT pt.*, u.nombre_completo AS asignada_a_nombre, u_creador.nombre_completo AS creado_por_nombre
         FROM proyecto_tareas pt
         LEFT JOIN usuarios u ON pt.asignada_a_id = u.id
         LEFT JOIN usuarios u_creador ON pt.creado_por_id = u_creador.id
         WHERE pt.proyecto_id = :pid
         ORDER BY pt.orden ASC, pt.creado_en ASC",
        ['pid' => $proyecto_id]
    );
}


function crear_tarea_proyecto(int $proyecto_id, array $datos, int $creador_id): int {
    $orden = db_one(
        "SELECT COALESCE(MAX(orden), 0) + 1 AS prox FROM proyecto_tareas WHERE proyecto_id = :pid",
        ['pid' => $proyecto_id]
    );
    $orden = (int) ($orden['prox'] ?? 1);

    db_exec(
        "INSERT INTO proyecto_tareas
         (proyecto_id, titulo, descripcion, es_hito, asignada_a_id,
          fecha_inicio, fecha_fin_plan, orden, creado_por_id)
         VALUES (:pid, :tit, :desc, :hito, :asig, :fi, :ffp, :ord, :uid)",
        [
            'pid' => $proyecto_id,
            'tit' => mb_substr($datos['titulo'], 0, 200),
            'desc' => $datos['descripcion'] ?? null,
            'hito' => !empty($datos['es_hito']) ? 1 : 0,
            'asig' => !empty($datos['asignada_a_id']) ? (int) $datos['asignada_a_id'] : null,
            'fi' => !empty($datos['fecha_inicio']) ? $datos['fecha_inicio'] : null,
            'ffp' => !empty($datos['fecha_fin_plan']) ? $datos['fecha_fin_plan'] : null,
            'ord' => $orden,
            'uid' => $creador_id,
        ]
    );
    return (int) db_last_id();
}


function actualizar_tarea_proyecto(int $tarea_id, array $datos): void {
    db_exec(
        "UPDATE proyecto_tareas SET
            titulo = :tit, descripcion = :desc, es_hito = :hito,
            asignada_a_id = :asig, fecha_inicio = :fi, fecha_fin_plan = :ffp
         WHERE id = :id",
        [
            'tit' => mb_substr($datos['titulo'], 0, 200),
            'desc' => $datos['descripcion'] ?? null,
            'hito' => !empty($datos['es_hito']) ? 1 : 0,
            'asig' => !empty($datos['asignada_a_id']) ? (int) $datos['asignada_a_id'] : null,
            'fi' => !empty($datos['fecha_inicio']) ? $datos['fecha_inicio'] : null,
            'ffp' => !empty($datos['fecha_fin_plan']) ? $datos['fecha_fin_plan'] : null,
            'id' => $tarea_id,
        ]
    );
}


function cambiar_estado_tarea(int $tarea_id, string $nuevo_estado): void {
    $set_completada = '';
    if ($nuevo_estado === 'completada') {
        $set_completada = ', fecha_completada = COALESCE(fecha_completada, NOW())';
    } else {
        $set_completada = ', fecha_completada = NULL';
    }
    db_exec(
        "UPDATE proyecto_tareas SET estado = :est $set_completada WHERE id = :id",
        ['est' => $nuevo_estado, 'id' => $tarea_id]
    );
}


function eliminar_tarea_proyecto(int $tarea_id): void {
    db_exec("DELETE FROM proyecto_tareas WHERE id = :id", ['id' => $tarea_id]);
}


// ============================================================================
// ADJUNTOS
// ============================================================================

function listar_adjuntos_proyecto(int $proyecto_id, ?int $comentario_id = null): array {
    if ($comentario_id !== null) {
        return db_all(
            "SELECT pa.*, u.nombre_completo AS subido_por_nombre
             FROM proyecto_adjuntos pa
             LEFT JOIN usuarios u ON pa.subido_por_id = u.id
             WHERE pa.proyecto_id = :pid AND pa.comentario_id = :cid
             ORDER BY pa.subido_en DESC",
            ['pid' => $proyecto_id, 'cid' => $comentario_id]
        );
    }
    return db_all(
        "SELECT pa.*, u.nombre_completo AS subido_por_nombre
         FROM proyecto_adjuntos pa
         LEFT JOIN usuarios u ON pa.subido_por_id = u.id
         WHERE pa.proyecto_id = :pid AND pa.comentario_id IS NULL
         ORDER BY pa.subido_en DESC",
        ['pid' => $proyecto_id]
    );
}


function guardar_adjunto_proyecto(int $proyecto_id, ?int $comentario_id, array $archivo, int $usuario_id, ?string $descripcion = null): int {
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir: código ' . $archivo['error']);
    }
    if ($archivo['size'] > 20 * 1024 * 1024) {
        throw new RuntimeException('El archivo excede 20 MB.');
    }

    $dir = __DIR__ . '/../uploads/proyectos/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $nombre_original = $archivo['name'];
    $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    $bloqueadas = ['php','phtml','php3','php4','php5','php7','phps','sh','exe','bat','cmd','com','scr'];
    if (in_array($ext, $bloqueadas, true)) {
        throw new RuntimeException("Tipo de archivo bloqueado: $ext");
    }

    $nombre_archivo = 'proy' . $proyecto_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destino = $dir . $nombre_archivo;

    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
        throw new RuntimeException('No se pudo mover el archivo.');
    }

    db_exec(
        "INSERT INTO proyecto_adjuntos
         (proyecto_id, comentario_id, nombre_original, nombre_archivo,
          tipo_mime, tamano_bytes, subido_por_id, descripcion)
         VALUES (:pid, :cid, :nom_ori, :nom_arch, :mime, :tam, :uid, :desc)",
        [
            'pid' => $proyecto_id,
            'cid' => $comentario_id,
            'nom_ori' => mb_substr($nombre_original, 0, 255),
            'nom_arch' => $nombre_archivo,
            'mime' => $archivo['type'] ?? null,
            'tam' => (int) $archivo['size'],
            'uid' => $usuario_id,
            'desc' => $descripcion ? mb_substr($descripcion, 0, 255) : null,
        ]
    );
    return (int) db_last_id();
}


function eliminar_adjunto_proyecto(int $adjunto_id): void {
    $a = db_one("SELECT nombre_archivo FROM proyecto_adjuntos WHERE id = :id", ['id' => $adjunto_id]);
    if ($a) {
        $ruta = __DIR__ . '/../uploads/proyectos/' . $a['nombre_archivo'];
        if (file_exists($ruta)) @unlink($ruta);
        db_exec("DELETE FROM proyecto_adjuntos WHERE id = :id", ['id' => $adjunto_id]);
    }
}


// ============================================================================
// ESTADÍSTICAS Y PERMISOS
// ============================================================================

function stats_proyectos(): array {
    $r = db_one(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN estado IN ('en_curso','aprobado') THEN 1 ELSE 0 END) AS activos,
            SUM(CASE WHEN estado = 'propuesto' THEN 1 ELSE 0 END) AS propuestos,
            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) AS completados,
            SUM(CASE WHEN estado = 'pausado' THEN 1 ELSE 0 END) AS pausados,
            SUM(CASE WHEN prioridad = 'critica' AND estado IN ('en_curso','aprobado','propuesto') THEN 1 ELSE 0 END) AS criticos
         FROM proyectos
         WHERE activo = 1"
    );
    return [
        'total' => (int) ($r['total'] ?? 0),
        'activos' => (int) ($r['activos'] ?? 0),
        'propuestos' => (int) ($r['propuestos'] ?? 0),
        'completados' => (int) ($r['completados'] ?? 0),
        'pausados' => (int) ($r['pausados'] ?? 0),
        'criticos' => (int) ($r['criticos'] ?? 0),
    ];
}


/**
 * ¿Puede editar este proyecto?
 * - Admin: siempre
 * - Líder del proyecto: sí
 * - Participante: sí (pueden colaborar)
 * - Ingeniero/técnico: sí (puede crear/editar pero no eliminar)
 */
function puede_editar_proyecto(array $proyecto, ?array $usuario = null): bool {
    if (!$usuario) {
        if (!function_exists('usuario_actual')) return false;
        $usuario = usuario_actual();
    }
    if (!$usuario) return false;

    if (function_exists('tiene_permiso')) {
        if (tiene_permiso('administrar')) return true;
        if (tiene_permiso('resolver')) return true; // ingenieros pueden editar
    }

    // Líder del proyecto
    if ((int) ($proyecto['lider_id'] ?? 0) === (int) $usuario['id']) return true;

    // Participante
    $part = db_one(
        "SELECT id FROM proyecto_participantes
         WHERE proyecto_id = :pid AND usuario_id = :uid",
        ['pid' => (int) $proyecto['id'], 'uid' => (int) $usuario['id']]
    );
    return !empty($part);
}


/**
 * ¿Puede eliminar proyectos? Solo admin (Captura-like: técnicos NO).
 */
function puede_eliminar_proyecto(): bool {
    return function_exists('tiene_permiso') && tiene_permiso('administrar');
}


/**
 * ¿Puede aprobar/cambiar estado a aprobado/en_curso?
 * Admin o líder del proyecto.
 */
function puede_aprobar_proyecto(array $proyecto, ?array $usuario = null): bool {
    if (!$usuario) {
        if (!function_exists('usuario_actual')) return false;
        $usuario = usuario_actual();
    }
    if (!$usuario) return false;

    if (function_exists('tiene_permiso') && tiene_permiso('administrar')) return true;
    if ((int) ($proyecto['lider_id'] ?? 0) === (int) $usuario['id']) return true;
    return false;
}


/**
 * Tipos de proyecto existentes (para autocompletar combinando los sugeridos + los ya usados).
 */
function tipos_proyecto_usados(): array {
    $r = db_all(
        "SELECT DISTINCT tipo FROM proyectos WHERE tipo IS NOT NULL AND tipo <> '' ORDER BY tipo"
    );
    return array_column($r, 'tipo');
}
