<?php
/**
 * ============================================================================
 * proyectos.php - Listado de proyectos
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/proyectos_helpers.php';

requerir_login();
$u = usuario_actual();
$es_admin = tiene_permiso('administrar');
$puede_crear = $es_admin || tiene_permiso('resolver') || tiene_permiso('crear_solicitud');

// Filtros
$f_busqueda = trim((string) input('q', ''));
$f_estado = trim((string) input('estado', ''));
$f_prioridad = trim((string) input('prioridad', ''));
$f_tipo = trim((string) input('tipo', ''));
$f_lider = (int) input('lider_id', 0);
$f_mios = (int) input('mios', 0) === 1;

$errores = [];

// Procesar POST (crear proyecto)
if (es_post() && $puede_crear) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token inválido.';
    } else {
        $op = (string) input('op', '');
        if ($op === 'crear') {
            $datos = [
                'codigo' => trim((string) input('codigo', '')),
                'nombre' => trim((string) input('nombre', '')),
                'descripcion' => trim((string) input('descripcion', '')) ?: null,
                'tipo' => trim((string) input('tipo', 'Otro')) ?: 'Otro',
                'estado' => 'propuesto',
                'prioridad' => (string) input('prioridad', 'media'),
                'sucursal_id' => (int) input('sucursal_id', 0) ?: null,
                'area_id' => (int) input('area_id', 0) ?: null,
                'lider_id' => (int) input('lider_id_form', 0) ?: null,
                'fecha_inicio_plan' => trim((string) input('fecha_inicio_plan', '')) ?: null,
                'fecha_fin_plan' => trim((string) input('fecha_fin_plan', '')) ?: null,
                'presupuesto' => trim((string) input('presupuesto', '')) ?: null,
                'cliente_interno' => trim((string) input('cliente_interno', '')) ?: null,
                'tecnologias' => trim((string) input('tecnologias', '')) ?: null,
                'notas' => trim((string) input('notas', '')) ?: null,
            ];

            if ($datos['codigo'] === '') $errores[] = 'El código es obligatorio.';
            if ($datos['nombre'] === '') $errores[] = 'El nombre es obligatorio.';

            if (empty($errores)) {
                try {
                    $id = crear_proyecto($datos, (int) $u['id']);
                    registrar_auditoria('crear_proyecto', 'proyectos', $id, $datos['nombre']);
                    flash_set('success', "Proyecto '{$datos['nombre']}' creado como propuesto.");
                    header('Location: ' . url('proyecto_ver.php?id=' . $id));
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

$proyectos = listar_proyectos([
    'busqueda' => $f_busqueda ?: null,
    'estado' => $f_estado ?: null,
    'prioridad' => $f_prioridad ?: null,
    'tipo' => $f_tipo ?: null,
    'lider_id' => $f_lider ?: null,
    'participante_id' => $f_mios ? $u['id'] : null,
]);
$stats = stats_proyectos();
$tipos_usados = tipos_proyecto_usados();

// Tipos para datalist: combinar sugeridos + usados, sin duplicados
$tipos_lista = array_values(array_unique(array_merge(PROYECTO_TIPOS_SUGERIDOS, $tipos_usados)));
sort($tipos_lista);

$sucursales = db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo=1 ORDER BY nombre");
$areas = db_all("SELECT id, nombre, color FROM areas WHERE activo=1 ORDER BY nombre");
$usuarios_lista = db_all("SELECT id, nombre_completo, usuario FROM usuarios WHERE activo=1 ORDER BY nombre_completo");

$titulo_pagina = 'Proyectos';
$pagina_activa = 'proyectos';
require_once __DIR__ . '/config/header.php';
?>

<div class="animate-fade-in space-y-4">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900 flex items-center gap-2">
                <i data-lucide="folder-kanban" class="w-6 h-6 text-bacal-700"></i>
                Proyectos
            </h2>
            <p class="text-xs text-zinc-500 mt-0.5">Desarrollos, migraciones, implementaciones y otros proyectos del área.</p>
        </div>

        <?php if ($puede_crear): ?>
        <button onclick="document.getElementById('modal_nuevo').showModal()"
                class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Nuevo proyecto
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

    <!-- KPIs clickeables -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        <?php
        $kpis = [
            ['key' => '', 'label' => 'Total', 'value' => $stats['total'], 'color' => '#52525B', 'icon' => 'folder'],
            ['key' => 'en_curso', 'label' => 'Activos', 'value' => $stats['activos'], 'color' => '#7C3AED', 'icon' => 'play-circle'],
            ['key' => 'propuesto', 'label' => 'Propuestos', 'value' => $stats['propuestos'], 'color' => '#71717A', 'icon' => 'lightbulb'],
            ['key' => 'pausado', 'label' => 'Pausados', 'value' => $stats['pausados'], 'color' => '#F59E0B', 'icon' => 'pause-circle'],
            ['key' => 'completado', 'label' => 'Completados', 'value' => $stats['completados'], 'color' => '#16A34A', 'icon' => 'check-circle-2'],
        ];
        foreach ($kpis as $k):
            $url_filtro = url('proyectos.php' . ($k['key'] ? '?estado=' . urlencode($k['key']) : ''));
            $activo = ($f_estado === $k['key'] && !$f_mios);
        ?>
        <a href="<?= e($url_filtro) ?>"
           class="bg-white rounded-xl border <?= $activo ? 'border-bacal-300 bg-bacal-50' : 'border-zinc-200' ?> p-3 hover:shadow-sm transition-shadow">
            <div class="text-[10px] uppercase tracking-wider font-bold" style="color: <?= e($k['color']) ?>"><?= e($k['label']) ?></div>
            <div class="font-display text-2xl font-extrabold" style="color: <?= e($k['color']) ?>"><?= (int) $k['value'] ?></div>
        </a>
        <?php endforeach; ?>

        <a href="<?= url('proyectos.php?mios=1') ?>"
           class="bg-white rounded-xl border <?= $f_mios ? 'border-bacal-300 bg-bacal-50' : 'border-zinc-200' ?> p-3 hover:shadow-sm transition-shadow">
            <div class="text-[10px] uppercase tracking-wider font-bold text-blue-700">Mis proyectos</div>
            <div class="font-display text-2xl font-extrabold text-blue-700">
                <?= count(listar_proyectos(['participante_id' => $u['id']])) ?>
            </div>
        </a>
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

            <select name="estado" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm">
                <option value="">Todos los estados</option>
                <?php foreach (PROYECTO_ESTADOS as $key => $cfg): ?>
                <option value="<?= e($key) ?>" <?= $f_estado === $key ? 'selected' : '' ?>><?= e($cfg['label']) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="prioridad" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm">
                <option value="">Todas prioridades</option>
                <?php foreach (PROYECTO_PRIORIDADES as $key => $cfg): ?>
                <option value="<?= e($key) ?>" <?= $f_prioridad === $key ? 'selected' : '' ?>><?= e($cfg['label']) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="tipo" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm">
                <option value="">Todos los tipos</option>
                <?php foreach ($tipos_lista as $tp): ?>
                <option value="<?= e($tp) ?>" <?= $f_tipo === $tp ? 'selected' : '' ?>><?= e($tp) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="lider_id" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm">
                <option value="0">Todos los líderes</option>
                <?php foreach ($usuarios_lista as $usr): ?>
                <option value="<?= $usr['id'] ?>" <?= $f_lider === (int) $usr['id'] ? 'selected' : '' ?>><?= e($usr['nombre_completo']) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Filtrar</button>

            <?php if ($f_busqueda || $f_estado || $f_prioridad || $f_tipo || $f_lider || $f_mios): ?>
            <a href="<?= url('proyectos.php') ?>" class="px-3 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm hover:bg-zinc-50">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Listado -->
    <?php if (empty($proyectos)): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm px-6 py-16 text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-3">
            <i data-lucide="folder-kanban" class="w-8 h-8 text-zinc-400"></i>
        </div>
        <p class="text-sm font-semibold text-zinc-700 mb-1">Sin proyectos registrados</p>
        <?php if ($puede_crear): ?>
        <p class="text-xs text-zinc-500 mb-4">Crea proyectos para llevar bitácora de desarrollos, migraciones e implementaciones.</p>
        <button onclick="document.getElementById('modal_nuevo').showModal()"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
            <i data-lucide="plus" class="w-4 h-4"></i> Crear primer proyecto
        </button>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php foreach ($proyectos as $p):
            $est = etiqueta_estado_proyecto($p['estado']);
            $pri = etiqueta_prioridad_proyecto($p['prioridad']);
            $avance = (int) $p['avance'];
            $tareas_total = (int) $p['num_tareas'];
            $tareas_ok = (int) $p['tareas_completadas'];
        ?>
        <a href="<?= url('proyecto_ver.php?id=' . $p['id']) ?>"
           class="bg-white rounded-xl border border-zinc-200 shadow-sm hover:shadow-md transition-shadow p-4 flex flex-col gap-3">

            <div class="flex items-start justify-between gap-2">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-[10px] font-mono font-bold text-zinc-500 uppercase tracking-wider"><?= e($p['codigo']) ?></span>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase"
                              style="color: <?= e($est['color']) ?>; background-color: <?= e($est['color']) ?>15">
                            <?= e($est['label']) ?>
                        </span>
                    </div>
                    <div class="font-display text-base font-bold text-zinc-900 line-clamp-2"><?= e($p['nombre']) ?></div>
                </div>
                <span class="inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded uppercase whitespace-nowrap"
                      style="color: <?= e($pri['color']) ?>; background-color: <?= e($pri['color']) ?>15">
                    <?= e($pri['label']) ?>
                </span>
            </div>

            <?php if (!empty($p['descripcion'])): ?>
            <div class="text-xs text-zinc-600 line-clamp-2"><?= e($p['descripcion']) ?></div>
            <?php endif; ?>

            <div class="text-xs text-zinc-600 space-y-1">
                <?php if (!empty($p['tipo'])): ?>
                <div class="flex items-center gap-1.5">
                    <i data-lucide="tag" class="w-3 h-3 text-zinc-400"></i>
                    <span><?= e($p['tipo']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['lider_nombre'])): ?>
                <div class="flex items-center gap-1.5">
                    <i data-lucide="user" class="w-3 h-3 text-zinc-400"></i>
                    <span>Líder: <strong><?= e($p['lider_nombre']) ?></strong></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['sucursal_codigo'])): ?>
                <div class="flex items-center gap-1.5">
                    <i data-lucide="building" class="w-3 h-3 text-zinc-400"></i>
                    <span><?= e($p['sucursal_codigo']) ?> · <?= e($p['sucursal_nombre']) ?></span>
                </div>
                <?php else: ?>
                <div class="flex items-center gap-1.5 text-zinc-500">
                    <i data-lucide="globe" class="w-3 h-3 text-zinc-400"></i>
                    <span class="italic">Todas las sucursales</span>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['fecha_fin_plan'])):
                    $dias_para_fin = (strtotime($p['fecha_fin_plan']) - time()) / 86400;
                    $color_fecha = $dias_para_fin < 0 ? 'text-bacal-700 font-bold' : ($dias_para_fin < 14 ? 'text-amber-700 font-semibold' : 'text-zinc-600');
                ?>
                <div class="flex items-center gap-1.5 <?= $color_fecha ?>">
                    <i data-lucide="calendar" class="w-3 h-3"></i>
                    <span>Fin: <?= e(date('d/M/Y', strtotime($p['fecha_fin_plan']))) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Barra de avance -->
            <?php if ($avance > 0 || in_array($p['estado'], ['en_curso','pausado','completado'], true)): ?>
            <div>
                <div class="flex items-center justify-between text-[10px] mb-1">
                    <span class="text-zinc-500 font-bold uppercase tracking-wide">Avance</span>
                    <span class="font-bold text-zinc-900"><?= $avance ?>%</span>
                </div>
                <div class="w-full bg-zinc-100 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full rounded-full" style="width: <?= $avance ?>%; background-color: <?= e($est['color']) ?>"></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats inferior -->
            <div class="flex items-center gap-3 pt-2 mt-auto border-t border-zinc-100 text-xs text-zinc-600">
                <?php if ($tareas_total > 0): ?>
                <div class="flex items-center gap-1">
                    <i data-lucide="list-checks" class="w-3.5 h-3.5"></i>
                    <span class="font-bold"><?= $tareas_ok ?>/<?= $tareas_total ?></span>
                </div>
                <?php endif; ?>
                <?php if ((int) $p['num_participantes'] > 0): ?>
                <div class="flex items-center gap-1">
                    <i data-lucide="users" class="w-3.5 h-3.5"></i>
                    <span class="font-bold"><?= (int) $p['num_participantes'] ?></span>
                </div>
                <?php endif; ?>
                <?php if ((int) $p['num_comentarios'] > 0): ?>
                <div class="flex items-center gap-1">
                    <i data-lucide="message-square" class="w-3.5 h-3.5"></i>
                    <span class="font-bold"><?= (int) $p['num_comentarios'] ?></span>
                </div>
                <?php endif; ?>
                <?php if ((int) $p['num_adjuntos'] > 0): ?>
                <div class="flex items-center gap-1">
                    <i data-lucide="paperclip" class="w-3.5 h-3.5"></i>
                    <span class="font-bold"><?= (int) $p['num_adjuntos'] ?></span>
                </div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($puede_crear): ?>
<!-- Modal Nuevo Proyecto -->
<dialog id="modal_nuevo" class="rounded-xl shadow-2xl backdrop:bg-black/50 w-full max-w-3xl p-0">
    <form method="POST" class="bg-white">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="crear">

        <div class="px-5 py-3 border-b border-zinc-200 flex items-center justify-between">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-4 h-4 text-bacal-700"></i>
                Nuevo proyecto
            </h3>
            <button type="button" onclick="document.getElementById('modal_nuevo').close()" class="p-1 rounded hover:bg-zinc-100 text-zinc-500">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="p-5 space-y-3 max-h-[75vh] overflow-y-auto">

            <div class="bg-blue-50 px-3 py-2 rounded text-xs text-blue-900">
                💡 El proyecto se creará como <strong>Propuesto</strong>. Se podrá aprobar y mover a "En curso" después.
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Código *</label>
                    <input type="text" name="codigo" required maxlength="50"
                           placeholder="PROY-001"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm font-mono focus:outline-none focus:border-bacal-700">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Nombre *</label>
                    <input type="text" name="nombre" required maxlength="200"
                           placeholder="Ej. Migración servidor de correo"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Descripción / Objetivo</label>
                <textarea name="descripcion" rows="3"
                          placeholder="¿Qué se va a hacer? ¿Por qué? ¿Cuál es el objetivo?"
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Tipo</label>
                    <input type="text" name="tipo" list="tipos_proyecto" maxlength="80"
                           value="Otro"
                           placeholder="Escribe o selecciona"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                    <datalist id="tipos_proyecto">
                        <?php foreach ($tipos_lista as $tp): ?>
                        <option value="<?= e($tp) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Prioridad</label>
                    <select name="prioridad" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <?php foreach (PROYECTO_PRIORIDADES as $key => $cfg): ?>
                        <option value="<?= e($key) ?>" <?= $key === 'media' ? 'selected' : '' ?>><?= e($cfg['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Líder del proyecto</label>
                    <select name="lider_id_form" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Asignar después —</option>
                        <?php foreach ($usuarios_lista as $usr): ?>
                        <option value="<?= $usr['id'] ?>" <?= (int) $u['id'] === (int) $usr['id'] ? 'selected' : '' ?>><?= e($usr['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Sucursal</label>
                    <select name="sucursal_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Todas las sucursales —</option>
                        <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= e($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Área</label>
                    <select name="area_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin área específica —</option>
                        <?php foreach ($areas as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= e($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Inicio planeado</label>
                    <input type="date" name="fecha_inicio_plan"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Fin planeado</label>
                    <input type="date" name="fecha_fin_plan"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Presupuesto ($)</label>
                    <input type="number" name="presupuesto" min="0" step="0.01"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Cliente interno / solicitante</label>
                    <input type="text" name="cliente_interno" maxlength="150"
                           placeholder="Ej. Dirección general, RH, etc."
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Tecnologías / stack</label>
                    <input type="text" name="tecnologias" maxlength="255"
                           placeholder="Ej. PHP, MySQL, React, Windows Server"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Notas iniciales</label>
                <textarea name="notas" rows="2"
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></textarea>
            </div>
        </div>

        <div class="px-5 py-3 border-t border-zinc-200 flex justify-end gap-2 bg-zinc-50">
            <button type="button" onclick="document.getElementById('modal_nuevo').close()"
                    class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                Crear proyecto
            </button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php require_once __DIR__ . '/config/footer.php'; ?>
