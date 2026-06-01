<?php
/**
 * ============================================================================
 * estacion_ver.php - Ficha individual de una estación de trabajo
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

$id = (int) input('id', 0);
$est = $id > 0 ? obtener_estacion($id) : null;

if (!$est) {
    flash_set('error', 'Estación no encontrada.');
    header('Location: ' . url('estaciones.php'));
    exit;
}

if (!tiene_permiso('ver_todas_sucursales') && (int) $u['sucursal_id'] !== (int) $est['sucursal_id']) {
    flash_set('error', 'No tienes permiso para ver esta estación.');
    header('Location: ' . url('estaciones.php'));
    exit;
}

$errores = [];

// Procesar POST
if (es_post() && $puede_gestionar) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token inválido.';
    } else {
        $op = (string) input('op', '');

        try {
            if ($op === 'actualizar') {
                $datos = [
                    'codigo' => trim((string) input('codigo', '')),
                    'nombre' => trim((string) input('nombre', '')),
                    'descripcion' => trim((string) input('descripcion', '')) ?: null,
                    'sucursal_id' => (int) input('sucursal_est', $est['sucursal_id']),
                    'area_id' => (int) input('area_est', 0) ?: null,
                    'ubicacion' => trim((string) input('ubicacion', '')) ?: null,
                    'responsable_id' => (int) input('responsable_id', 0) ?: null,
                    'responsable_nombre' => trim((string) input('responsable_nombre', '')) ?: null,
                    'notas' => trim((string) input('notas', '')) ?: null,
                ];
                if ($datos['codigo'] === '' || $datos['nombre'] === '') {
                    $errores[] = 'Código y nombre son obligatorios.';
                } else {
                    actualizar_estacion($id, $datos);
                    flash_set('success', 'Estación actualizada.');
                    header('Location: ' . url("estacion_ver.php?id=$id"));
                    exit;
                }
            } elseif ($op === 'agregar_equipos') {
                $ids = input('equipo_ids', []);
                if (!is_array($ids)) $ids = [];
                if (count($ids) === 0) {
                    $errores[] = 'Selecciona al menos un equipo.';
                } else {
                    $n = agregar_equipos_a_estacion($id, $ids);
                    flash_set('success', "$n equipo(s) asignados a la estación.");
                    header('Location: ' . url("estacion_ver.php?id=$id"));
                    exit;
                }
            } elseif ($op === 'desasignar_equipo') {
                $eid = (int) input('equipo_id', 0);
                if ($eid > 0) {
                    asignar_equipo_a_estacion($eid, null);
                    flash_set('success', 'Equipo desasignado de la estación.');
                    header('Location: ' . url("estacion_ver.php?id=$id"));
                    exit;
                }
            } elseif ($op === 'eliminar' && $es_admin) {
                eliminar_estacion($id);
                flash_set('success', 'Estación eliminada y equipos desasignados.');
                header('Location: ' . url('estaciones.php'));
                exit;
            }
        } catch (Throwable $e) {
            $errores[] = 'Error: ' . $e->getMessage();
        }
    }
}

$equipos_asignados = listar_equipos_de_estacion($id);
$incidencias = listar_incidencias_de_estacion($id, 30);
$equipos_disponibles = listar_equipos_disponibles_para_estacion((int) $est['sucursal_id'], $id);

// Filtrar disponibles que NO estén ya asignados a esta estación
$equipos_para_agregar = array_filter($equipos_disponibles, fn($e) => empty($e['estacion_id']));

$sucursales = db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo=1 ORDER BY nombre");
$areas = db_all("SELECT id, nombre FROM areas WHERE activo=1 ORDER BY nombre");
$usuarios_lista = db_all("SELECT id, nombre_completo FROM usuarios WHERE activo=1 ORDER BY nombre_completo");

// Stats de incidencias
$stats_inc = [
    'total' => count($incidencias),
    'abiertas' => count(array_filter($incidencias, fn($i) => !$i['es_final'])),
    'cerradas' => count(array_filter($incidencias, fn($i) => $i['es_final'])),
];

$titulo_pagina = $est['nombre'];
$pagina_activa = 'estaciones';
require_once __DIR__ . '/config/header.php';
?>

<div class="max-w-6xl mx-auto animate-fade-in space-y-4">

    <!-- Header -->
    <div class="flex items-center gap-3 flex-wrap">
        <a href="<?= url('estaciones.php') ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 text-xs text-zinc-500 mb-0.5">
                <span class="font-mono font-bold"><?= e($est['codigo']) ?></span>
                <span>·</span>
                <span><?= e($est['sucursal_codigo']) ?></span>
                <?php if (!empty($est['area_nombre'])): ?>
                <span>·</span>
                <span><?= e($est['area_nombre']) ?></span>
                <?php endif; ?>
            </div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900 truncate"><?= e($est['nombre']) ?></h2>
        </div>

        <?php if ($puede_gestionar): ?>
        <?php if (!empty($equipos_para_agregar)): ?>
        <button onclick="document.getElementById('modal_agregar').showModal()"
                class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Agregar equipos
        </button>
        <?php endif; ?>
        <button onclick="document.getElementById('modal_editar').showModal()"
                class="px-3 py-2 rounded-lg border border-zinc-300 hover:bg-zinc-50 text-sm font-semibold text-zinc-700 flex items-center gap-1.5">
            <i data-lucide="edit-3" class="w-4 h-4"></i>
            Editar
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
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold">Equipos</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= count($equipos_asignados) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold">Incidencias totales</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= $stats_inc['total'] ?></div>
        </div>
        <div class="bg-white rounded-xl border <?= $stats_inc['abiertas'] > 0 ? 'border-bacal-300 bg-bacal-50' : 'border-zinc-200' ?> p-4">
            <div class="text-[10px] <?= $stats_inc['abiertas'] > 0 ? 'text-bacal-700' : 'text-zinc-500' ?> uppercase tracking-wider font-bold">Abiertas</div>
            <div class="font-display text-2xl font-extrabold <?= $stats_inc['abiertas'] > 0 ? 'text-bacal-700' : 'text-zinc-900' ?>"><?= $stats_inc['abiertas'] ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-emerald-700 uppercase tracking-wider font-bold">Cerradas</div>
            <div class="font-display text-2xl font-extrabold text-emerald-700"><?= $stats_inc['cerradas'] ?></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Columna principal -->
        <div class="lg:col-span-2 space-y-4">

            <!-- Equipos asignados -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-zinc-100 flex items-center justify-between">
                    <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                        <i data-lucide="monitor" class="w-4 h-4 text-bacal-700"></i>
                        Equipos en esta estación
                        <span class="text-xs font-normal text-zinc-500">(<?= count($equipos_asignados) ?>)</span>
                    </h3>
                    <?php if ($puede_gestionar && !empty($equipos_para_agregar)): ?>
                    <button onclick="document.getElementById('modal_agregar').showModal()"
                            class="text-xs text-bacal-700 hover:underline font-semibold flex items-center gap-1">
                        <i data-lucide="plus" class="w-3 h-3"></i> Agregar
                    </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($equipos_asignados)): ?>
                <div class="px-5 py-10 text-center">
                    <p class="text-sm font-semibold text-zinc-700 mb-1">Sin equipos asignados</p>
                    <?php if ($puede_gestionar && !empty($equipos_para_agregar)): ?>
                    <p class="text-xs text-zinc-500 mb-3">Tienes <?= count($equipos_para_agregar) ?> equipo(s) disponibles en esta sucursal para asignar.</p>
                    <button onclick="document.getElementById('modal_agregar').showModal()"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                        <i data-lucide="plus" class="w-4 h-4"></i> Asignar equipos
                    </button>
                    <?php else: ?>
                    <p class="text-xs text-zinc-500">No hay equipos disponibles para asignar en esta sucursal.</p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 border-b border-zinc-100">
                        <tr>
                            <th class="px-3 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">Código</th>
                            <th class="px-3 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">Equipo</th>
                            <th class="px-3 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">Tipo</th>
                            <th class="px-3 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">Marca/Modelo</th>
                            <?php if ($puede_gestionar): ?>
                            <th class="px-3 py-2"></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($equipos_asignados as $eq): ?>
                    <tr class="hover:bg-zinc-50">
                        <td class="px-3 py-2.5">
                            <span class="font-mono text-xs font-bold text-zinc-900"><?= e($eq['codigo_inventario']) ?></span>
                        </td>
                        <td class="px-3 py-2.5">
                            <a href="<?= url('equipo_ver.php?id=' . $eq['id']) ?>"
                               class="font-semibold text-zinc-900 hover:text-bacal-700"><?= e($eq['nombre']) ?></a>
                        </td>
                        <td class="px-3 py-2.5 text-xs text-zinc-700">
                            <?= !empty($eq['tipo']) ? e($eq['tipo']) : '<span class="text-zinc-400">—</span>' ?>
                        </td>
                        <td class="px-3 py-2.5 text-xs text-zinc-700">
                            <?php if (!empty($eq['marca']) || !empty($eq['modelo'])): ?>
                            <?= e(trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? ''))) ?>
                            <?php else: ?>
                            <span class="text-zinc-400">—</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($puede_gestionar): ?>
                        <td class="px-3 py-2.5 text-right">
                            <form method="POST" onsubmit="return confirm('¿Desasignar este equipo de la estación? El equipo seguirá existiendo pero sin estación asignada.');" class="inline-block">
                                <?= csrf_input() ?>
                                <input type="hidden" name="op" value="desasignar_equipo">
                                <input type="hidden" name="equipo_id" value="<?= $eq['id'] ?>">
                                <button type="submit" class="p-1.5 rounded text-zinc-400 hover:text-bacal-700 hover:bg-zinc-100" title="Desasignar de la estación">
                                    <i data-lucide="unlink" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Incidencias relacionadas -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-zinc-100">
                    <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                        <i data-lucide="activity" class="w-4 h-4 text-bacal-700"></i>
                        Incidencias relacionadas
                        <span class="text-xs font-normal text-zinc-500">(directas + equipos)</span>
                    </h3>
                </div>

                <?php if (empty($incidencias)): ?>
                <div class="px-5 py-10 text-center text-xs text-zinc-500">
                    Sin incidencias registradas para esta estación o sus equipos.
                </div>
                <?php else: ?>
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 border-b border-zinc-100">
                        <tr>
                            <th class="px-3 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">Folio</th>
                            <th class="px-3 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">Título</th>
                            <th class="px-3 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">Equipo</th>
                            <th class="px-3 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">Estado</th>
                            <th class="px-3 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($incidencias as $i): ?>
                    <tr class="hover:bg-zinc-50 cursor-pointer" onclick="window.location.href='<?= url('incidencia_ver.php?id=' . $i['id']) ?>'">
                        <td class="px-3 py-2.5">
                            <span class="font-mono text-xs font-bold text-zinc-900"><?= e($i['folio']) ?></span>
                            <?php if ($i['relacion'] === 'directa'): ?>
                            <div class="text-[9px] font-bold text-amber-700 uppercase">Directa</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="font-semibold text-zinc-900 truncate max-w-xs"><?= e($i['titulo']) ?></div>
                            <?php if (!empty($i['asignado_a_nombre'])): ?>
                            <div class="text-[10px] text-zinc-500">→ <?= e($i['asignado_a_nombre']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5 text-xs">
                            <?php if (!empty($i['equipo_codigo'])): ?>
                            <span class="font-mono text-zinc-700"><?= e($i['equipo_codigo']) ?></span>
                            <?php else: ?>
                            <span class="text-zinc-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded uppercase"
                                  style="color: <?= e($i['estado_color']) ?>; background-color: <?= e($i['estado_color']) ?>15">
                                <?= e($i['estado_nombre']) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-xs text-zinc-600">
                            <?= e(date('d/M/Y', strtotime($i['creado_en']))) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar derecha -->
        <div class="space-y-4">

            <!-- Información de la estación -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
                <h3 class="font-display text-sm font-bold text-zinc-900 mb-3 flex items-center gap-1.5">
                    <i data-lucide="info" class="w-3.5 h-3.5 text-bacal-700"></i>
                    Información
                </h3>

                <div class="space-y-2 text-xs">
                    <div>
                        <div class="text-[10px] font-bold text-zinc-500 uppercase">Sucursal</div>
                        <div class="font-semibold text-zinc-900"><?= e($est['sucursal_nombre']) ?></div>
                    </div>
                    <?php if (!empty($est['area_nombre'])): ?>
                    <div>
                        <div class="text-[10px] font-bold text-zinc-500 uppercase">Área</div>
                        <div class="font-semibold text-zinc-900 flex items-center gap-1.5">
                            <?php if (!empty($est['area_color'])): ?>
                            <span class="w-2 h-2 rounded-full" style="background-color: <?= e($est['area_color']) ?>"></span>
                            <?php endif; ?>
                            <?= e($est['area_nombre']) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($est['responsable_usuario'])): ?>
                    <div>
                        <div class="text-[10px] font-bold text-zinc-500 uppercase">Responsable</div>
                        <div class="font-semibold text-zinc-900"><?= e($est['responsable_usuario']) ?></div>
                    </div>
                    <?php elseif (!empty($est['responsable_nombre'])): ?>
                    <div>
                        <div class="text-[10px] font-bold text-zinc-500 uppercase">Responsable</div>
                        <div class="font-semibold text-zinc-900"><?= e($est['responsable_nombre']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($est['ubicacion'])): ?>
                    <div>
                        <div class="text-[10px] font-bold text-zinc-500 uppercase">Ubicación</div>
                        <div class="font-semibold text-zinc-900"><?= e($est['ubicacion']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($est['descripcion']) || !empty($est['notas'])): ?>
                <div class="mt-3 pt-3 border-t border-zinc-100 space-y-2">
                    <?php if (!empty($est['descripcion'])): ?>
                    <div>
                        <div class="text-[10px] font-bold text-zinc-500 uppercase mb-0.5">Descripción</div>
                        <div class="text-xs text-zinc-700 whitespace-pre-wrap"><?= e($est['descripcion']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($est['notas'])): ?>
                    <div>
                        <div class="text-[10px] font-bold text-zinc-500 uppercase mb-0.5">Notas</div>
                        <div class="text-xs text-zinc-700 whitespace-pre-wrap"><?= e($est['notas']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Acciones rápidas -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
                <h3 class="font-display text-sm font-bold text-zinc-900 mb-3">Acciones rápidas</h3>
                <div class="space-y-1">
                    <a href="<?= url('nueva_incidencia.php?estacion_id=' . $id) ?>"
                       class="w-full text-left px-3 py-2 rounded-lg hover:bg-zinc-50 text-xs font-medium flex items-center gap-2 text-bacal-700">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                        Crear incidencia para esta estación
                    </a>
                    <a href="<?= url('bitacora.php?estacion_id=' . $id) ?>"
                       class="w-full text-left px-3 py-2 rounded-lg hover:bg-zinc-50 text-xs font-medium flex items-center gap-2 text-zinc-700">
                        <i data-lucide="list" class="w-3.5 h-3.5"></i>
                        Ver todas en bitácora
                    </a>
                </div>
            </div>

            <!-- Metadata -->
            <div class="bg-zinc-50 rounded-xl border border-zinc-200 p-4 text-xs text-zinc-600 space-y-1">
                <?php if (!empty($est['creado_por_nombre'])): ?>
                <div>Creado por <?= e($est['creado_por_nombre']) ?> · <?= e(fmt_tiempo_relativo($est['creado_en'])) ?></div>
                <?php endif; ?>
                <div>Actualizado · <?= e(fmt_tiempo_relativo($est['actualizado_en'])) ?></div>
            </div>

            <?php if ($es_admin): ?>
            <form method="POST" onsubmit="return confirm('¿Eliminar esta estación? Los equipos quedarán sin asignación.');" class="text-right">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="eliminar">
                <button type="submit" class="text-xs text-zinc-500 hover:text-bacal-700 inline-flex items-center gap-1">
                    <i data-lucide="trash-2" class="w-3 h-3"></i> Eliminar estación
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($puede_gestionar): ?>

<!-- Modal: Agregar equipos -->
<?php if (!empty($equipos_para_agregar)): ?>
<dialog id="modal_agregar" class="rounded-xl shadow-2xl backdrop:bg-black/50 w-full max-w-lg p-0">
    <form method="POST" class="bg-white">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="agregar_equipos">

        <div class="px-5 py-3 border-b border-zinc-200 flex items-center justify-between">
            <h3 class="font-display text-base font-bold text-zinc-900">Agregar equipos a la estación</h3>
            <button type="button" onclick="document.getElementById('modal_agregar').close()" class="p-1 rounded hover:bg-zinc-100 text-zinc-500">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="p-5 space-y-3">
            <div class="bg-blue-50 px-3 py-2 rounded text-xs text-blue-900">
                Selecciona los equipos de <strong><?= e($est['sucursal_nombre']) ?></strong> que pertenecen a esta estación.
                Marca todos los que apliquen.
            </div>

            <div class="space-y-1 max-h-96 overflow-y-auto border border-zinc-200 rounded-lg p-2">
                <?php foreach ($equipos_para_agregar as $eq): ?>
                <label class="flex items-center gap-2 p-2 rounded hover:bg-zinc-50 cursor-pointer">
                    <input type="checkbox" name="equipo_ids[]" value="<?= $eq['id'] ?>"
                           class="w-4 h-4 rounded text-bacal-700 focus:ring-bacal-700">
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-semibold text-zinc-900 truncate"><?= e($eq['nombre']) ?></div>
                        <div class="text-[10px] text-zinc-500">
                            <span class="font-mono"><?= e($eq['codigo_inventario']) ?></span>
                            <?php if (!empty($eq['tipo'])): ?>· <?= e($eq['tipo']) ?><?php endif; ?>
                            <?php if (!empty($eq['marca'])): ?>· <?= e($eq['marca']) ?><?php endif; ?>
                        </div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="px-5 py-3 border-t border-zinc-200 flex justify-end gap-2 bg-zinc-50">
            <button type="button" onclick="document.getElementById('modal_agregar').close()" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Asignar seleccionados</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<!-- Modal: Editar estación -->
<dialog id="modal_editar" class="rounded-xl shadow-2xl backdrop:bg-black/50 w-full max-w-2xl p-0">
    <form method="POST" class="bg-white">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="actualizar">

        <div class="px-5 py-3 border-b border-zinc-200 flex items-center justify-between">
            <h3 class="font-display text-base font-bold text-zinc-900">Editar estación</h3>
            <button type="button" onclick="document.getElementById('modal_editar').close()" class="p-1 rounded hover:bg-zinc-100 text-zinc-500">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="p-5 space-y-3 max-h-[70vh] overflow-y-auto">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Código *</label>
                    <input type="text" name="codigo" required value="<?= e($est['codigo']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm font-mono focus:outline-none focus:border-bacal-700">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Nombre *</label>
                    <input type="text" name="nombre" required value="<?= e($est['nombre']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Descripción</label>
                <textarea name="descripcion" rows="2" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"><?= e($est['descripcion'] ?? '') ?></textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Sucursal *</label>
                    <select name="sucursal_est" required class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (int) $est['sucursal_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Área</label>
                    <select name="area_est" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin área —</option>
                        <?php foreach ($areas as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= (int) $est['area_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Ubicación física</label>
                <input type="text" name="ubicacion" value="<?= e($est['ubicacion'] ?? '') ?>" maxlength="255"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Responsable (usuario)</label>
                    <select name="responsable_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Ninguno —</option>
                        <?php foreach ($usuarios_lista as $usr): ?>
                        <option value="<?= $usr['id'] ?>" <?= (int) $est['responsable_id'] === (int) $usr['id'] ? 'selected' : '' ?>><?= e($usr['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">o nombre libre</label>
                    <input type="text" name="responsable_nombre" value="<?= e($est['responsable_nombre'] ?? '') ?>" maxlength="150"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Notas</label>
                <textarea name="notas" rows="2" class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"><?= e($est['notas'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="px-5 py-3 border-t border-zinc-200 flex justify-end gap-2 bg-zinc-50">
            <button type="button" onclick="document.getElementById('modal_editar').close()" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Guardar</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php require_once __DIR__ . '/config/footer.php'; ?>
