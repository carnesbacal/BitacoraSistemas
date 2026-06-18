<?php
/**
 * ============================================================================
 * config/auth.php - Autenticación y control de sesión
 * ============================================================================
 * Maneja el login, logout, verificación de sesión y permisos por rol.
 * Debe incluirse en TODA página protegida del sistema.
 * ============================================================================
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sesiones_helpers.php';

// --- Configuración de sesión segura ---
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// --- Constantes de seguridad ---
define('MAX_INTENTOS_FALLIDOS', 5);       // Después de 5 intentos se bloquea
define('TIEMPO_BLOQUEO_MIN', 15);          // Bloqueo de 15 minutos
define('TIEMPO_SESION_INACTIVA_MIN', 120); // 2 horas de inactividad

/**
 * Versión del esquema de sesión. Si cambia la estructura de $_SESSION['usuario']
 * (por ejemplo se agregan o quitan campos en login()), INCREMENTAR este número.
 * Esto fuerza un logout automático de sesiones viejas que tengan estructura distinta.
 */
define('SESSION_VERSION', 5);

/**
 * Intenta iniciar sesión con usuario y contraseña.
 * Devuelve [exito, mensaje, debe_cambiar_password].
 */
function login(string $usuario, string $password): array {
    $usuario = trim($usuario);

    if ($usuario === '' || $password === '') {
        return [false, 'Usuario y contraseña son obligatorios.', false];
    }

    $row = db_one(
        "SELECT u.*, r.nombre AS rol_nombre,
                r.puede_administrar, r.puede_ver_todas_sucursales,
                r.puede_resolver, r.puede_crear_solicitud, r.puede_ver_reportes,
                u.preferencias
         FROM usuarios u
         INNER JOIN roles r ON u.rol_id = r.id
         WHERE u.usuario = :usuario AND u.activo = 1",
        ['usuario' => $usuario]
    );

    if (!$row) {
        return [false, 'Usuario o contraseña incorrectos.', false];
    }

    // Verificar bloqueo
    if ($row['bloqueado_hasta'] && strtotime($row['bloqueado_hasta']) > time()) {
        $minutos_restantes = ceil((strtotime($row['bloqueado_hasta']) - time()) / 60);
        return [false, "Cuenta bloqueada temporalmente. Intenta en {$minutos_restantes} minutos.", false];
    }

    // Verificar contraseña
    if (!password_verify($password, $row['password_hash'])) {
        // Incrementar intentos fallidos
        $intentos = (int) $row['intentos_fallidos'] + 1;
        $bloqueo = null;

        if ($intentos >= MAX_INTENTOS_FALLIDOS) {
            $bloqueo = date('Y-m-d H:i:s', time() + TIEMPO_BLOQUEO_MIN * 60);
            $intentos = 0; // resetear para que después del bloqueo empiece de cero
        }

        db_exec(
            "UPDATE usuarios SET intentos_fallidos = :int, bloqueado_hasta = :bloq WHERE id = :id",
            ['int' => $intentos, 'bloq' => $bloqueo, 'id' => $row['id']]
        );

        if ($bloqueo) {
            return [false, 'Demasiados intentos fallidos. Cuenta bloqueada por ' . TIEMPO_BLOQUEO_MIN . ' minutos.', false];
        }

        $restantes = MAX_INTENTOS_FALLIDOS - $intentos;
        return [false, "Usuario o contraseña incorrectos. Intentos restantes: {$restantes}.", false];
    }

    // ÉXITO: limpiar intentos y actualizar último login
    db_exec(
        "UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_login = NOW() WHERE id = :id",
        ['id' => $row['id']]
    );

    // Limpiar TODOS los datos de la sesión anterior antes de regenerar.
    // Necesario para evitar que datos de un usuario previo (ej. sesión de prueba
    // de otra cuenta en el mismo navegador) se copien al nuevo session_id.
    session_unset();

    // Regenerar ID de sesión para prevenir fixation
    session_regenerate_id(true);

    // Cargar datos en sesión
    $_SESSION['usuario'] = [
        'id'             => (int) $row['id'],
        'usuario'        => $row['usuario'],
        'nombre'         => $row['nombre_completo'],
        'nombre_completo'=> $row['nombre_completo'],
        'email'          => $row['email'],
        'telefono'       => $row['telefono'] ?? null,
        'rol_id'         => (int) $row['rol_id'],
        'rol_nombre'     => $row['rol_nombre'],
        'sucursal_id'    => $row['sucursal_id'] ? (int) $row['sucursal_id'] : null,
        'area_id'        => $row['area_id'] ? (int) $row['area_id'] : null,
        'puesto'         => $row['puesto'],
        'avatar_url'     => $row['avatar_url'] ?? null,
        'pagina_inicio_preferida' => $row['pagina_inicio_preferida'] ?? 'dashboard.php',
        'tema_preferido' => $row['tema_preferido'] ?? 'auto',
        'permisos' => [
            'administrar'         => (bool) $row['puede_administrar'],
            'ver_todas_sucursales'=> (bool) $row['puede_ver_todas_sucursales'],
            'resolver'            => (bool) $row['puede_resolver'],
            'crear_solicitud'     => (bool) $row['puede_crear_solicitud'],
            'ver_reportes'        => (bool) $row['puede_ver_reportes'],
        ],
        'debe_cambiar_password' => (bool) $row['debe_cambiar_password'],
        'preferencias' => json_decode((string)($row['preferencias'] ?? '{}'), true) ?: [],
    ];
    $_SESSION['ultima_actividad'] = time();
    $_SESSION['version'] = SESSION_VERSION;

    // Auditoría
    registrar_auditoria('login', null, null, 'Inicio de sesión exitoso');

    // Registrar la sesión activa en BD (para tracking y cierre remoto)
    registrar_sesion_activa((int) $row['id']);

    return [true, 'Bienvenido, ' . $row['nombre_completo'], (bool) $row['debe_cambiar_password']];
}

/**
 * Cierra la sesión actual.
 * NOTA: No llama a esta_logueado() para evitar recursión.
 * Si necesitas auditar el cierre, hazlo desde el caller (ej. logout.php).
 */
function logout(): void {
    // Registrar auditoría solo si hay datos válidos en sesión (sin llamar a esta_logueado)
    if (isset($_SESSION['usuario']['id'])) {
        registrar_auditoria('logout', null, null, 'Cierre de sesión');
    }
    // Marcar la sesión como cerrada en BD
    cerrar_sesion_actual('logout normal');

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Limpia la sesión actual sin destruir la cookie ni auditar.
 * Uso interno: invalidar sesiones desactualizadas sin causar recursión
 * ni romper la sesión activa (no regenera ID porque interactúa mal con use_strict_mode).
 */
function limpiar_sesion_invalida(): void {
    // Solo limpiamos los datos de aplicación; el ID de sesión se mantiene
    // para que la próxima llamada a login() lo pueda regenerar normalmente.
    unset($_SESSION['usuario']);
    unset($_SESSION['ultima_actividad']);
    unset($_SESSION['version']);
}

/**
 * ¿Hay un usuario logueado?
 * Invalida automáticamente sesiones desactualizadas (versión vieja o estructura corrupta).
 */
function esta_logueado(): bool {
    if (!isset($_SESSION['usuario'])) return false;

    // Verificar que la sesión sea de la versión actual
    if (!isset($_SESSION['version']) || $_SESSION['version'] !== SESSION_VERSION) {
        limpiar_sesion_invalida();
        return false;
    }

    // Verificar que la estructura mínima esté presente (defensa contra corrupción)
    $u = $_SESSION['usuario'];
    if (!isset($u['id'], $u['nombre'], $u['rol_nombre'], $u['permisos']) || !is_array($u['permisos'])) {
        limpiar_sesion_invalida();
        return false;
    }

    // Verificar inactividad
    if (isset($_SESSION['ultima_actividad'])) {
        $inactivo_min = (time() - $_SESSION['ultima_actividad']) / 60;
        if ($inactivo_min > TIEMPO_SESION_INACTIVA_MIN) {
            limpiar_sesion_invalida();
            return false;
        }
    }

    // Verificar que la sesión sigue activa en BD (admin pudo cerrarla remotamente)
    $estado = sesion_sigue_activa();
    if (!$estado['activa']) {
        // Guardar motivo para mostrarlo en la pantalla de login si se quiere
        $_SESSION['motivo_cierre_forzado'] = $estado['motivo'] ?? 'sesión cerrada por administrador';
        limpiar_sesion_invalida();
        return false;
    }

    $_SESSION['ultima_actividad'] = time();
    return true;
}

/**
 * Devuelve el usuario actual (o null).
 */
function usuario_actual(): ?array {
    return esta_logueado() ? $_SESSION['usuario'] : null;
}

/**
 * Verifica si el usuario actual tiene un permiso específico.
 * Permisos válidos: administrar, ver_todas_sucursales, resolver, crear_solicitud, ver_reportes
 */
function tiene_permiso(string $permiso): bool {
    $u = usuario_actual();
    return $u !== null && !empty($u['permisos'][$permiso]);
}

/**
 * Obliga a que el usuario esté logueado. Si no, redirige al login.
 */
function requerir_login(): void {
    if (!esta_logueado()) {
        $destino = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: ' . url('login.php') . '?redir=' . urlencode($destino));
        exit;
    }

    // Si debe cambiar password y no está en la página de cambio, forzarlo
    $u = usuario_actual();
    $pagina_actual = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($u['debe_cambiar_password'] && $pagina_actual !== 'cambiar_password.php' && $pagina_actual !== 'logout.php') {
        header('Location: ' . url('cambiar_password.php'));
        exit;
    }
}

/**
 * Obliga a que el usuario tenga un permiso específico.
 */
function requerir_permiso(string $permiso): void {
    requerir_login();
    if (!tiene_permiso($permiso)) {
        http_response_code(403);
        die('No tienes permiso para acceder a esta sección.');
    }
}

/**
 * Cambia la contraseña del usuario actual.
 */
function cambiar_password(string $password_actual, string $password_nuevo): array {
    $u = usuario_actual();
    if (!$u) return [false, 'No has iniciado sesión.'];

    if (strlen($password_nuevo) < 8) {
        return [false, 'La nueva contraseña debe tener al menos 8 caracteres.'];
    }

    $row = db_one("SELECT password_hash FROM usuarios WHERE id = :id", ['id' => $u['id']]);
    if (!$row || !password_verify($password_actual, $row['password_hash'])) {
        return [false, 'La contraseña actual no es correcta.'];
    }

    if (password_verify($password_nuevo, $row['password_hash'])) {
        return [false, 'La nueva contraseña debe ser distinta a la actual.'];
    }

    $nuevo_hash = password_hash($password_nuevo, PASSWORD_DEFAULT);
    db_exec(
        "UPDATE usuarios SET password_hash = :h, debe_cambiar_password = 0 WHERE id = :id",
        ['h' => $nuevo_hash, 'id' => $u['id']]
    );

    $_SESSION['usuario']['debe_cambiar_password'] = false;
    registrar_auditoria('cambio_password', 'usuarios', $u['id'], 'Cambio de contraseña');

    return [true, 'Contraseña actualizada correctamente.'];
}

/**
 * Registra una acción en la auditoría del sistema.
 */
function registrar_auditoria(string $accion, ?string $entidad = null, ?int $entidad_id = null, ?string $descripcion = null): void {
    $u = usuario_actual();
    db_exec(
        "INSERT INTO auditoria_sistema (usuario_id, accion, entidad, entidad_id, descripcion, ip, user_agent)
         VALUES (:uid, :acc, :ent, :eid, :desc, :ip, :ua)",
        [
            'uid'  => $u['id'] ?? null,
            'acc'  => $accion,
            'ent'  => $entidad,
            'eid'  => $entidad_id,
            'desc' => $descripcion,
            'ip'   => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]
    );
}
