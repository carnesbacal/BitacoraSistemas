<?php
/**
 * ============================================================================
 * api/catalogo_crear_rapido.php
 * ============================================================================
 * Crea un elemento de catálogo (area, categoria, subcategoria, tipo_trabajo,
 * origen) sin salir del formulario de incidencia nueva.
 *
 * POST-only. Requiere permiso `administrar` + CSRF.
 *
 * Parámetros POST:
 *   tabla        — area | categoria | subcategoria | tipo_trabajo | origen
 *   nombre       — texto del nuevo elemento (obligatorio)
 *   color        — hex #rrggbb (opcional, solo para categoria y tipo_trabajo)
 *   categoria_id — id de categoría padre (obligatorio si tabla=subcategoria)
 *
 * Responde JSON: { ok:true, id, nombre, color } | { ok:false, error }
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');
requerir_login();
requerir_permiso('administrar');

if (!es_post() || !csrf_valido(input('_csrf'))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido']);
    exit;
}

// --- Tablas permitidas y sus columnas ---
$tablas_permitidas = [
    'area'          => ['tabla_real' => 'areas',              'col_nombre' => 'nombre', 'tiene_color' => false],
    'categoria'     => ['tabla_real' => 'categorias',         'col_nombre' => 'nombre', 'tiene_color' => true],
    'subcategoria'  => ['tabla_real' => 'subcategorias',      'col_nombre' => 'nombre', 'tiene_color' => false],
    'tipo_trabajo'  => ['tabla_real' => 'tipos_trabajo',      'col_nombre' => 'nombre', 'tiene_color' => true],
    'origen'        => ['tabla_real' => 'origenes_reporte',   'col_nombre' => 'nombre', 'tiene_color' => false],
];

$tabla_key = trim((string) input('tabla', ''));
if (!array_key_exists($tabla_key, $tablas_permitidas)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Tipo de catálogo no válido']);
    exit;
}

$cfg  = $tablas_permitidas[$tabla_key];
$nombre = trim((string) input('nombre', ''));

if ($nombre === '') {
    echo json_encode(['ok' => false, 'error' => 'El nombre es obligatorio']);
    exit;
}
if (mb_strlen($nombre) > 100) {
    echo json_encode(['ok' => false, 'error' => 'El nombre no puede superar 100 caracteres']);
    exit;
}

// Color (solo para tablas que lo admiten)
$color = null;
if ($cfg['tiene_color']) {
    $color_input = trim((string) input('color', '#6B7280'));
    $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $color_input) ? $color_input : '#6B7280';
}

// categoria_id (requerido solo para subcategoria)
$categoria_id = null;
if ($tabla_key === 'subcategoria') {
    $categoria_id = (int) input('categoria_id', 0) ?: null;
    if (!$categoria_id) {
        echo json_encode(['ok' => false, 'error' => 'Debes seleccionar una categoría primero']);
        exit;
    }
}

// --- Verificar duplicados (case-insensitive) ---
$tabla_real = $cfg['tabla_real'];
$col_nombre = $cfg['col_nombre'];

if ($tabla_key === 'subcategoria' && $categoria_id) {
    $existente = db_one(
        "SELECT id FROM {$tabla_real} WHERE LOWER({$col_nombre}) = LOWER(:nombre) AND categoria_id = :cid",
        ['nombre' => $nombre, 'cid' => $categoria_id]
    );
} else {
    $existente = db_one(
        "SELECT id FROM {$tabla_real} WHERE LOWER({$col_nombre}) = LOWER(:nombre)",
        ['nombre' => $nombre]
    );
}

if ($existente) {
    echo json_encode(['ok' => false, 'error' => 'Ya existe un elemento con ese nombre']);
    exit;
}

// --- Insertar ---
try {
    if ($cfg['tiene_color'] && $tabla_key === 'subcategoria') {
        // subcategoria no tiene color pero sí categoria_id
        db_exec(
            "INSERT INTO {$tabla_real} ({$col_nombre}, categoria_id) VALUES (:nombre, :cid)",
            ['nombre' => $nombre, 'cid' => $categoria_id]
        );
    } elseif ($cfg['tiene_color']) {
        db_exec(
            "INSERT INTO {$tabla_real} ({$col_nombre}, color) VALUES (:nombre, :color)",
            ['nombre' => $nombre, 'color' => $color]
        );
    } elseif ($tabla_key === 'subcategoria') {
        db_exec(
            "INSERT INTO {$tabla_real} ({$col_nombre}, categoria_id) VALUES (:nombre, :cid)",
            ['nombre' => $nombre, 'cid' => $categoria_id]
        );
    } else {
        db_exec(
            "INSERT INTO {$tabla_real} ({$col_nombre}) VALUES (:nombre)",
            ['nombre' => $nombre]
        );
    }

    $nuevo_id = db_last_id();

    echo json_encode([
        'ok'     => true,
        'id'     => $nuevo_id,
        'nombre' => $nombre,
        'color'  => $color,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al guardar: ' . $e->getMessage()]);
}
