<?php
/**
 * ============================================================================
 * mapa_sucursal.php - Mapa multi-planta con drag & drop de equipos
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/mapa_helpers.php';

requerir_login();
$u = usuario_actual();
$es_admin = tiene_permiso('administrar');

// ----------------------------------------------------------------------------
// Procesar POST (acciones de admin sobre plantas)
// ----------------------------------------------------------------------------
if (es_post() && $es_admin) {
    if (!csrf_valido(input('_csrf'))) {
        flash_set('error', 'Token inválido.');
    } else {
        $op = (string) input('op', '');
        $sucursal_id_post = (int) input('sucursal_id_post', 0);

        try {
            if ($op === 'crear_planta') {
                $nombre = trim((string) input('nombre_planta', ''));
                if ($sucursal_id_post && $nombre !== '') {
                    $pid = crear_planta($sucursal_id_post, $nombre);
                    registrar_auditoria('crear_planta', 'sucursal_plantas', $pid, "Planta: $nombre");
                    flash_set('success', "Planta '$nombre' creada.");
                    header('Location: ' . url("mapa_sucursal.php?sucursal_id=$sucursal_id_post&planta_id=$pid"));
                    exit;
                }
            } elseif ($op === 'renombrar_planta') {
                $planta_id_op = (int) input('planta_id_op', 0);
                $nombre_nuevo = trim((string) input('nombre_planta', ''));
                if ($planta_id_op && $nombre_nuevo !== '') {
                    renombrar_planta($planta_id_op, $nombre_nuevo);
                    flash_set('success', 'Planta renombrada.');
                }
            } elseif ($op === 'eliminar_planta') {
                $planta_id_op = (int) input('planta_id_op', 0);
                if ($planta_id_op) {
                    eliminar_planta($planta_id_op);
                    registrar_auditoria('eliminar_planta', 'sucursal_plantas', $planta_id_op);
                    flash_set('success', 'Planta eliminada. Sus equipos volvieron a "Sin ubicar".');
                }
            }
        } catch (Throwable $e) {
            flash_set('error', 'Error: ' . $e->getMessage());
        }

        header('Location: ' . url("mapa_sucursal.php?sucursal_id=$sucursal_id_post"));
        exit;
    }
}

// ----------------------------------------------------------------------------
// Sucursales disponibles
// ----------------------------------------------------------------------------
$puede_ver_todas = tiene_permiso('ver_todas_sucursales');
$sucursales = $puede_ver_todas
    ? db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo=1 ORDER BY nombre")
    : db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo=1 AND id = :sid",
        ['sid' => $u['sucursal_id']]);

if (empty($sucursales)) {
    flash_set('error', 'No tienes acceso a ninguna sucursal.');
    header('Location: ' . url('dashboard.php'));
    exit;
}

// Sucursal y planta seleccionadas
$sucursal_id = (int) input('sucursal_id', $sucursales[0]['id']);
$sucursal = null;
foreach ($sucursales as $s) {
    if ((int) $s['id'] === $sucursal_id) { $sucursal = $s; break; }
}
if (!$sucursal) $sucursal = $sucursales[0];
$sucursal_id = (int) $sucursal['id'];

// Plantas de esta sucursal
$plantas = listar_plantas_de_sucursal($sucursal_id);

// Planta seleccionada
$planta_id = (int) input('planta_id', 0);
$planta = null;
if ($planta_id) {
    foreach ($plantas as $p) if ((int) $p['id'] === $planta_id) { $planta = $p; break; }
}
if (!$planta && !empty($plantas)) $planta = $plantas[0];

// Equipos y estaciones
$equipos_en_mapa = $planta ? equipos_en_planta((int) $planta['id']) : [];
$equipos_sin_ubicar = equipos_sin_planta_en_sucursal($sucursal_id);
$estaciones_en_mapa = $planta ? estaciones_en_planta((int) $planta['id']) : [];
$estaciones_sin_ubicar = estaciones_sin_planta_en_sucursal($sucursal_id);

// IDs de estaciones ya posicionadas en ESTA planta
$ids_estaciones_posicionadas = array_column($estaciones_en_mapa, 'id');

// FILTRO IMPORTANTE: si un equipo pertenece a una estación que ya está posicionada,
// NO mostrarlo individualmente en el mapa (se representará por el pin de su estación).
// Sí lo mostramos si su estación está sin ubicar o si no tiene estación.
$equipos_en_mapa = array_values(array_filter($equipos_en_mapa, function($eq) use ($ids_estaciones_posicionadas) {
    return empty($eq['estacion_id']) || !in_array((int) $eq['estacion_id'], $ids_estaciones_posicionadas, true);
}));

// Equipos sin ubicar: ocultar los que ya pertenecen a una estación posicionada
$equipos_sin_ubicar = array_values(array_filter($equipos_sin_ubicar, function($eq) use ($ids_estaciones_posicionadas) {
    return empty($eq['estacion_id']) || !in_array((int) $eq['estacion_id'], $ids_estaciones_posicionadas, true);
}));

$titulo_pagina = 'Mapa de sucursal';
$pagina_activa = 'mapa';
require_once __DIR__ . '/config/header.php';
?>

<div class="animate-fade-in space-y-4" x-data="mapaSucursal()">

    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">Mapa de sucursal</h2>
            <p class="text-xs text-zinc-500 mt-0.5">Ubicación física de los equipos por planta.</p>
        </div>

        <div class="flex items-center gap-2">
            <?php if (count($sucursales) > 1): ?>
            <form method="GET" class="inline-block">
                <select name="sucursal_id" onchange="this.form.submit()"
                        class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-semibold focus:outline-none focus:border-bacal-700">
                    <?php foreach ($sucursales as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $sucursal_id ? 'selected' : '' ?>>
                        <?= e($s['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>

            <?php if ($es_admin && $planta): ?>
            <button @click="modoEdicion = !modoEdicion"
                    :class="modoEdicion ? 'bg-amber-100 border-amber-300 text-amber-800' : 'bg-white border-zinc-300 text-zinc-700'"
                    class="px-3 py-2 rounded-lg border text-sm font-semibold flex items-center gap-1.5 hover:border-zinc-400">
                <i data-lucide="move" class="w-4 h-4"></i>
                <span x-text="modoEdicion ? 'Saliendo del modo edición' : 'Editar posiciones'"></span>
            </button>

            <label class="cursor-pointer px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-semibold text-zinc-700 hover:bg-zinc-50 flex items-center gap-1.5">
                <i data-lucide="upload" class="w-4 h-4"></i>
                <?= !empty($planta['plano_url']) ? 'Cambiar plano' : 'Subir plano' ?>
                <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden"
                       @change="subirPlano($event)">
            </label>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pestañas de plantas -->
    <?php if (!empty($plantas) || $es_admin): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <div class="flex items-center overflow-x-auto border-b border-zinc-100">
            <?php foreach ($plantas as $p):
                $activa = $planta && (int) $p['id'] === (int) $planta['id'];
            ?>
            <a href="<?= url("mapa_sucursal.php?sucursal_id=$sucursal_id&planta_id={$p['id']}") ?>"
               class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px whitespace-nowrap flex items-center gap-2 transition-colors
                      <?= $activa ? 'border-bacal-700 text-bacal-700 bg-bacal-50/40' : 'border-transparent text-zinc-600 hover:text-zinc-900 hover:bg-zinc-50' ?>">
                <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                <?= e($p['nombre']) ?>
                <?php if (!empty($p['plano_url'])): ?>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" title="Tiene plano"></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>

            <?php if ($es_admin): ?>
            <button @click="modalCrear = true"
                    class="px-3 py-2.5 text-sm font-semibold text-zinc-500 hover:text-bacal-700 whitespace-nowrap flex items-center gap-1">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Nueva planta
            </button>
            <?php endif; ?>
        </div>

        <?php if ($es_admin && $planta): ?>
        <div class="px-4 py-2 bg-zinc-50 flex items-center justify-between text-xs">
            <span class="text-zinc-500">
                Planta actual: <strong class="text-zinc-900"><?= e($planta['nombre']) ?></strong>
            </span>
            <div class="flex gap-2">
                <button @click="modalRenombrar = true; nombreRenombrar = '<?= e($planta['nombre']) ?>'"
                        class="text-zinc-600 hover:text-bacal-700 font-semibold flex items-center gap-1">
                    <i data-lucide="edit-3" class="w-3 h-3"></i> Renombrar
                </button>
                <button @click="confirmarEliminarPlanta()"
                        class="text-zinc-600 hover:text-bacal-700 font-semibold flex items-center gap-1">
                    <i data-lucide="trash-2" class="w-3 h-3"></i> Eliminar planta
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Mensaje si no hay plantas -->
    <?php if (empty($plantas)): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-12 text-center">
        <div class="w-20 h-20 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-4">
            <i data-lucide="layers" class="w-10 h-10 text-zinc-400"></i>
        </div>
        <p class="font-semibold text-zinc-700 mb-1">Esta sucursal aún no tiene plantas</p>
        <?php if ($es_admin): ?>
        <p class="text-xs text-zinc-500 mb-4">Crea la primera (ej. "Planta baja", "Piso 1", "Bodega").</p>
        <button @click="modalCrear = true"
                class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold inline-flex items-center gap-1.5">
            <i data-lucide="plus" class="w-4 h-4"></i> Crear primera planta
        </button>
        <?php else: ?>
        <p class="text-xs text-zinc-500">Pide al administrador que configure las plantas.</p>
        <?php endif; ?>
    </div>
    <?php else: ?>

    <!-- Leyenda -->
    <div class="flex items-center gap-3 text-[11px] text-zinc-600 flex-wrap">
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-emerald-600"></span> En uso</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-amber-500"></span> En mantenimiento</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-bacal-700"></span> Con incidencia abierta</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-zinc-500"></span> Baja</span>
        <?php if ($es_admin): ?>
        <span class="ml-auto text-[10px] text-zinc-400">
            💡 Activa "Editar posiciones" para arrastrar equipos
        </span>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        <!-- Mapa principal -->
        <div class="lg:col-span-3 bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
            <?php if (empty($planta['plano_url'])): ?>
            <div class="aspect-video flex flex-col items-center justify-center text-center p-8 bg-zinc-50">
                <div class="w-20 h-20 rounded-full bg-zinc-200 flex items-center justify-center mb-4">
                    <i data-lucide="image" class="w-10 h-10 text-zinc-400"></i>
                </div>
                <p class="font-semibold text-zinc-700 mb-1">
                    "<?= e($planta['nombre']) ?>" aún no tiene plano
                </p>
                <?php if ($es_admin): ?>
                <p class="text-xs text-zinc-500 mb-4">Sube una imagen del plano de esta planta.</p>
                <label class="cursor-pointer px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
                    <i data-lucide="upload" class="w-4 h-4"></i>
                    Subir imagen del plano
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden"
                           @change="subirPlano($event)">
                </label>
                <?php else: ?>
                <p class="text-xs text-zinc-500">Pide al administrador que suba uno.</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="relative overflow-hidden" x-ref="contenedorMapa"
                 :class="modoEdicion ? 'cursor-crosshair' : ''">
                <img :src="planoUrl" alt="Plano" class="w-full h-auto block select-none" draggable="false">

                <?php foreach ($equipos_en_mapa as $eq):
                    $color = color_pin_equipo($eq['estado_vida'], (int) $eq['incidencias_abiertas']);
                ?>
                <div class="absolute group"
                     data-equipo-id="<?= (int) $eq['id'] ?>"
                     data-pos-x="<?= e((string) $eq['pos_x']) ?>"
                     data-pos-y="<?= e((string) $eq['pos_y']) ?>"
                     style="left: <?= e((string) $eq['pos_x']) ?>%; top: <?= e((string) $eq['pos_y']) ?>%; transform: translate(-50%, -50%);"
                     :class="modoEdicion ? 'cursor-move' : 'cursor-pointer'"
                     @mousedown="iniciarArrastre($event, <?= (int) $eq['id'] ?>)"
                     @click.stop="abrirEquipo($event, <?= (int) $eq['id'] ?>)">
                    <div class="w-5 h-5 rounded-full border-2 border-white shadow-lg flex items-center justify-center text-white text-[8px] font-bold transition-transform hover:scale-125"
                         style="background-color: <?= e($color) ?>"
                         title="<?= e($eq['codigo_inventario']) ?> · <?= e($eq['nombre']) ?>">
                        <?php if ((int) $eq['incidencias_abiertas'] > 0): ?>
                        <i data-lucide="alert-circle" class="w-3 h-3"></i>
                        <?php endif; ?>
                    </div>

                    <!-- Botón remover del mapa (solo modo edición) -->
                    <button type="button"
                            x-show="modoEdicion && esAdmin"
                            x-cloak
                            @click.stop="removerEquipoDelMapa(<?= (int) $eq['id'] ?>, '<?= e(addslashes($eq['nombre'])) ?>')"
                            class="absolute -top-2 -left-2 w-4 h-4 rounded-full bg-zinc-900 hover:bg-bacal-700 text-white flex items-center justify-center shadow-md z-30"
                            title="Quitar del mapa">
                        <i data-lucide="x" class="w-3 h-3"></i>
                    </button>

                    <div class="absolute z-20 left-1/2 -translate-x-1/2 -top-2 -translate-y-full
                                bg-zinc-900 text-white text-xs rounded-lg px-2.5 py-1.5 whitespace-nowrap
                                opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none shadow-lg">
                        <div class="font-mono font-bold text-[10px] opacity-80"><?= e($eq['codigo_inventario']) ?></div>
                        <div class="font-semibold"><?= e($eq['nombre']) ?></div>
                        <?php if ((int) $eq['incidencias_abiertas'] > 0): ?>
                        <div class="text-[10px] text-bacal-300 mt-0.5">
                            ⚠ <?= (int) $eq['incidencias_abiertas'] ?> incidencia(s) abierta(s)
                        </div>
                        <?php endif; ?>
                        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-zinc-900"></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php
                // Pins de ESTACIONES en el mapa
                foreach ($estaciones_en_mapa as $est):
                    $inc_abiertas = (int) $est['incidencias_abiertas'];
                    $color_est = $inc_abiertas > 0 ? '#C8102E' : '#7C3AED'; // morado o rojo si tiene incidencias
                ?>
                <div class="absolute group z-10"
                     data-estacion-id="<?= (int) $est['id'] ?>"
                     data-pos-x="<?= e((string) $est['pos_x']) ?>"
                     data-pos-y="<?= e((string) $est['pos_y']) ?>"
                     style="left: <?= e((string) $est['pos_x']) ?>%; top: <?= e((string) $est['pos_y']) ?>%; transform: translate(-50%, -50%);"
                     :class="modoEdicion ? 'cursor-move' : 'cursor-pointer'"
                     @mousedown="iniciarArrastreEstacion($event, <?= (int) $est['id'] ?>)"
                     @click.stop="abrirEstacion($event, <?= (int) $est['id'] ?>)">
                    <!-- Pin compacto: solo cuadrado pequeño con conteo -->
                    <div class="w-6 h-6 rounded-md border-2 border-white shadow-lg flex items-center justify-center text-white text-[10px] font-bold transition-transform hover:scale-125 relative"
                         style="background-color: <?= e($color_est) ?>"
                         title="<?= e($est['nombre']) ?> · <?= (int) $est['num_equipos'] ?> equipo(s)">
                        <i data-lucide="layout-grid" class="w-3 h-3"></i>
                        <span class="absolute -top-1.5 -right-1.5 bg-white text-zinc-900 rounded-full w-4 h-4 flex items-center justify-center text-[9px] font-bold border border-zinc-300"><?= (int) $est['num_equipos'] ?></span>
                        <?php if ($inc_abiertas > 0): ?>
                        <span class="absolute -bottom-1 -right-1 bg-white rounded-full p-0.5">
                            <i data-lucide="alert-circle" class="w-2.5 h-2.5 text-bacal-700"></i>
                        </span>
                        <?php endif; ?>
                    </div>

                    <!-- Botón remover del mapa (solo modo edición) -->
                    <button type="button"
                            x-show="modoEdicion && esAdmin"
                            x-cloak
                            @click.stop="removerEstacionDelMapa(<?= (int) $est['id'] ?>, '<?= e(addslashes($est['nombre'])) ?>')"
                            class="absolute -top-2 -left-2 w-4 h-4 rounded-full bg-zinc-900 hover:bg-bacal-700 text-white flex items-center justify-center shadow-md z-30"
                            title="Quitar del mapa">
                        <i data-lucide="x" class="w-3 h-3"></i>
                    </button>

                    <!-- Tooltip -->
                    <div class="absolute z-20 left-1/2 -translate-x-1/2 -top-2 -translate-y-full
                                bg-zinc-900 text-white text-xs rounded-lg px-2.5 py-1.5 whitespace-nowrap
                                opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none shadow-lg">
                        <div class="font-mono font-bold text-[10px] opacity-80"><?= e($est['codigo']) ?></div>
                        <div class="font-semibold"><?= e($est['nombre']) ?></div>
                        <div class="text-[10px] opacity-80 mt-0.5">
                            <?= (int) $est['num_equipos'] ?> equipo(s)
                        </div>
                        <?php if ($inc_abiertas > 0): ?>
                        <div class="text-[10px] text-bacal-300 mt-0.5">
                            ⚠ <?= $inc_abiertas ?> incidencia(s) abierta(s)
                        </div>
                        <?php endif; ?>
                        <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-zinc-900"></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <div x-show="arrastrandoDesdeFuera" x-cloak
                     class="absolute inset-0 bg-bacal-700/10 border-4 border-dashed border-bacal-700 pointer-events-none"></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar de equipos y estaciones sin ubicar -->
        <div class="lg:col-span-1 space-y-3">

            <!-- Bandeja de ESTACIONES sin ubicar (PRIMERO) -->
            <?php if (!empty($estaciones_sin_ubicar)): ?>
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-zinc-100 bg-purple-50">
                    <h3 class="font-display text-sm font-bold text-zinc-900 flex items-center gap-1.5">
                        <i data-lucide="layout-grid" class="w-4 h-4 text-purple-600"></i>
                        Estaciones sin ubicar
                        <span class="text-xs font-normal text-zinc-500">(<?= count($estaciones_sin_ubicar) ?>)</span>
                    </h3>
                </div>
                <div class="max-h-[400px] overflow-y-auto">
                    <?php foreach ($estaciones_sin_ubicar as $est):
                        $color_est = (int) $est['incidencias_abiertas'] > 0 ? '#C8102E' : '#7C3AED';
                    ?>
                    <div class="px-3 py-2 border-b border-zinc-100 last:border-b-0 flex items-center gap-2 hover:bg-zinc-50"
                         <?php if ($es_admin && $planta && !empty($planta['plano_url'])): ?>
                         draggable="true"
                         @dragstart="iniciarArrastreEstacionDesdeBandeja($event, <?= (int) $est['id'] ?>)"
                         @dragend="terminarArrastreDesdeBandeja()"
                         <?php endif; ?>>
                        <div class="w-3 h-3 rounded-sm flex-shrink-0" style="background-color: <?= e($color_est) ?>"></div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-semibold text-zinc-900 truncate"><?= e($est['nombre']) ?></div>
                            <div class="text-[10px] text-zinc-500">
                                <span class="font-mono"><?= e($est['codigo']) ?></span>
                                · <?= (int) $est['num_equipos'] ?> equipo(s)
                            </div>
                        </div>
                        <?php if ($es_admin && $planta && !empty($planta['plano_url'])): ?>
                        <i data-lucide="grip-vertical" class="w-3.5 h-3.5 text-zinc-400 flex-shrink-0"></i>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($es_admin && $planta && !empty($planta['plano_url'])): ?>
                <div class="px-4 py-2 bg-purple-50 border-t border-purple-200 text-[10px] text-purple-900">
                    💡 Arrastra al mapa para ubicar la estación completa
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Bandeja de EQUIPOS sin ubicar (SEGUNDO) -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-zinc-100 bg-zinc-50">
                    <h3 class="font-display text-sm font-bold text-zinc-900 flex items-center gap-1.5">
                        <i data-lucide="archive" class="w-4 h-4 text-zinc-500"></i>
                        Equipos sin ubicar
                        <span class="text-xs font-normal text-zinc-500">(<?= count($equipos_sin_ubicar) ?>)</span>
                    </h3>
                </div>

                <?php if (empty($equipos_sin_ubicar)): ?>
                <div class="px-4 py-8 text-center">
                    <i data-lucide="check-circle-2" class="w-8 h-8 mx-auto text-emerald-500 mb-2"></i>
                    <p class="text-xs text-zinc-500">Todos los equipos<br>están ubicados</p>
                </div>
                <?php else: ?>
                <div class="max-h-[600px] overflow-y-auto">
                    <?php foreach ($equipos_sin_ubicar as $eq):
                        $color = color_pin_equipo($eq['estado_vida'], (int) $eq['incidencias_abiertas']);
                    ?>
                    <div class="px-3 py-2 border-b border-zinc-100 last:border-b-0 flex items-center gap-2 hover:bg-zinc-50"
                         <?php if ($es_admin && $planta && !empty($planta['plano_url'])): ?>
                         draggable="true"
                         @dragstart="iniciarArrastreDesdeBandeja($event, <?= (int) $eq['id'] ?>)"
                         @dragend="terminarArrastreDesdeBandeja()"
                         <?php endif; ?>>
                        <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: <?= e($color) ?>"></div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-semibold text-zinc-900 truncate"><?= e($eq['nombre']) ?></div>
                            <div class="text-[10px] text-zinc-500 font-mono"><?= e($eq['codigo_inventario']) ?></div>
                        </div>
                        <?php if ($es_admin && $planta && !empty($planta['plano_url'])): ?>
                        <i data-lucide="grip-vertical" class="w-3.5 h-3.5 text-zinc-400 flex-shrink-0"></i>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($es_admin && $planta && !empty($planta['plano_url'])): ?>
                <div class="px-4 py-2 bg-amber-50 border-t border-amber-200 text-[10px] text-amber-900">
                    💡 Arrastra al mapa para ubicarlos en "<?= e($planta['nombre']) ?>"
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($es_admin && $planta): ?>
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-4">
                <h3 class="font-display text-xs font-bold text-zinc-900 mb-2 uppercase tracking-wide">
                    Resumen · <?= e($planta['nombre']) ?>
                </h3>
                <div class="space-y-1.5 text-xs text-zinc-700">
                    <div class="flex justify-between">
                        <span>Estaciones en esta planta</span>
                        <span class="font-bold text-purple-600"><?= count($estaciones_en_mapa) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Equipos sueltos en esta planta</span>
                        <span class="font-bold"><?= count($equipos_en_mapa) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Equipos sin ubicar</span>
                        <span class="font-bold"><?= count($equipos_sin_ubicar) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Estaciones sin ubicar</span>
                        <span class="font-bold"><?= count($estaciones_sin_ubicar) ?></span>
                    </div>
                    <div class="flex justify-between pt-1.5 border-t border-zinc-100">
                        <span>Con problemas aquí</span>
                        <span class="font-bold text-bacal-700">
                            <?= count(array_filter($equipos_en_mapa, fn($e) => (int) $e['incidencias_abiertas'] > 0))
                              + count(array_filter($estaciones_en_mapa, fn($e) => (int) $e['incidencias_abiertas'] > 0)) ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; // fin if !empty($plantas) ?>

    <!-- ============================================================
         MODAL: Crear planta
         ============================================================ -->
    <?php if ($es_admin): ?>
    <div x-show="modalCrear" x-cloak
         class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
         @click.self="modalCrear = false">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6"
             x-show="modalCrear" x-transition>
            <h3 class="font-display text-lg font-bold text-zinc-900 mb-4">Nueva planta</h3>
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="crear_planta">
                <input type="hidden" name="sucursal_id_post" value="<?= $sucursal_id ?>">
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nombre</label>
                <input type="text" name="nombre_planta" required maxlength="80"
                       placeholder="Ej. Planta baja, Piso 1, Bodega"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="modalCrear = false" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Crear planta</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
         MODAL: Renombrar planta
         ============================================================ -->
    <?php if ($planta): ?>
    <div x-show="modalRenombrar" x-cloak
         class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
         @click.self="modalRenombrar = false">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6"
             x-show="modalRenombrar" x-transition>
            <h3 class="font-display text-lg font-bold text-zinc-900 mb-4">Renombrar planta</h3>
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="renombrar_planta">
                <input type="hidden" name="sucursal_id_post" value="<?= $sucursal_id ?>">
                <input type="hidden" name="planta_id_op" value="<?= (int) $planta['id'] ?>">
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nuevo nombre</label>
                <input type="text" name="nombre_planta" required maxlength="80"
                       x-model="nombreRenombrar"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                <div class="flex justify-end gap-2 mt-5">
                    <button type="button" @click="modalRenombrar = false" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Form oculto para eliminar planta -->
    <form id="formEliminarPlanta" method="POST" class="hidden">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="eliminar_planta">
        <input type="hidden" name="sucursal_id_post" value="<?= $sucursal_id ?>">
        <input type="hidden" name="planta_id_op" value="<?= (int) $planta['id'] ?>">
    </form>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function mapaSucursal() {
    return {
        modoEdicion: false,
        planoUrl: '<?= $planta && !empty($planta['plano_url']) ? e(url($planta['plano_url']) . '?v=' . time()) : '' ?>',
        sucursalId: <?= $sucursal_id ?>,
        plantaId: <?= $planta ? (int) $planta['id'] : 0 ?>,
        esAdmin: <?= $es_admin ? 'true' : 'false' ?>,
        modalCrear: false,
        modalRenombrar: false,
        nombreRenombrar: '',
        arrastrando: null,
        arrastrandoDesdeFuera: false,

        // ============================================================
        // ARRASTRE DE EQUIPOS YA EN EL MAPA
        // ============================================================
        iniciarArrastre(evento, equipoId) {
            if (!this.modoEdicion || !this.esAdmin) return;
            evento.preventDefault();
            this.arrastrando = { id: equipoId, tipo: 'equipo', elemento: evento.currentTarget };

            document.addEventListener('mousemove', this.moverArrastre);
            document.addEventListener('mouseup', this.soltarArrastre);
        },

        // ============================================================
        // ARRASTRE DE ESTACIONES YA EN EL MAPA
        // ============================================================
        iniciarArrastreEstacion(evento, estacionId) {
            if (!this.modoEdicion || !this.esAdmin) return;
            evento.preventDefault();
            this.arrastrando = { id: estacionId, tipo: 'estacion', elemento: evento.currentTarget };

            document.addEventListener('mousemove', this.moverArrastre);
            document.addEventListener('mouseup', this.soltarArrastre);
        },

        moverArrastre: (evento) => {
            const ctx = window._mapaCtx;
            if (!ctx || !ctx.arrastrando) return;
            const cont = ctx.$refs.contenedorMapa;
            const rect = cont.getBoundingClientRect();
            const x = ((evento.clientX - rect.left) / rect.width) * 100;
            const y = ((evento.clientY - rect.top) / rect.height) * 100;
            ctx.arrastrando.elemento.style.left = Math.max(0, Math.min(100, x)) + '%';
            ctx.arrastrando.elemento.style.top = Math.max(0, Math.min(100, y)) + '%';
            ctx.arrastrando.elemento.dataset.posX = x;
            ctx.arrastrando.elemento.dataset.posY = y;
        },

        soltarArrastre: async (evento) => {
            const ctx = window._mapaCtx;
            if (!ctx || !ctx.arrastrando) return;
            const { id, tipo } = ctx.arrastrando;
            const x = parseFloat(ctx.arrastrando.elemento.dataset.posX);
            const y = parseFloat(ctx.arrastrando.elemento.dataset.posY);
            document.removeEventListener('mousemove', ctx.moverArrastre);
            document.removeEventListener('mouseup', ctx.soltarArrastre);
            ctx.arrastrando = null;
            if (tipo === 'estacion') {
                await ctx.guardarPosicionEstacion(id, x, y);
            } else {
                await ctx.guardarPosicion(id, x, y);
            }
        },

        // ============================================================
        // CLICK EN PINS
        // ============================================================
        abrirEquipo(evento, equipoId) {
            if (this.modoEdicion) return;
            window.location.href = '<?= url('equipo_ver.php?id=') ?>' + equipoId;
        },

        abrirEstacion(evento, estacionId) {
            if (this.modoEdicion) return;
            window.location.href = '<?= url('estacion_ver.php?id=') ?>' + estacionId;
        },

        // ============================================================
        // ARRASTRE DESDE BANDEJA (drag&drop HTML5)
        // ============================================================
        iniciarArrastreDesdeBandeja(evento, equipoId) {
            this.arrastrandoDesdeFuera = true;
            evento.dataTransfer.effectAllowed = 'move';
            // Prefijo "eq:" indica equipo
            evento.dataTransfer.setData('text/plain', 'eq:' + equipoId);
        },

        iniciarArrastreEstacionDesdeBandeja(evento, estacionId) {
            this.arrastrandoDesdeFuera = true;
            evento.dataTransfer.effectAllowed = 'move';
            // Prefijo "est:" indica estación
            evento.dataTransfer.setData('text/plain', 'est:' + estacionId);
        },

        terminarArrastreDesdeBandeja() {
            this.arrastrandoDesdeFuera = false;
        },

        // ============================================================
        // GUARDAR POSICIONES
        // ============================================================
        async guardarPosicion(equipoId, x, y) {
            try {
                const fd = new FormData();
                fd.append('_csrf', '<?= e(csrf_token()) ?>');
                fd.append('equipo_id', equipoId);
                fd.append('planta_id', this.plantaId);
                fd.append('pos_x', x);
                fd.append('pos_y', y);

                const resp = await fetch('<?= url('api/equipo_posicion.php') ?>', {
                    method: 'POST', body: fd, credentials: 'same-origin'
                });
                const data = await resp.json();
                if (!data.ok) alert('Error al guardar: ' + (data.error || 'desconocido'));
            } catch (e) {
                alert('Error de conexión: ' + e.message);
            }
        },

        async guardarPosicionEstacion(estacionId, x, y) {
            try {
                const fd = new FormData();
                fd.append('_csrf', '<?= e(csrf_token()) ?>');
                fd.append('estacion_id', estacionId);
                fd.append('planta_id', this.plantaId);
                fd.append('pos_x', x);
                fd.append('pos_y', y);

                const resp = await fetch('<?= url('api/estacion_posicion.php') ?>', {
                    method: 'POST', body: fd, credentials: 'same-origin'
                });
                const data = await resp.json();
                if (!data.ok) alert('Error al guardar: ' + (data.error || 'desconocido'));
            } catch (e) {
                alert('Error de conexión: ' + e.message);
            }
        },

        // ============================================================
        // QUITAR DEL MAPA (deja sin ubicar, no elimina)
        // ============================================================
        async removerEquipoDelMapa(equipoId, nombre) {
            if (!confirm('¿Quitar "' + nombre + '" del mapa?\n\nEl equipo no se elimina, solo vuelve a "Equipos sin ubicar".')) return;
            try {
                const fd = new FormData();
                fd.append('_csrf', '<?= e(csrf_token()) ?>');
                fd.append('equipo_id', equipoId);
                // sin planta_id ni pos_x/pos_y → el endpoint desubica

                const resp = await fetch('<?= url('api/equipo_posicion.php') ?>', {
                    method: 'POST', body: fd, credentials: 'same-origin'
                });
                const data = await resp.json();
                if (data.ok) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'desconocido'));
                }
            } catch (e) {
                alert('Error de conexión: ' + e.message);
            }
        },

        async removerEstacionDelMapa(estacionId, nombre) {
            if (!confirm('¿Quitar "' + nombre + '" del mapa?\n\nLa estación no se elimina, solo vuelve a "Estaciones sin ubicar".')) return;
            try {
                const fd = new FormData();
                fd.append('_csrf', '<?= e(csrf_token()) ?>');
                fd.append('estacion_id', estacionId);
                // sin planta_id ni pos_x/pos_y → el endpoint desubica

                const resp = await fetch('<?= url('api/estacion_posicion.php') ?>', {
                    method: 'POST', body: fd, credentials: 'same-origin'
                });
                const data = await resp.json();
                if (data.ok) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'desconocido'));
                }
            } catch (e) {
                alert('Error de conexión: ' + e.message);
            }
        },

        // ============================================================
        // SUBIR PLANO
        // ============================================================
        async subirPlano(evento) {
            const archivo = evento.target.files[0];
            if (!archivo) return;
            if (archivo.size > 10 * 1024 * 1024) {
                alert('La imagen excede 10 MB.');
                return;
            }

            const fd = new FormData();
            fd.append('_csrf', '<?= e(csrf_token()) ?>');
            fd.append('planta_id', this.plantaId);
            fd.append('plano', archivo);

            try {
                const resp = await fetch('<?= url('api/sucursal_plano_subir.php') ?>', {
                    method: 'POST', body: fd, credentials: 'same-origin'
                });
                const data = await resp.json();
                if (data.ok) location.reload();
                else alert('Error: ' + (data.error || 'desconocido'));
            } catch (e) {
                alert('Error de conexión: ' + e.message);
            }
        },

        confirmarEliminarPlanta() {
            if (confirm('¿Eliminar esta planta? Sus equipos volverán a "Sin ubicar" (no se eliminan los equipos).')) {
                document.getElementById('formEliminarPlanta').submit();
            }
        },

        init() {
            window._mapaCtx = this;
            const cont = this.$refs.contenedorMapa;
            if (!cont || !this.esAdmin) return;

            cont.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });

            cont.addEventListener('drop', async (e) => {
                e.preventDefault();
                const data = e.dataTransfer.getData('text/plain');
                if (!data) return;

                const rect = cont.getBoundingClientRect();
                const x = ((e.clientX - rect.left) / rect.width) * 100;
                const y = ((e.clientY - rect.top) / rect.height) * 100;

                // Soporta formato "eq:123", "est:5" o solo "123" (compatibilidad)
                if (data.startsWith('est:')) {
                    const estacionId = parseInt(data.substring(4));
                    if (estacionId) {
                        await this.guardarPosicionEstacion(estacionId, x, y);
                        location.reload();
                    }
                } else if (data.startsWith('eq:')) {
                    const equipoId = parseInt(data.substring(3));
                    if (equipoId) {
                        await this.guardarPosicion(equipoId, x, y);
                        location.reload();
                    }
                } else {
                    // Compatibilidad: solo número = equipo
                    const equipoId = parseInt(data);
                    if (equipoId) {
                        await this.guardarPosicion(equipoId, x, y);
                        location.reload();
                    }
                }
            });
        }
    }
}
</script>

<?php require_once __DIR__ . '/config/footer.php'; ?>
