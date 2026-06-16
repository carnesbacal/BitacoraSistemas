<?php
/**
 * ============================================================================
 * api/tecnico_menos_cargado.php
 * ============================================================================
 * Retorna el técnico con menos incidencias abiertas en este momento.
 * Usado por el botón "Asignar al menos cargado" en el formulario.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/inteligencia_helpers.php';

header('Content-Type: application/json; charset=utf-8');
requerir_login();

$sucursal_id = (int) input('sucursal_id', 0) ?: null;

$tecnico = tecnico_menos_cargado($sucursal_id);

if ($tecnico) {
    echo json_encode([
        'ok' => true,
        'id' => (int) $tecnico['id'],
        'nombre' => $tecnico['nombre_completo'],
        'avatar_url' => $tecnico['avatar_url'] ? url($tecnico['avatar_url']) : null,
        'abiertas' => (int) $tecnico['abiertas'],
    ]);
} else {
    echo json_encode(['ok' => false, 'error' => 'No hay técnicos disponibles']);
}
