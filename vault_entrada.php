<?php
/**
 * ============================================================================
 * vault_entrada.php - Ver / Crear / Editar entrada del vault
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/vault_helpers.php';

requerir_login();
$u = usuario_actual();
$es_admin = vault_puede_administrar();

$accion = (string) input('accion', 'ver');
$id = (int) input('id', 0);

$entrada = null;
$modo_edicion = false;

// Cargar entrada si se especifica
if ($id > 0) {
    $entrada = vault_obtener_entrada($id);
    if (!$entrada) {
        flash_set('error', 'Entrada no encontrada.');
        header('Location: ' . url('vault.php'));
        exit;
    }
    // Verificar permiso de lectura
    if (!vault_usuario_puede_ver($entrada, $u)) {
        flash_set('error', 'No tienes permiso para ver esta entrada.');
        header('Location: ' . url('vault.php'));
        exit;
    }
    // Registrar acceso
    vault_registrar_acceso($id, (int) $u['id'], 'ver_entrada');
}

// Sólo admin puede crear/editar/eliminar
if (in_array($accion, ['nuevo', 'editar', 'eliminar'], true) && !$es_admin) {
    flash_set('error', 'Solo admin puede gestionar entradas.');
    header('Location: ' . url('vault.php'));
    exit;
}

if ($accion === 'editar' && $entrada) $modo_edicion = true;
if ($accion === 'nuevo') $modo_edicion = true;

$errores = [];

// ----------------------------------------------------------------------------
// Procesar POST
// ----------------------------------------------------------------------------
if (es_post() && $es_admin) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token inválido.';
    } else {
        $op = (string) input('op', '');

        try {
            if ($op === 'eliminar' && $entrada) {
                vault_eliminar_entrada((int) $entrada['id'], (int) $u['id']);
                registrar_auditoria('eliminar_vault_entrada', 'vault_entradas', (int) $entrada['id'], "Entrada: {$entrada['nombre']}");
                flash_set('success', 'Entrada eliminada.');
                header('Location: ' . url('vault.php'));
                exit;
            }

            if (in_array($op, ['crear', 'editar_guardar'], true)) {
                $datos = [
                    'categoria_id'  => (int) input('categoria_id', 0),
                    'nombre'        => trim((string) input('nombre', '')),
                    'url'           => trim((string) input('url', '')) ?: null,
                    'usuario'       => trim((string) input('usuario_login', '')) ?: null,
                    'password'      => (string) input('password', ''),
                    'notas'         => trim((string) input('notas', '')) ?: null,
                    'archivos'      => trim((string) input('archivos', '')) ?: null,
                    'version_build' => trim((string) input('version_build', '')) ?: null,
                    'vencimiento'   => trim((string) input('vencimiento', '')) ?: null,
                    'tags'          => trim((string) input('tags', '')) ?: null,
                    'sucursal_id'   => (int) input('sucursal_id', 0) ?: null,
                    'sensibilidad'  => (string) input('sensibilidad', 'normal'),
                    'permisos_tipo' => (string) input('permisos_tipo', 'admin'),
                ];

                // Validaciones
                if ($datos['categoria_id'] <= 0) $errores[] = 'Selecciona una categoría.';
                if ($datos['nombre'] === '') $errores[] = 'El nombre es obligatorio.';
                if (!in_array($datos['sensibilidad'], ['normal','alta','critica'], true)) $datos['sensibilidad'] = 'normal';
                if (!in_array($datos['permisos_tipo'], ['todos','rol','sucursal','usuarios','admin'], true)) $datos['permisos_tipo'] = 'admin';

                if (empty($errores)) {
                    if ($op === 'crear') {
                        $nuevo_id = vault_crear_entrada($datos, (int) $u['id']);

                        // Permisos granulares
                        if (in_array($datos['permisos_tipo'], ['rol','sucursal','usuarios'], true)) {
                            $tipo_perm = $datos['permisos_tipo'] === 'usuarios' ? 'usuario' : $datos['permisos_tipo'];
                            $refs = (array) input('permisos_refs', []);
                            vault_guardar_permisos($nuevo_id, $tipo_perm, $refs);
                        }

                        registrar_auditoria('crear_vault_entrada', 'vault_entradas', $nuevo_id, "Entrada: {$datos['nombre']}");
                        flash_set('success', 'Entrada creada.');
                        header('Location: ' . url('vault_entrada.php?id=' . $nuevo_id));
                        exit;
                    } else {
                        // Detectar si se cambia el password
                        $cambiar_pwd = (input('cambiar_password') === '1');
                        vault_actualizar_entrada((int) $entrada['id'], $datos, (int) $u['id'], $cambiar_pwd);

                        // Actualizar permisos
                        if (in_array($datos['permisos_tipo'], ['rol','sucursal','usuarios'], true)) {
                            $tipo_perm = $datos['permisos_tipo'] === 'usuarios' ? 'usuario' : $datos['permisos_tipo'];
                            $refs = (array) input('permisos_refs', []);
                            vault_guardar_permisos((int) $entrada['id'], $tipo_perm, $refs);
                        } else {
                            // Si cambió a 'todos' o 'admin', limpiar permisos
                            db_exec("DELETE FROM vault_permisos WHERE entrada_id = :eid", ['eid' => $entrada['id']]);
                        }

                        registrar_auditoria('editar_vault_entrada', 'vault_entradas', (int) $entrada['id'], "Entrada: {$datos['nombre']}");
                        flash_set('success', 'Entrada actualizada.');
                        header('Location: ' . url('vault_entrada.php?id=' . $entrada['id']));
                        exit;
                    }
                }
            }
        } catch (Throwable $e) {
            $errores[] = 'Error: ' . $e->getMessage();
        }
    }
}

// Datos para selectores
$categorias = vault_listar_categorias();
$sucursales = db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo=1 ORDER BY nombre");
$roles_lista = db_all("SELECT id, nombre FROM roles WHERE activo=1 ORDER BY id");
$usuarios_lista = db_all("SELECT id, usuario, nombre_completo FROM usuarios WHERE activo=1 ORDER BY nombre_completo");

// IDs con permiso actual (para rellenar checkboxes en modo edición)
$permisos_actuales = [];
if ($entrada && in_array($entrada['permisos_tipo'], ['rol','sucursal','usuarios'], true)) {
    $tipo = $entrada['permisos_tipo'] === 'usuarios' ? 'usuario' : $entrada['permisos_tipo'];
    $permisos_actuales = vault_obtener_permisos((int) $entrada['id'], $tipo);
}

$titulo_pagina = $modo_edicion
    ? ($accion === 'nuevo' ? 'Nueva entrada' : 'Editar entrada')
    : ($entrada['nombre'] ?? 'Entrada');
$pagina_activa = 'vault';
require_once __DIR__ . '/config/header.php';

$valores = $entrada ?: [
    'categoria_id' => (int) input('categoria_id', 0),
    'nombre' => '', 'url' => '', 'usuario' => '', 'notas' => '', 'archivos' => '',
    'version_build' => '', 'vencimiento' => '', 'tags' => '',
    'sucursal_id' => null, 'sensibilidad' => 'normal', 'permisos_tipo' => 'admin',
    'tiene_password' => 0,
];
?>

<div class="max-w-4xl mx-auto animate-fade-in space-y-4"
     x-data="vaultEntrada(<?= (int) ($entrada['id'] ?? 0) ?>)">

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= url('vault.php') ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div class="flex-1 min-w-0">
            <?php if ($modo_edicion): ?>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">
                <?= $accion === 'nuevo' ? 'Nueva entrada' : 'Editar entrada' ?>
            </h2>
            <p class="text-xs text-zinc-500">Completa los datos. Los campos con * son obligatorios.</p>
            <?php else: ?>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900 truncate"><?= e($entrada['nombre']) ?></h2>
            <div class="flex items-center gap-2 mt-0.5">
                <span class="text-xs text-zinc-500"><?= e($entrada['familia']) ?> · <?= e($entrada['categoria_nombre']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!$modo_edicion && $es_admin): ?>
        <a href="<?= url('vault_entrada.php?accion=editar&id=' . $entrada['id']) ?>"
           class="px-3 py-2 rounded-lg border border-zinc-300 hover:bg-zinc-50 text-sm font-semibold text-zinc-700 flex items-center gap-1.5">
            <i data-lucide="edit-3" class="w-4 h-4"></i> Editar
        </a>
        <?php endif; ?>
    </div>

    <!-- Errores -->
    <?php if (!empty($errores)): ?>
    <div class="px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <ul class="list-disc list-inside text-xs">
            <?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($modo_edicion): ?>
    <!-- ============================================================
         MODO EDICIÓN / CREACIÓN
         ============================================================ -->
    <form method="POST" class="space-y-4">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="<?= $accion === 'nuevo' ? 'crear' : 'editar_guardar' ?>">

        <!-- Información básica -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4 text-bacal-700"></i> Información básica
            </h3>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nombre *</label>
                <input type="text" name="nombre" required maxlength="200"
                       value="<?= e($valores['nombre']) ?>"
                       placeholder="ej. Sonicwall Bacal, Office 2019, Procedimiento corte de cajero"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Categoría *</label>
                    <select name="categoria_id" required
                            class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Selecciona —</option>
                        <?php
                        $ult_familia = null;
                        foreach ($categorias as $c):
                            if ($c['familia'] !== $ult_familia):
                                if ($ult_familia !== null) echo '</optgroup>';
                                echo '<optgroup label="' . e($c['familia']) . '">';
                                $ult_familia = $c['familia'];
                            endif;
                        ?>
                        <option value="<?= $c['id'] ?>" <?= (int) $valores['categoria_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['nombre']) ?>
                        </option>
                        <?php endforeach;
                        if ($ult_familia !== null) echo '</optgroup>';
                        ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Sucursal</label>
                    <select name="sucursal_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Todas / N/A —</option>
                        <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (int) $valores['sucursal_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Credenciales -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="key-round" class="w-4 h-4 text-bacal-700"></i> Acceso y credenciales
            </h3>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">URL / IP</label>
                <input type="text" name="url" maxlength="500"
                       value="<?= e($valores['url'] ?? '') ?>"
                       placeholder="https://192.168.1.1 o RDP: 192.168.1.50"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Usuario</label>
                    <input type="text" name="usuario_login" maxlength="200"
                           value="<?= e($valores['usuario'] ?? '') ?>"
                           placeholder="admin, root, etc."
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">
                        Contraseña
                        <?php if ($accion === 'editar' && (int) $valores['tiene_password'] === 1): ?>
                        <span class="text-zinc-400 font-normal normal-case">(deja en blanco para conservar)</span>
                        <?php endif; ?>
                    </label>
                    <div class="relative" x-data="{ visible: false }">
                        <input :type="visible ? 'text' : 'password'" name="password"
                               placeholder="••••••••"
                               autocomplete="new-password"
                               @input="$dispatch('pwd-input')"
                               class="w-full px-3 py-2 pr-10 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700 font-mono">
                        <button type="button" @click="visible = !visible"
                                class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-zinc-400 hover:text-zinc-700">
                            <i :data-lucide="visible ? 'eye-off' : 'eye'" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <?php if ($accion === 'editar'): ?>
                    <label class="flex items-center gap-1.5 text-[11px] text-zinc-600 mt-1.5 cursor-pointer">
                        <input type="checkbox" name="cambiar_password" value="1"
                               @pwd-input.window="$el.checked = true"
                               class="rounded">
                        Cambiar contraseña actual
                    </label>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detalles -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="file-edit" class="w-4 h-4 text-bacal-700"></i> Detalles
            </h3>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Notas / Instrucciones</label>
                <textarea name="notas" rows="6"
                          placeholder="Instrucciones de uso, datos adicionales, pasos del procedimiento, etc. Soporta saltos de línea."
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700 font-mono"><?= e($valores['notas'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">
                    Archivos relacionados
                    <span class="text-zinc-400 font-normal normal-case">(rutas a la carpeta de red, editables)</span>
                </label>
                <textarea name="archivos" rows="3"
                          placeholder="\\servidor\compartida\Sistemas\manual.pdf&#10;\\servidor\compartida\Sistemas\backup.cfg"
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700 font-mono"><?= e($valores['archivos'] ?? '') ?></textarea>
                <p class="text-[10px] text-zinc-500 mt-1">💡 Cuando muevas archivos en la red, edita esta entrada para actualizar las rutas.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Versión / Build</label>
                    <input type="text" name="version_build" maxlength="100"
                           value="<?= e($valores['version_build'] ?? '') ?>"
                           placeholder="v3.2.1, 2019, etc."
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Vencimiento</label>
                    <input type="date" name="vencimiento"
                           value="<?= e($valores['vencimiento'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Tags</label>
                    <input type="text" name="tags" maxlength="500"
                           value="<?= e($valores['tags'] ?? '') ?>"
                           placeholder="red, firewall, sonicwall"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>
        </div>

        <!-- Seguridad y permisos -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="shield" class="w-4 h-4 text-bacal-700"></i> Seguridad y permisos
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-2 uppercase tracking-wide">Sensibilidad</label>
                    <div class="space-y-1.5">
                        <?php foreach (['normal' => ['Normal', 'Información general'],
                                         'alta' => ['Alta', 'Requiere precaución'],
                                         'critica' => ['Crítica', 'Solo personas autorizadas']] as $val => [$lbl, $desc]): ?>
                        <label class="flex items-start gap-2 p-2 rounded border border-zinc-200 hover:bg-zinc-50 cursor-pointer">
                            <input type="radio" name="sensibilidad" value="<?= $val ?>" <?= ($valores['sensibilidad'] ?? 'normal') === $val ? 'checked' : '' ?>
                                   class="mt-0.5">
                            <div class="text-xs">
                                <div class="font-semibold text-zinc-900"><?= e($lbl) ?></div>
                                <div class="text-zinc-500 text-[10px]"><?= e($desc) ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-2 uppercase tracking-wide">¿Quién puede ver esta entrada?</label>
                    <select name="permisos_tipo" x-model="permisosTipo"
                            class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="admin" <?= ($valores['permisos_tipo'] ?? 'admin') === 'admin' ? 'selected' : '' ?>>🔒 Solo admin</option>
                        <option value="todos" <?= ($valores['permisos_tipo'] ?? '') === 'todos' ? 'selected' : '' ?>>🌐 Todos los usuarios</option>
                        <option value="rol" <?= ($valores['permisos_tipo'] ?? '') === 'rol' ? 'selected' : '' ?>>🛡️ Por rol</option>
                        <option value="sucursal" <?= ($valores['permisos_tipo'] ?? '') === 'sucursal' ? 'selected' : '' ?>>📍 Por sucursal</option>
                        <option value="usuarios" <?= ($valores['permisos_tipo'] ?? '') === 'usuarios' ? 'selected' : '' ?>>👥 Lista de usuarios</option>
                    </select>

                    <!-- Roles -->
                    <div x-show="permisosTipo === 'rol'" x-cloak class="mt-3 p-3 border border-zinc-200 rounded-lg max-h-40 overflow-y-auto space-y-1.5">
                        <?php foreach ($roles_lista as $r): ?>
                        <label class="flex items-center gap-2 text-xs">
                            <input type="checkbox" name="permisos_refs[]" value="<?= $r['id'] ?>"
                                   <?= $entrada && $valores['permisos_tipo'] === 'rol' && in_array((int) $r['id'], $permisos_actuales, true) ? 'checked' : '' ?>
                                   class="rounded">
                            <?= e($r['nombre']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Sucursales -->
                    <div x-show="permisosTipo === 'sucursal'" x-cloak class="mt-3 p-3 border border-zinc-200 rounded-lg max-h-40 overflow-y-auto space-y-1.5">
                        <?php foreach ($sucursales as $s): ?>
                        <label class="flex items-center gap-2 text-xs">
                            <input type="checkbox" name="permisos_refs[]" value="<?= $s['id'] ?>"
                                   <?= $entrada && $valores['permisos_tipo'] === 'sucursal' && in_array((int) $s['id'], $permisos_actuales, true) ? 'checked' : '' ?>
                                   class="rounded">
                            <?= e($s['nombre']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Usuarios -->
                    <div x-show="permisosTipo === 'usuarios'" x-cloak class="mt-3 p-3 border border-zinc-200 rounded-lg max-h-60 overflow-y-auto space-y-1.5">
                        <?php foreach ($usuarios_lista as $usr): ?>
                        <label class="flex items-center gap-2 text-xs">
                            <input type="checkbox" name="permisos_refs[]" value="<?= $usr['id'] ?>"
                                   <?= $entrada && $valores['permisos_tipo'] === 'usuarios' && in_array((int) $usr['id'], $permisos_actuales, true) ? 'checked' : '' ?>
                                   class="rounded">
                            <span class="font-medium"><?= e($usr['nombre_completo']) ?></span>
                            <span class="text-zinc-400 font-mono text-[10px]">@<?= e($usr['usuario']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones -->
        <div class="flex justify-end gap-2">
            <a href="<?= $entrada ? url('vault_entrada.php?id=' . $entrada['id']) : url('vault.php') ?>"
               class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</a>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
                <i data-lucide="check" class="w-4 h-4"></i>
                <?= $accion === 'nuevo' ? 'Crear entrada' : 'Guardar cambios' ?>
            </button>
        </div>
    </form>

    <?php else: ?>
    <!-- ============================================================
         MODO VISTA
         ============================================================ -->

    <!-- Badges arriba -->
    <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase"
              style="color: <?= e($entrada['categoria_color']) ?>; background-color: <?= e($entrada['categoria_color']) ?>15">
            <i data-lucide="<?= e($entrada['categoria_icono']) ?>" class="w-3 h-3 inline -mt-0.5"></i>
            <?= e($entrada['categoria_nombre']) ?>
        </span>
        <?php if ($entrada['sensibilidad'] !== 'normal'):
            $sens_c = $entrada['sensibilidad'] === 'critica' ? '#DC2626' : '#F59E0B';
        ?>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase"
              style="color: <?= e($sens_c) ?>; background-color: <?= e($sens_c) ?>15">
            🔥 Sensibilidad <?= e($entrada['sensibilidad']) ?>
        </span>
        <?php endif; ?>
        <?php if (!empty($entrada['sucursal_codigo'])): ?>
        <span class="text-[10px] font-medium text-zinc-600 bg-zinc-100 px-2 py-0.5 rounded uppercase">
            <i data-lucide="map-pin" class="w-3 h-3 inline -mt-0.5"></i> <?= e($entrada['sucursal_codigo']) ?>
        </span>
        <?php endif; ?>
        <?php if (!empty($entrada['version_build'])): ?>
        <span class="text-[10px] font-medium text-zinc-600 bg-zinc-100 px-2 py-0.5 rounded">
            <i data-lucide="tag" class="w-3 h-3 inline -mt-0.5"></i> v<?= e($entrada['version_build']) ?>
        </span>
        <?php endif; ?>
        <button @click="toggleFavorito()"
                class="ml-auto p-1.5 rounded hover:bg-zinc-100 text-zinc-400 hover:text-amber-500"
                :class="esFavorito ? 'text-amber-500' : ''"
                title="Marcar favorito">
            <i data-lucide="star" class="w-4 h-4" :class="esFavorito ? 'fill-amber-400' : ''"></i>
        </button>
    </div>

    <!-- Credenciales -->
    <?php if (!empty($entrada['url']) || !empty($entrada['usuario']) || (int) $entrada['tiene_password'] === 1): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5 space-y-3">
        <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
            <i data-lucide="key-round" class="w-4 h-4 text-bacal-700"></i> Acceso
        </h3>

        <?php if (!empty($entrada['url'])): ?>
        <div class="flex items-start gap-3">
            <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider w-20 pt-1">URL/IP</div>
            <div class="flex-1 font-mono text-sm text-zinc-900 break-all">
                <?php if (preg_match('#^https?://#i', $entrada['url'])): ?>
                <a href="<?= e($entrada['url']) ?>" target="_blank" rel="noopener" class="text-blue-700 hover:underline"><?= e($entrada['url']) ?></a>
                <?php else: ?>
                <?= e($entrada['url']) ?>
                <?php endif; ?>
            </div>
            <button @click="copiar('<?= e(addslashes($entrada['url'])) ?>', 'URL')"
                    class="text-zinc-400 hover:text-bacal-700 p-1" title="Copiar">
                <i data-lucide="copy" class="w-4 h-4"></i>
            </button>
        </div>
        <?php endif; ?>

        <?php if (!empty($entrada['usuario'])): ?>
        <div class="flex items-start gap-3">
            <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider w-20 pt-1">Usuario</div>
            <div class="flex-1 font-mono text-sm text-zinc-900"><?= e($entrada['usuario']) ?></div>
            <button @click="copiar('<?= e(addslashes($entrada['usuario'])) ?>', 'Usuario')"
                    class="text-zinc-400 hover:text-bacal-700 p-1" title="Copiar">
                <i data-lucide="copy" class="w-4 h-4"></i>
            </button>
        </div>
        <?php endif; ?>

        <?php if ((int) $entrada['tiene_password'] === 1): ?>
        <div class="flex items-start gap-3">
            <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider w-20 pt-1">Contraseña</div>
            <div class="flex-1 font-mono text-sm">
                <span x-show="!passwordVisible" class="text-zinc-400">••••••••••••</span>
                <span x-show="passwordVisible" x-cloak class="text-zinc-900 break-all" x-text="passwordActual"></span>
            </div>
            <button @click="mostrarPassword()" class="text-zinc-400 hover:text-bacal-700 p-1" title="Mostrar/ocultar">
                <i data-lucide="eye" class="w-4 h-4" x-show="!passwordVisible"></i>
                <i data-lucide="eye-off" class="w-4 h-4" x-show="passwordVisible" x-cloak></i>
            </button>
            <button @click="copiarPassword()" class="text-zinc-400 hover:text-bacal-700 p-1" title="Copiar (se borra en 30s)">
                <i data-lucide="copy" class="w-4 h-4"></i>
            </button>
        </div>
        <p class="text-[10px] text-zinc-400 pl-[5.5rem]">
            Al copiar, el portapapeles se limpia automáticamente en 30 segundos por seguridad.
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Notas -->
    <?php if (!empty($entrada['notas'])): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
        <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2 mb-3">
            <i data-lucide="file-text" class="w-4 h-4 text-bacal-700"></i> Notas
        </h3>
        <div class="text-sm text-zinc-700 whitespace-pre-wrap font-mono leading-relaxed"><?= e($entrada['notas']) ?></div>
    </div>
    <?php endif; ?>

    <!-- Archivos relacionados -->
    <?php if (!empty($entrada['archivos'])): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
        <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2 mb-2">
            <i data-lucide="folder-open" class="w-4 h-4 text-bacal-700"></i> Archivos relacionados
            <span class="text-[10px] font-normal text-zinc-500">(en la carpeta de red)</span>
        </h3>
        <div class="space-y-1">
            <?php foreach (explode("\n", $entrada['archivos']) as $linea):
                $linea = trim($linea);
                if ($linea === '') continue;
            ?>
            <div class="flex items-center gap-2 group">
                <i data-lucide="file" class="w-3.5 h-3.5 text-zinc-400 flex-shrink-0"></i>
                <code class="flex-1 text-xs font-mono text-zinc-700 break-all"><?= e($linea) ?></code>
                <button @click="copiar('<?= e(addslashes($linea)) ?>', 'Ruta')"
                        class="opacity-0 group-hover:opacity-100 text-zinc-400 hover:text-bacal-700 p-1 transition-opacity"
                        title="Copiar ruta">
                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tags -->
    <?php if (!empty($entrada['tags'])): ?>
    <div class="flex items-center gap-2 flex-wrap">
        <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Tags:</span>
        <?php foreach (array_filter(array_map('trim', explode(',', $entrada['tags']))) as $tag): ?>
        <span class="text-[10px] px-2 py-0.5 rounded bg-zinc-100 text-zinc-700 font-medium">#<?= e($tag) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Metadata abajo -->
    <div class="bg-zinc-50 rounded-xl border border-zinc-200 p-4 text-xs text-zinc-600 space-y-1">
        <?php if (!empty($entrada['vencimiento'])):
            $ts_v = strtotime($entrada['vencimiento']);
            $venc_clase = $ts_v < time() ? 'text-bacal-700 font-bold' : ($ts_v < time() + (30 * 86400) ? 'text-amber-700 font-bold' : '');
        ?>
        <div class="<?= $venc_clase ?>">
            <i data-lucide="calendar-clock" class="w-3.5 h-3.5 inline -mt-0.5"></i>
            Vencimiento: <?= e(date('d / M / Y', $ts_v)) ?>
            <?php if ($ts_v < time()): ?> · ⚠️ VENCIDO<?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($entrada['creado_por_nombre'])): ?>
        <div>Creado por <?= e($entrada['creado_por_nombre']) ?> · <?= e(fmt_tiempo_relativo($entrada['creado_en'])) ?></div>
        <?php endif; ?>
        <?php if (!empty($entrada['actualizado_por_nombre']) && $entrada['actualizado_en'] !== $entrada['creado_en']): ?>
        <div>Actualizado por <?= e($entrada['actualizado_por_nombre']) ?> · <?= e(fmt_tiempo_relativo($entrada['actualizado_en'])) ?></div>
        <?php endif; ?>
    </div>

    <?php if ($es_admin): ?>
    <!-- Acciones admin -->
    <div class="flex justify-end gap-2">
        <form method="POST" onsubmit="return confirm('¿Eliminar esta entrada? Esto NO es reversible desde la interfaz.');">
            <?= csrf_input() ?>
            <input type="hidden" name="op" value="eliminar">
            <button type="submit" class="px-4 py-2 rounded-lg border border-zinc-300 hover:bg-bacal-50 hover:border-bacal-300 hover:text-bacal-700 text-zinc-700 text-sm font-semibold flex items-center gap-1.5">
                <i data-lucide="trash-2" class="w-4 h-4"></i> Eliminar
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php endif; // fin modo vista ?>
</div>

<script>
function vaultEntrada(entradaId) {
    return {
        entradaId: entradaId,
        permisosTipo: '<?= e($valores['permisos_tipo'] ?? 'admin') ?>',
        passwordVisible: false,
        passwordActual: '',
        esFavorito: false,
        timerPwd: null,

        init() {
            // Verificar si es favorito
            this.verificarFavorito();
        },

        async verificarFavorito() {
            if (!this.entradaId) return;
            // Por defecto al cargar, no marcar nada hasta que el usuario lo haga
        },

        async mostrarPassword() {
            if (this.passwordVisible) {
                this.passwordVisible = false;
                this.passwordActual = '';
                return;
            }
            try {
                const resp = await fetch('<?= url('api/vault_ver_password.php') ?>?id=' + this.entradaId, {
                    credentials: 'same-origin'
                });
                const data = await resp.json();
                if (data.ok) {
                    this.passwordActual = data.password;
                    this.passwordVisible = true;
                    // Auto-ocultar tras 30 segundos
                    clearTimeout(this.timerPwd);
                    this.timerPwd = setTimeout(() => {
                        this.passwordVisible = false;
                        this.passwordActual = '';
                    }, 30000);
                } else {
                    alert('Error: ' + (data.error || 'No se pudo obtener'));
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        },

        async copiarPassword() {
            try {
                const resp = await fetch('<?= url('api/vault_ver_password.php') ?>?id=' + this.entradaId + '&modo=copiar', {
                    credentials: 'same-origin'
                });
                const data = await resp.json();
                if (data.ok) {
                    await navigator.clipboard.writeText(data.password);
                    this.toast('Contraseña copiada · se borra en 30s');
                    // Limpiar el portapapeles tras 30s
                    setTimeout(() => {
                        navigator.clipboard.writeText('').catch(() => {});
                    }, 30000);
                } else {
                    alert('Error: ' + (data.error || 'No se pudo obtener'));
                }
            } catch (e) {
                alert('Error: ' + e.message);
            }
        },

        async copiar(texto, etiqueta) {
            try {
                await navigator.clipboard.writeText(texto);
                this.toast(etiqueta + ' copiado');
            } catch (e) {
                alert('No se pudo copiar: ' + e.message);
            }
        },

        async toggleFavorito() {
            try {
                const fd = new FormData();
                fd.append('_csrf', '<?= e(csrf_token()) ?>');
                fd.append('entrada_id', this.entradaId);
                const resp = await fetch('<?= url('api/vault_favorito.php') ?>', {
                    method: 'POST', body: fd, credentials: 'same-origin'
                });
                const data = await resp.json();
                if (data.ok) {
                    this.esFavorito = (data.estado === 'agregado');
                    this.toast(data.estado === 'agregado' ? '⭐ Agregado a favoritos' : 'Eliminado de favoritos');
                }
            } catch (e) {}
        },

        toast(mensaje) {
            const div = document.createElement('div');
            div.className = 'fixed top-4 right-4 z-[200] bg-zinc-900 text-white text-sm px-4 py-2 rounded-lg shadow-lg animate-slide-up';
            div.textContent = mensaje;
            document.body.appendChild(div);
            setTimeout(() => div.remove(), 3000);
        }
    }
}
</script>

<?php require_once __DIR__ . '/config/footer.php'; ?>
