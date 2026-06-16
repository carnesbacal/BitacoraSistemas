<?php
/**
 * ============================================================================
 * mantenimiento_editar.php - Editar mantenimiento existente
 * ============================================================================
 * Solo permite editar mantenimientos en estados modificables.
 * Permite editar el conjunto de equipos cubiertos (uno o varios).
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/mantenimientos_helpers.php';
require_once __DIR__ . '/config/notificaciones_helpers.php';

requerir_login();

if (!puede_administrar_mantenimientos()) {
    flash_set('error', 'Sin permiso.');
    header('Location: ' . url('mantenimientos.php'));
    exit;
}

$u = usuario_actual();
$id = (int) input('id', 0);

$m = db_one("SELECT * FROM mantenimientos WHERE id = :id", ['id' => $id]);
if (!$m) {
    flash_set('error', 'Mantenimiento no encontrado.');
    header('Location: ' . url('mantenimientos.php'));
    exit;
}

if (!in_array($m['estado'], ['programado','proximo','vencido','en_progreso'], true)) {
    flash_set('error', 'Este mantenimiento ya no se puede editar (está ' . $m['estado'] . ').');
    header('Location: ' . url('mantenimiento_ver.php?id=' . $id));
    exit;
}

$errores = [];

// ----------------------------------------------------------------------------
// Procesar POST
// ----------------------------------------------------------------------------
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token inválido.';
    } else {
        // Equipos seleccionados (varios)
        $equipo_ids_raw = input('equipo_ids', []);
        if (!is_array($equipo_ids_raw)) $equipo_ids_raw = [$equipo_ids_raw];
        $equipo_ids = array_values(array_unique(array_filter(
            array_map('intval', $equipo_ids_raw),
            fn($v) => $v > 0
        )));

        $titulo       = trim((string) input('titulo', ''));
        $descripcion  = trim((string) input('descripcion', ''));
        $fecha        = (string) input('fecha_programada', '');
        $hora         = (string) input('hora_programada', '');
        $asignado_id  = (int) input('asignado_a_id', 0);
        $proveedor_id = (int) input('proveedor_id', 0);
        $es_recurrente = input('es_recurrente') ? 1 : 0;
        $recurrencia_tipo = (string) input('recurrencia_tipo', '');
        $recurrencia_valor = (int) input('recurrencia_valor', 0);

        // Validar que los equipos existan y estén activos
        if ($equipo_ids) {
            $ph = implode(',', array_fill(0, count($equipo_ids), '?'));
            $validos = db_all(
                "SELECT id FROM equipos WHERE id IN ($ph) AND activo = 1",
                $equipo_ids
            );
            $equipo_ids = array_map(fn($r) => (int) $r['id'], $validos);
        }

        if (empty($equipo_ids)) $errores[] = 'Selecciona al menos un equipo.';
        if ($titulo === '') $errores[] = 'El título es obligatorio.';
        if ($fecha === '') $errores[] = 'La fecha es obligatoria.';

        if ($es_recurrente) {
            if (!in_array($recurrencia_tipo, ['dias','semanas','meses','anios'], true)) $errores[] = 'Tipo de recurrencia inválido.';
            if ($recurrencia_valor < 1) $errores[] = 'Valor de recurrencia inválido.';
        }

        if (empty($errores)) {
            // Recalcular estado si la fecha cambia
            $dias_hasta = (strtotime($fecha) - strtotime(date('Y-m-d'))) / 86400;
            $nuevo_estado = $m['estado'];
            if ($m['estado'] !== 'en_progreso') {
                $nuevo_estado = $dias_hasta < 0 ? 'vencido' : ($dias_hasta <= 3 ? 'proximo' : 'programado');
            }

            $asignado_anterior = (int) $m['asignado_a_id'];

            try {
                db()->beginTransaction();

                db_exec(
                    "UPDATE mantenimientos SET
                        equipo_id = :eid, titulo = :tit, descripcion = :desc,
                        fecha_programada = :fp, hora_programada = :hp,
                        asignado_a_id = :aid, proveedor_id = :pid,
                        estado = :est,
                        es_recurrente = :rec, recurrencia_tipo = :rt, recurrencia_valor = :rv
                     WHERE id = :id",
                    [
                        'eid'  => $equipo_ids[0],
                        'tit'  => mb_substr($titulo, 0, 200),
                        'desc' => $descripcion ?: null,
                        'fp'   => $fecha, 'hp' => $hora ?: null,
                        'aid'  => $asignado_id ?: null,
                        'pid'  => $proveedor_id ?: null,
                        'est'  => $nuevo_estado,
                        'rec'  => $es_recurrente,
                        'rt'   => $es_recurrente ? $recurrencia_tipo : null,
                        'rv'   => $es_recurrente ? $recurrencia_valor : null,
                        'id'   => $id,
                    ]
                );

                // Sincronizar el conjunto de equipos (tabla puente)
                sincronizar_mantenimiento_equipos($id, $equipo_ids);

                db()->commit();
            } catch (Throwable $ex) {
                if (db()->inTransaction()) db()->rollBack();
                $errores[] = 'Error al guardar: ' . $ex->getMessage();
            }

            if (empty($errores)) {
                registrar_auditoria('editar_mantenimiento', 'mantenimientos', $id,
                    "Editó mantenimiento: $titulo" . (count($equipo_ids) > 1 ? " (" . count($equipo_ids) . " equipos)" : ''));

                // Si cambió el técnico asignado, notificar al nuevo
                if ($asignado_id > 0 && $asignado_id !== $asignado_anterior && $asignado_id !== (int) $u['id']) {
                    $n_eq = count($equipo_ids);
                    if ($n_eq === 1) {
                        $equipo_info = db_one("SELECT codigo_inventario FROM equipos WHERE id = :id", ['id' => $equipo_ids[0]]);
                        $detalle = "Equipo " . ($equipo_info['codigo_inventario'] ?? '') . " · " . date('d/m/Y', strtotime($fecha));
                    } else {
                        $detalle = "$n_eq equipos · " . date('d/m/Y', strtotime($fecha));
                    }
                    crear_notificacion(
                        $asignado_id,
                        'asignacion',
                        "Mantenimiento asignado: $titulo",
                        $detalle,
                        url('mantenimiento_ver.php?id=' . $id),
                        'mantenimientos',
                        $id
                    );
                }

                flash_set('success', 'Mantenimiento actualizado.');
                header('Location: ' . url('mantenimiento_ver.php?id=' . $id));
                exit;
            }
        }
    }
}

// ----------------------------------------------------------------------------
// Catálogos
// ----------------------------------------------------------------------------
$equipos_list = db_all(
    "SELECT e.id, e.codigo_inventario, e.nombre, e.tipo, e.sucursal_id, s.nombre sucursal_nombre,
            e.area_id, a.nombre area_nombre
     FROM equipos e
     INNER JOIN sucursales s ON e.sucursal_id = s.id
     LEFT JOIN areas a ON e.area_id = a.id
     WHERE e.activo = 1 ORDER BY s.nombre, e.codigo_inventario"
);

$tecnicos = db_all(
    "SELECT u.id, u.nombre_completo FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE u.activo = 1 AND r.puede_resolver = 1 ORDER BY u.nombre_completo"
);

$proveedores = db_all("SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre");

// ----------------------------------------------------------------------------
// Datos para el selector (JSON para Alpine)
// ----------------------------------------------------------------------------
$equipos_js = array_map(fn($e) => [
    'id'          => (int) $e['id'],
    'codigo'      => (string) $e['codigo_inventario'],
    'nombre'      => (string) $e['nombre'],
    'sucursal_id' => (int) $e['sucursal_id'],
    'sucursal'    => (string) $e['sucursal_nombre'],
    'area_id'     => (int) ($e['area_id'] ?? 0),
    'area'        => (string) ($e['area_nombre'] ?? ''),
    'tipo'        => (string) ($e['tipo'] ?? ''),
], $equipos_list);

$areas_map = [];
$tipos_map = [];
foreach ($equipos_list as $e) {
    $aid = (int) ($e['area_id'] ?? 0);
    if ($aid > 0 && !isset($areas_map[$aid])) {
        $areas_map[$aid] = ['id' => $aid, 'nombre' => (string) ($e['area_nombre'] ?? '')];
    }
    $tp = trim((string) ($e['tipo'] ?? ''));
    if ($tp !== '') $tipos_map[$tp] = ($tipos_map[$tp] ?? 0) + 1;
}
$areas_js = array_values($areas_map);
usort($areas_js, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
$tipos_js = [];
foreach ($tipos_map as $nombre => $count) $tipos_js[] = ['nombre' => $nombre, 'count' => $count];
usort($tipos_js, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

// Equipos actualmente seleccionados (o lo enviado si hubo error)
if (es_post()) {
    $pp = input('equipo_ids', []);
    if (!is_array($pp)) $pp = [$pp];
    $preseleccionados = array_values(array_filter(array_map('intval', $pp), fn($v) => $v > 0));
} else {
    $preseleccionados = mantenimiento_equipos_ids($id);
}

// json_encode + e() (htmlspecialchars) escapa las comillas como &quot; para el
// atributo x-data; el navegador las decodifica antes de que Alpine evalúe el JS.
// (No usar JSON_HEX_QUOT/APOS aquí: romperían la estructura como expresión JS.)
$_json_flags = JSON_HEX_TAG | JSON_UNESCAPED_UNICODE;

$titulo_pagina = 'Editar mantenimiento';
$pagina_activa = 'mantenimientos';
require_once __DIR__ . '/config/header.php';
?>

<div class="max-w-3xl mx-auto animate-fade-in">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= url('mantenimiento_ver.php?id=' . $id) ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">Editar mantenimiento</h2>
            <p class="text-xs text-zinc-500"><?= e($m['titulo']) ?></p>
        </div>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="mb-5 px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <ul class="list-disc list-inside text-xs">
            <?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5"
          x-data="{ esRecurrente: <?= (int) $m['es_recurrente'] === 1 ? 'true' : 'false' ?> }">
        <?= csrf_input() ?>

        <!-- Equipos -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4"
             x-data="equipoPicker(
                <?= e(json_encode($equipos_js, $_json_flags)) ?>,
                <?= e(json_encode($areas_js, $_json_flags)) ?>,
                <?= e(json_encode($tipos_js, $_json_flags)) ?>,
                <?= e(json_encode($preseleccionados, $_json_flags)) ?>
             )">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="monitor" class="w-4 h-4 text-bacal-700"></i> Equipos *
            </h3>
            <p class="text-[11px] text-zinc-500 -mt-2">
                Uno o varios. Agrega rápido por sucursal/zona o por tipo, o búscalos y márcalos.
            </p>

            <!-- Agregar rápido -->
            <div class="flex flex-wrap gap-2">
                <select @change="agregarPorArea($event.target.value); $event.target.value=''"
                        class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-xs focus:outline-none focus:border-bacal-700">
                    <option value="">+ Agregar por área…</option>
                    <template x-for="a in areas" :key="'a'+a.id">
                        <option :value="a.id" x-text="a.nombre + ' (' + countArea(a.id) + ')'"></option>
                    </template>
                </select>
                <select @change="agregarPorTipo($event.target.value); $event.target.value=''"
                        class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-xs focus:outline-none focus:border-bacal-700">
                    <option value="">+ Agregar por tipo…</option>
                    <template x-for="t in tipos" :key="'t'+t.nombre">
                        <option :value="t.nombre" x-text="t.nombre + ' (' + t.count + ')'"></option>
                    </template>
                </select>
                <button type="button" @click="seleccionados = []" x-show="seleccionados.length > 0"
                        class="px-3 py-2 rounded-lg border border-zinc-300 text-zinc-600 text-xs hover:bg-zinc-50">
                    Quitar todos
                </button>
            </div>

            <!-- Buscador -->
            <input type="text" x-model="busqueda" placeholder="Buscar por código, nombre o tipo…"
                   class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">

            <!-- Seleccionados (chips) -->
            <div x-show="seleccionados.length > 0" x-cloak class="flex flex-wrap gap-1.5">
                <template x-for="id in seleccionados" :key="'chip'+id">
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold bg-bacal-50 text-bacal-800 border border-bacal-200 px-2 py-0.5 rounded">
                        <span x-text="labelDe(id)"></span>
                        <button type="button" @click="quitar(id)" class="text-bacal-500 hover:text-bacal-800 leading-none">&times;</button>
                    </span>
                </template>
            </div>

            <!-- Lista con casillas -->
            <div class="max-h-64 overflow-y-auto border border-zinc-200 rounded-lg divide-y divide-zinc-100">
                <template x-for="eq in filtrados" :key="eq.id">
                    <label class="flex items-center gap-2 px-3 py-2 hover:bg-zinc-50 cursor-pointer">
                        <input type="checkbox" :value="eq.id" :checked="seleccionados.includes(eq.id)"
                               @change="toggle(eq.id)"
                               class="rounded text-bacal-700 focus:ring-bacal-500">
                        <span class="font-mono text-xs font-bold text-zinc-700" x-text="eq.codigo"></span>
                        <span class="text-sm text-zinc-800 truncate" x-text="eq.nombre"></span>
                        <span class="text-[10px] text-zinc-400 ml-auto whitespace-nowrap"
                              x-text="[eq.sucursal, eq.area, eq.tipo].filter(Boolean).join(' · ')"></span>
                    </label>
                </template>
                <div x-show="filtrados.length === 0" class="px-3 py-4 text-center text-xs text-zinc-400">
                    Sin resultados
                </div>
            </div>

            <!-- Inputs ocultos enviados al servidor -->
            <template x-for="id in seleccionados" :key="'h'+id">
                <input type="hidden" name="equipo_ids[]" :value="id">
            </template>

            <p class="text-[11px] text-zinc-500">
                <span class="font-bold text-zinc-700" x-text="seleccionados.length"></span> equipo(s) seleccionado(s)
            </p>
        </div>

        <!-- Información básica -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-bacal-700"></i> Información
            </h3>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Título *</label>
                <input type="text" name="titulo" required maxlength="200"
                       value="<?= e($m['titulo']) ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Descripción</label>
                <textarea name="descripcion" rows="3"
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"><?= e((string) $m['descripcion']) ?></textarea>
            </div>
        </div>

        <!-- Programación -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="calendar-clock" class="w-4 h-4 text-bacal-700"></i> Programación
            </h3>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Fecha *</label>
                    <input type="date" name="fecha_programada" required value="<?= e($m['fecha_programada']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Hora (opcional)</label>
                    <input type="time" name="hora_programada" value="<?= e((string) $m['hora_programada']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>

            <div class="border-t border-zinc-100 pt-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="es_recurrente" value="1" x-model="esRecurrente"
                           class="rounded text-bacal-700 focus:ring-bacal-500">
                    <span class="text-sm font-semibold text-zinc-700">Mantenimiento recurrente</span>
                </label>

                <div x-show="esRecurrente" x-cloak x-transition class="mt-3 grid grid-cols-2 gap-4 pl-6">
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Cada</label>
                        <input type="number" name="recurrencia_valor" min="1" max="365"
                               value="<?= e((string) ($m['recurrencia_valor'] ?: 3)) ?>"
                               class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Unidad</label>
                        <select name="recurrencia_tipo" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                            <?php $rt = $m['recurrencia_tipo'] ?: 'meses'; ?>
                            <option value="dias" <?= $rt === 'dias' ? 'selected' : '' ?>>Días</option>
                            <option value="semanas" <?= $rt === 'semanas' ? 'selected' : '' ?>>Semanas</option>
                            <option value="meses" <?= $rt === 'meses' ? 'selected' : '' ?>>Meses</option>
                            <option value="anios" <?= $rt === 'anios' ? 'selected' : '' ?>>Años</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Asignación -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="users" class="w-4 h-4 text-bacal-700"></i> Quién lo hace
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Técnico</label>
                    <select name="asignado_a_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($tecnicos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= (int) $m['asignado_a_id'] === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['nombre_completo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Proveedor</label>
                    <select name="proveedor_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Ninguno —</option>
                        <?php foreach ($proveedores as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (int) $m['proveedor_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="<?= url('mantenimiento_ver.php?id=' . $id) ?>" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</a>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Guardar cambios</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('equipoPicker', (equipos, areas, tipos, preseleccionados, modoInicial) => ({
        equipos: equipos,
        areas: areas,
        tipos: tipos,
        seleccionados: (preseleccionados || []).map(Number),
        busqueda: '',
        modo: modoInicial || 'agrupado',

        get filtrados() {
            const q = this.busqueda.trim().toLowerCase();
            if (!q) return this.equipos;
            return this.equipos.filter(e =>
                (e.codigo + ' ' + e.nombre + ' ' + (e.tipo || '')).toLowerCase().includes(q)
            );
        },
        toggle(id) {
            id = Number(id);
            const i = this.seleccionados.indexOf(id);
            if (i >= 0) this.seleccionados.splice(i, 1);
            else this.seleccionados.push(id);
        },
        quitar(id) {
            const i = this.seleccionados.indexOf(Number(id));
            if (i >= 0) this.seleccionados.splice(i, 1);
        },
        agregarPorArea(aid) {
            if (!aid) return;
            aid = Number(aid);
            this.equipos.filter(e => e.area_id === aid).forEach(e => {
                if (!this.seleccionados.includes(e.id)) this.seleccionados.push(e.id);
            });
        },
        agregarPorTipo(tp) {
            if (!tp) return;
            this.equipos.filter(e => e.tipo === tp).forEach(e => {
                if (!this.seleccionados.includes(e.id)) this.seleccionados.push(e.id);
            });
        },
        countArea(aid) {
            aid = Number(aid);
            return this.equipos.filter(e => e.area_id === aid).length;
        },
        labelDe(id) {
            const e = this.equipos.find(x => x.id === Number(id));
            return e ? e.codigo : id;
        },
    }));
});
</script>

<?php require_once __DIR__ . '/config/footer.php'; ?>
