<?php
/**
 * ============================================================================
 * mantenimiento_ver.php - Vista detallada y acciones de un mantenimiento
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/mantenimientos_helpers.php';
require_once __DIR__ . '/config/notificaciones_helpers.php';

requerir_login();

$u = usuario_actual();
$id = (int) input('id', 0);

$m = $id > 0 ? db_one(
    "SELECT m.*,
            e.codigo_inventario equipo_codigo, e.nombre equipo_nombre, e.sucursal_id equipo_sucursal_id,
            s.nombre sucursal_nombre,
            ua.nombre_completo asignado_nombre,
            ur.nombre_completo realizado_nombre,
            uc.nombre_completo creado_nombre,
            p.nombre proveedor_nombre, p.telefono proveedor_telefono,
            ip.folio padre_folio
     FROM mantenimientos m
     INNER JOIN equipos e ON m.equipo_id = e.id
     INNER JOIN sucursales s ON e.sucursal_id = s.id
     LEFT JOIN usuarios ua ON m.asignado_a_id = ua.id
     LEFT JOIN usuarios ur ON m.realizado_por_id = ur.id
     LEFT JOIN usuarios uc ON m.creado_por_id = uc.id
     LEFT JOIN proveedores p ON m.proveedor_id = p.id
     LEFT JOIN mantenimientos pad ON m.mantenimiento_padre_id = pad.id
     LEFT JOIN incidencias ip ON m.incidencia_generada_id = ip.id
     WHERE m.id = :id",
    ['id' => $id]
) : null;

if (!$m) {
    $titulo_pagina = 'Mantenimiento no encontrado';
    require_once __DIR__ . '/config/header.php';
    ?>
    <div class="max-w-md mx-auto text-center py-20">
        <div class="w-16 h-16 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-4">
            <i data-lucide="search-x" class="w-8 h-8 text-zinc-400"></i>
        </div>
        <h2 class="font-display text-xl font-bold text-zinc-900 mb-2">Mantenimiento no encontrado</h2>
        <a href="<?= url('mantenimientos.php') ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold rounded-lg">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Volver
        </a>
    </div>
    <?php
    require_once __DIR__ . '/config/footer.php';
    exit;
}

$puede_editar = puede_administrar_mantenimientos();
$puede_completar = $puede_editar && in_array($m['estado'], ['programado','proximo','en_progreso','vencido'], true);
$puede_cancelar = $puede_editar && in_array($m['estado'], ['programado','proximo','en_progreso','vencido'], true);

// ----------------------------------------------------------------------------
// Procesar acciones POST
// ----------------------------------------------------------------------------
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        flash_set('error', 'Token inválido.');
    } elseif (!$puede_editar) {
        flash_set('error', 'Sin permiso.');
    } else {
        $op = (string) input('op', '');

        try {
            if ($op === 'iniciar') {
                db_exec("UPDATE mantenimientos SET estado = 'en_progreso', fecha_inicio_real = NOW() WHERE id = :id", ['id' => $id]);
                registrar_auditoria('iniciar_mantenimiento', 'mantenimientos', $id, "Inició mantenimiento {$m['titulo']}");
                flash_set('success', 'Mantenimiento iniciado.');
            } elseif ($op === 'completar') {
                $resultado = trim((string) input('resultado', ''));
                $costo = trim((string) input('costo', ''));
                $costo_val = $costo !== '' ? (float) $costo : null;

                db_exec(
                    "UPDATE mantenimientos
                     SET estado = 'completado',
                         fecha_completado = NOW(),
                         realizado_por_id = :uid,
                         resultado = :res,
                         costo = :cost
                     WHERE id = :id",
                    ['uid' => $u['id'], 'res' => $resultado ?: null, 'cost' => $costo_val, 'id' => $id]
                );

                // Si era recurrente, generar el siguiente
                $siguiente_id = null;
                if ((int) $m['es_recurrente'] === 1) {
                    $siguiente_id = generar_siguiente_recurrente($id);
                }

                registrar_auditoria('completar_mantenimiento', 'mantenimientos', $id,
                    "Completó mantenimiento {$m['titulo']}" .
                    ($siguiente_id ? " · Siguiente recurrente generado: #$siguiente_id" : ''));

                // Notificar al creador (si no fue él mismo)
                if ($m['creado_por_id'] && (int) $m['creado_por_id'] !== (int) $u['id']) {
                    crear_notificacion(
                        (int) $m['creado_por_id'],
                        'mantenimiento_completado',
                        "Mantenimiento completado: {$m['titulo']}",
                        "Equipo {$m['equipo_codigo']}" . ($costo_val ? " · Costo \$" . number_format($costo_val, 2) : ''),
                        url('mantenimiento_ver.php?id=' . $id),
                        'mantenimientos',
                        $id
                    );
                }

                if ($siguiente_id) {
                    flash_set('success', "Mantenimiento completado. Siguiente recurrente generado para " .
                        date('d/m/Y', strtotime(db_one("SELECT fecha_programada FROM mantenimientos WHERE id = :id", ['id' => $siguiente_id])['fecha_programada'])));
                } else {
                    flash_set('success', 'Mantenimiento marcado como completado.');
                }
            } elseif ($op === 'cancelar') {
                $motivo = trim((string) input('motivo_cancelacion', ''));
                db_exec(
                    "UPDATE mantenimientos SET estado = 'cancelado',
                     resultado = CONCAT(COALESCE(resultado,''), CASE WHEN resultado IS NOT NULL THEN '\n\n' ELSE '' END, :m)
                     WHERE id = :id",
                    ['m' => 'CANCELADO: ' . ($motivo ?: 'sin motivo especificado'), 'id' => $id]
                );
                registrar_auditoria('cancelar_mantenimiento', 'mantenimientos', $id, "Canceló mantenimiento: $motivo");
                flash_set('success', 'Mantenimiento cancelado.');
            } elseif ($op === 'reabrir') {
                // Reabrir uno completado/cancelado: regresar a programado
                db_exec(
                    "UPDATE mantenimientos
                     SET estado = CASE WHEN fecha_programada < CURDATE() THEN 'vencido' ELSE 'programado' END,
                         fecha_completado = NULL, realizado_por_id = NULL
                     WHERE id = :id",
                    ['id' => $id]
                );
                registrar_auditoria('reabrir_mantenimiento', 'mantenimientos', $id, "Reabrió mantenimiento");
                flash_set('success', 'Mantenimiento reabierto.');
            } elseif ($op === 'eliminar') {
                db_exec("DELETE FROM mantenimientos WHERE id = :id", ['id' => $id]);
                registrar_auditoria('eliminar_mantenimiento', 'mantenimientos', $id, "Eliminó mantenimiento {$m['titulo']}");
                flash_set('success', 'Mantenimiento eliminado.');
                header('Location: ' . url('mantenimientos.php'));
                exit;
            }
        } catch (Throwable $e) {
            flash_set('error', 'Error: ' . $e->getMessage());
        }
    }

    header('Location: ' . url('mantenimiento_ver.php?id=' . $id));
    exit;
}

// Recargar datos por si hubo cambios
$m = db_one(
    "SELECT m.*,
            e.codigo_inventario equipo_codigo, e.nombre equipo_nombre,
            s.nombre sucursal_nombre,
            ua.nombre_completo asignado_nombre,
            ur.nombre_completo realizado_nombre,
            uc.nombre_completo creado_nombre,
            p.nombre proveedor_nombre, p.telefono proveedor_telefono,
            ip.folio padre_folio
     FROM mantenimientos m
     INNER JOIN equipos e ON m.equipo_id = e.id
     INNER JOIN sucursales s ON e.sucursal_id = s.id
     LEFT JOIN usuarios ua ON m.asignado_a_id = ua.id
     LEFT JOIN usuarios ur ON m.realizado_por_id = ur.id
     LEFT JOIN usuarios uc ON m.creado_por_id = uc.id
     LEFT JOIN proveedores p ON m.proveedor_id = p.id
     LEFT JOIN incidencias ip ON m.incidencia_generada_id = ip.id
     WHERE m.id = :id",
    ['id' => $id]
);

// Todos los equipos cubiertos por este mantenimiento (tabla puente)
$equipos_m = mantenimiento_equipos($id);
$num_equipos = count($equipos_m);

$cfg_estado = MANTENIMIENTO_ESTADOS[$m['estado']] ?? MANTENIMIENTO_ESTADOS['programado'];

$titulo_pagina = $m['titulo'];
$pagina_activa = 'mantenimientos';
require_once __DIR__ . '/config/header.php';
?>

<div class="max-w-5xl mx-auto animate-fade-in space-y-5"
     x-data="{ mostrarCompletar: false, mostrarCancelar: false }">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm">
        <a href="<?= url('mantenimientos.php') ?>" class="text-zinc-500 hover:text-bacal-700 flex items-center gap-1.5">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Mantenimientos
        </a>
        <i data-lucide="chevron-right" class="w-3 h-3 text-zinc-300"></i>
        <span class="text-zinc-700 font-medium">#<?= $id ?></span>
    </div>

    <!-- Header principal -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
        <div class="flex flex-col md:flex-row md:items-start gap-4">
            <!-- Fecha grande -->
            <div class="flex-shrink-0">
                <div class="w-20 h-24 rounded-xl text-white flex flex-col items-center justify-center"
                     style="background-color: <?= e($cfg_estado['color']) ?>">
                    <div class="text-[11px] font-bold uppercase opacity-90"><?= e(date('M', strtotime($m['fecha_programada']))) ?></div>
                    <div class="text-3xl font-extrabold leading-none mt-1"><?= e(date('d', strtotime($m['fecha_programada']))) ?></div>
                    <div class="text-[10px] mt-1 opacity-75"><?= e(date('Y', strtotime($m['fecha_programada']))) ?></div>
                </div>
                <?php if ($m['hora_programada']): ?>
                <div class="text-center mt-2 text-xs font-mono font-bold text-zinc-700"><?= e(date('H:i', strtotime($m['hora_programada']))) ?> hrs</div>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-2">
                    <?= badge_estado_mant($m['estado']) ?>
                    <?php if ($m['es_recurrente']): ?>
                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-purple-700 bg-purple-50 border border-purple-200 px-2 py-0.5 rounded">
                        <i data-lucide="repeat" class="w-3 h-3"></i> <?= e(fmt_recurrencia($m['recurrencia_tipo'], (int) $m['recurrencia_valor'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <h1 class="font-display text-2xl font-extrabold text-zinc-900 leading-tight"><?= e($m['titulo']) ?></h1>

                <?php if ($num_equipos <= 1): ?>
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-600 mt-3">
                    <a href="<?= url('equipo_ver.php?id=' . $m['equipo_id']) ?>" class="flex items-center gap-1 hover:text-bacal-700">
                        <i data-lucide="monitor" class="w-3.5 h-3.5"></i>
                        <span class="font-mono font-bold"><?= e($m['equipo_codigo']) ?></span>
                        <span class="text-zinc-500"><?= e($m['equipo_nombre']) ?></span>
                    </a>
                    <span class="flex items-center gap-1">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                        <?= e($m['sucursal_nombre']) ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="text-xs text-zinc-600 mt-3">
                    <div class="flex items-center gap-1 text-[10px] font-bold text-zinc-500 uppercase tracking-wide mb-1.5">
                        <i data-lucide="layers" class="w-3.5 h-3.5"></i> <?= $num_equipos ?> equipos
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <?php foreach ($equipos_m as $eq): ?>
                        <a href="<?= url('equipo_ver.php?id=' . $eq['id']) ?>"
                           class="inline-flex items-center gap-1 bg-zinc-50 border border-zinc-200 rounded px-2 py-0.5 hover:border-bacal-300 hover:text-bacal-700"
                           title="<?= e($eq['nombre']) ?> · <?= e($eq['sucursal_nombre']) ?>">
                            <span class="font-mono font-bold"><?= e($eq['codigo_inventario']) ?></span>
                            <span class="text-zinc-500 truncate max-w-[160px]"><?= e($eq['nombre']) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($m['descripcion']): ?>
        <div class="mt-5 pt-4 border-t border-zinc-100">
            <h3 class="text-[10px] uppercase font-bold text-zinc-500 mb-2">Descripción</h3>
            <p class="text-sm text-zinc-700 whitespace-pre-wrap"><?= e($m['descripcion']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Acciones según estado -->
    <?php if ($puede_editar): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
        <h3 class="text-xs font-bold text-zinc-600 uppercase tracking-wide mb-3">Acciones</h3>
        <div class="flex flex-wrap gap-2">
            <?php if (in_array($m['estado'], ['programado','proximo','vencido'], true)): ?>
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="iniciar">
                <button type="submit" class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold flex items-center gap-1.5">
                    <i data-lucide="play" class="w-4 h-4"></i> Iniciar trabajo
                </button>
            </form>
            <?php endif; ?>

            <?php if ($puede_completar): ?>
            <button @click="mostrarCompletar = true"
                    class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold flex items-center gap-1.5">
                <i data-lucide="check-circle-2" class="w-4 h-4"></i> Completar
            </button>
            <?php endif; ?>

            <?php if (in_array($m['estado'], ['programado','proximo','vencido'], true)): ?>
            <a href="<?= url('mantenimiento_editar.php?id=' . $id) ?>"
               class="px-4 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50 flex items-center gap-1.5">
                <i data-lucide="edit-3" class="w-4 h-4"></i> Editar
            </a>

            <?php if (!$m['incidencia_generada_id']): ?>
            <form method="POST" action="<?= url('api/mantenimiento_convertir_incidencia.php') ?>"
                  onsubmit="return confirm('Esto creará una nueva incidencia con los datos de este mantenimiento. ¿Continuar?');">
                <?= csrf_input() ?>
                <input type="hidden" name="mantenimiento_id" value="<?= $id ?>">
                <button type="submit" class="px-4 py-2 rounded-lg border border-amber-300 bg-amber-50 text-amber-800 text-sm font-medium hover:bg-amber-100 flex items-center gap-1.5">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i> Convertir en incidencia
                </button>
            </form>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($puede_cancelar): ?>
            <button @click="mostrarCancelar = true"
                    class="px-4 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-500 hover:text-bacal-700 hover:bg-zinc-50 flex items-center gap-1.5">
                <i data-lucide="x-circle" class="w-4 h-4"></i> Cancelar
            </button>
            <?php endif; ?>

            <?php if (in_array($m['estado'], ['completado','cancelado'], true)): ?>
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="reabrir">
                <button type="submit" class="px-4 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50 flex items-center gap-1.5">
                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Reabrir
                </button>
            </form>
            <?php endif; ?>

            <form method="POST" onsubmit="return confirm('¿Eliminar permanentemente este mantenimiento?');" class="ml-auto">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="eliminar">
                <button type="submit" class="px-3 py-2 rounded-lg text-zinc-400 hover:text-bacal-700 text-sm flex items-center gap-1.5">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> Eliminar
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Asignación y proveedor -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
            <h3 class="text-xs font-bold text-zinc-600 uppercase tracking-wide mb-3 flex items-center gap-1.5">
                <i data-lucide="user" class="w-3.5 h-3.5"></i> Técnico asignado
            </h3>
            <?php if ($m['asignado_nombre']): ?>
            <div class="font-semibold text-zinc-900"><?= e($m['asignado_nombre']) ?></div>
            <?php else: ?>
            <div class="text-xs text-zinc-400 italic">Sin asignar</div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
            <h3 class="text-xs font-bold text-zinc-600 uppercase tracking-wide mb-3 flex items-center gap-1.5">
                <i data-lucide="truck" class="w-3.5 h-3.5"></i> Proveedor externo
            </h3>
            <?php if ($m['proveedor_nombre']): ?>
            <a href="<?= url('proveedor_ver.php?id=' . $m['proveedor_id']) ?>" class="font-semibold text-zinc-900 hover:text-bacal-700">
                <?= e($m['proveedor_nombre']) ?>
            </a>
            <?php if ($m['proveedor_telefono']): ?>
            <div class="text-xs text-zinc-500 mt-1 flex items-center gap-1">
                <i data-lucide="phone" class="w-3 h-3"></i> <?= e($m['proveedor_telefono']) ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="text-xs text-zinc-400 italic">No aplica</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Resultado / detalles de completado -->
    <?php if ($m['estado'] === 'completado' || $m['estado'] === 'cancelado' || $m['resultado']): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-5">
        <h3 class="text-sm font-bold text-emerald-900 mb-3 flex items-center gap-1.5">
            <i data-lucide="<?= $m['estado'] === 'completado' ? 'check-circle-2' : 'file-text' ?>" class="w-4 h-4"></i>
            <?= $m['estado'] === 'completado' ? 'Trabajo realizado' : ($m['estado'] === 'cancelado' ? 'Cancelación' : 'Resultado') ?>
        </h3>
        <?php if ($m['resultado']): ?>
        <p class="text-sm text-emerald-900 whitespace-pre-wrap mb-3"><?= e($m['resultado']) ?></p>
        <?php endif; ?>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-xs text-emerald-800 pt-3 border-t border-emerald-200">
            <?php if ($m['fecha_completado']): ?>
            <div>
                <div class="font-bold opacity-75 text-[10px] uppercase mb-0.5">Fecha de completado</div>
                <?= e(fmt_fecha($m['fecha_completado'])) ?>
            </div>
            <?php endif; ?>
            <?php if ($m['realizado_nombre']): ?>
            <div>
                <div class="font-bold opacity-75 text-[10px] uppercase mb-0.5">Realizado por</div>
                <?= e($m['realizado_nombre']) ?>
            </div>
            <?php endif; ?>
            <?php if ($m['costo']): ?>
            <div>
                <div class="font-bold opacity-75 text-[10px] uppercase mb-0.5">Costo</div>
                <strong>$<?= number_format((float) $m['costo'], 2) ?></strong>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Si se convirtió a incidencia -->
    <?php if ($m['incidencia_generada_id']): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center gap-3">
        <i data-lucide="alert-circle" class="w-5 h-5 text-amber-700 flex-shrink-0"></i>
        <div class="flex-1 text-sm">
            Este mantenimiento se convirtió en la incidencia
            <a href="<?= url('incidencia_ver.php?id=' . $m['incidencia_generada_id']) ?>" class="font-mono font-bold text-amber-900 hover:underline">
                <?= e($m['padre_folio']) ?>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Metadatos al pie -->
    <div class="text-[10px] text-zinc-400 flex flex-wrap gap-x-4 gap-y-1">
        <span>Creado por <strong><?= e($m['creado_nombre'] ?? 'Sistema') ?></strong> <?= e(fmt_tiempo_relativo($m['creado_en'])) ?></span>
        <?php if ($m['mantenimiento_padre_id']): ?>
        <a href="<?= url('mantenimiento_ver.php?id=' . $m['mantenimiento_padre_id']) ?>" class="text-purple-700 hover:underline flex items-center gap-1">
            <i data-lucide="repeat" class="w-3 h-3"></i> Generado por recurrencia (ver padre)
        </a>
        <?php endif; ?>
    </div>

    <!-- ========================================================
         MODAL: Completar mantenimiento
         ======================================================== -->
    <div x-show="mostrarCompletar" x-cloak x-transition.opacity
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         @click.self="mostrarCompletar = false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="completar">

                <div class="px-6 py-4 border-b border-zinc-100 flex items-center justify-between">
                    <h3 class="font-display text-lg font-bold text-zinc-900 flex items-center gap-2">
                        <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i> Completar mantenimiento
                    </h3>
                    <button type="button" @click="mostrarCompletar = false" class="text-zinc-400 hover:text-zinc-700">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <?php if ((int) $m['es_recurrente'] === 1): ?>
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-3 text-xs text-purple-900 flex items-start gap-2">
                        <i data-lucide="repeat" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
                        <div>
                            <strong>Este mantenimiento es recurrente.</strong> Al completarlo, el sistema generará automáticamente el siguiente para dentro de <?= e(fmt_recurrencia($m['recurrencia_tipo'], (int) $m['recurrencia_valor'])) ?>.
                        </div>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">¿Qué se hizo?</label>
                        <textarea name="resultado" rows="4"
                                  placeholder="Describe el trabajo realizado, piezas reemplazadas, observaciones..."
                                  class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Costo total (opcional)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500 text-sm">$</span>
                            <input type="number" name="costo" step="0.01" min="0"
                                   placeholder="0.00"
                                   class="w-full pl-7 pr-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-zinc-100 flex justify-end gap-2">
                    <button type="button" @click="mostrarCompletar = false" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold flex items-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4"></i> Marcar como completado
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================
         MODAL: Cancelar
         ======================================================== -->
    <div x-show="mostrarCancelar" x-cloak x-transition.opacity
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         @click.self="mostrarCancelar = false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="cancelar">

                <div class="px-6 py-4 border-b border-zinc-100">
                    <h3 class="font-display text-lg font-bold text-zinc-900">Cancelar mantenimiento</h3>
                </div>

                <div class="p-6">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Motivo</label>
                    <textarea name="motivo_cancelacion" rows="3" required
                              placeholder="¿Por qué se cancela?"
                              class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></textarea>
                </div>

                <div class="px-6 py-4 border-t border-zinc-100 flex justify-end gap-2">
                    <button type="button" @click="mostrarCancelar = false" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Atrás</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Confirmar cancelación</button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>
