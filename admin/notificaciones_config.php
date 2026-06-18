<?php
/**
 * ============================================================================
 * admin/notificaciones_config.php
 * ============================================================================
 * Panel de administración para configurar canales de notificación
 * externos: Email (SMTP) y Telegram Bot.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/notificaciones_canales.php';

$u = usuario_actual();
if (!tiene_permiso('administrar')) {
    flash_set('error', 'Solo administradores pueden acceder a esta sección.');
    header('Location: ' . url('dashboard.php'));
    exit;
}

$errores = [];
$cfg = db_one("SELECT * FROM configuracion_notificaciones WHERE id = 1") ?? [];

// ============================================================================
// Procesar POST
// ============================================================================
if (es_post() && csrf_valido(input('_csrf'))) {
    $op = (string) input('op', '');

    try {
        // --- Guardar configuración ---
        if ($op === 'guardar') {
            $smtp_seg = input('smtp_seguridad', 'tls');
            if (!in_array($smtp_seg, ['tls','ssl','none'], true)) $smtp_seg = 'tls';

            db_exec(
                "UPDATE configuracion_notificaciones SET
                    smtp_host         = :host,
                    smtp_port         = :port,
                    smtp_seguridad    = :seg,
                    smtp_usuario      = :usr,
                    smtp_password     = :pwd,
                    smtp_from_email   = :from_mail,
                    smtp_from_nombre  = :from_name,
                    smtp_activo       = :smtp_on,
                    telegram_bot_token = :tg_token,
                    telegram_activo   = :tg_on,
                    actualizado_por   = :actor
                 WHERE id = 1",
                [
                    'host'      => trim((string) input('smtp_host', '')) ?: null,
                    'port'      => max(1, min(65535, (int) input('smtp_port', 587))),
                    'seg'       => $smtp_seg,
                    'usr'       => trim((string) input('smtp_usuario', '')) ?: null,
                    'pwd'       => (string) input('smtp_password', '') !== '' ? (string) input('smtp_password', '') : ($cfg['smtp_password'] ?? null),
                    'from_mail' => trim((string) input('smtp_from_email', '')) ?: null,
                    'from_name' => trim((string) input('smtp_from_nombre', 'Bitácora Sistemas')) ?: 'Bitácora Sistemas',
                    'smtp_on'   => input('smtp_activo') ? 1 : 0,
                    'tg_token'  => trim((string) input('telegram_bot_token', '')) ?: ($cfg['telegram_bot_token'] ?? null),
                    'tg_on'     => input('telegram_activo') ? 1 : 0,
                    'actor'     => $u['id'],
                ]
            );
            flash_set('success', 'Configuración guardada correctamente.');
            header('Location: ' . url('admin/notificaciones_config.php'));
            exit;
        }

        // --- Prueba de canal ---
        if ($op === 'test') {
            $canal   = (string) input('test_canal', '');
            $destino = trim((string) input('test_destino', ''));
            if (!$destino) {
                $errores[] = 'Ingresa un destino para la prueba.';
            } elseif (!in_array($canal, ['email','telegram'], true)) {
                $errores[] = 'Canal desconocido.';
            } else {
                $res = nc_test_canal($canal, $destino);
                if ($res['ok']) {
                    flash_set('success', 'Mensaje de prueba enviado correctamente a ' . $destino . '.');
                } else {
                    flash_set('error', 'Error al enviar prueba: ' . ($res['error'] ?? 'desconocido'));
                }
                header('Location: ' . url('admin/notificaciones_config.php'));
                exit;
            }
        }
    } catch (Throwable $e) {
        $errores[] = 'Error: ' . $e->getMessage();
    }
}

// Recargar configuración (puede haber cambiado)
$cfg = db_one("SELECT * FROM configuracion_notificaciones WHERE id = 1") ?? [];

// Últimos 30 envíos del log
$log_envios = db_all(
    "SELECT ne.*, u.nombre_completo usuario_nombre
     FROM notificacion_envios ne
     LEFT JOIN usuarios u ON u.id = ne.usuario_id
     ORDER BY ne.enviado_en DESC
     LIMIT 30"
);

$pagina_activa = 'admin_notificaciones_config';
$titulo_pagina = 'Configuración de Notificaciones';
require_once __DIR__ . '/../config/header.php';
?>

<div class="max-w-4xl mx-auto animate-fade-in space-y-6">

    <!-- Encabezado -->
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-bacal-50 flex items-center justify-center">
            <i data-lucide="bell-ring" class="w-5 h-5 text-bacal-700"></i>
        </div>
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">Notificaciones</h2>
            <p class="text-xs text-zinc-500">Configura canales externos de Email y Telegram</p>
        </div>
    </div>

    <!-- Flash messages -->
    <?php foreach (flash_get() as $fm): ?>
    <div class="px-4 py-3 rounded-lg text-sm font-medium
        <?= $fm['tipo'] === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-700' ?>">
        <?= e($fm['mensaje']) ?>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($errores)): ?>
    <div class="px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
        <?php foreach ($errores as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Formulario principal -->
    <form method="POST">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="guardar">

        <!-- ================================================================
             SECCIÓN: Email (SMTP)
        ================================================================ -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-5 mb-6">

            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="mail" class="w-5 h-5 text-bacal-700"></i>
                    <h3 class="font-display text-base font-bold text-zinc-900">Correo electrónico (SMTP)</h3>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <span class="text-xs text-zinc-500 font-medium">Activar</span>
                    <input type="checkbox" name="smtp_activo" value="1"
                           <?= !empty($cfg['smtp_activo']) ? 'checked' : '' ?>
                           class="w-4 h-4 accent-bacal-700">
                </label>
            </div>

            <!-- Ayuda rápida por proveedor -->
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800 space-y-0.5">
                <p class="font-bold mb-1">Configuración rápida por proveedor:</p>
                <p><span class="font-semibold">cPanel (este proyecto):</span> mail.carnesbacal.com.mx · Puerto 465 · SSL</p>
                <p><span class="font-semibold">Gmail:</span> smtp.gmail.com · Puerto 587 · TLS (requiere contraseña de aplicación)</p>
                <p><span class="font-semibold">Outlook/Hotmail:</span> smtp.office365.com · Puerto 587 · TLS</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Servidor SMTP</label>
                    <input type="text" name="smtp_host" value="<?= e((string)($cfg['smtp_host'] ?? '')) ?>"
                           placeholder="mail.tudominio.com"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Puerto</label>
                        <input type="number" name="smtp_port" value="<?= (int)($cfg['smtp_port'] ?? 587) ?>"
                               min="1" max="65535"
                               class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Seguridad</label>
                        <select name="smtp_seguridad"
                                class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                            <option value="tls"  <?= ($cfg['smtp_seguridad'] ?? 'tls') === 'tls'  ? 'selected' : '' ?>>TLS (587)</option>
                            <option value="ssl"  <?= ($cfg['smtp_seguridad'] ?? '') === 'ssl'  ? 'selected' : '' ?>>SSL (465)</option>
                            <option value="none" <?= ($cfg['smtp_seguridad'] ?? '') === 'none' ? 'selected' : '' ?>>Sin cifrado</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Usuario SMTP</label>
                    <input type="text" name="smtp_usuario" value="<?= e((string)($cfg['smtp_usuario'] ?? '')) ?>"
                           placeholder="notificaciones@tudominio.com" autocomplete="off"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Contraseña SMTP</label>
                    <input type="password" name="smtp_password" value=""
                           placeholder="<?= !empty($cfg['smtp_password']) ? '(guardada, dejar en blanco para no cambiar)' : 'Contraseña' ?>"
                           autocomplete="new-password"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Email remitente (From)</label>
                    <input type="email" name="smtp_from_email" value="<?= e((string)($cfg['smtp_from_email'] ?? '')) ?>"
                           placeholder="notificaciones@tudominio.com"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nombre remitente</label>
                    <input type="text" name="smtp_from_nombre" value="<?= e((string)($cfg['smtp_from_nombre'] ?? 'Bitácora Sistemas')) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>
        </div>

        <!-- ================================================================
             SECCIÓN: Telegram
        ================================================================ -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-5 mb-6">

            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="send" class="w-5 h-5 text-bacal-700"></i>
                    <h3 class="font-display text-base font-bold text-zinc-900">Telegram Bot</h3>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <span class="text-xs text-zinc-500 font-medium">Activar</span>
                    <input type="checkbox" name="telegram_activo" value="1"
                           <?= !empty($cfg['telegram_activo']) ? 'checked' : '' ?>
                           class="w-4 h-4 accent-bacal-700">
                </label>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Token del Bot</label>
                <input type="password" name="telegram_bot_token" value=""
                       placeholder="<?= !empty($cfg['telegram_bot_token']) ? '(guardado, dejar en blanco para no cambiar)' : '123456789:ABCdefGHIjklMNOpqrsTUVwxyz' ?>"
                       autocomplete="off"
                       class="w-full md:w-2/3 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <!-- Instrucciones paso a paso -->
            <div class="bg-zinc-50 border border-zinc-200 rounded-lg p-4 text-xs text-zinc-700 space-y-1.5">
                <p class="font-bold text-zinc-800 mb-2">¿Cómo crear un bot de Telegram?</p>
                <p><span class="font-semibold text-zinc-900">1.</span> Abre Telegram y busca <span class="font-mono bg-zinc-200 px-1 rounded">@BotFather</span></p>
                <p><span class="font-semibold text-zinc-900">2.</span> Envíale el comando <span class="font-mono bg-zinc-200 px-1 rounded">/newbot</span> y sigue las instrucciones (elige nombre y username del bot)</p>
                <p><span class="font-semibold text-zinc-900">3.</span> BotFather te dará un token — cópialo aquí arriba</p>
                <p><span class="font-semibold text-zinc-900">4.</span> Cada usuario que quiera recibir notificaciones debe <strong>buscar tu bot</strong> en Telegram y enviarle cualquier mensaje (ej. <span class="font-mono bg-zinc-200 px-1 rounded">/start</span>)</p>
                <p><span class="font-semibold text-zinc-900">5.</span> Para obtener su Chat ID, el usuario debe hablar con <span class="font-mono bg-zinc-200 px-1 rounded">@userinfobot</span> — ese bot le responderá con su ID numérico</p>
                <p><span class="font-semibold text-zinc-900">6.</span> El usuario pega ese Chat ID en su perfil (sección Preferencias) y listo</p>
                <div class="mt-2 pt-2 border-t border-zinc-200">
                    <p class="text-amber-700 font-semibold">⚠ Importante:</p>
                    <p>El usuario DEBE enviar un mensaje al bot antes de que el sistema pueda contactarle. Si el bot nunca recibió un mensaje de ese usuario, los envíos fallarán con "chat not found".</p>
                </div>
            </div>
        </div>

        <!-- Botón guardar -->
        <div class="flex justify-end">
            <button type="submit"
                    class="px-6 py-2.5 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                Guardar configuración
            </button>
        </div>
    </form>

    <!-- ====================================================================
         SECCIÓN: Pruebas
    ==================================================================== -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
        <h3 class="font-display text-base font-bold text-zinc-900 mb-4">Enviar mensaje de prueba</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Prueba Email -->
            <form method="POST" class="flex flex-col gap-2">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="test">
                <input type="hidden" name="test_canal" value="email">
                <label class="text-xs font-bold text-zinc-700 uppercase tracking-wide">Email de prueba</label>
                <div class="flex gap-2">
                    <input type="email" name="test_destino"
                           placeholder="destino@ejemplo.com"
                           class="flex-1 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <button type="submit"
                            class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold whitespace-nowrap">
                        Enviar prueba
                    </button>
                </div>
                <p class="text-[10px] text-zinc-400">Requiere SMTP configurado y activo</p>
            </form>

            <!-- Prueba Telegram -->
            <form method="POST" class="flex flex-col gap-2">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="test">
                <input type="hidden" name="test_canal" value="telegram">
                <label class="text-xs font-bold text-zinc-700 uppercase tracking-wide">Telegram de prueba</label>
                <div class="flex gap-2">
                    <input type="text" name="test_destino"
                           placeholder="Chat ID (ej. 123456789)"
                           class="flex-1 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <button type="submit"
                            class="px-4 py-2 rounded-lg bg-sky-500 hover:bg-sky-600 text-white text-sm font-semibold whitespace-nowrap">
                        Enviar prueba
                    </button>
                </div>
                <p class="text-[10px] text-zinc-400">Requiere token de bot configurado y activo</p>
            </form>

        </div>
    </div>

    <!-- ====================================================================
         SECCIÓN: Log de últimos envíos
    ==================================================================== -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-zinc-100">
            <h3 class="font-display text-base font-bold text-zinc-900">Últimos 30 envíos externos</h3>
        </div>

        <?php if (empty($log_envios)): ?>
        <div class="px-6 py-8 text-center text-zinc-400 text-sm">
            <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 text-zinc-300"></i>
            <p>Aún no hay envíos registrados.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-zinc-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                        <th class="px-4 py-3 text-left font-semibold">Usuario</th>
                        <th class="px-4 py-3 text-left font-semibold">Canal</th>
                        <th class="px-4 py-3 text-left font-semibold">Tipo</th>
                        <th class="px-4 py-3 text-left font-semibold">Asunto</th>
                        <th class="px-4 py-3 text-left font-semibold">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($log_envios as $env): ?>
                    <tr class="hover:bg-zinc-50 transition-colors">
                        <td class="px-4 py-2.5 text-zinc-500 text-xs whitespace-nowrap">
                            <?= e(fmt_fecha($env['enviado_en'])) ?>
                        </td>
                        <td class="px-4 py-2.5 text-zinc-700 text-xs">
                            <?= e($env['usuario_nombre'] ?? '—') ?>
                        </td>
                        <td class="px-4 py-2.5">
                            <?php if ($env['canal'] === 'email'): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                <i data-lucide="mail" class="w-3 h-3"></i> Email
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-sky-50 text-sky-700 border border-sky-200">
                                <i data-lucide="send" class="w-3 h-3"></i> Telegram
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2.5 text-zinc-500 text-xs"><?= e($env['tipo'] ?? '—') ?></td>
                        <td class="px-4 py-2.5 text-zinc-700 text-xs max-w-xs truncate"><?= e($env['asunto'] ?? '—') ?></td>
                        <td class="px-4 py-2.5">
                            <?php if ($env['estado'] === 'ok'): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <i data-lucide="check" class="w-3 h-3"></i> OK
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-red-50 text-red-700 border border-red-200"
                                  title="<?= e($env['error_detalle'] ?? '') ?>">
                                <i data-lucide="x" class="w-3 h-3"></i> Error
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
