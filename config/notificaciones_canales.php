<?php
/**
 * ============================================================================
 * config/notificaciones_canales.php
 * ============================================================================
 * Despacho de notificaciones a canales externos: Email (SMTP nativo) y Telegram.
 * No requiere Composer, curl ni mail(). Solo fsockopen + file_get_contents.
 *
 * Funciones públicas:
 *   dispatch_notificacion(...)  — decide qué canales usar y envía
 *   nc_test_canal(...)          — prueba un canal desde el panel admin
 * ============================================================================
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/app.php';

// ============================================================================
// Configuración global (caché por request)
// ============================================================================

function _nc_config(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $row = db_one("SELECT * FROM configuracion_notificaciones WHERE id = 1");
        $cache = $row ?? [];
    } catch (Throwable $e) {
        $cache = [];
    }
    return $cache;
}

// ============================================================================
// Preferencias por usuario y tipo (caché por request)
// ============================================================================

function _nc_preferencias(int $usuario_id, string $tipo): array {
    static $cache = [];
    $key = "{$usuario_id}:{$tipo}";
    if (isset($cache[$key])) return $cache[$key];

    $defaults = ['canal_email' => 0, 'canal_telegram' => 0];
    // Por defecto: email activado para asignacion y mencion
    if (in_array($tipo, ['asignacion', 'mencion'], true)) {
        $defaults['canal_email'] = 1;
    }

    try {
        $row = db_one(
            "SELECT canal_email, canal_telegram FROM notificacion_preferencias
             WHERE usuario_id = :uid AND tipo = :tipo",
            ['uid' => $usuario_id, 'tipo' => $tipo]
        );
        $cache[$key] = $row ? [
            'canal_email'    => (int) $row['canal_email'],
            'canal_telegram' => (int) $row['canal_telegram'],
        ] : $defaults;
    } catch (Throwable $e) {
        $cache[$key] = $defaults;
    }
    return $cache[$key];
}

// ============================================================================
// Dispatcher principal
// ============================================================================

/**
 * Verifica preferencias del usuario y configuración global, y envía
 * notificación por los canales externos que corresponda.
 *
 * @param int         $usuario_id  Destinatario
 * @param string      $tipo        Tipo de notificación (ver NOTIF_TIPOS)
 * @param string      $titulo      Título corto
 * @param string      $mensaje     Texto descriptivo
 * @param string|null $url         Enlace relativo a la acción
 * @param int|null    $notif_id    ID del registro en tabla notificaciones
 */
function dispatch_notificacion(
    int $usuario_id,
    string $tipo,
    string $titulo,
    string $mensaje,
    ?string $url,
    ?int $notif_id
): void {
    $cfg  = _nc_config();
    $pref = _nc_preferencias($usuario_id, $tipo);

    // Obtener datos del usuario (email y telegram_chat_id)
    try {
        $usr = db_one(
            "SELECT email, telegram_chat_id FROM usuarios WHERE id = :id AND activo = 1",
            ['id' => $usuario_id]
        );
    } catch (Throwable $e) {
        return;
    }
    if (!$usr) return;

    $url_completa = $url ? (rtrim(APP_URL, '/') . '/' . ltrim($url, '/')) : APP_URL;

    // --- Canal Email ---
    if (!empty($cfg['smtp_activo']) && !empty($pref['canal_email']) && !empty($usr['email'])) {
        $resultado = _nc_enviar_email(
            $cfg,
            $usr['email'],
            $titulo,
            $mensaje,
            $url_completa,
            $tipo
        );
        _nc_log_envio($notif_id, $usuario_id, 'email', $tipo, $titulo, $resultado);
    }

    // --- Canal Telegram ---
    if (!empty($cfg['telegram_activo']) && !empty($pref['canal_telegram']) && !empty($usr['telegram_chat_id'])) {
        $resultado = _nc_enviar_telegram(
            (string) $cfg['telegram_bot_token'],
            (string) $usr['telegram_chat_id'],
            $titulo,
            $mensaje,
            $url_completa
        );
        _nc_log_envio($notif_id, $usuario_id, 'telegram', $tipo, $titulo, $resultado);
    }
}

// ============================================================================
// Envío por Email (SMTP nativo con fsockopen — sin composer, sin mail())
// ============================================================================

/**
 * Envía un email via SMTP nativo.
 * Soporta: TLS/STARTTLS (puerto 587), SSL (puerto 465), sin cifrado.
 *
 * @return array ['ok' => bool, 'error' => string|null]
 */
function _nc_enviar_email(
    array $cfg,
    string $dest_email,
    string $asunto,
    string $mensaje_texto,
    string $url_accion,
    string $tipo
): array {
    $host      = (string) ($cfg['smtp_host'] ?? '');
    $port      = (int) ($cfg['smtp_port'] ?? 587);
    $seguridad = (string) ($cfg['smtp_seguridad'] ?? 'tls');
    $usuario   = (string) ($cfg['smtp_usuario'] ?? '');
    $password  = (string) ($cfg['smtp_password'] ?? '');
    $from_mail = (string) ($cfg['smtp_from_email'] ?? $usuario);
    $from_name = (string) ($cfg['smtp_from_nombre'] ?? 'Bitácora Sistemas');

    if (!$host || !$from_mail || !$dest_email) {
        return ['ok' => false, 'error' => 'Configuración SMTP incompleta'];
    }

    // Generar HTML del email
    $html = _nc_email_html($asunto, $mensaje_texto, $url_accion, $from_name);

    // Cabeceras MIME
    $boundary   = md5(uniqid('', true));
    $message_id = '<' . uniqid('bs', true) . '@' . ($cfg['smtp_host'] ?? 'bitacora') . '>';
    $date       = date('r');

    $headers  = "Date: {$date}\r\n";
    $headers .= "Message-ID: {$message_id}\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from_mail}>\r\n";
    $headers .= "To: {$dest_email}\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: BitacoraSistemas/2.0\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode("{$asunto}\r\n\r\n{$mensaje_texto}\r\n\r\nVer: {$url_accion}")) . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($html)) . "\r\n";
    $body .= "--{$boundary}--\r\n";

    $data = $headers . "\r\n" . $body;

    // Abrir socket
    $errno = 0; $errstr = '';
    $timeout = 10;

    try {
        if ($seguridad === 'ssl') {
            $sock = @fsockopen("ssl://{$host}", $port, $errno, $errstr, $timeout);
        } else {
            $sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
        }

        if (!$sock) {
            return ['ok' => false, 'error' => "No se pudo conectar al servidor SMTP: {$errstr} ({$errno})"];
        }

        stream_set_timeout($sock, $timeout);

        $leer = function() use ($sock): string {
            $resp = '';
            while ($line = fgets($sock, 512)) {
                $resp .= $line;
                if ($line[3] === ' ') break; // Última línea de la respuesta SMTP
            }
            return $resp;
        };

        $enviar = function(string $cmd) use ($sock): void {
            fwrite($sock, $cmd . "\r\n");
        };

        $leer(); // Saludo del servidor

        // STARTTLS si es TLS (puerto 587)
        if ($seguridad === 'tls') {
            $enviar("EHLO " . gethostname());
            $leer();
            $enviar("STARTTLS");
            $resp = $leer();
            if (!str_starts_with($resp, '220')) {
                fclose($sock);
                return ['ok' => false, 'error' => 'STARTTLS rechazado: ' . trim($resp)];
            }
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($sock);
                return ['ok' => false, 'error' => 'No se pudo activar TLS'];
            }
        }

        $enviar("EHLO " . gethostname());
        $leer();

        // Autenticación LOGIN
        $enviar("AUTH LOGIN");
        $leer();
        $enviar(base64_encode($usuario));
        $leer();
        $enviar(base64_encode($password));
        $auth_resp = $leer();
        if (!str_starts_with($auth_resp, '235')) {
            fclose($sock);
            return ['ok' => false, 'error' => 'Autenticación SMTP fallida: ' . trim($auth_resp)];
        }

        // Envío
        $enviar("MAIL FROM:<{$from_mail}>");
        $leer();
        $enviar("RCPT TO:<{$dest_email}>");
        $rcpt_resp = $leer();
        if (!str_starts_with($rcpt_resp, '250')) {
            fclose($sock);
            return ['ok' => false, 'error' => 'RCPT TO rechazado: ' . trim($rcpt_resp)];
        }
        $enviar("DATA");
        $leer();
        fwrite($sock, $data . "\r\n.\r\n");
        $data_resp = $leer();
        $enviar("QUIT");
        fclose($sock);

        if (!str_starts_with($data_resp, '250')) {
            return ['ok' => false, 'error' => 'DATA rechazado: ' . trim($data_resp)];
        }

        return ['ok' => true, 'error' => null];

    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ============================================================================
// Envío por Telegram (Bot API via file_get_contents)
// ============================================================================

/**
 * Envía un mensaje a un chat/usuario de Telegram.
 *
 * @return array ['ok' => bool, 'error' => string|null]
 */
function _nc_enviar_telegram(
    string $bot_token,
    string $chat_id,
    string $titulo,
    string $mensaje,
    string $url_accion
): array {
    if (!$bot_token || !$chat_id) {
        return ['ok' => false, 'error' => 'Token de bot o Chat ID vacíos'];
    }

    $texto = "*{$titulo}*\n{$mensaje}\n\n🔗 [Ver detalle]({$url_accion})";

    $params = http_build_query([
        'chat_id'    => $chat_id,
        'text'       => $texto,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => 'true',
    ]);

    $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage?{$params}";

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => 8,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    try {
        $respuesta = @file_get_contents($api_url, false, $ctx);
        if ($respuesta === false) {
            return ['ok' => false, 'error' => 'No se pudo contactar a la API de Telegram'];
        }
        $json = json_decode($respuesta, true);
        if (!empty($json['ok'])) {
            return ['ok' => true, 'error' => null];
        }
        $desc = $json['description'] ?? 'Error desconocido';
        return ['ok' => false, 'error' => "Telegram API: {$desc}"];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// ============================================================================
// HTML del email
// ============================================================================

function _nc_email_html(
    string $asunto,
    string $mensaje,
    string $url_accion,
    string $nombre_app
): string {
    $color_header = '#36454F'; // bacal-700
    $color_boton  = '#36454F';
    $año          = date('Y');
    $asunto_e     = htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8');
    $mensaje_e    = nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'));
    $url_e        = htmlspecialchars($url_accion, ENT_QUOTES, 'UTF-8');
    $nombre_e     = htmlspecialchars($nombre_app, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f5f6;font-family:Inter,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f5f6;padding:32px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" border="0"
             style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">

        <!-- Header -->
        <tr>
          <td style="background:{$color_header};padding:24px 32px;">
            <p style="margin:0;font-size:20px;font-weight:700;color:#ffffff;letter-spacing:-0.3px;">
              {$nombre_e}
            </p>
          </td>
        </tr>

        <!-- Contenido -->
        <tr>
          <td style="padding:32px;">
            <h2 style="margin:0 0 12px;font-size:18px;font-weight:700;color:#1b1f24;">{$asunto_e}</h2>
            <p style="margin:0 0 24px;font-size:14px;color:#545d68;line-height:1.6;">{$mensaje_e}</p>
            <a href="{$url_e}"
               style="display:inline-block;padding:12px 24px;background:{$color_boton};color:#fff;
                      font-size:14px;font-weight:600;text-decoration:none;border-radius:8px;">
              Ver detalle →
            </a>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:16px 32px;border-top:1px solid #e8e9eb;background:#f4f5f6;">
            <p style="margin:0;font-size:11px;color:#8e959f;text-align:center;">
              © {$año} {$nombre_e} · Este correo fue generado automáticamente.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

// ============================================================================
// Log de envíos
// ============================================================================

function _nc_log_envio(
    ?int $notif_id,
    int $usuario_id,
    string $canal,
    string $tipo,
    string $asunto,
    array $resultado
): void {
    try {
        db_exec(
            "INSERT INTO notificacion_envios
                (notificacion_id, usuario_id, canal, tipo, asunto, estado, error_detalle)
             VALUES
                (:nid, :uid, :canal, :tipo, :asunto, :estado, :err)",
            [
                'nid'    => $notif_id,
                'uid'    => $usuario_id,
                'canal'  => $canal,
                'tipo'   => $tipo,
                'asunto' => mb_substr($asunto, 0, 250),
                'estado' => $resultado['ok'] ? 'ok' : 'error',
                'err'    => $resultado['ok'] ? null : mb_substr((string)($resultado['error'] ?? ''), 0, 1000),
            ]
        );
    } catch (Throwable $e) {
        error_log('_nc_log_envio fallido: ' . $e->getMessage());
    }
}

// ============================================================================
// Función de prueba (para el panel admin)
// ============================================================================

/**
 * Envía un mensaje de prueba a un destino específico.
 *
 * @param string $canal   'email' o 'telegram'
 * @param string $destino Email address o Chat ID de Telegram
 * @return array ['ok' => bool, 'error' => string|null]
 */
function nc_test_canal(string $canal, string $destino): array {
    $cfg = _nc_config();

    if ($canal === 'email') {
        if (empty($cfg['smtp_host'])) {
            return ['ok' => false, 'error' => 'El servidor SMTP no está configurado'];
        }
        return _nc_enviar_email(
            $cfg,
            $destino,
            'Prueba de email · Bitácora Sistemas',
            'Este es un mensaje de prueba enviado desde el panel de administración de notificaciones.',
            APP_URL,
            'test'
        );
    }

    if ($canal === 'telegram') {
        if (empty($cfg['telegram_bot_token'])) {
            return ['ok' => false, 'error' => 'El token del bot de Telegram no está configurado'];
        }
        return _nc_enviar_telegram(
            (string) $cfg['telegram_bot_token'],
            $destino,
            'Prueba de Telegram · Bitácora Sistemas',
            'Este es un mensaje de prueba enviado desde el panel de administración de notificaciones.',
            APP_URL
        );
    }

    return ['ok' => false, 'error' => 'Canal desconocido: ' . $canal];
}
