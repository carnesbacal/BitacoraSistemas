<?php
/**
 * ============================================================================
 * api/estacion_posicion.php
 * ============================================================================
 * Guarda la posición de una estación en el mapa (drag&drop).
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/mapa_helpers.php';

header('Content-Type: application/json');

requerir_login();

if (!tiene_permiso('administrar')) {
    echo json_encode(['ok' => false, 'error' => 'Sin permisos']);
    exit;
}

if (!es_post() || !csrf_valido(input('_csrf'))) {
    echo json_encode(['ok' => false, 'error' => 'Token inválido']);
    exit;
}

$estacion_id = (int) input('estacion_id', 0);
$planta_id = (int) input('planta_id', 0);
$pos_x_raw = input('pos_x', '');
$pos_y_raw = input('pos_y', '');

if ($estacion_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'estacion_id inválido']);
    exit;
}

try {
    if ($pos_x_raw === '' || $pos_y_raw === '' || $planta_id <= 0) {
        // Desubicar
        actualizar_posicion_estacion($estacion_id, null, null, null);
    } else {
        actualizar_posicion_estacion(
            $estacion_id,
            $planta_id,
            (float) $pos_x_raw,
            (float) $pos_y_raw
        );
    }
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
