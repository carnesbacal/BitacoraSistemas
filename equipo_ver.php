<?php
/**
 * ============================================================================
 * equipo_ver.php - Vista detallada de un equipo
 * ============================================================================
 * Muestra toda la información del equipo organizada en tabs:
 *   - Información general (con depreciación)
 *   - Fotos
 *   - Mantenimientos (próximos + historial)
 *   - Transferencias
 *   - Incidencias relacionadas
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/equipos_helpers.php';

requerir_login();

$u_actual = usuario_actual();
$id = (int) input('id', 0);

$equipo = $id > 0 ? db_one(
    "SELECT e.*,
            s.nombre sucursal_nombre, s.codigo sucursal_codigo,
            a.nombre area_nombre, a.color area_color,
            p.nombre proveedor_nombre, p.telefono proveedor_telefono, p.email proveedor_email
     FROM equipos e
     INNER JOIN sucursales s ON e.sucursal_id = s.id
     LEFT JOIN areas a ON e.area_id = a.id
     LEFT JOIN proveedores p ON e.proveedor_id = p.id
     WHERE e.id = :id",
    ['id' => $id]
) : null;

if (!$equipo) {
    $titulo_pagina = 'Equipo no encontrado';
    require_once __DIR__ . '/config/header.php';
    ?>
    <div class="max-w-md mx-auto text-center py-20">
        <div class="w-16 h-16 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-4">
            <i data-lucide="search-x" class="w-8 h-8 text-zinc-400"></i>
        </div>
        <h2 class="font-display text-xl font-bold text-zinc-900 mb-2">Equipo no encontrado</h2>
        <a href="<?= url('admin/equipos.php') ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold rounded-lg">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Volver a equipos
        </a>
    </div>
    <?php
    require_once __DIR__ . '/config/footer.php';
    exit;
}

// Verificar permisos para ver este equipo
if (!puede_ver_equipo($equipo)) {
    flash_set('error', 'No tienes permiso para ver este equipo.');
    header('Location: ' . url('dashboard.php'));
    exit;
}

$puede_editar = puede_administrar_equipos();

// Cargar datos relacionados
$fotos = db_all(
    "SELECT * FROM equipo_fotos WHERE equipo_id = :id ORDER BY es_portada DESC, creado_en DESC",
    ['id' => $id]
);

// Mantenimientos en los que participa este equipo (vía tabla puente, para
// incluir también los mantenimientos agrupados que cubren varios equipos).
$mantenimientos_proximos = db_all(
    "SELECT m.*, u.nombre_completo asignado_nombre, p.nombre proveedor_nombre,
            (SELECT COUNT(*) FROM mantenimiento_equipos me2 WHERE me2.mantenimiento_id = m.id) AS num_equipos
     FROM mantenimientos m
     INNER JOIN mantenimiento_equipos me ON me.mantenimiento_id = m.id AND me.equipo_id = :id
     LEFT JOIN usuarios u ON m.asignado_a_id = u.id
     LEFT JOIN proveedores p ON m.proveedor_id = p.id
     WHERE m.estado IN ('programado','proximo','en_progreso')
     ORDER BY m.fecha_programada ASC, m.hora_programada ASC
     LIMIT 10",
    ['id' => $id]
);

$mantenimientos_historial = db_all(
    "SELECT m.*, u.nombre_completo realizado_nombre, p.nombre proveedor_nombre,
            (SELECT COUNT(*) FROM mantenimiento_equipos me2 WHERE me2.mantenimiento_id = m.id) AS num_equipos
     FROM mantenimientos m
     INNER JOIN mantenimiento_equipos me ON me.mantenimiento_id = m.id AND me.equipo_id = :id
     LEFT JOIN usuarios u ON m.realizado_por_id = u.id
     LEFT JOIN proveedores p ON m.proveedor_id = p.id
     WHERE m.estado IN ('completado','cancelado','vencido')
     ORDER BY m.fecha_completado DESC, m.fecha_programada DESC
     LIMIT 20",
    ['id' => $id]
);

$transferencias = db_all(
    "SELECT t.*,
            so.nombre origen_sucursal, sd.nombre destino_sucursal,
            ao.nombre origen_area, ad.nombre destino_area,
            u.nombre_completo realizado_por
     FROM equipo_transferencias t
     LEFT JOIN sucursales so ON t.sucursal_origen_id = so.id
     INNER JOIN sucursales sd ON t.sucursal_destino_id = sd.id
     LEFT JOIN areas ao ON t.area_origen_id = ao.id
     LEFT JOIN areas ad ON t.area_destino_id = ad.id
     LEFT JOIN usuarios u ON t.realizado_por_id = u.id
     WHERE t.equipo_id = :id
     ORDER BY t.fecha_transferencia DESC, t.creado_en DESC",
    ['id' => $id]
);

$incidencias = db_all(
    "SELECT i.id, i.folio, i.titulo, i.fecha_evento, i.fecha_resolucion,
            est.nombre estado_nombre, est.color estado_color, est.es_final,
            sev.nombre severidad_nombre, sev.color severidad_color
     FROM incidencias i
     INNER JOIN estados est ON i.estado_id = est.id
     INNER JOIN severidades sev ON i.severidad_id = sev.id
     WHERE i.equipo_id = :id
     ORDER BY i.fecha_evento DESC
     LIMIT 30",
    ['id' => $id]
);

// KPIs
$kpis = db_one(
    "SELECT
        (SELECT COUNT(*) FROM incidencias WHERE equipo_id = :id1) AS total_incidencias,
        (SELECT COUNT(*) FROM incidencias i INNER JOIN estados e ON i.estado_id = e.id
         WHERE i.equipo_id = :id2 AND e.es_final = 0) AS incidencias_abiertas,
        (SELECT COUNT(*) FROM mantenimientos m
          INNER JOIN mantenimiento_equipos me ON me.mantenimiento_id = m.id
          WHERE me.equipo_id = :id3 AND m.estado = 'completado') AS mant_completados,
        (SELECT COALESCE(SUM(costo), 0) FROM mantenimientos WHERE equipo_id = :id4 AND estado = 'completado') AS costo_total_mant",
    array_fill_keys(['id1','id2','id3','id4'], $id)
);

// Depreciación
$dep = calcular_depreciacion($equipo);

// Sucursales y áreas para el modal de transferencia
$sucursales = db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo=1 ORDER BY nombre");
$areas_lista = db_all("SELECT id, nombre FROM areas WHERE activo=1 ORDER BY nombre");

// Constantes
$MAX_FOTOS = 20;
$MAX_FOTO_BYTES = 5 * 1024 * 1024;

$titulo_pagina = $equipo['codigo_inventario'] . ' · ' . $equipo['nombre'];
$pagina_activa = 'equipos';
require_once __DIR__ . '/config/header.php';
?>

<div class="max-w-6xl mx-auto animate-fade-in space-y-5"
     x-data="{ tabActivo: 'info', mostrarTransferir: false }">

    <!-- Breadcrumb + acciones -->
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-sm">
            <a href="<?= $puede_editar ? url('admin/equipos.php') : url('dashboard.php') ?>"
               class="text-zinc-500 hover:text-bacal-700 flex items-center gap-1.5">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <?= $puede_editar ? 'Equipos' : 'Volver' ?>
            </a>
            <i data-lucide="chevron-right" class="w-3 h-3 text-zinc-300"></i>
            <span class="text-zinc-700 font-medium"><?= e($equipo['codigo_inventario']) ?></span>
        </div>

        <?php if ($puede_editar): ?>
        <div class="flex gap-2">
            <button type="button" @click="mostrarTransferir = true"
                    class="px-3 py-1.5 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50 flex items-center gap-1.5">
                <i data-lucide="arrow-right-left" class="w-4 h-4"></i> Transferir
            </button>
            <a href="<?= url('admin/equipos.php?accion=editar&id=' . $id) ?>"
               class="px-3 py-1.5 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50 flex items-center gap-1.5">
                <i data-lucide="edit-3" class="w-4 h-4"></i> Editar
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Header principal -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <div class="flex flex-col md:flex-row">
            <!-- Foto portada o placeholder -->
            <div class="md:w-48 h-48 md:h-auto bg-zinc-100 flex-shrink-0 relative">
                <?php
                $foto_portada = null;
                foreach ($fotos as $f) {
                    if ((int) $f['es_portada'] === 1) { $foto_portada = $f; break; }
                }
                if (!$foto_portada && !empty($fotos)) $foto_portada = $fotos[0];
                ?>
                <?php if ($foto_portada): ?>
                <img src="<?= e(url($foto_portada['ruta'])) ?>" alt="<?= e($equipo['nombre']) ?>"
                     class="w-full h-full object-cover">
                <?php else: ?>
                <div class="w-full h-full flex items-center justify-center">
                    <i data-lucide="monitor" class="w-16 h-16 text-zinc-300"></i>
                </div>
                <?php endif; ?>
            </div>

            <!-- Información principal -->
            <div class="flex-1 p-6">
                <div class="flex items-start justify-between gap-3 mb-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span class="font-mono text-sm font-bold text-zinc-500"><?= e($equipo['codigo_inventario']) ?></span>
                            <?= badge_estado_vida($equipo['estado_vida']) ?>
                            <?php if ($equipo['area_nombre']): ?>
                            <?= badge($equipo['area_nombre'], $equipo['area_color']) ?>
                            <?php endif; ?>
                        </div>
                        <h1 class="font-display text-2xl font-extrabold text-zinc-900 leading-tight"><?= e($equipo['nombre']) ?></h1>
                        <?php if ($equipo['marca'] || $equipo['modelo']): ?>
                        <p class="text-sm text-zinc-600 mt-1">
                            <?= e(trim(($equipo['marca'] ?? '') . ' ' . ($equipo['modelo'] ?? ''))) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-4 text-xs">
                    <div>
                        <div class="text-zinc-500 font-bold uppercase tracking-wide text-[10px]">Sucursal</div>
                        <div class="text-zinc-900 font-semibold mt-0.5"><?= e($equipo['sucursal_nombre']) ?></div>
                    </div>
                    <?php if ($equipo['ubicacion']): ?>
                    <div>
                        <div class="text-zinc-500 font-bold uppercase tracking-wide text-[10px]">Ubicación</div>
                        <div class="text-zinc-900 font-semibold mt-0.5"><?= e($equipo['ubicacion']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($equipo['proveedor_nombre']): ?>
                    <div>
                        <div class="text-zinc-500 font-bold uppercase tracking-wide text-[10px]">Proveedor</div>
                        <a href="<?= url('proveedor_ver.php?id=' . $equipo['proveedor_id']) ?>"
                           class="text-bacal-700 hover:underline font-semibold mt-0.5 block"><?= e($equipo['proveedor_nombre']) ?></a>
                    </div>
                    <?php endif; ?>
                    <?php if ($equipo['numero_serie']): ?>
                    <div>
                        <div class="text-zinc-500 font-bold uppercase tracking-wide text-[10px]">Núm. Serie</div>
                        <div class="text-zinc-900 font-mono text-[11px] mt-0.5"><?= e($equipo['numero_serie']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Incidencias totales</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= (int) $kpis['total_incidencias'] ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] uppercase tracking-wider font-bold mb-1 <?= (int) $kpis['incidencias_abiertas'] > 0 ? 'text-bacal-700' : 'text-zinc-500' ?>">Abiertas ahora</div>
            <div class="font-display text-2xl font-extrabold <?= (int) $kpis['incidencias_abiertas'] > 0 ? 'text-bacal-700' : 'text-zinc-900' ?>"><?= (int) $kpis['incidencias_abiertas'] ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Mantenimientos</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= (int) $kpis['mant_completados'] ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Gasto en mant.</div>
            <div class="font-display text-xl font-extrabold text-zinc-900">$<?= number_format((float) $kpis['costo_total_mant'], 0) ?></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-zinc-200">
        <div class="flex gap-1 -mb-px overflow-x-auto">
            <?php
            $tabs = [
                'info' => ['Información', 'info', null],
                'fotos' => ['Fotos', 'image', count($fotos)],
                'mantenimientos' => ['Mantenimientos', 'wrench', count($mantenimientos_proximos)],
                'transferencias' => ['Transferencias', 'arrow-right-left', count($transferencias)],
                'incidencias' => ['Historial de incidencias', 'history', count($incidencias)],
            ];
            foreach ($tabs as $key => [$label, $icon, $count]):
            ?>
            <button type="button" @click="tabActivo = '<?= $key ?>'"
                    class="flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors whitespace-nowrap"
                    :class="tabActivo === '<?= $key ?>' ? 'border-bacal-700 text-bacal-700' : 'border-transparent text-zinc-500 hover:text-zinc-700'">
                <i data-lucide="<?= $icon ?>" class="w-4 h-4"></i>
                <?= e($label) ?>
                <?php if ($count !== null && $count > 0): ?>
                <span class="text-[10px] font-bold bg-zinc-100 text-zinc-700 px-1.5 py-0.5 rounded ml-1"><?= $count ?></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- TAB: Información -->
    <div x-show="tabActivo === 'info'" x-cloak class="space-y-5">

        <!-- Datos de compra y vida útil -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="shopping-cart" class="w-4 h-4 text-bacal-700"></i> Datos de compra y vida útil
            </h3>

            <?php if (!$equipo['fecha_compra'] && !$equipo['costo_compra'] && !$equipo['vida_util_meses']): ?>
            <div class="text-center py-6 text-xs text-zinc-400 italic">
                Sin datos de compra registrados.
                <?php if ($puede_editar): ?>
                <a href="<?= url('admin/equipos.php?accion=editar&id=' . $id) ?>" class="text-bacal-700 hover:underline font-semibold">Agrégalos editando el equipo →</a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
                <div>
                    <div class="text-[10px] text-zinc-500 uppercase font-bold mb-1">Fecha de compra</div>
                    <div class="text-sm text-zinc-900 font-semibold"><?= $equipo['fecha_compra'] ? e(date('d/m/Y', strtotime($equipo['fecha_compra']))) : '—' ?></div>
                </div>
                <div>
                    <div class="text-[10px] text-zinc-500 uppercase font-bold mb-1">Costo de compra</div>
                    <div class="text-sm text-zinc-900 font-semibold"><?= $equipo['costo_compra'] !== null ? '$' . number_format((float) $equipo['costo_compra'], 2) : '—' ?></div>
                </div>
                <div>
                    <div class="text-[10px] text-zinc-500 uppercase font-bold mb-1">Vida útil estimada</div>
                    <div class="text-sm text-zinc-900 font-semibold"><?= $equipo['vida_util_meses'] ? e(fmt_meses_humano((int) $equipo['vida_util_meses'])) : '—' ?></div>
                </div>
                <div>
                    <div class="text-[10px] text-zinc-500 uppercase font-bold mb-1">Antigüedad</div>
                    <div class="text-sm text-zinc-900 font-semibold">
                        <?php
                        if ($equipo['fecha_compra']) {
                            $compra = new DateTime($equipo['fecha_compra']);
                            $hoy = new DateTime();
                            $diff = $compra->diff($hoy);
                            $meses_total = ($diff->y * 12) + $diff->m;
                            echo e(fmt_meses_humano($meses_total));
                        } else echo '—';
                        ?>
                    </div>
                </div>
            </div>

            <?php if ($dep): ?>
            <!-- Depreciación calculada -->
            <div class="border-t border-zinc-100 pt-5">
                <h4 class="text-xs font-bold text-zinc-700 uppercase tracking-wide mb-3">Depreciación lineal</h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Valor actual -->
                    <div class="bg-gradient-to-br from-emerald-50 to-white border border-emerald-200 rounded-lg p-4">
                        <div class="text-[10px] text-emerald-700 uppercase font-bold mb-1">Valor estimado actual</div>
                        <div class="font-display text-3xl font-extrabold text-emerald-700">
                            $<?= number_format($dep['valor_actual'], 2) ?>
                        </div>
                        <div class="text-[11px] text-emerald-600 mt-1">
                            Depreciado: $<?= number_format($dep['depreciacion_total'], 2) ?> (<?= $dep['porcentaje_depreciado'] ?>%)
                        </div>
                    </div>

                    <!-- Barra de vida útil -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] text-zinc-600 uppercase font-bold">Vida útil consumida</span>
                            <span class="text-sm font-bold <?= $dep['porcentaje_vida_usada'] >= 80 ? 'text-bacal-700' : ($dep['porcentaje_vida_usada'] >= 50 ? 'text-amber-700' : 'text-emerald-700') ?>">
                                <?= $dep['porcentaje_vida_usada'] ?>%
                            </span>
                        </div>
                        <div class="w-full bg-zinc-100 rounded-full h-3 overflow-hidden">
                            <div class="h-full transition-all"
                                 style="width: <?= min(100, $dep['porcentaje_vida_usada']) ?>%; background-color: <?= $dep['porcentaje_vida_usada'] >= 80 ? '#C8102E' : ($dep['porcentaje_vida_usada'] >= 50 ? '#D97706' : '#16A34A') ?>"></div>
                        </div>
                        <div class="text-[11px] text-zinc-500 mt-2">
                            <?php if ($dep['agotado']): ?>
                            <strong class="text-bacal-700">Vida útil agotada</strong> · El equipo opera más allá de su vida útil estimada.
                            <?php else: ?>
                            Restan <strong><?= e(fmt_meses_humano($dep['meses_restantes'])) ?></strong> de vida útil estimada.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <p class="text-[10px] text-zinc-400 italic mt-3">
                    Cálculo lineal con valor de rescate del 10%. Es una estimación, no un valor contable oficial.
                </p>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Estado de baja -->
        <?php if ($equipo['estado_vida'] === 'dado_de_baja'): ?>
        <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-5">
            <h3 class="font-display text-sm font-bold text-zinc-900 mb-2 flex items-center gap-2">
                <i data-lucide="archive" class="w-4 h-4"></i> Equipo dado de baja
            </h3>
            <div class="grid grid-cols-2 gap-3 text-xs text-zinc-700">
                <div>
                    <div class="text-[10px] text-zinc-500 uppercase font-bold mb-1">Fecha de baja</div>
                    <?= $equipo['fecha_baja'] ? e(date('d/m/Y', strtotime($equipo['fecha_baja']))) : 'No registrada' ?>
                </div>
                <div>
                    <div class="text-[10px] text-zinc-500 uppercase font-bold mb-1">Motivo</div>
                    <?= $equipo['motivo_baja'] ? e($equipo['motivo_baja']) : '—' ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notas -->
        <?php if ($equipo['notas']): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
            <h3 class="text-xs font-bold text-amber-800 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                <i data-lucide="sticky-note" class="w-3.5 h-3.5"></i> Notas
            </h3>
            <p class="text-xs text-amber-900 whitespace-pre-wrap"><?= e($equipo['notas']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Fotos -->
    <div x-show="tabActivo === 'fotos'" x-cloak>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6"
             x-data="galeriaEquipo()">

            <div class="flex items-center justify-between mb-4">
                <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                    <i data-lucide="image" class="w-4 h-4 text-bacal-700"></i> Galería
                    <span class="text-xs font-normal text-zinc-500">(<?= count($fotos) ?>/<?= $MAX_FOTOS ?>)</span>
                </h3>

                <?php if ($puede_editar && count($fotos) < $MAX_FOTOS): ?>
                <input type="file" x-ref="inputFotos" accept="image/jpeg,image/png,image/webp" multiple
                       @change="subirVarias($event.target.files)" class="hidden">
                <button type="button" @click="$refs.inputFotos.click()"
                        :disabled="subiendo"
                        class="px-3 py-1.5 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5 disabled:opacity-50">
                    <template x-if="!subiendo">
                        <span class="flex items-center gap-1.5"><i data-lucide="upload" class="w-3.5 h-3.5"></i> Subir fotos</span>
                    </template>
                    <template x-if="subiendo">
                        <span class="flex items-center gap-1.5"><i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> <span x-text="progreso"></span></span>
                    </template>
                </button>
                <?php endif; ?>
            </div>

            <p class="text-[11px] text-zinc-500 mb-4">Máximo <?= $MAX_FOTOS ?> fotos · Cada una hasta 5 MB · JPG/PNG/WebP. Útiles para registrar el estado del equipo antes/después de mantenimientos.</p>

            <div x-show="error" x-cloak class="mb-4 px-3 py-2 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-xs"
                 x-text="error"></div>

            <?php if (empty($fotos)): ?>
            <div class="border-2 border-dashed border-zinc-200 rounded-lg py-12 text-center">
                <i data-lucide="image-off" class="w-10 h-10 mx-auto text-zinc-300 mb-2"></i>
                <p class="text-sm text-zinc-500">Aún no hay fotos de este equipo.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                <?php foreach ($fotos as $f): ?>
                <div class="relative group">
                    <a href="<?= e(url($f['ruta'])) ?>" target="_blank" rel="noopener"
                       class="block aspect-square rounded-lg overflow-hidden bg-zinc-100 border border-zinc-200">
                        <img src="<?= e(url($f['ruta'])) ?>" alt="<?= e((string) $f['descripcion']) ?>"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
                    </a>
                    <?php if ((int) $f['es_portada'] === 1): ?>
                    <div class="absolute top-2 left-2 bg-amber-400 text-amber-900 text-[10px] font-bold px-1.5 py-0.5 rounded flex items-center gap-1">
                        <i data-lucide="star" class="w-3 h-3 fill-current"></i> Portada
                    </div>
                    <?php endif; ?>
                    <?php if ($puede_editar): ?>
                    <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                        <?php if ((int) $f['es_portada'] !== 1): ?>
                        <form method="POST" action="<?= url('api/equipo_foto_portada.php') ?>">
                            <?= csrf_input() ?>
                            <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                            <button type="submit" class="p-1 rounded bg-white/90 backdrop-blur shadow text-amber-600 hover:bg-white" title="Marcar como portada">
                                <i data-lucide="star" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" action="<?= url('api/equipo_foto_eliminar.php') ?>"
                              onsubmit="return confirm('¿Eliminar esta foto?');">
                            <?= csrf_input() ?>
                            <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                            <button type="submit" class="p-1 rounded bg-white/90 backdrop-blur shadow text-bacal-700 hover:bg-white" title="Eliminar">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    <?php if ($f['descripcion']): ?>
                    <p class="text-[10px] text-zinc-500 mt-1 truncate" title="<?= e($f['descripcion']) ?>"><?= e($f['descripcion']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Mantenimientos -->
    <div x-show="tabActivo === 'mantenimientos'" x-cloak class="space-y-5">
        <!-- Próximos -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                    <i data-lucide="calendar-clock" class="w-4 h-4 text-bacal-700"></i> Próximos mantenimientos
                </h3>
                <?php if ($puede_editar): ?>
                <a href="<?= url('mantenimiento_nuevo.php?equipo_id=' . $id) ?>"
                   class="px-3 py-1.5 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Programar
                </a>
                <?php endif; ?>
            </div>

            <?php if (empty($mantenimientos_proximos)): ?>
            <p class="text-xs text-zinc-400 italic text-center py-6">No hay mantenimientos programados.</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($mantenimientos_proximos as $m):
                    $dias_a = (strtotime($m['fecha_programada']) - strtotime(date('Y-m-d'))) / 86400;
                    $color_dias = $dias_a < 0 ? '#DC2626' : ($dias_a <= 3 ? '#D97706' : '#0EA5E9');
                ?>
                <a href="<?= url('mantenimiento_ver.php?id=' . $m['id']) ?>" class="block border border-zinc-200 rounded-lg p-3 hover:bg-zinc-50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg flex flex-col items-center justify-center text-white flex-shrink-0"
                             style="background-color: <?= $color_dias ?>">
                            <div class="text-[10px] font-bold uppercase"><?= e(date('M', strtotime($m['fecha_programada']))) ?></div>
                            <div class="text-base font-extrabold leading-none"><?= e(date('d', strtotime($m['fecha_programada']))) ?></div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm text-zinc-900 truncate"><?= e($m['titulo']) ?></div>
                            <div class="text-[11px] text-zinc-500 mt-0.5 flex flex-wrap gap-x-3">
                                <?php if ($m['asignado_nombre']): ?>
                                <span><i data-lucide="user" class="w-3 h-3 inline -mt-0.5"></i> <?= e($m['asignado_nombre']) ?></span>
                                <?php endif; ?>
                                <?php if ($m['proveedor_nombre']): ?>
                                <span><i data-lucide="truck" class="w-3 h-3 inline -mt-0.5"></i> <?= e($m['proveedor_nombre']) ?></span>
                                <?php endif; ?>
                                <?php if ($m['es_recurrente']): ?>
                                <span class="text-purple-700"><i data-lucide="repeat" class="w-3 h-3 inline -mt-0.5"></i> Cada <?= (int) $m['recurrencia_valor'] ?> <?= e($m['recurrencia_tipo']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase" style="color: <?= $color_dias ?>; background-color: <?= $color_dias ?>15">
                            <?= $dias_a < 0 ? 'Vencido' : ($dias_a == 0 ? 'Hoy' : "En " . (int) $dias_a . "d") ?>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Historial -->
        <?php if (!empty($mantenimientos_historial)): ?>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-bacal-700"></i> Historial
            </h3>
            <div class="space-y-2">
                <?php foreach ($mantenimientos_historial as $m): ?>
                <a href="<?= url('mantenimiento_ver.php?id=' . $m['id']) ?>" class="flex items-center gap-3 p-3 border border-zinc-100 rounded-lg hover:bg-zinc-50">
                    <i data-lucide="<?= $m['estado'] === 'completado' ? 'check-circle-2' : ($m['estado'] === 'cancelado' ? 'x-circle' : 'alert-circle') ?>"
                       class="w-5 h-5 flex-shrink-0 <?= $m['estado'] === 'completado' ? 'text-emerald-600' : ($m['estado'] === 'cancelado' ? 'text-zinc-400' : 'text-bacal-600') ?>"></i>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm text-zinc-900 truncate"><?= e($m['titulo']) ?></div>
                        <div class="text-[11px] text-zinc-500 mt-0.5">
                            <?= e(date('d/M/Y', strtotime($m['fecha_completado'] ?: $m['fecha_programada']))) ?>
                            <?php if ($m['realizado_nombre']): ?>
                            · por <?= e($m['realizado_nombre']) ?>
                            <?php endif; ?>
                            <?php if ($m['costo']): ?>
                            · $<?= number_format((float) $m['costo'], 0) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: Transferencias -->
    <div x-show="tabActivo === 'transferencias'" x-cloak>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                    <i data-lucide="arrow-right-left" class="w-4 h-4 text-bacal-700"></i> Historial de transferencias
                </h3>
                <?php if ($puede_editar): ?>
                <button type="button" @click="mostrarTransferir = true"
                        class="px-3 py-1.5 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Nueva transferencia
                </button>
                <?php endif; ?>
            </div>

            <?php if (empty($transferencias)): ?>
            <p class="text-xs text-zinc-400 italic text-center py-6">Este equipo no ha sido transferido.</p>
            <?php else: ?>
            <div class="relative pl-6">
                <div class="absolute left-2.5 top-2 bottom-2 w-0.5 bg-zinc-200"></div>
                <?php foreach ($transferencias as $t): ?>
                <div class="relative pb-5 last:pb-0">
                    <div class="absolute -left-4 top-1.5 w-3 h-3 rounded-full bg-bacal-700 border-2 border-white shadow"></div>
                    <div class="bg-zinc-50 rounded-lg p-3 border border-zinc-200">
                        <div class="flex items-center gap-2 flex-wrap text-sm mb-1">
                            <?php if ($t['origen_sucursal']): ?>
                            <span class="font-semibold text-zinc-900"><?= e($t['origen_sucursal']) ?></span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-zinc-400"></i>
                            <?php endif; ?>
                            <span class="font-semibold text-bacal-700"><?= e($t['destino_sucursal']) ?></span>
                        </div>
                        <?php if ($t['origen_area'] || $t['destino_area']): ?>
                        <div class="text-[11px] text-zinc-600 mb-1">
                            Área:
                            <?= e($t['origen_area'] ?: '—') ?> → <?= e($t['destino_area'] ?: '—') ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($t['motivo']): ?>
                        <div class="text-xs text-zinc-700 mt-1"><?= e($t['motivo']) ?></div>
                        <?php endif; ?>
                        <div class="text-[10px] text-zinc-400 mt-2">
                            <?= e(date('d/M/Y', strtotime($t['fecha_transferencia']))) ?>
                            <?php if ($t['realizado_por']): ?> · por <?= e($t['realizado_por']) ?><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Incidencias -->
    <div x-show="tabActivo === 'incidencias'" x-cloak>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 text-bacal-700"></i> Historial de incidencias
            </h3>
            <?php if (empty($incidencias)): ?>
            <p class="text-xs text-zinc-400 italic text-center py-6">Este equipo no tiene incidencias registradas.</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($incidencias as $inc): ?>
                <a href="<?= url('incidencia_ver.php?id=' . $inc['id']) ?>"
                   class="block border border-zinc-200 rounded-lg p-3 hover:bg-zinc-50 transition-colors">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="font-mono text-[10px] font-bold text-zinc-500"><?= e($inc['folio']) ?></span>
                                <?= badge($inc['severidad_nombre'], $inc['severidad_color']) ?>
                                <?= badge($inc['estado_nombre'], $inc['estado_color']) ?>
                            </div>
                            <div class="font-semibold text-sm text-zinc-900 truncate"><?= e($inc['titulo']) ?></div>
                            <div class="text-[10px] text-zinc-500 mt-1">
                                <?= e(fmt_fecha($inc['fecha_evento'], false)) ?>
                                <?php if ($inc['fecha_resolucion']): ?>
                                · Resuelta <?= e(fmt_tiempo_relativo($inc['fecha_resolucion'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <i data-lucide="arrow-up-right" class="w-4 h-4 text-zinc-300 flex-shrink-0 mt-1"></i>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========================================================
         Modal: Transferir equipo
         ======================================================== -->
    <?php if ($puede_editar): ?>
    <div x-show="mostrarTransferir" x-cloak
         x-transition.opacity
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         @click.self="mostrarTransferir = false">

        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <form method="POST" action="<?= url('api/equipo_transferir.php') ?>">
                <?= csrf_input() ?>
                <input type="hidden" name="equipo_id" value="<?= $id ?>">

                <div class="px-6 py-4 border-b border-zinc-100 flex items-center justify-between">
                    <h3 class="font-display text-lg font-bold text-zinc-900 flex items-center gap-2">
                        <i data-lucide="arrow-right-left" class="w-5 h-5 text-bacal-700"></i>
                        Transferir equipo
                    </h3>
                    <button type="button" @click="mostrarTransferir = false" class="text-zinc-400 hover:text-zinc-700">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="bg-zinc-50 rounded-lg p-3 text-xs">
                        <div class="text-zinc-500 mb-1">Equipo:</div>
                        <div class="font-semibold text-zinc-900"><?= e($equipo['codigo_inventario']) ?> · <?= e($equipo['nombre']) ?></div>
                        <div class="text-zinc-500 mt-2">Ubicación actual:</div>
                        <div class="font-semibold text-zinc-900"><?= e($equipo['sucursal_nombre']) ?><?php if ($equipo['area_nombre']): ?> · <?= e($equipo['area_nombre']) ?><?php endif; ?></div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nueva sucursal *</label>
                        <select name="sucursal_destino_id" required class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                            <option value="">— Selecciona —</option>
                            <?php foreach ($sucursales as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= (int) $s['id'] === (int) $equipo['sucursal_id'] ? 'disabled' : '' ?>>
                                <?= e($s['nombre']) ?> (<?= e($s['codigo']) ?>)
                                <?= (int) $s['id'] === (int) $equipo['sucursal_id'] ? '— actual' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nueva área (opcional)</label>
                        <select name="area_destino_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                            <option value="">— Sin área —</option>
                            <?php foreach ($areas_lista as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= e($a['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Fecha de transferencia *</label>
                        <input type="date" name="fecha_transferencia" required value="<?= e(date('Y-m-d')) ?>"
                               class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Motivo</label>
                        <input type="text" name="motivo" maxlength="255"
                               placeholder="ej. Reasignación de área, cierre de sucursal, préstamo temporal"
                               class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Notas adicionales</label>
                        <textarea name="notas" rows="3" maxlength="500"
                                  class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></textarea>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-zinc-100 flex justify-end gap-2">
                    <button type="button" @click="mostrarTransferir = false" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
                    <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
                        <i data-lucide="arrow-right-left" class="w-4 h-4"></i> Confirmar transferencia
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function galeriaEquipo() {
    return {
        subiendo: false,
        progreso: 'Subiendo...',
        error: '',

        async subirVarias(archivos) {
            if (!archivos || archivos.length === 0) return;
            this.error = '';

            const arr = Array.from(archivos);
            const max = <?= $MAX_FOTOS - count($fotos) ?>;
            if (arr.length > max) {
                this.error = `Solo puedes subir ${max} foto(s) más (límite total: ${<?= $MAX_FOTOS ?>}).`;
                return;
            }

            this.subiendo = true;
            const fd = new FormData();
            fd.append('_csrf', '<?= e(csrf_token()) ?>');
            fd.append('equipo_id', '<?= $id ?>');
            arr.forEach(f => fd.append('fotos[]', f));

            this.progreso = `Subiendo ${arr.length} foto(s)…`;

            try {
                const resp = await fetch('<?= url('api/equipo_fotos_subir.php') ?>', {
                    method: 'POST', body: fd, credentials: 'same-origin'
                });
                const data = await resp.json();
                if (data.ok) {
                    window.location.reload();
                } else {
                    this.error = data.error || 'Error al subir.';
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
