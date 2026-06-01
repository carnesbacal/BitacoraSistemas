<?php
/**
 * ============================================================================
 * config/db.php - Conexión a la base de datos
 * ============================================================================
 * Usa PDO con prepared statements para prevenir SQL injection.
 * Ajusta las credenciales si tu XAMPP usa valores diferentes.
 * ============================================================================
 */

// --- Credenciales de conexión (ajustar si es necesario) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'carnes_bacal');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- Configuración global de la app ---
define('APP_NAME', 'Carnes Bacal · Bitácora');
define('APP_VERSION', '1.5.1');

/**
 * APP_URL se auto-detecta a partir de la ubicación real del proyecto.
 * Funciona sin importar dónde lo copies dentro de htdocs (en raíz, en
 * subcarpeta, en subsubcarpeta, etc.). NO HACE FALTA MODIFICAR ESTO.
 *
 * Ejemplo: si el proyecto está en C:\xampp\htdocs\UtilidadesBacal\BitacoraSistemas
 * APP_URL se calculará automáticamente como:
 *   http://localhost/UtilidadesBacal/BitacoraSistemas
 */
$_protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// __DIR__ apunta a /config, así que subimos un nivel para llegar a la raíz del proyecto
$_raiz_proyecto = realpath(__DIR__ . '/..');
// DOCUMENT_ROOT es C:\xampp\htdocs (o equivalente)
$_doc_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
// Calculamos la parte de URL: lo que está después de htdocs
if ($_doc_root && $_raiz_proyecto && str_starts_with($_raiz_proyecto, $_doc_root)) {
    $_ruta_relativa = substr($_raiz_proyecto, strlen($_doc_root));
    $_ruta_relativa = str_replace('\\', '/', $_ruta_relativa); // Windows → URL
    define('APP_URL', $_protocolo . '://' . $_host . $_ruta_relativa);
} else {
    // Fallback por si no se puede detectar automáticamente
    define('APP_URL', $_protocolo . '://' . $_host);
}
unset($_protocolo, $_host, $_raiz_proyecto, $_doc_root, $_ruta_relativa);

// --- Zona horaria ---
date_default_timezone_set('America/Tijuana');

/**
 * Devuelve una instancia única de PDO (singleton).
 * Usar: $pdo = db();
 */
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En producción no mostrar detalles del error; en desarrollo sí
            die("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }

    return $pdo;
}

/**
 * Helper rápido para consultas que devuelven UN solo registro.
 */
function db_one(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Helper rápido para consultas que devuelven MUCHOS registros.
 */
function db_all(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Helper para INSERT / UPDATE / DELETE. Devuelve filas afectadas.
 */
function db_exec(string $sql, array $params = []): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Devuelve el último ID insertado.
 */
function db_last_id(): int {
    return (int) db()->lastInsertId();
}
