<?php
/**
 * ============================================================================
 * reportes/reporte_costos.php - Análisis de costos de sistemas
 * ============================================================================
 * Reporte completo y filtrable de costos:
 *   - KPIs: total, externo (proveedores), interno (materiales + mano de obra), promedio
 *   - Tendencia por día / semana / mes
 *   - Desglose interno vs externo
 *   - Ranking de incidencias más caras
 *   - Ranking de proveedores más caros
 *   - Costos por sucursal
 *   - Exportación CSV
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/reportes_helpers.php';
require_once __DIR__ . '/../config/incidencia_costos_helpers.php';

// El análisis agregado de costos incluye mano de obra interna → solo admin
requerir_permiso('administrar');

$periodo = resolver_periodo();
[$sucursal_filtro, $sucursales_lista, $where_sucursal, $params_sucursal] = resolver_filtro_sucursal();

// Agrupación de tendencia
$agrupar = (string) input('agrupar', 'mes');
if (!in_array($agrupar, ['dia', 'semana', 'mes'], true)) $agrupar = 'mes';

$es_exportacion = (input('exportar') === 'csv');

// ----------------------------------------------------------------------------
// Cargar datos
// ----------------------------------------------------------------------------
$resumen     = costos_resumen_periodo($periodo['desde'], $periodo['hasta'], $where_sucursal, $params_sucursal);
$ranking_inc = costos_ranking_incidencias($periodo['desde'], $periodo['hasta'], 20, $where_sucursal, $params_sucursal);
$ranking_prov= costos_ranking_proveedores($periodo['desde'], $periodo['hasta'], 20, $where_sucursal, $params_sucursal);
$tendencia   = costos_tendencia($periodo['desde'], $periodo['hasta'], $agrupar, $where_sucursal, $params_sucursal);
$por_sucursal= $sucursal_filtro ? [] : costos_por_sucursal($periodo['desde'], $periodo['hasta']);

// ----------------------------------------------------------------------------
// Exportación CSV
// ----------------------------------------------------------------------------
if ($es_exportacion) {
    csv_iniciar('reporte_costos_' . date('Ymd_His') . '.csv');
    csv_fila(['REPORTE DE COSTOS DE SISTEMAS']);
    csv_fila(['Período:', $periodo['etiqueta']]);
    csv_fila(['Generado:', date('Y-m-d H:i')]);
    csv_fila(['']);

    csv_fila(['RESUMEN']);
    csv_fila(['Costo total', number_format($resumen['total'], 2)]);
    csv_fila(['Costo externo (proveedores)', number_format($resumen['externo'], 2)]);
    csv_fila(['  Mano de obra', number_format($resumen['mano_obra'], 2)]);
    csv_fila(['  Materiales proveedor', number_format($resumen['materiales'], 2)]);
    csv_fila(['Costo interno (materiales + mano de obra)', number_format($resumen['interno'], 2)]);
    csv_fila(['  Materiales comprados', number_format($resumen['materiales_comprados'], 2)]);
    csv_fila(['Incidencias con costo', $resumen['con_costo']]);
    csv_fila(['Incidencias con proveedor', $resumen['con_proveedor']]);
    csv_fila(['Costo promedio por incidencia', number_format($resumen['promedio'], 2)]);
    csv_fila(['']);

    csv_fila(['INCIDENCIAS MÁS CARAS']);
    csv_fila(['Folio', 'Título', 'Sucursal', 'Proveedor', 'Mano obra', 'Materiales prov.', 'Materiales comprados', 'Total']);
    foreach ($ranking_inc as $r) {
        csv_fila([
            $r['folio'], $r['titulo'], $r['sucursal_nombre'],
            $r['proveedor_nombre'] ?? $r['proveedor_externo_info'] ?? '',
            number_format((float) $r['mano_obra'], 2),
            number_format((float) $r['materiales'], 2),
            number_format((float) $r['materiales_comprados'], 2),
            number_format((float) $r['total'], 2),
        ]);
    }
    csv_fila(['']);

    csv_fila(['PROVEEDORES MÁS CAROS']);
    csv_fila(['Proveedor', 'Servicio', 'Incidencias', 'Mano obra', 'Materiales', 'Total']);
    foreach ($ranking_prov as $p) {
        csv_fila([
            $p['nombre'], $p['servicio'] ?? '', $p['num_incidencias'],
            number_format((float) $p['mano_obra'], 2),
            number_format((float) $p['materiales'], 2),
            number_format((float) $p['total'], 2),
        ]);
    }

    if (!empty($por_sucursal)) {
        csv_fila(['']);
        csv_fila(['COSTOS POR SUCURSAL']);
        csv_fila(['Sucursal', 'Incidencias', 'Externo', 'Interno', 'Total']);
        foreach ($por_sucursal as $s) {
            csv_fila([
                $s['nombre'], $s['num_incidencias'],
                number_format((float) $s['externo'], 2),
                number_format((float) $s['interno'], 2),
                number_format((float) $s['total'], 2),
            ]);
        }
    }
    exit;
}

// Datos para gráficas
$tend_labels = array_map(fn($t) => $t['label'], $tendencia);
$tend_externo = array_map(fn($t) => round((float) $t['externo'], 2), $tendencia);
$tend_interno = array_map(fn($t) => round((float) $t['interno'], 2), $tendencia);

$titulo_pagina = 'Reporte de costos';
$pagina_activa = 'reportes';
require_once __DIR__ . '/../config/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
@media print { .no-print { display: none !important; } aside, header.h-16 { display: none !important; } body { background: white !important; } }
</style>

<div class="animate-fade-in space-y-5">

    <!-- Encabezado -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 no-print">
        <div class="flex items-center gap-3">
            <a href="<?= url('reportes/reportes.php') ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h2 class="font-display text-2xl font-extrabold text-zinc-900">Reporte de costos</h2>
                <p class="text-xs text-zinc-500"><?= e($periodo['etiqueta']) ?></p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="flex items-center gap-1.5 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                <i data-lucide="printer" class="w-4 h-4"></i> Imprimir
            </button>
            <a href="<?= url('reportes/reporte_costos.php?' . http_build_query(array_merge($_GET, ['exportar' => 'csv']))) ?>"
               class="flex items-center gap-1.5 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                <i data-lucide="download" class="w-4 h-4"></i> CSV
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="bg-white rounded-xl border border-zinc-200 shadow-sm p-4 no-print">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[10px] font-bold text-zinc-600 mb-1 uppercase">Período</label>
                <select name="periodo" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <?php $p_val = input('periodo', 'mes_actual');
                    foreach (['hoy'=>'Hoy','semana_actual'=>'Semana','mes_actual'=>'Mes actual','mes_anterior'=>'Mes anterior','trimestre'=>'90 días','año_actual'=>'Año','personalizado'=>'Personalizado'] as $k=>$l): ?>
                    <option value="<?= $k ?>" <?= $p_val === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (input('periodo') === 'personalizado'): ?>
            <div>
                <label class="block text-[10px] font-bold text-zinc-600 mb-1 uppercase">Desde</label>
                <input type="date" name="desde" value="<?= e(input('desde', date('Y-m-01'))) ?>" onchange="this.form.submit()"
                       class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-zinc-600 mb-1 uppercase">Hasta</label>
                <input type="date" name="hasta" value="<?= e(input('hasta', date('Y-m-d'))) ?>" onchange="this.form.submit()"
                       class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <?php endif; ?>

            <div>
                <label class="block text-[10px] font-bold text-zinc-600 mb-1 uppercase">Agrupar tendencia</label>
                <select name="agrupar" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <?php foreach (['dia'=>'Por día','semana'=>'Por semana','mes'=>'Por mes'] as $k=>$l): ?>
                    <option value="<?= $k ?>" <?= $agrupar === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($sucursales_lista)): ?>
            <div class="ml-auto">
                <label class="block text-[10px] font-bold text-zinc-600 mb-1 uppercase">Sucursal</label>
                <select name="sucursal" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">Todas</option>
                    <?php foreach ($sucursales_lista as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $sucursal_filtro == $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <!-- KPIs -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gradient-to-br from-bacal-50 to-white rounded-xl border border-bacal-200 shadow-sm p-5">
            <div class="text-[11px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Costo total</div>
            <div class="font-display text-3xl font-extrabold text-bacal-700 leading-none"><?= e(fmt_dinero_corto($resumen['total'])) ?></div>
            <div class="text-[10px] text-zinc-400 mt-1.5"><?= e(fmt_dinero($resumen['total'])) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
            <div class="text-[11px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Proveedores</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900 leading-none"><?= e(fmt_dinero_corto($resumen['externo'])) ?></div>
            <div class="text-[10px] text-zinc-400 mt-1.5"><?= $resumen['pct_externo'] ?>% · MO <?= e(fmt_dinero_corto($resumen['mano_obra'])) ?> + Mat <?= e(fmt_dinero_corto($resumen['materiales'])) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
            <div class="text-[11px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Costo interno</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900 leading-none"><?= e(fmt_dinero_corto($resumen['interno'])) ?></div>
            <div class="text-[10px] text-zinc-400 mt-1.5"><?= $resumen['pct_interno'] ?>% · Mat. comprados <?= e(fmt_dinero_corto($resumen['materiales_comprados'])) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
            <div class="text-[11px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Promedio / incidencia</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900 leading-none"><?= e(fmt_dinero_corto($resumen['promedio'])) ?></div>
            <div class="text-[10px] text-zinc-400 mt-1.5"><?= $resumen['con_costo'] ?> con costo · <?= $resumen['con_proveedor'] ?> con proveedor</div>
        </div>
    </div>

    <!-- Tendencia + Desglose -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-3">Tendencia de costos</h3>
            <?php if (array_sum($tend_externo) + array_sum($tend_interno) > 0): ?>
            <div class="h-64"><canvas id="chartTendencia"></canvas></div>
            <?php else: ?>
            <div class="h-64 flex items-center justify-center text-sm text-zinc-400">Sin costos en el período.</div>
            <?php endif; ?>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-3">Externo vs Interno</h3>
            <?php if ($resumen['total'] > 0): ?>
            <div class="h-48 flex items-center justify-center"><canvas id="chartDesglose"></canvas></div>
            <div class="mt-4 space-y-2 text-xs">
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-bacal-600"></span> Proveedores</span>
                    <span class="font-semibold"><?= e(fmt_dinero($resumen['externo'])) ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-zinc-400"></span> Interno</span>
                    <span class="font-semibold"><?= e(fmt_dinero($resumen['interno'])) ?></span>
                </div>
            </div>
            <?php else: ?>
            <div class="h-48 flex items-center justify-center text-sm text-zinc-400">Sin datos.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ranking incidencias más caras -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-zinc-100 flex items-center gap-2">
            <i data-lucide="trending-up" class="w-5 h-5 text-bacal-700"></i>
            <h3 class="font-display text-base font-bold text-zinc-900">Incidencias más caras</h3>
            <span class="text-xs text-zinc-500">(<?= count($ranking_inc) ?>)</span>
        </div>
        <?php if (empty($ranking_inc)): ?>
        <div class="px-5 py-10 text-center text-sm text-zinc-400">Sin incidencias con costo en el período.</div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 border-b border-zinc-200">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider w-8">#</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Incidencia</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Atendió</th>
                        <th class="px-4 py-2.5 text-right text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Mano obra</th>
                        <th class="px-4 py-2.5 text-right text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Materiales</th>
                        <th class="px-4 py-2.5 text-right text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Mat. comp.</th>
                        <th class="px-4 py-2.5 text-right text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($ranking_inc as $idx => $r): ?>
                    <tr class="hover:bg-zinc-50">
                        <td class="px-4 py-2.5 text-zinc-400 font-mono text-xs"><?= $idx + 1 ?></td>
                        <td class="px-4 py-2.5">
                            <a href="<?= url('incidencia_ver.php?id=' . $r['id']) ?>" class="block group">
                                <span class="font-mono text-[10px] font-bold text-zinc-500"><?= e($r['folio']) ?></span>
                                <div class="font-semibold text-sm text-zinc-900 group-hover:text-bacal-700 truncate max-w-xs"><?= e($r['titulo']) ?></div>
                                <div class="text-[10px] text-zinc-400"><?= e($r['sucursal_nombre']) ?> · <?= e(date('d/m/Y', strtotime($r['fecha_evento']))) ?></div>
                            </a>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-zinc-600">
                            <?php if ($r['proveedor_nombre']): ?>
                                <span class="inline-flex items-center gap-1"><i data-lucide="truck" class="w-3 h-3"></i><?= e($r['proveedor_nombre']) ?></span>
                            <?php elseif ($r['proveedor_externo_info']): ?>
                                <span class="text-zinc-500"><?= e($r['proveedor_externo_info']) ?></span>
                            <?php else: ?>
                                <span class="text-zinc-400 italic">Interno</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2.5 text-right text-xs text-zinc-600"><?= (float) $r['mano_obra'] > 0 ? e(fmt_dinero((float) $r['mano_obra'])) : '—' ?></td>
                        <td class="px-4 py-2.5 text-right text-xs text-zinc-600"><?= (float) $r['materiales'] > 0 ? e(fmt_dinero((float) $r['materiales'])) : '—' ?></td>
                        <td class="px-4 py-2.5 text-right text-xs text-zinc-600"><?= (float) $r['materiales_comprados'] > 0 ? e(fmt_dinero((float) $r['materiales_comprados'])) : '—' ?></td>
                        <td class="px-4 py-2.5 text-right font-bold text-sm text-bacal-700"><?= e(fmt_dinero((float) $r['total'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Ranking proveedores más caros -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-zinc-100 flex items-center gap-2">
            <i data-lucide="truck" class="w-5 h-5 text-bacal-700"></i>
            <h3 class="font-display text-base font-bold text-zinc-900">Proveedores más caros</h3>
            <span class="text-xs text-zinc-500">(<?= count($ranking_prov) ?>)</span>
        </div>
        <?php if (empty($ranking_prov)): ?>
        <div class="px-5 py-10 text-center text-sm text-zinc-400">Sin gastos a proveedores registrados en el período.</div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 border-b border-zinc-200">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider w-8">#</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Proveedor</th>
                        <th class="px-4 py-2.5 text-center text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Incid.</th>
                        <th class="px-4 py-2.5 text-right text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Mano obra</th>
                        <th class="px-4 py-2.5 text-right text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Materiales</th>
                        <th class="px-4 py-2.5 text-right text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($ranking_prov as $idx => $p): ?>
                    <tr class="hover:bg-zinc-50">
                        <td class="px-4 py-2.5 text-zinc-400 font-mono text-xs"><?= $idx + 1 ?></td>
                        <td class="px-4 py-2.5">
                            <div class="font-semibold text-sm text-zinc-900"><?= e($p['nombre']) ?></div>
                            <?php if ($p['servicio']): ?><div class="text-[10px] text-zinc-400"><?= e($p['servicio']) ?></div><?php endif; ?>
                        </td>
                        <td class="px-4 py-2.5 text-center text-sm text-zinc-700"><?= (int) $p['num_incidencias'] ?></td>
                        <td class="px-4 py-2.5 text-right text-xs text-zinc-600"><?= e(fmt_dinero((float) $p['mano_obra'])) ?></td>
                        <td class="px-4 py-2.5 text-right text-xs text-zinc-600"><?= e(fmt_dinero((float) $p['materiales'])) ?></td>
                        <td class="px-4 py-2.5 text-right font-bold text-sm text-bacal-700"><?= e(fmt_dinero((float) $p['total'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Costos por sucursal -->
    <?php if (!empty($por_sucursal)): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-zinc-100 flex items-center gap-2">
            <i data-lucide="map-pin" class="w-5 h-5 text-bacal-700"></i>
            <h3 class="font-display text-base font-bold text-zinc-900">Costos por sucursal</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 border-b border-zinc-200">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Sucursal</th>
                        <th class="px-4 py-2.5 text-center text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Incid.</th>
                        <th class="px-4 py-2.5 text-right text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Externo</th>
                        <th class="px-4 py-2.5 text-right text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Interno</th>
                        <th class="px-4 py-2.5 text-right text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($por_sucursal as $s): ?>
                    <tr class="hover:bg-zinc-50">
                        <td class="px-4 py-2.5 font-semibold text-zinc-900"><?= e($s['nombre']) ?> <span class="text-[10px] text-zinc-400 font-mono"><?= e($s['codigo']) ?></span></td>
                        <td class="px-4 py-2.5 text-center text-zinc-700"><?= (int) $s['num_incidencias'] ?></td>
                        <td class="px-4 py-2.5 text-right text-xs text-zinc-600"><?= e(fmt_dinero((float) $s['externo'])) ?></td>
                        <td class="px-4 py-2.5 text-right text-xs text-zinc-600"><?= e(fmt_dinero((float) $s['interno'])) ?></td>
                        <td class="px-4 py-2.5 text-right font-bold text-sm text-bacal-700"><?= e(fmt_dinero((float) $s['total'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fmtMoney = (v) => '$' + Number(v).toLocaleString('es-MX', {minimumFractionDigits: 0, maximumFractionDigits: 0});

    // Tendencia (barras apiladas)
    const ctxT = document.getElementById('chartTendencia');
    if (ctxT) {
        new Chart(ctxT, {
            type: 'bar',
            data: {
                labels: <?= json_encode($tend_labels) ?>,
                datasets: [
                    {
                        label: 'Proveedores',
                        data: <?= json_encode($tend_externo) ?>,
                        backgroundColor: '#36454F',
                        borderRadius: 4,
                    },
                    {
                        label: 'Costo interno',
                        data: <?= json_encode($tend_interno) ?>,
                        backgroundColor: '#a1a1aa',
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, ticks: { callback: (v) => fmtMoney(v) } }
                },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + fmtMoney(c.raw) } }
                }
            }
        });
    }

    // Desglose (dona)
    const ctxD = document.getElementById('chartDesglose');
    if (ctxD) {
        new Chart(ctxD, {
            type: 'doughnut',
            data: {
                labels: ['Proveedores', 'Interno'],
                datasets: [{
                    data: [<?= round($resumen['externo'], 2) ?>, <?= round($resumen['interno'], 2) ?>],
                    backgroundColor: ['#36454F', '#a1a1aa'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => c.label + ': ' + fmtMoney(c.raw) } }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
