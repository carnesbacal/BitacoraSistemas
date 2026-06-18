<?php
/**
 * ============================================================================
 * config/sesiones_helpers.php
 * ============================================================================
 * Funciones para gestionar sesiones activas en BD: registro al iniciar sesión,
 * detección de dispositivo/navegador, listado de activas, cierre remoto.
 *
 * Se usa para que el admin pueda ver dónde está logueado un usuario y
 * forzar el cierre de sesión desde otra computadora.
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

/**
 * Detecta dispositivo y navegador a partir del User-Agent.
 * Retorna ['dispositivo' => '...', 'navegador' => '...']
 */
function detectar_dispositivo(string $user_agent): array {
    $ua = $user_agent;
    $dispositivo = 'Desconocido';
    $navegador = 'Desconocido';

    // Dispositivo
    if (preg_match('/iPhone/i', $ua)) {
        $dispositivo = 'iPhone';
    } elseif (preg_match('/iPad/i', $ua)) {
        $dispositivo = 'iPad';
    } elseif (preg_match('/Android/i', $ua)) {
        if (preg_match('/Mobile/i', $ua)) $dispositivo = 'Android (móvil)';
        else $dispositivo = 'Android (tablet)';
    } elseif (preg_match('/Windows NT 10\.0/i', $ua)) {
        $dispositivo = 'Windows 10/11';
    } elseif (preg_match('/Windows NT 6\.3/i', $ua)) {
        $dispositivo = 'Windows 8.1';
    } elseif (preg_match('/Windows NT 6\.[0-2]/i', $ua)) {
        $dispositivo = 'Windows 7/8';
    } elseif (preg_match('/Windows/i', $ua)) {
        $dispositivo = 'Windows';
    } elseif (preg_match('/Macintosh|Mac OS X/i', $ua)) {
        $dispositivo = 'macOS';
    } elseif (preg_match('/Linux/i', $ua)) {
        $dispositivo = 'Linux';
    }

    // Navegador (en orden de especificidad para no confundir)
    if (preg_match('/Edg\//i', $ua)) {
        $navegador = 'Edge';
    } elseif (preg_match('/OPR\/|Opera/i', $ua)) {
        $navegador = 'Opera';
    } elseif (preg_match('/Firefox/i', $ua)) {
        $navegador = 'Firefox';
    } elseif (preg_match('/Chrome/i', $ua)) {
        $navegador = 'Chrome';
    } elseif (preg_match('/Safari/i', $ua)) {
        $navegador = 'Safari';
    }

    return ['dispositivo' => $dispositivo, 'navegador' => $navegador];
}

/**
 * Obtiene la IP del cliente (considera proxies/Cloudflare).
 */
function obtener_ip_cliente(): string {
    $candidatos = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($candidatos as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Registra una nueva sesión activa al iniciar sesión.
 * Se llama desde login() en auth.php
 */
function registrar_sesion_activa(int $usuario_id): void {
    $session_id = session_id();
    if (empty($session_id)) return;

    $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $det = detectar_dispositivo($ua);
    $ip = obtener_ip_cliente();

    // Borrar cualquier sesión vieja con este mismo session_id (por si quedó huérfana)
    db_exec("DELETE FROM sesiones WHERE session_id = :sid", ['sid' => $session_id]);

    db_exec(
        "INSERT INTO sesiones (usuario_id, session_id, ip, user_agent, dispositivo, navegador, activa)
         VALUES (:uid, :sid, :ip, :ua, :disp, :nav, 1)",
        [
            'uid'  => $usuario_id,
            'sid'  => $session_id,
            'ip'   => $ip,
            'ua'   => $ua,
            'disp' => $det['dispositivo'],
            'nav'  => $det['navegador'],
        ]
    );
}

/**
 * Verifica que la sesión PHP actual sigue marcada como activa en BD
 * Y que pertenece al $usuario_id indicado.
 * Esto previene que un session_id de otro entorno o usuario sea aceptado.
 *
 * @param int|null $usuario_id  ID del usuario en sesión. null = sin verificación de usuario (legacy).
 */
function sesion_sigue_activa(?int $usuario_id = null): array {
    $session_id = session_id();
    if (empty($session_id)) return ['activa' => false, 'motivo' => null];

    if ($usuario_id !== null) {
        $row = db_one(
            "SELECT activa, motivo_cierre FROM sesiones
             WHERE session_id = :sid AND usuario_id = :uid LIMIT 1",
            ['sid' => $session_id, 'uid' => $usuario_id]
        );
    } else {
        $row = db_one(
            "SELECT activa, motivo_cierre FROM sesiones WHERE session_id = :sid LIMIT 1",
            ['sid' => $session_id]
        );
    }

    if (!$row) {
        // Sin registro: sesión nueva o de otro entorno. La validación cruzada
        // ID↔usuario en esta_logueado() detecta contaminaciones reales.
        return ['activa' => true, 'motivo' => null];
    }

    if ((int) $row['activa'] !== 1) {
        return ['activa' => false, 'motivo' => $row['motivo_cierre']];
    }

    db_exec(
        "UPDATE sesiones SET ultima_actividad = NOW() WHERE session_id = :sid",
        ['sid' => $session_id]
    );

    return ['activa' => true, 'motivo' => null];
}

/**
 * Cierra la sesión actual en BD (al hacer logout).
 */
function cerrar_sesion_actual(string $motivo = 'logout normal'): void {
    $session_id = session_id();
    if (empty($session_id)) return;

    db_exec(
        "UPDATE sesiones SET activa = 0, motivo_cierre = :m, cerrada_en = NOW()
         WHERE session_id = :sid AND activa = 1",
        ['m' => $motivo, 'sid' => $session_id]
    );
}

/**
 * Lista las sesiones activas de un usuario (para admin o para el propio usuario).
 */
function listar_sesiones_activas(int $usuario_id): array {
    return db_all(
        "SELECT * FROM sesiones
         WHERE usuario_id = :uid AND activa = 1
         ORDER BY ultima_actividad DESC",
        ['uid' => $usuario_id]
    );
}

/**
 * Lista todas las sesiones (activas e inactivas) de un usuario.
 */
function listar_historial_sesiones(int $usuario_id, int $limite = 20): array {
    return db_all(
        "SELECT * FROM sesiones
         WHERE usuario_id = :uid
         ORDER BY creado_en DESC
         LIMIT $limite",
        ['uid' => $usuario_id]
    );
}

/**
 * Fuerza el cierre de una sesión específica (admin invalida sesión remota).
 */
function forzar_cierre_sesion(int $sesion_id, string $motivo = 'cerrada por administrador'): bool {
    db_exec(
        "UPDATE sesiones SET activa = 0, motivo_cierre = :m, cerrada_en = NOW()
         WHERE id = :id AND activa = 1",
        ['m' => $motivo, 'id' => $sesion_id]
    );
    return true;
}

/**
 * Cierra TODAS las sesiones activas de un usuario.
 */
function cerrar_todas_sesiones_usuario(int $usuario_id, string $motivo = 'cerradas por administrador'): int {
    $activas = db_one(
        "SELECT COUNT(*) c FROM sesiones WHERE usuario_id = :uid AND activa = 1",
        ['uid' => $usuario_id]
    );
    $total = (int) ($activas['c'] ?? 0);

    if ($total > 0) {
        db_exec(
            "UPDATE sesiones SET activa = 0, motivo_cierre = :m, cerrada_en = NOW()
             WHERE usuario_id = :uid AND activa = 1",
            ['m' => $motivo, 'uid' => $usuario_id]
        );
    }
    return $total;
}

/**
 * Limpia sesiones huérfanas: registros marcados como activos en BD
 * pero cuya última actividad supera el tiempo de inactividad permitido.
 * Se llama automáticamente en esta_logueado() con probabilidad 1/100
 * para no agregar latencia en cada request.
 *
 * También cierra sesiones "activas" de más de 8 horas sin actividad.
 */
function limpiar_sesiones_huerfanas(): void {
    $horas_max = 8; // sesión activa por más de 8h sin actividad → cerrar
    try {
        db_exec(
            "UPDATE sesiones
             SET activa = 0,
                 motivo_cierre = 'expirada por inactividad (cleanup automático)',
                 cerrada_en = NOW()
             WHERE activa = 1
               AND ultima_actividad < NOW() - INTERVAL :h HOUR",
            ['h' => $horas_max]
        );
    } catch (Throwable $e) {
        // No interrumpir la ejecución si falla el cleanup
    }
}

/**
 * Retorna el icono Lucide para el dispositivo dado.
 */
function icono_dispositivo(string $disp): string {
    if (stripos($disp, 'iPhone') !== false || stripos($disp, 'Android (móvil') !== false) return 'smartphone';
    if (stripos($disp, 'iPad') !== false || stripos($disp, 'tablet') !== false) return 'tablet';
    if (stripos($disp, 'Windows') !== false) return 'monitor';
    if (stripos($disp, 'Mac') !== false) return 'laptop';
    if (stripos($disp, 'Linux') !== false) return 'terminal';
    return 'globe';
}
