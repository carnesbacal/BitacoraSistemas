<?php
/**
 * ============================================================================
 * config/app.php - Configuración pública de la aplicación
 * ============================================================================
 * Este archivo SÍ se versiona en git. Aquí va todo lo que no es sensible:
 * nombre, versión, zona horaria, URL auto-detectada y helpers de base de datos.
 *
 * Las credenciales de BD se mantienen en config/db.php (excluido de git).
 * ============================================================================
 */

// --- Metadata de la aplicación ---
define('APP_NAME',    'Carnes Bacal · Mantenimiento');
define('APP_VERSION', '2.0.9');

/**
 * APP_URL se auto-detecta a partir de la ubicación real del proyecto.
 * Funciona sin importar dónde lo copies dentro de htdocs (raíz, subcarpeta, etc.).
 * NO HACE FALTA MODIFICAR ESTO.
 *
 * Ejemplo: si el proyecto está en C:\xampp\htdocs\UtilidadesBacal\BitacoraSistemas
 * APP_URL se calculará como: http://localhost/UtilidadesBacal/BitacoraSistemas
 */
$_protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_raiz_proyecto = realpath(__DIR__ . '/..');
$_doc_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
if ($_doc_root && $_raiz_proyecto && str_starts_with($_raiz_proyecto, $_doc_root)) {
    $_ruta_relativa = substr($_raiz_proyecto, strlen($_doc_root));
    $_ruta_relativa = str_replace('\\', '/', $_ruta_relativa);
    define('APP_URL', $_protocolo . '://' . $_host . $_ruta_relativa);
} else {
    define('APP_URL', $_protocolo . '://' . $_host);
}
unset($_protocolo, $_host, $_raiz_proyecto, $_doc_root, $_ruta_relativa);

// --- Zona horaria ---
date_default_timezone_set('America/Tijuana');

/**
 * Devuelve una instancia única de PDO (singleton).
 * Requiere que DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET ya estén definidos
 * (los define config/db.php antes de incluir este archivo).
 */
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, $port, DB_NAME, DB_CHARSET
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
            die("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }

    return $pdo;
}

/** Consulta que devuelve UN solo registro. */
function db_one(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Consulta que devuelve MUCHOS registros. */
function db_all(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** INSERT / UPDATE / DELETE. Devuelve filas afectadas. */
function db_exec(string $sql, array $params = []): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/** Devuelve el último ID insertado. */
function db_last_id(): int {
    return (int) db()->lastInsertId();
}
