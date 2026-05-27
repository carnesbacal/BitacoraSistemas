<?php
/**
 * ============================================================================
 * api/vault_ver_password.php
 * ============================================================================
 * Devuelve la contraseña descifrada de una entrada del vault.
 * Registra el acceso en auditoría.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/vault_helpers.php';

requerir_login();
header('Content-Type: application/json; charset=utf-8');

$u = usuario_actual();
$id = (int) input('id', 0);
$modo = (string) input('modo', 'ver');

if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

$entrada = vault_obtener_entrada($id);
if (!$entrada) {
    echo json_encode(['ok' => false, 'error' => 'Entrada no encontrada']);
    exit;
}

if (!vault_usuario_puede_ver($entrada, $u)) {
    echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
    exit;
}

$password = vault_obtener_password($id, $u);
if ($password === null) {
    echo json_encode(['ok' => false, 'error' => 'Sin contraseña registrada']);
    exit;
}

// Registrar el acceso
$accion = $modo === 'copiar' ? 'copiar_password' : 'ver_password';
vault_registrar_acceso($id, (int) $u['id'], $accion);

echo json_encode(['ok' => true, 'password' => $password]);
