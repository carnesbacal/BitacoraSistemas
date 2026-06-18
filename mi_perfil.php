<?php
/**
 * ============================================================================
 * mi_perfil.php - Edición del perfil del usuario actual
 * ============================================================================
 * El usuario logueado puede editar sus propios datos, subir foto y ver sus
 * estadísticas personales.
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/notificaciones_helpers.php';

requerir_login();

$u = usuario_actual();
$id = (int) $u['id'];

$errores = [];

// ----------------------------------------------------------------------------
// Procesar POST (actualizar datos básicos)
// ----------------------------------------------------------------------------
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token de seguridad inválido.';
    } else {
        $op = (string) input('op', '');
        try {
            if ($op === 'datos') {
                $nombre   = trim((string) input('nombre_completo', ''));
                $email    = trim((string) input('email', ''));
                $telefono = trim((string) input('telefono', ''));
                $puesto   = trim((string) input('puesto', ''));
                $pagina   = (string) input('pagina_inicio_preferida', 'dashboard.php');

                $paginas_validas = ['dashboard.php', 'bitacora.php', 'incidencia_nueva.php', 'notificaciones.php', 'base_conocimiento.php'];
                if (!in_array($pagina, $paginas_validas, true)) $pagina = 'dashboard.php';

                if ($nombre === '') $errores[] = 'El nombre completo es obligatorio.';
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errores[] = 'El email no parece válido.';
                }

                if (empty($errores)) {
                    db_exec(
                        "UPDATE usuarios SET
                            nombre_completo = :n, email = :e, telefono = :t, puesto = :pu,
                            pagina_inicio_preferida = :pag
                         WHERE id = :id",
                        ['n' => $nombre, 'e' => $email ?: null, 't' => $telefono ?: null,
                         'pu' => $puesto ?: null, 'pag' => $pagina, 'id' => $id]
                    );

                    // Actualizar la sesión con los nuevos datos
                    $_SESSION['usuario']['nombre'] = $nombre;
                    $_SESSION['usuario']['nombre_completo'] = $nombre;
                    $_SESSION['usuario']['email'] = $email ?: null;
                    $_SESSION['usuario']['telefono'] = $telefono ?: null;
                    $_SESSION['usuario']['puesto'] = $puesto ?: null;
                    $_SESSION['usuario']['pagina_inicio_preferida'] = $pagina;

                    registrar_auditoria('editar_perfil', 'usuarios', $id, 'Editó su perfil');
                    flash_set('success', 'Perfil actualizado correctamente.');
                    header('Location: ' . url('mi_perfil.php'));
                    exit;
                }
            } elseif ($op === 'notificaciones') {
                // Guardar Chat ID de Telegram (solo dígitos y guión para IDs de grupos negativos)
                $tg_id = trim((string) input('telegram_chat_id', ''));
                if ($tg_id !== '' && !preg_match('/^-?\d+$/', $tg_id)) {
                    $errores[] = 'El Chat ID de Telegram solo puede contener números (puede ser negativo para grupos).';
                } else {
                    db_exec(
                        "UPDATE usuarios SET telegram_chat_id = :tgid WHERE id = :id",
                        ['tgid' => $tg_id !== '' ? $tg_id : null, 'id' => $id]
                    );

                    // Guardar preferencias por tipo
                    foreach (array_keys(NOTIF_TIPOS) as $tipo) {
                        $canal_email = input("pref_email_{$tipo}") ? 1 : 0;
                        $canal_tg    = input("pref_tg_{$tipo}") ? 1 : 0;
                        db_exec(
                            "INSERT INTO notificacion_preferencias
                                (usuario_id, tipo, canal_email, canal_telegram)
                             VALUES (:uid, :tipo, :email, :tg)
                             ON DUPLICATE KEY UPDATE
                                canal_email = :email2, canal_telegram = :tg2",
                            [
                                'uid'    => $id,
                                'tipo'   => $tipo,
                                'email'  => $canal_email,
                                'tg'     => $canal_tg,
                                'email2' => $canal_email,
                                'tg2'    => $canal_tg,
                            ]
                        );
                    }
                    flash_set('success', 'Preferencias de notificación guardadas.');
                    header('Location: ' . url('mi_perfil.php') . '#preferencias');
                    exit;
                }
            } elseif ($op === 'eliminar_avatar') {
                // Borrar archivo físico si existe
                if (!empty($u['avatar_url'])) {
                    $ruta_disco = __DIR__ . '/' . $u['avatar_url'];
                    if (file_exists($ruta_disco)) @unlink($ruta_disco);
                }
                db_exec("UPDATE usuarios SET avatar_url = NULL WHERE id = :id", ['id' => $id]);
                $_SESSION['usuario']['avatar_url'] = null;
                registrar_auditoria('eliminar_avatar', 'usuarios', $id, 'Eliminó su foto de perfil');
                flash_set('success', 'Foto de perfil eliminada.');
                header('Location: ' . url('mi_perfil.php'));
                exit;
            }
        } catch (Throwable $e) {
            $errores[] = 'Error: ' . $e->getMessage();
        }
    }
}

// ----------------------------------------------------------------------------
// Cargar datos actualizados desde la BD (por si cambió algo desde otro tab)
// ----------------------------------------------------------------------------
$u_data = db_one(
    "SELECT u.*, r.nombre rol_nombre, s.nombre sucursal_nombre, a.nombre area_nombre
     FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     LEFT JOIN sucursales s ON u.sucursal_id = s.id
     LEFT JOIN areas a ON u.area_id = a.id
     WHERE u.id = :id",
    ['id' => $id]
);

// ----------------------------------------------------------------------------
// Estadísticas personales
// ----------------------------------------------------------------------------
$stats = db_one(
    "SELECT
        (SELECT COUNT(*) FROM incidencias WHERE reportado_por_id = :id1) AS total_creadas,
        (SELECT COUNT(*) FROM incidencias WHERE asignado_a_id = :id2) AS total_asignadas,
        (SELECT COUNT(*) FROM incidencias WHERE resuelto_por_id = :id3) AS total_resueltas,
        (SELECT AVG(tiempo_resolucion_min) FROM incidencias WHERE resuelto_por_id = :id4 AND tiempo_resolucion_min IS NOT NULL) AS avg_resolucion,
        (SELECT COUNT(*) FROM incidencias_comentarios WHERE usuario_id = :id5) AS total_comentarios,
        (SELECT COUNT(*) FROM incidencias WHERE asignado_a_id = :id6 AND estado_id IN (SELECT id FROM estados WHERE es_final = 0)) AS abiertas_actuales",
    array_fill_keys(['id1','id2','id3','id4','id5','id6'], $id)
);

// ----------------------------------------------------------------------------
// Actividad reciente del usuario (últimos 10 eventos en auditoría)
// ----------------------------------------------------------------------------
$actividad = db_all(
    "SELECT * FROM auditoria_sistema
     WHERE usuario_id = :id
     ORDER BY creado_en DESC
     LIMIT 10",
    ['id' => $id]
);

// Tamaños máximos de avatar
$MAX_AVATAR_BYTES = 5 * 1024 * 1024; // 5 MB

// ----------------------------------------------------------------------------
// Preferencias de notificación del usuario
// ----------------------------------------------------------------------------
$notif_prefs_raw = db_all(
    "SELECT tipo, canal_email, canal_telegram FROM notificacion_preferencias WHERE usuario_id = :uid",
    ['uid' => $id]
);
$notif_prefs = [];
foreach ($notif_prefs_raw as $r) {
    $notif_prefs[$r['tipo']] = $r;
}
$cfg_notif = db_one("SELECT smtp_activo, telegram_activo FROM configuracion_notificaciones WHERE id = 1") ?? [];
$canal_email_activo    = !empty($cfg_notif['smtp_activo']);
$canal_telegram_activo = !empty($cfg_notif['telegram_activo']);

$titulo_pagina = 'Mi perfil';
$pagina_activa = 'mi_perfil';
require_once __DIR__ . '/config/header.php';
?>

<div class="max-w-5xl mx-auto animate-fade-in space-y-5"
     x-data="{ tabActivo: 'datos' }">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-2">
        <?= render_avatar($u_data, 'w-16 h-16') ?>
        <div class="flex-1">
            <h2 class="font-display text-2xl font-extrabold text-zinc-900"><?= e($u_data['nombre_completo']) ?></h2>
            <p class="text-xs text-zinc-500 mt-0.5">
                <span class="font-mono"><?= e($u_data['usuario']) ?></span> ·
                <?= e($u_data['rol_nombre']) ?>
                <?php if ($u_data['sucursal_nombre']): ?> · <?= e($u_data['sucursal_nombre']) ?><?php endif; ?>
                <?php if ($u_data['area_nombre']): ?> · <?= e($u_data['area_nombre']) ?><?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <ul class="list-disc list-inside text-xs">
            <?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="border-b border-zinc-200">
        <div class="flex gap-1 -mb-px overflow-x-auto">
            <?php
            $tabs = [
                'datos' => ['Datos personales', 'user'],
                'foto' => ['Foto de perfil', 'image'],
                'preferencias' => ['Preferencias', 'sliders-horizontal'],
                'estadisticas' => ['Mis estadísticas', 'bar-chart-3'],
                'actividad' => ['Mi actividad', 'history'],
            ];
            foreach ($tabs as $key => [$label, $icon]):
            ?>
            <button type="button" @click="tabActivo = '<?= $key ?>'"
                    class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tabActivo === '<?= $key ?>' ? 'border-bacal-700 text-bacal-700' : 'border-transparent text-zinc-500 hover:text-zinc-700'">
                <i data-lucide="<?= $icon ?>" class="w-4 h-4"></i>
                <?= e($label) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- TAB: Datos personales -->
    <div x-show="tabActivo === 'datos'" x-cloak>
        <form method="POST" class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <?= csrf_input() ?>
            <input type="hidden" name="op" value="datos">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nombre de usuario</label>
                    <div class="px-3 py-2 rounded-lg border border-zinc-200 bg-zinc-50 text-sm text-zinc-700 font-mono">
                        <?= e($u_data['usuario']) ?>
                    </div>
                    <p class="text-[10px] text-zinc-500 mt-1">El nombre de usuario no se puede cambiar.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Rol</label>
                    <div class="px-3 py-2 rounded-lg border border-zinc-200 bg-zinc-50 text-sm text-zinc-700">
                        <?= e($u_data['rol_nombre']) ?>
                    </div>
                    <p class="text-[10px] text-zinc-500 mt-1">Solo el administrador puede cambiarlo.</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nombre completo *</label>
                    <input type="text" name="nombre_completo" required maxlength="150"
                           value="<?= e($u_data['nombre_completo']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Email</label>
                    <input type="email" name="email" maxlength="150"
                           value="<?= e((string) $u_data['email']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Teléfono</label>
                    <input type="text" name="telefono" maxlength="50"
                           value="<?= e((string) $u_data['telefono']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Puesto</label>
                    <input type="text" name="puesto" maxlength="100"
                           value="<?= e((string) $u_data['puesto']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>

            <!-- Mantengo pagina_inicio en hidden para no perderla -->
            <input type="hidden" name="pagina_inicio_preferida" value="<?= e($u_data['pagina_inicio_preferida'] ?? 'dashboard.php') ?>">

            <div class="flex justify-between items-center pt-3 border-t border-zinc-100">
                <a href="<?= url('cambiar_password.php') ?>" class="text-xs font-semibold text-bacal-700 hover:text-bacal-800 flex items-center gap-1.5">
                    <i data-lucide="key" class="w-3.5 h-3.5"></i> Cambiar mi contraseña
                </a>
                <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>

    <!-- TAB: Foto de perfil -->
    <div x-show="tabActivo === 'foto'" x-cloak>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6"
             x-data="avatarUpload()">

            <h3 class="font-display text-base font-bold text-zinc-900 mb-1">Foto de perfil</h3>
            <p class="text-xs text-zinc-500 mb-5">Imagen cuadrada o se recortará automáticamente. Máximo 5 MB. Formatos: JPG, PNG, WebP.</p>

            <div class="flex items-start gap-6">
                <!-- Vista actual -->
                <div class="flex-shrink-0">
                    <?= render_avatar($u_data, 'w-32 h-32', 'border-4 border-zinc-100') ?>
                </div>

                <!-- Acciones -->
                <div class="flex-1 space-y-3">
                    <input type="file" x-ref="inputFoto" accept="image/jpeg,image/png,image/webp"
                           @change="subir($event.target.files[0])" class="hidden">

                    <button type="button" @click="$refs.inputFoto.click()"
                            :disabled="subiendo"
                            class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-2 disabled:opacity-50">
                        <template x-if="!subiendo">
                            <span class="flex items-center gap-2"><i data-lucide="upload" class="w-4 h-4"></i> Subir foto</span>
                        </template>
                        <template x-if="subiendo">
                            <span class="flex items-center gap-2"><i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Subiendo…</span>
                        </template>
                    </button>

                    <?php if (!empty($u_data['avatar_url'])): ?>
                    <form method="POST" onsubmit="return confirm('¿Eliminar tu foto de perfil?');">
                        <?= csrf_input() ?>
                        <input type="hidden" name="op" value="eliminar_avatar">
                        <button type="submit" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm font-medium hover:bg-zinc-50 flex items-center gap-2">
                            <i data-lucide="trash-2" class="w-4 h-4"></i> Quitar foto actual
                        </button>
                    </form>
                    <?php endif; ?>

                    <p class="text-[11px] text-zinc-500 leading-relaxed">
                        Si la imagen no es cuadrada, se recortará automáticamente desde el centro y se redimensionará a 400×400 píxeles.
                    </p>

                    <!-- Feedback de error -->
                    <div x-show="error" x-cloak class="text-xs text-bacal-700 bg-bacal-50 border border-bacal-200 rounded-lg px-3 py-2"
                         x-text="error"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: Preferencias -->
    <div x-show="tabActivo === 'preferencias'" x-cloak>
        <form method="POST" class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <?= csrf_input() ?>
            <input type="hidden" name="op" value="datos">
            <input type="hidden" name="nombre_completo" value="<?= e($u_data['nombre_completo']) ?>">
            <input type="hidden" name="email" value="<?= e((string) $u_data['email']) ?>">
            <input type="hidden" name="telefono" value="<?= e((string) $u_data['telefono']) ?>">
            <input type="hidden" name="puesto" value="<?= e((string) $u_data['puesto']) ?>">

            <h3 class="font-display text-base font-bold text-zinc-900 mb-3">Preferencias de uso</h3>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-2 uppercase tracking-wide">Página que abre al iniciar sesión</label>
                <select name="pagina_inicio_preferida"
                        class="w-full md:w-80 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="dashboard.php" <?= $u_data['pagina_inicio_preferida'] === 'dashboard.php' ? 'selected' : '' ?>>Dashboard</option>
                    <option value="bitacora.php" <?= $u_data['pagina_inicio_preferida'] === 'bitacora.php' ? 'selected' : '' ?>>Bitácora</option>
                    <?php if (tiene_permiso('crear_solicitud')): ?>
                    <option value="incidencia_nueva.php" <?= $u_data['pagina_inicio_preferida'] === 'incidencia_nueva.php' ? 'selected' : '' ?>>Nueva incidencia</option>
                    <?php endif; ?>
                    <option value="notificaciones.php" <?= $u_data['pagina_inicio_preferida'] === 'notificaciones.php' ? 'selected' : '' ?>>Notificaciones</option>
                    <option value="base_conocimiento.php" <?= $u_data['pagina_inicio_preferida'] === 'base_conocimiento.php' ? 'selected' : '' ?>>Base de conocimiento</option>
                </select>
                <p class="text-[10px] text-zinc-500 mt-1">Cuando inicies sesión te dirigiremos directamente a esta página.</p>
            </div>

            <div class="flex justify-end pt-3 border-t border-zinc-100">
                <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                    Guardar preferencias
                </button>
            </div>
        </form>

        <!-- ================================================================
             Preferencias de notificaciones externas
        ================================================================ -->
        <form method="POST" class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4 mt-4">
            <?= csrf_input() ?>
            <input type="hidden" name="op" value="notificaciones">

            <h3 class="font-display text-base font-bold text-zinc-900">Notificaciones externas</h3>

            <?php if (!$canal_email_activo && !$canal_telegram_activo): ?>
            <div class="bg-zinc-50 border border-zinc-200 rounded-lg px-4 py-3 text-sm text-zinc-500">
                <i data-lucide="info" class="w-4 h-4 inline-block mr-1 text-zinc-400"></i>
                El administrador aún no ha configurado ningún canal externo (Email o Telegram).
            </div>
            <?php else: ?>

            <?php if ($canal_telegram_activo): ?>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Tu Chat ID de Telegram</label>
                <input type="text" name="telegram_chat_id"
                       value="<?= e((string)($u_data['telegram_chat_id'] ?? '')) ?>"
                       placeholder="Ej. 123456789"
                       class="w-full md:w-64 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                <p class="text-[10px] text-zinc-500 mt-1">
                    Para obtenerlo: busca <span class="font-mono bg-zinc-100 px-1 rounded">@userinfobot</span> en Telegram y escríbele cualquier mensaje — te responderá con tu ID.
                    También debes enviarle un mensaje primero al bot del sistema para activar las notificaciones.
                </p>
            </div>
            <?php endif; ?>

            <!-- Tabla de preferencias por tipo -->
            <div class="overflow-x-auto rounded-lg border border-zinc-200">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 text-zinc-500 text-xs uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-semibold">Tipo de notificación</th>
                            <th class="px-4 py-2.5 text-center font-semibold">In-App</th>
                            <?php if ($canal_email_activo): ?>
                            <th class="px-4 py-2.5 text-center font-semibold">
                                <span class="flex items-center justify-center gap-1">
                                    <i data-lucide="mail" class="w-3.5 h-3.5"></i> Email
                                </span>
                            </th>
                            <?php endif; ?>
                            <?php if ($canal_telegram_activo): ?>
                            <th class="px-4 py-2.5 text-center font-semibold">
                                <span class="flex items-center justify-center gap-1">
                                    <i data-lucide="send" class="w-3.5 h-3.5"></i> Telegram
                                </span>
                            </th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php
                        $etiquetas_tipo = [
                            'asignacion'             => 'Se me asigna una incidencia',
                            'cambio_estado'          => 'Cambio de estado en mis incidencias',
                            'comentario'             => 'Nuevo comentario en mis incidencias',
                            'mencion'                => 'Me mencionan en un comentario',
                            'reincidencia'           => 'Se detecta una reincidencia',
                            'sla_vencido'            => 'SLA vencido',
                            'sla_riesgo'             => 'SLA en riesgo',
                            'incidencia_creada'      => 'Nueva incidencia creada',
                            'incidencia_resuelta'    => 'Incidencia resuelta',
                            'mantenimiento_proximo'  => 'Mantenimiento próximo',
                            'mantenimiento_vencido'  => 'Mantenimiento vencido',
                            'mantenimiento_completado' => 'Mantenimiento completado',
                            'sistema'                => 'Avisos del sistema',
                        ];
                        foreach (array_keys(NOTIF_TIPOS) as $tipo):
                            $pref = $notif_prefs[$tipo] ?? null;
                            // Defaults: email en asignacion y mencion si no hay registro
                            $def_email = in_array($tipo, ['asignacion','mencion'], true) ? 1 : 0;
                            $val_email = $pref ? (int)$pref['canal_email'] : $def_email;
                            $val_tg    = $pref ? (int)$pref['canal_telegram'] : 0;
                        ?>
                        <tr class="hover:bg-zinc-50 transition-colors">
                            <td class="px-4 py-2.5 text-zinc-700">
                                <?= e($etiquetas_tipo[$tipo] ?? $tipo) ?>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <span class="text-emerald-600" title="Siempre activado">
                                    <i data-lucide="check" class="w-4 h-4 inline-block"></i>
                                </span>
                            </td>
                            <?php if ($canal_email_activo): ?>
                            <td class="px-4 py-2.5 text-center">
                                <input type="checkbox" name="pref_email_<?= $tipo ?>" value="1"
                                       <?= $val_email ? 'checked' : '' ?>
                                       class="w-4 h-4 accent-bacal-700">
                            </td>
                            <?php endif; ?>
                            <?php if ($canal_telegram_activo): ?>
                            <td class="px-4 py-2.5 text-center">
                                <input type="checkbox" name="pref_tg_<?= $tipo ?>" value="1"
                                       <?= $val_tg ? 'checked' : '' ?>
                                       class="w-4 h-4 accent-sky-500">
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end pt-3 border-t border-zinc-100">
                <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                    Guardar preferencias de notificación
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- TAB: Estadísticas -->
    <div x-show="tabActivo === 'estadisticas'" x-cloak class="space-y-4">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            <?php
            $kpis = [
                ['Incidencias creadas', $stats['total_creadas'], 'file-plus', '#2563EB'],
                ['Asignadas (total)', $stats['total_asignadas'], 'user-check', '#7C3AED'],
                ['Resueltas', $stats['total_resueltas'], 'check-circle-2', '#16A34A'],
                ['Abiertas ahora', $stats['abiertas_actuales'], 'clock', ((int) $stats['abiertas_actuales']) > 5 ? '#DC2626' : '#D97706'],
                ['Comentarios', $stats['total_comentarios'], 'message-square', '#0EA5E9'],
                ['T. promedio resolución', $stats['avg_resolucion'] !== null ? fmt_duracion((int) $stats['avg_resolucion']) : '—', 'timer', '#9333EA'],
            ];
            foreach ($kpis as [$label, $valor, $icono, $color]):
            ?>
            <div class="bg-white rounded-xl border border-zinc-200 p-4 shadow-sm">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center mb-2" style="background-color: <?= e($color) ?>15">
                    <i data-lucide="<?= e($icono) ?>" class="w-4 h-4" style="color: <?= e($color) ?>"></i>
                </div>
                <div class="font-display text-xl font-extrabold text-zinc-900 leading-none"><?= e((string) $valor) ?></div>
                <div class="text-[10px] text-zinc-500 mt-1.5 uppercase tracking-wider font-bold"><?= e($label) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (tiene_permiso('resolver') && (int) $stats['total_asignadas'] > 0): ?>
        <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-5 text-sm text-zinc-700">
            <div class="flex items-center gap-2 mb-2">
                <i data-lucide="info" class="w-4 h-4 text-bacal-700"></i>
                <strong>Tip:</strong>
            </div>
            <p class="text-xs leading-relaxed">
                Puedes ver el detalle de todas las incidencias en las que has trabajado en
                <a href="<?= url('bitacora.php?asignado_a=' . $id) ?>" class="text-bacal-700 hover:underline font-semibold">la bitácora filtrada</a>.
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Actividad -->
    <div x-show="tabActivo === 'actividad'" x-cloak>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm">
            <div class="px-5 py-3 border-b border-zinc-100">
                <h3 class="font-display text-base font-bold text-zinc-900">Mi actividad reciente</h3>
                <p class="text-xs text-zinc-500 mt-0.5">Últimas 10 acciones que realizaste en el sistema.</p>
            </div>
            <div class="divide-y divide-zinc-100">
                <?php if (empty($actividad)): ?>
                <div class="px-5 py-10 text-center text-xs text-zinc-400 italic">Sin actividad registrada.</div>
                <?php else: ?>
                <?php foreach ($actividad as $act): ?>
                <div class="px-5 py-3 flex items-start gap-3">
                    <div class="w-2 h-2 rounded-full bg-bacal-600 mt-1.5 flex-shrink-0"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-sm text-zinc-900"><?= e($act['descripcion'] ?? $act['accion']) ?></span>
                            <span class="text-[10px] font-mono text-zinc-400 bg-zinc-100 px-1.5 py-0.5 rounded"><?= e($act['accion']) ?></span>
                        </div>
                        <div class="text-[11px] text-zinc-500 mt-0.5">
                            <?= e(fmt_fecha($act['creado_en'])) ?> · IP: <?= e($act['ip'] ?? '—') ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function avatarUpload() {
    return {
        subiendo: false,
        error: '',

        async subir(archivo) {
            if (!archivo) return;
            this.error = '';

            // Validar tipo
            const tiposOk = ['image/jpeg', 'image/png', 'image/webp'];
            if (!tiposOk.includes(archivo.type)) {
                this.error = 'Solo se permiten imágenes JPG, PNG o WebP.';
                return;
            }
            // Validar tamaño
            if (archivo.size > <?= $MAX_AVATAR_BYTES ?>) {
                this.error = 'La imagen excede los 5 MB. Comprímela e intenta de nuevo.';
                return;
            }

            this.subiendo = true;
            const fd = new FormData();
            fd.append('_csrf', '<?= e(csrf_token()) ?>');
            fd.append('avatar', archivo);

            try {
                const resp = await fetch('<?= url('api/avatar_subir.php') ?>', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                });
                const data = await resp.json();
                if (data.ok) {
                    window.location.reload();
                } else {
                    this.error = data.error || 'Error al subir la imagen.';
                }
            } catch (e) {
                this.error = 'Error de red: ' + e.message;
            }
            this.subiendo = false;
        }
    }
}
</script>

<?php require_once __DIR__ . '/config/footer.php'; ?>
