<?php
/**
 * ============================================================================
 * admin/importar_descargar_ejemplo.php
 * ============================================================================
 * Genera y descarga un CSV de ejemplo con datos de muestra para cada tipo.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/importacion_helpers.php';

requerir_login();
if (!tiene_permiso('administrar')) {
    http_response_code(403);
    die('Sin permiso.');
}

$tipo = (string) input('tipo', '');
if (!isset(IMPORTAR_COLUMNAS[$tipo])) {
    http_response_code(404);
    die('Tipo no válido.');
}

$contenido = generar_csv_ejemplo($tipo);
$nombre_archivo = "ejemplo_{$tipo}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Content-Length: ' . strlen($contenido));
header('X-Content-Type-Options: nosniff');

echo $contenido;
exit;
