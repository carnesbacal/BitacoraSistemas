<?php
/**
 * ============================================================================
 * estaciones.php - Listado de estaciones de trabajo
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/estaciones_helpers.php';

requerir_login();
$u = usuario_actual();
$es_admin = tiene_permiso('administrar');
$puede_gestionar = $es_admin || tiene_permiso('resolver');

// Filtros
$f_busqueda = trim((string) input('q', ''));
$f_sucursal = (int) input('sucursal_id', 0);
$f_area = (int) input('area_id', 0);

if (!tiene_permiso('ver_todas_sucursales')) {
    $f_sucursal = (int) $u['sucursal_id'];
}

$errores = [];

// Procesar POST
if (es_post() && $puede_gestionar) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token inválido.';
    } else {
        $op = (string) input('op', '');
        if ($op === 'crear') {
            $datos = [
                'codigo' => trim((string) input('codigo', '')),
                'nombre' => trim((string) input('nombre', '')),
                'descripcion' => trim((string) input('descripcion', '')) ?: null,
                'sucursal_id' => (int) input('sucursal_est', $u['sucursal_id']),
                'area_id' => (int) input('area_est', 0) ?: null,
                'ubicacion' => trim((string) input('ubicacion', '')) ?: null,
                'responsable_id' => (int) input('responsable_id', 0) ?: null,
                'responsable_nombre' => trim((string) input('responsable_nombre', '')) ?: null,
                'notas' => trim((string) input('notas', '')) ?: null,
            ];

            if ($datos['codigo'] === '') $errores[] = 'El código es obligatorio.';
            if ($datos['nombre'] === '') $errores[] = 'El nombre es obligatorio.';

            if (empty($errores)) {
                try {
                    $id = crear_estacion($datos, (int) $u['id']);
                    registrar_auditoria('crear_estacion', 'estaciones_trabajo', $id, $datos['nombre']);
                    flash_set('success', "Estación '{$datos['nombre']}' creada.");
                    header('Location: ' . url('estacion_ver.php?id=' . $id));
                    exit;
                } catch (Throwable $e) {
                    $errores[] = 'Error: ' . $e->getMessage();
                    if (str_contains($e->getMessage(), 'Duplicate')) {
                        $errores[] = "El código '{$datos['codigo']}' ya existe.";
                    }
                }
            }
        }
    }
}

$estaciones = listar_estaciones([
    'busqueda' => $f_busqueda ?: null,
    'sucursal_id' => $f_sucursal ?: null,
    'area_id' => $f_area ?: null,
]);
$stats = stats_estaciones($f_sucursal ?: null);

$sucursales = tiene_permiso('ver_todas_sucursales')
    ? db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo=1 ORDER BY nombre")
    : db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo=1 AND id = :sid", ['sid' => $u['sucursal_id']]);
$areas = db_all("SELECT id, nombre, color FROM areas WHERE activo=1 ORDER BY nombre");
$usuarios_lista = db_all("SELECT id, nombre_completo FROM usuarios WHERE activo=1 ORDER BY nombre_completo");

$titulo_pagina = 'Estaciones de trabajo';
$pagina_activa = 'estaciones';
require_once __DIR__ . '/config/header.php';
?>

<div class="animate-fade-in space-y-4">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900 flex items-center gap-2">
                <i data-lucide="layout-grid" class="w-6 h-6 text-bacal-700"></i>
                Estaciones de trabajo
            </h2>
            <p class="text-xs text-zinc-500 mt-0.5">Agrupa equipos por puesto, caja u oficina (ej. "Caja 1", "Puesto Beatriz").</p>
        </div>

        <?php if ($puede_gestionar): ?>
        <button onclick="document.getElementById('modal_nueva').showModal()"
                class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Nueva estación
        </button>
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

    <!-- KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold">Total estaciones</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= $stats['total'] ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold">Áreas con estaciones</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= $stats['num_areas'] ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-emerald-700 uppercase tracking-wider font-bold">Equipos asignados</div>
            <div class="font-display text-2xl font-extrabold text-emerald-700"><?= $stats['equipos_asignados'] ?></div>
        </div>
        <div class="bg-white rounded-xl border <?= $stats['equipos_sin_asignar'] > 0 ? 'border-amber-300 bg-amber-50' : 'border-zinc-200' ?> p-4">
            <div class="text-[10px] <?= $stats['equipos_sin_asignar'] > 0 ? 'text-amber-700' : 'text-zinc-500' ?> uppercase tracking-wider font-bold">Equipos sin asignar</div>
            <div class="font-display text-2xl font-extrabold <?= $stats['equipos_sin_asignar'] > 0 ? 'text-amber-700' : 'text-zinc-900' ?>"><?= $stats['equipos_sin_asignar'] ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-3">
        <form method="GET" class="flex flex-wrap gap-2 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400"></i>
                <input type="text" name="q" value="<?= e($f_busqueda) ?>"
                       placeholder="Buscar por código, nombre o descripción..."
                       class="w-full pl-9 pr-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <?php if (tiene_permiso('ver_todas_sucursales')): ?>
            <select name="sucursal_id" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm">
                <option value="0">Todas las sucursales</option>
                <?php foreach ($sucursales as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $f_sucursal === (int) $s['id'] ? 'selected' : '' ?>>
                    <?= e($s['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <select name="area_id" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm">
                <option value="0">Todas las áreas</option>
                <?php foreach ($areas as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $f_area === (int) $a['id'] ? 'selected' : '' ?>>
                    <?= e($a['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Filtrar</button>
        </form>
    </div>

    <!-- Listado -->
    <?php if (empty($estaciones)): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm px-6 py-16 text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-3">
            <i data-lucide="layout-grid" class="w-8 h-8 text-zinc-400"></i>
        </div>
        <p class="text-sm font-semibold text-zinc-700 mb-1">Sin estaciones registradas</p>
        <?php if ($puede_gestionar): ?>
        <p class="text-xs text-zinc-500 mb-4">Crea estaciones para agrupar equipos por puesto (ej. "Caja 1", "Puesto Beatriz").</p>
        <button onclick="document.getElementById('modal_nueva').showModal()"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
            <i data-lucide="plus" class="w-4 h-4"></i> Crear primera estación
        </button>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php foreach ($estaciones as $est): ?>
        <a href="<?= url('estacion_ver.php?id=' . $est['id']) ?>"
           class="bg-white rounded-xl border border-zinc-200 shadow-sm hover:shadow-md transition-shadow p-4 flex flex-col gap-3">

            <div class="flex items-start justify-between gap-2">
                <div class="flex-1 min-w-0">
                    <div class="text-[10px] font-mono font-bold text-zinc-500 uppercase tracking-wider"><?= e($est['codigo']) ?></div>
                    <div class="font-display text-base font-bold text-zinc-900 truncate"><?= e($est['nombre']) ?></div>
                </div>
                <?php if ((int) $est['incidencias_abiertas'] > 0): ?>
                <span class="inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded bg-bacal-50 text-bacal-700 uppercase">
                    <i data-lucide="alert-circle" class="w-3 h-3"></i>
                    <?= (int) $est['incidencias_abiertas'] ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="text-xs text-zinc-600 space-y-1">
                <?php if (!empty($est['area_nombre'])): ?>
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full" style="background-color: <?= e($est['area_color'] ?? '#71717A') ?>"></span>
                    <span><?= e($est['area_nombre']) ?></span>
                </div>
                <?php endif; ?>
                <div class="flex items-center gap-1.5">
                    <i data-lucide="building" class="w-3 h-3 text-zinc-400"></i>
                    <span><?= e($est['sucursal_codigo']) ?> · <?= e($est['sucursal_nombre']) ?></span>
                </div>
                <?php if (!empty($est['responsable_usuario'])): ?>
                <div class="flex items-center gap-1.5">
                    <i data-lucide="user" class="w-3 h-3 text-zinc-400"></i>
                    <span><?= e($est['responsable_usuario']) ?></span>
                </div>
                <?php elseif (!empty($est['responsable_nombre'])): ?>
                <div class="flex items-center gap-1.5">
                    <i data-lucide="user" class="w-3 h-3 text-zinc-400"></i>
                    <span><?= e($est['responsable_nombre']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($est['ubicacion'])): ?>
                <div class="flex items-center gap-1.5 text-zinc-500">
                    <i data-lucide="map-pin" class="w-3 h-3 text-zinc-400"></i>
                    <span class="truncate"><?= e($est['ubicacion']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex items-center gap-3 pt-2 mt-auto border-t border-zinc-100 text-xs">
                <div class="flex items-center gap-1 text-zinc-700">
                    <i data-lucide="monitor" class="w-3.5 h-3.5"></i>
                    <span class="font-bold"><?= (int) $est['num_equipos'] ?></span>
                    <span class="text-zinc-500">equipos</span>
                </div>
                <?php if ((int) $est['incidencias_30d'] > 0): ?>
                <div class="flex items-center gap-1 text-zinc-700">
                    <i data-lucide="activity" class="w-3.5 h-3.5"></i>
                    <span class="font-bold"><?= (int) $est['incidencias_30d'] ?></span>
                    <span class="text-zinc-500">en 30d</span>
                </div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($puede_gestionar): ?>
<!-- Modal Nueva Estación -->
<dialog id="modal_nueva" class="rounded-xl shadow-2xl backdrop:bg-black/50 w-full max-w-2xl p-0">
    <form method="POST" class="bg-white">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="crear">

        <div class="px-5 py-3 border-b border-zinc-200 flex items-center justify-between">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4 text-bacal-700"></i>
                Nueva estación de trabajo
            </h3>
            <button type="button" onclick="document.getElementById('modal_nueva').close()" class="p-1 rounded hover:bg-zinc-100 text-zinc-500">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="p-5 space-y-3 max-h-[70vh] overflow-y-auto">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Código *</label>
                    <input type="text" name="codigo" required maxlength="50"
                           placeholder="EST-CAJA-1"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm font-mono focus:outline-none focus:border-bacal-700">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nombre *</label>
                    <input type="text" name="nombre" required maxlength="150"
                           placeholder="Caja 1, Puesto Beatriz, Estación contabilidad 3"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Descripción</label>
                <textarea name="descripcion" rows="2"
                          placeholder="Para qué se usa esta estación..."
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Sucursal *</label>
                    <select name="sucursal_est" required class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (int) $u['sucursal_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Área</label>
                    <select name="area_est" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin área específica —</option>
                        <?php foreach ($areas as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= e($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Ubicación física</label>
                <input type="text" name="ubicacion" maxlength="255"
                       placeholder="ej. Planta baja, esquina norte"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Responsable (usuario)</label>
                    <select name="responsable_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Ninguno —</option>
                        <?php foreach ($usuarios_lista as $usr): ?>
                        <option value="<?= $usr['id'] ?>"><?= e($usr['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">o nombre libre</label>
                    <input type="text" name="responsable_nombre" maxlength="150"
                           placeholder="Si no es usuario del sistema (ej. Beatriz)"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Notas</label>
                <textarea name="notas" rows="2"
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></textarea>
            </div>
        </div>

        <div class="px-5 py-3 border-t border-zinc-200 flex justify-end gap-2 bg-zinc-50">
            <button type="button" onclick="document.getElementById('modal_nueva').close()"
                    class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                Crear estación
            </button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php require_once __DIR__ . '/config/footer.php'; ?>
