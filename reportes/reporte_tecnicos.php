<?php
/**
 * ============================================================================
 * reportes/reporte_tecnicos.php - Productividad por técnico
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/reportes_helpers.php';
require_once __DIR__ . '/../config/incidencia_costos_helpers.php';

$periodo = resolver_periodo();
[$sucursal_filtro, $sucursales_lista, $where_sucursal, $params_sucursal] = resolver_filtro_sucursal();

$es_exportacion = (input('exportar') === 'csv');

// Estadísticas por técnico
$tecnicos = db_all(
    "SELECT u.id, u.nombre_completo, u.usuario, u.email,
            r.nombre rol_nombre,
            COUNT(DISTINCT i.id) total_asignadas,
            SUM(CASE WHEN est.es_final = 1 THEN 1 ELSE 0 END) resueltas,
            SUM(CASE WHEN est.es_final = 0 THEN 1 ELSE 0 END) abiertas,
            SUM(CASE WHEN sev.nivel = 1 THEN 1 ELSE 0 END) criticas_atendidas,
            AVG(CASE WHEN i.tiempo_respuesta_min IS NOT NULL THEN i.tiempo_respuesta_min END) avg_respuesta,
            AVG(CASE WHEN i.tiempo_resolucion_min IS NOT NULL THEN i.tiempo_resolucion_min END) avg_resolucion,
            SUM(CASE WHEN i.sla_cumplido = 1 THEN 1 ELSE 0 END) sla_cumplido,
            SUM(CASE WHEN i.sla_cumplido = 0 THEN 1 ELSE 0 END) sla_incumplido,
            COUNT(DISTINCT CASE WHEN i.es_reincidencia = 1 THEN i.id END) reincidencias_manejadas,
            u.tarifa_hora,
            COALESCE(SUM(i.horas_trabajadas), 0) total_horas,
            COALESCE(SUM(i.horas_trabajadas * i.tarifa_hora_aplicada), 0) costo_mano_obra
     FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     LEFT JOIN incidencias i ON i.asignado_a_id = u.id
        AND DATE(i.creado_en) BETWEEN :d AND :h $where_sucursal
     LEFT JOIN estados est ON i.estado_id = est.id
     LEFT JOIN severidades sev ON i.severidad_id = sev.id
     WHERE r.puede_resolver = 1 AND u.activo = 1
     GROUP BY u.id, u.nombre_completo, u.usuario, u.email, r.nombre, u.tarifa_hora
     ORDER BY total_asignadas DESC, u.nombre_completo ASC",
    array_merge(['d' => $periodo['desde'], 'h' => $periodo['hasta']], $params_sucursal)
);

// Agregar datos calculados
foreach ($tecnicos as &$t) {
    $sla_eval = (int) $t['sla_cumplido'] + (int) $t['sla_incumplido'];
    $t['sla_pct'] = $sla_eval > 0 ? round(((int) $t['sla_cumplido'] / $sla_eval) * 100) : null;
    $t['pct_resolucion'] = (int) $t['total_asignadas'] > 0
        ? round(((int) $t['resueltas'] / (int) $t['total_asignadas']) * 100)
        : 0;
}
unset($t);

// Solo admin ve costos de mano de obra (salarios)
$ver_costos = puede_ver_mano_obra_interna();

if ($es_exportacion) {
    csv_iniciar('productividad_tecnicos_' . date('Ymd_His') . '.csv');
    csv_fila(['PRODUCTIVIDAD POR TÉCNICO']);
    csv_fila(['Período:', $periodo['etiqueta']]);
    csv_fila(['']);
    $cols = ['Técnico', 'Rol', 'Total asignadas', 'Resueltas', 'Abiertas', '% Cierre',
             'Críticas atendidas', 'T. respuesta prom.', 'T. resolución prom.',
             'SLA cumplido', 'SLA incumplido', '% SLA'];
    if ($ver_costos) {
        $cols[] = 'Horas trabajadas';
        $cols[] = 'Tarifa/hora';
        $cols[] = 'Costo mano de obra';
    }
    csv_fila($cols);
    foreach ($tecnicos as $t) {
        $fila = [
            $t['nombre_completo'], $t['rol_nombre'],
            $t['total_asignadas'], $t['resueltas'], $t['abiertas'], $t['pct_resolucion'] . '%',
            $t['criticas_atendidas'],
            $t['avg_respuesta'] !== null ? fmt_duracion((int) $t['avg_respuesta']) : '—',
            $t['avg_resolucion'] !== null ? fmt_duracion((int) $t['avg_resolucion']) : '—',
            $t['sla_cumplido'], $t['sla_incumplido'],
            $t['sla_pct'] !== null ? $t['sla_pct'] . '%' : '—',
        ];
        if ($ver_costos) {
            $fila[] = number_format((float) $t['total_horas'], 2);
            $fila[] = $t['tarifa_hora'] !== null ? number_format((float) $t['tarifa_hora'], 2) : '';
            $fila[] = number_format((float) $t['costo_mano_obra'], 2);
        }
        csv_fila($fila);
    }
    exit;
}

$titulo_pagina = 'Productividad por técnico';
$pagina_activa = 'reportes';
require_once __DIR__ . '/../config/header.php';
?>

<style>
@media print { .no-print { display: none !important; } aside, header.h-16 { display: none !important; } body { background: white !important; } }
</style>

<div class="animate-fade-in space-y-5">

    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 no-print">
        <div class="flex items-center gap-3">
            <a href="<?= url('reportes/reportes.php') ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h2 class="font-display text-2xl font-extrabold text-zinc-900">Productividad por técnico</h2>
                <p class="text-xs text-zinc-500"><?= e($periodo['etiqueta']) ?></p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="flex items-center gap-1.5 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                <i data-lucide="printer" class="w-4 h-4"></i> Imprimir
            </button>
            <a href="<?= url('reportes/reporte_tecnicos.php?' . http_build_query(array_merge($_GET, ['exportar' => 'csv']))) ?>"
               class="flex items-center gap-1.5 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50">
                <i data-lucide="download" class="w-4 h-4"></i> CSV
            </a>
        </div>
    </div>

    <form method="GET" class="bg-white rounded-xl border border-zinc-200 shadow-sm p-4 no-print">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[10px] font-bold text-zinc-600 mb-1 uppercase">Período</label>
                <select name="periodo" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <?php $p_val = input('periodo', 'mes_actual');
                    foreach (['hoy'=>'Hoy','semana_actual'=>'Semana','mes_actual'=>'Mes actual','mes_anterior'=>'Mes anterior','trimestre'=>'90 días','año_actual'=>'Año'] as $k=>$l): ?>
                    <option value="<?= $k ?>" <?= $p_val === $k ? 'selected' : '' ?>><?= e($l) ?></option>
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

    <!-- Cards de técnicos -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($tecnicos as $t): ?>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
            <!-- Header del técnico -->
            <div class="flex items-center gap-3 mb-4 pb-4 border-b border-zinc-100">
                <div class="w-12 h-12 rounded-full flex items-center justify-center text-white text-sm font-bold shadow-sm flex-shrink-0"
                     style="background-color: <?= color_avatar($t['nombre_completo']) ?>">
                    <?= e(iniciales($t['nombre_completo'])) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm text-zinc-900 truncate"><?= e($t['nombre_completo']) ?></div>
                    <div class="text-[11px] text-zinc-500"><?= e($t['rol_nombre']) ?></div>
                </div>
            </div>

            <!-- Métricas principales -->
            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="text-center">
                    <div class="font-display text-xl font-extrabold text-zinc-900"><?= $t['total_asignadas'] ?></div>
                    <div class="text-[10px] text-zinc-500 uppercase tracking-wider">Asignadas</div>
                </div>
                <div class="text-center">
                    <div class="font-display text-xl font-extrabold text-emerald-700"><?= $t['resueltas'] ?></div>
                    <div class="text-[10px] text-zinc-500 uppercase tracking-wider">Resueltas</div>
                </div>
                <div class="text-center">
                    <div class="font-display text-xl font-extrabold <?= (int) $t['abiertas'] > 0 ? 'text-amber-700' : 'text-zinc-400' ?>"><?= $t['abiertas'] ?></div>
                    <div class="text-[10px] text-zinc-500 uppercase tracking-wider">Abiertas</div>
                </div>
            </div>

            <!-- Barra de cierre -->
            <div class="mb-4">
                <div class="flex items-center justify-between text-[10px] mb-1">
                    <span class="text-zinc-500 uppercase tracking-wider font-bold">% Cierre</span>
                    <span class="font-bold text-zinc-900"><?= $t['pct_resolucion'] ?>%</span>
                </div>
                <div class="h-1.5 bg-zinc-100 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: <?= $t['pct_resolucion'] ?>%"></div>
                </div>
            </div>

            <!-- Métricas adicionales -->
            <dl class="space-y-1.5 text-xs">
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500">T. respuesta prom.</dt>
                    <dd class="font-semibold text-zinc-900"><?= $t['avg_respuesta'] !== null ? e(fmt_duracion((int) $t['avg_respuesta'])) : '—' ?></dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500">T. resolución prom.</dt>
                    <dd class="font-semibold text-zinc-900"><?= $t['avg_resolucion'] !== null ? e(fmt_duracion((int) $t['avg_resolucion'])) : '—' ?></dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500">Críticas atendidas</dt>
                    <dd class="font-semibold <?= (int) $t['criticas_atendidas'] > 0 ? 'text-bacal-700' : 'text-zinc-900' ?>"><?= $t['criticas_atendidas'] ?></dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500">Reincidencias</dt>
                    <dd class="font-semibold <?= (int) $t['reincidencias_manejadas'] > 0 ? 'text-purple-700' : 'text-zinc-900' ?>"><?= $t['reincidencias_manejadas'] ?></dd>
                </div>
                <div class="flex justify-between gap-2 pt-1.5 border-t border-zinc-100">
                    <dt class="text-zinc-500 font-semibold">SLA cumplido</dt>
                    <dd>
                        <?php if ($t['sla_pct'] !== null): ?>
                        <span class="font-bold <?= $t['sla_pct'] >= 80 ? 'text-emerald-700' : ($t['sla_pct'] >= 50 ? 'text-amber-700' : 'text-bacal-700') ?>">
                            <?= $t['sla_pct'] ?>%
                        </span>
                        <span class="text-[10px] text-zinc-400">(<?= $t['sla_cumplido'] ?>/<?= (int) $t['sla_cumplido'] + (int) $t['sla_incumplido'] ?>)</span>
                        <?php else: ?>
                        <span class="text-zinc-400">—</span>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>

            <?php if ($ver_costos): ?>
            <!-- Costo de mano de obra (solo admin) -->
            <div class="mt-4 pt-4 border-t border-zinc-100">
                <div class="flex items-center gap-1.5 mb-2">
                    <i data-lucide="hand-coins" class="w-3.5 h-3.5 text-bacal-700"></i>
                    <span class="text-[10px] font-bold text-bacal-700 uppercase tracking-wider">Costo mano de obra</span>
                    <span class="text-[9px] text-zinc-400 bg-zinc-100 px-1.5 rounded">confidencial</span>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div>
                        <div class="font-display text-base font-extrabold text-zinc-900"><?= e(rtrim(rtrim(number_format((float) $t['total_horas'], 2), '0'), '.')) ?></div>
                        <div class="text-[9px] text-zinc-500 uppercase tracking-wider">Horas</div>
                    </div>
                    <div>
                        <div class="font-display text-base font-extrabold text-zinc-900"><?= $t['tarifa_hora'] !== null ? e(fmt_dinero_corto((float) $t['tarifa_hora'])) : '—' ?></div>
                        <div class="text-[9px] text-zinc-500 uppercase tracking-wider">Tarifa/h</div>
                    </div>
                    <div>
                        <div class="font-display text-base font-extrabold text-bacal-700"><?= e(fmt_dinero_corto((float) $t['costo_mano_obra'])) ?></div>
                        <div class="text-[9px] text-zinc-500 uppercase tracking-wider">Costo</div>
                    </div>
                </div>
                <?php if ($t['tarifa_hora'] === null && (float) $t['total_horas'] > 0): ?>
                <p class="text-[9px] text-amber-600 mt-1.5 text-center">⚠ Sin tarifa configurada. Asígnala en Usuarios.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <a href="<?= url('bitacora.php?asignado_a=' . $t['id']) ?>"
               class="mt-4 block text-center text-xs font-semibold text-bacal-700 hover:text-bacal-800 hover:underline">
                Ver todas sus incidencias →
            </a>
        </div>
        <?php endforeach; ?>

        <?php if (empty($tecnicos)): ?>
        <div class="col-span-full text-center py-12 bg-white rounded-xl border border-zinc-200">
            <p class="text-sm text-zinc-500">Sin técnicos con actividad en el período.</p>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
