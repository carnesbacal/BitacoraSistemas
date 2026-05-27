<?php
/**
 * ============================================================================
 * api/vault_favorito.php - Toggle favorito de una entrada del vault
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/vault_helpers.php';

requerir_login();
header('Content-Type: application/json; charset=utf-8');

if (!es_post() || !csrf_valido(input('_csrf'))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF inválido']);
    exit;
}

$u = usuario_actual();
$entrada_id = (int) input('entrada_id', 0);

if ($entrada_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

$entrada = vault_obtener_entrada($entrada_id);
if (!$entrada || !vault_usuario_puede_ver($entrada, $u)) {
    echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
    exit;
}

$estado = vault_toggle_favorito($entrada_id, (int) $u['id']);
echo json_encode(['ok' => true, 'estado' => $estado]);
