<?php
/**
 * ============================================================================
 * dashboard.php - Panel principal completo
 * ============================================================================
 * Muestra KPIs, gráficas de tendencia, distribución por categoría,
 * comparativa entre sucursales, últimas incidencias, alertas activas
 * y la lista de "tu trabajo del día".
 *
 * Respeta los permisos: usuarios sin "ver_todas_sucursales" solo ven la suya.
 * ============================================================================
 */
$titulo_pagina = 'Dashboard';
$pagina_activa = 'dashboard';
require_once __DIR__ . '/config/header.php';

require_once __DIR__ . '/config/mantenimientos_helpers.php';
require_once __DIR__ . '/config/inteligencia_helpers.php';
require_once __DIR__ . '/config/comunicacion_helpers.php';
require_once __DIR__ . '/config/incidencia_costos_helpers.php';

$u = usuario_actual();

// Actualizar estados de mantenimientos
actualizar_estados_mantenimientos();

// ----------------------------------------------------------------------------
// Determinar sucursal a mostrar (filtro)
// ----------------------------------------------------------------------------
$ver_todas = tiene_permiso('ver_todas_sucursales');
$sucursal_filtro = null;

if ($ver_todas) {
    $sucursal_filtro = input('sucursal') !== null && input('sucursal') !== ''
        ? (int) input('sucursal')
        : null;
} else {
    $sucursal_filtro = $u['sucursal_id'];
}

$where_sucursal = '';
$params_sucursal = [];
if ($sucursal_filtro) {
    $where_sucursal = ' AND i.sucursal_id = :sid ';
    $params_sucursal['sid'] = $sucursal_filtro;
}

$sucursales = db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo = 1 ORDER BY nombre");

// Resumen de costos del mes (solo admin: incluye mano de obra interna)
$puede_ver_costos = tiene_permiso('administrar');
$costos_mes = null;
if ($puede_ver_costos) {
    $where_costos = $sucursal_filtro ? ' AND i.sucursal_id = :sid ' : '';
    $params_costos = $sucursal_filtro ? ['sid' => $sucursal_filtro] : [];
    $costos_mes = costos_resumen_periodo(date('Y-m-01'), date('Y-m-d'), $where_costos, $params_costos);
}

// ----------------------------------------------------------------------------
// KPIs
// ----------------------------------------------------------------------------
$inicio_mes = date('Y-m-01 00:00:00');

$kpi_total_mes = (int) db_one(
    "SELECT COUNT(*) c FROM incidencias i WHERE i.creado_en >= :inicio $where_sucursal",
    array_merge(['inicio' => $inicio_mes], $params_sucursal)
)['c'];

$kpi_abiertas = (int) db_one(
    "SELECT COUNT(*) c FROM incidencias i
     INNER JOIN estados e ON i.estado_id = e.id
     WHERE e.es_final = 0 $where_sucursal",
    $params_sucursal
)['c'];

$kpi_criticas = (int) db_one(
    "SELECT COUNT(*) c FROM incidencias i
     INNER JOIN estados e ON i.estado_id = e.id
     INNER JOIN severidades s ON i.severidad_id = s.id
     WHERE s.nivel = 1 AND e.es_final = 0 $where_sucursal",
    $params_sucursal
)['c'];

$kpi_reincidencias = (int) db_one(
    "SELECT COUNT(*) c FROM incidencias i
     INNER JOIN estados e ON i.estado_id = e.id
     WHERE i.es_reincidencia = 1 AND e.es_final = 0 $where_sucursal",
    $params_sucursal
)['c'];

$kpi_tiempo_prom = db_one(
    "SELECT AVG(i.tiempo_resolucion_min) avg_min FROM incidencias i
     INNER JOIN estados e ON i.estado_id = e.id
     WHERE e.es_final = 1 AND i.fecha_cierre >= :inicio
       AND i.tiempo_resolucion_min IS NOT NULL $where_sucursal",
    array_merge(['inicio' => $inicio_mes], $params_sucursal)
)['avg_min'];
$kpi_tiempo_prom = $kpi_tiempo_prom ? (int) round($kpi_tiempo_prom) : null;

$sla_data = db_one(
    "SELECT
        SUM(CASE WHEN sla_cumplido = 1 THEN 1 ELSE 0 END) cumplidos,
        COUNT(sla_cumplido) total
     FROM incidencias i
     WHERE i.fecha_cierre >= :inicio AND i.sla_cumplido IS NOT NULL $where_sucursal",
    array_merge(['inicio' => $inicio_mes], $params_sucursal)
);
$kpi_sla_pct = ($sla_data['total'] ?? 0) > 0
    ? round(((int) $sla_data['cumplidos'] / (int) $sla_data['total']) * 100)
    : null;

// ----------------------------------------------------------------------------
// Datos de gráficas
// ----------------------------------------------------------------------------
$inicio_tendencia = date('Y-m-d', strtotime('-29 days'));
$rows_tendencia = db_all(
    "SELECT DATE(creado_en) fecha, COUNT(*) total,
            SUM(CASE WHEN es_reincidencia = 1 THEN 1 ELSE 0 END) reincidencias
     FROM incidencias i
     WHERE DATE(creado_en) >= :inicio $where_sucursal
     GROUP BY DATE(creado_en)
     ORDER BY fecha",
    array_merge(['inicio' => $inicio_tendencia], $params_sucursal)
);
$tendencia_map = [];
foreach ($rows_tendencia as $r) $tendencia_map[$r['fecha']] = $r;
$labels_tendencia = [];
$datos_tendencia = [];
$datos_reincidencias = [];
for ($i = 29; $i >= 0; $i--) {
    $f = date('Y-m-d', strtotime("-$i days"));
    $labels_tendencia[] = date('d/m', strtotime($f));
    $datos_tendencia[] = isset($tendencia_map[$f]) ? (int) $tendencia_map[$f]['total'] : 0;
    $datos_reincidencias[] = isset($tendencia_map[$f]) ? (int) $tendencia_map[$f]['reincidencias'] : 0;
}

$rows_cat = db_all(
    "SELECT c.nombre, c.color, COUNT(i.id) total
     FROM categorias c
     LEFT JOIN incidencias i ON i.categoria_id = c.id
        AND i.creado_en >= :inicio $where_sucursal
     WHERE c.activo = 1
     GROUP BY c.id, c.nombre, c.color
     HAVING total > 0
     ORDER BY total DESC",
    array_merge(['inicio' => $inicio_mes], $params_sucursal)
);
$labels_cat = array_column($rows_cat, 'nombre');
$datos_cat = array_map('intval', array_column($rows_cat, 'total'));
$colores_cat = array_column($rows_cat, 'color');

$rows_comparativa = [];
if ($ver_todas) {
    $rows_comparativa = db_all(
        "SELECT s.nombre, s.codigo,
                COUNT(i.id) total,
                SUM(CASE WHEN e.es_final = 0 THEN 1 ELSE 0 END) abiertas,
                SUM(CASE WHEN sev.nivel = 1 AND e.es_final = 0 THEN 1 ELSE 0 END) criticas
         FROM sucursales s
         LEFT JOIN incidencias i ON i.sucursal_id = s.id
            AND i.creado_en >= :inicio
         LEFT JOIN estados e ON i.estado_id = e.id
         LEFT JOIN severidades sev ON i.severidad_id = sev.id
         WHERE s.activo = 1
         GROUP BY s.id, s.nombre, s.codigo
         ORDER BY total DESC",
        ['inicio' => $inicio_mes]
    );
}

$inicio_top = date('Y-m-d', strtotime('-90 days'));
$top_equipos = db_all(
    "SELECT eq.id, eq.codigo_inventario, eq.nombre, s.nombre sucursal_nombre,
            COUNT(i.id) total
     FROM incidencias i
     INNER JOIN equipos eq ON i.equipo_id = eq.id
     INNER JOIN sucursales s ON eq.sucursal_id = s.id
     WHERE i.creado_en >= :inicio $where_sucursal
     GROUP BY eq.id, eq.codigo_inventario, eq.nombre, s.nombre
     ORDER BY total DESC
     LIMIT 5",
    array_merge(['inicio' => $inicio_top], $params_sucursal)
);

$top_areas = db_all(
    "SELECT a.id, a.nombre, a.color, COUNT(i.id) total
     FROM incidencias i
     INNER JOIN areas a ON i.area_id = a.id
     WHERE i.creado_en >= :inicio $where_sucursal
     GROUP BY a.id, a.nombre, a.color
     ORDER BY total DESC
     LIMIT 6",
    array_merge(['inicio' => $inicio_mes], $params_sucursal)
);
$max_top_areas = !empty($top_areas) ? max(array_column($top_areas, 'total')) : 1;

$ultimas = db_all(
    "SELECT i.id, i.folio, i.titulo, i.fecha_evento, i.es_reincidencia,
            s.nombre sucursal_nombre, s.codigo sucursal_codigo,
            a.nombre area_nombre, a.color area_color,
            sev.nombre severidad_nombre, sev.color severidad_color,
            est.nombre estado_nombre, est.color estado_color,
            rep.nombre_completo reportado_por_nombre
     FROM incidencias i
     INNER JOIN sucursales s ON i.sucursal_id = s.id
     INNER JOIN areas a ON i.area_id = a.id
     INNER JOIN severidades sev ON i.severidad_id = sev.id
     INNER JOIN estados est ON i.estado_id = est.id
     INNER JOIN usuarios rep ON i.reportado_por_id = rep.id
     WHERE 1=1 $where_sucursal
     ORDER BY i.creado_en DESC
     LIMIT 8",
    $params_sucursal
);

$mis_pendientes = [];
if (tiene_permiso('resolver')) {
    $mis_pendientes = db_all(
        "SELECT i.id, i.folio, i.titulo, i.fecha_evento,
                s.nombre sucursal_nombre,
                a.nombre area_nombre, a.color area_color,
                sev.nombre severidad_nombre, sev.color severidad_color,
                est.nombre estado_nombre, est.color estado_color
         FROM incidencias i
         INNER JOIN sucursales s ON i.sucursal_id = s.id
         INNER JOIN areas a ON i.area_id = a.id
         INNER JOIN severidades sev ON i.severidad_id = sev.id
         INNER JOIN estados est ON i.estado_id = est.id
         WHERE i.asignado_a_id = :uid AND est.es_final = 0
         ORDER BY sev.nivel ASC, i.fecha_evento ASC
         LIMIT 5",
        ['uid' => $u['id']]
    );
}

// Mantenimientos próximos (14 días)
$sucursal_para_mant = (tiene_permiso('ver_todas_sucursales') || tiene_permiso('administrar')) ? null : ($u['sucursal_id'] ?? null);
$mantenimientos_widget = proximos_mantenimientos(14, $sucursal_para_mant);

// Equipos problemáticos (con fallas recurrentes)
$equipos_problema = equipos_problematicos($sucursal_para_mant, 8);

// Anuncios visibles para este usuario (Fase 16)
$anuncios_visibles = anuncios_visibles(
    (int) $u['id'],
    $u['sucursal_id'] ? (int) $u['sucursal_id'] : null,
    (int) $u['rol_id'],
    false // solo no leídos + fijados
);

// Alertas
$alertas = [];

$criticas_sin_asignar = (int) db_one(
    "SELECT COUNT(*) c FROM incidencias i
     INNER JOIN estados e ON i.estado_id = e.id
     INNER JOIN severidades s ON i.severidad_id = s.id
     WHERE s.nivel = 1 AND e.es_final = 0 AND i.asignado_a_id IS NULL $where_sucursal",
    $params_sucursal
)['c'];
if ($criticas_sin_asignar > 0) {
    $alertas[] = ['tipo' => 'critica', 'icono' => 'alert-octagon',
        'titulo' => "$criticas_sin_asignar incidencia(s) crítica(s) sin asignar",
        'mensaje' => 'Requieren asignación inmediata a un técnico.',
        'enlace' => url('bitacora.php?severidad=1&sin_asignar=1')];
}

$sla_riesgo = (int) db_one(
    "SELECT COUNT(*) c FROM incidencias i
     INNER JOIN estados e ON i.estado_id = e.id
     WHERE e.es_final = 0 AND i.fecha_limite_sla IS NOT NULL
       AND i.fecha_limite_sla BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR)
       $where_sucursal",
    $params_sucursal
)['c'];
if ($sla_riesgo > 0) {
    $alertas[] = ['tipo' => 'warning', 'icono' => 'clock-alert',
        'titulo' => "$sla_riesgo incidencia(s) por vencer SLA",
        'mensaje' => 'Vencen en menos de 2 horas.',
        'enlace' => url('bitacora.php?sla_riesgo=1')];
}

$sla_vencido = (int) db_one(
    "SELECT COUNT(*) c FROM incidencias i
     INNER JOIN estados e ON i.estado_id = e.id
     WHERE e.es_final = 0 AND i.fecha_limite_sla IS NOT NULL
       AND i.fecha_limite_sla < NOW() $where_sucursal",
    $params_sucursal
)['c'];
if ($sla_vencido > 0) {
    $alertas[] = ['tipo' => 'critica', 'icono' => 'flame',
        'titulo' => "$sla_vencido incidencia(s) con SLA vencido",
        'mensaje' => 'Pasaron el tiempo de respuesta acordado y siguen abiertas.',
        'enlace' => url('bitacora.php?sla_vencido=1')];
}

$sin_actualizar = (int) db_one(
    "SELECT COUNT(*) c FROM incidencias i
     INNER JOIN estados e ON i.estado_id = e.id
     WHERE e.es_final = 0 AND i.actualizado_en < DATE_SUB(NOW(), INTERVAL 7 DAY) $where_sucursal",
    $params_sucursal
)['c'];
if ($sin_actualizar > 0) {
    $alertas[] = ['tipo' => 'info', 'icono' => 'clock',
        'titulo' => "$sin_actualizar incidencia(s) sin movimiento",
        'mensaje' => 'Llevan más de 7 días sin actualización.',
        'enlace' => url('bitacora.php?sin_actualizar=1')];
}

$h = (int) date('G');
$saludo = $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');
$meses_es = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$mes_actual_es = $meses_es[(int) date('n') - 1] . ' ' . date('Y');
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="space-y-6 animate-fade-in">

    <!-- Encabezado: saludo + filtro de sucursal -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h2 class="font-display text-3xl font-extrabold text-zinc-900 leading-tight">
                    <?= $saludo ?>, <?= e(explode(' ', $u['nombre'])[0]) ?>
                </h2>
                <?php if (tiene_permiso('ver_todas_sucursales') && count($sucursales) > 1 && usuario_prefiere_radio_sucursal()): ?>
                <form method="GET" class="flex items-center gap-2 flex-wrap">
                    <label class="flex items-center gap-1.5 cursor-pointer text-sm font-medium text-zinc-600">
                        <input type="radio" name="sucursal" value="" onchange="this.form.submit()"
                               <?= !$sucursal_filtro ? 'checked' : '' ?>>
                        Todas
                    </label>
                    <?php foreach ($sucursales as $s): ?>
                    <label class="flex items-center gap-1.5 cursor-pointer text-sm font-medium text-zinc-600">
                        <input type="radio" name="sucursal" value="<?= $s['id'] ?>" onchange="this.form.submit()"
                               <?= $sucursal_filtro == $s['id'] ? 'checked' : '' ?>>
                        <?= e($s['nombre']) ?>
                    </label>
                    <?php endforeach; ?>
                </form>
                <?php endif; ?>
            </div>
            <p class="text-sm text-zinc-500 mt-1">
                Resumen del mes en curso ·
                <span class="font-medium text-zinc-700"><?= e($mes_actual_es) ?></span>
            </p>
        </div>

        <?php if ($ver_todas && !usuario_prefiere_radio_sucursal()): ?>
        <form method="GET" class="flex items-center gap-2">
            <div class="relative">
                <i data-lucide="store" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400"></i>
                <select name="sucursal" onchange="this.form.submit()"
                        class="pl-9 pr-8 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 focus:outline-none focus:border-bacal-700 focus:ring-2 focus:ring-bacal-100 appearance-none cursor-pointer">
                    <option value="">Todas las sucursales</option>
                    <?php foreach ($sucursales as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $sucursal_filtro == $s['id'] ? 'selected' : '' ?>>
                        <?= e($s['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <i data-lucide="chevron-down" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none"></i>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Widget: Tablero de anuncios (Fase 16) -->
    <?php if (!empty($anuncios_visibles)): ?>
    <div class="space-y-2">
        <?php foreach ($anuncios_visibles as $an):
            $cfg_an = ANUNCIO_TIPOS[$an['tipo']] ?? ANUNCIO_TIPOS['info'];
        ?>
        <div class="rounded-xl border-l-4 shadow-sm p-4 relative"
             x-data="{ visible: true }"
             x-show="visible"
             x-transition
             style="border-left-color: <?= e($cfg_an['color']) ?>; background-color: <?= e($cfg_an['color']) ?>08">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background-color: <?= e($cfg_an['color']) ?>15">
                    <i data-lucide="<?= e($cfg_an['icono']) ?>" class="w-4 h-4" style="color: <?= e($cfg_an['color']) ?>"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <h3 class="font-display font-bold text-zinc-900"><?= e($an['titulo']) ?></h3>
                        <?php if ((int) $an['fijado'] === 1): ?>
                        <span class="text-[9px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded">📌 FIJADO</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-sm text-zinc-700 whitespace-pre-wrap"><?= e($an['contenido']) ?></p>
                    <div class="text-[10px] text-zinc-500 mt-2">
                        Publicado <?= e(fmt_tiempo_relativo($an['creado_en'])) ?> por <?= e($an['creado_por_nombre'] ?? 'Admin') ?>
                    </div>
                </div>

                <!-- Botón cerrar (no aparece si está fijado y ya lo leyó) -->
                <button type="button"
                        @click="cerrarAnuncio(<?= (int) $an['id'] ?>); visible = false"
                        class="text-zinc-400 hover:text-zinc-700 p-1 flex-shrink-0"
                        title="<?= (int) $an['fijado'] === 1 ? 'Marcar como leído' : 'Cerrar (no volver a mostrar)' ?>">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>

        <script>
        function cerrarAnuncio(id) {
            fetch('<?= url('api/anuncio_cerrar.php') ?>', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_csrf=<?= e(csrf_token()) ?>&anuncio_id=' + id
            }).catch(() => {});
        }
        </script>
    </div>
    <?php endif; ?>

    <!-- Alertas activas -->
    <?php if (!empty($alertas)): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-<?= min(count($alertas), 4) ?> gap-3">
        <?php foreach ($alertas as $al):
            $estilos = [
                'critica' => ['bg' => 'bg-bacal-50 border-bacal-200 text-bacal-800 hover:bg-bacal-100', 'icon' => 'text-bacal-700'],
                'warning' => ['bg' => 'bg-amber-50 border-amber-200 text-amber-800 hover:bg-amber-100', 'icon' => 'text-amber-600'],
                'info'    => ['bg' => 'bg-blue-50 border-blue-200 text-blue-800 hover:bg-blue-100',     'icon' => 'text-blue-600'],
            ];
            $est = $estilos[$al['tipo']];
        ?>
        <a href="<?= e($al['enlace']) ?>" class="border rounded-xl p-4 flex items-start gap-3 transition-colors <?= $est['bg'] ?>">
            <i data-lucide="<?= $al['icono'] ?>" class="w-5 h-5 flex-shrink-0 mt-0.5 <?= $est['icon'] ?>"></i>
            <div class="flex-1 min-w-0">
                <div class="font-semibold text-sm leading-snug"><?= e($al['titulo']) ?></div>
                <div class="text-xs opacity-80 mt-0.5"><?= e($al['mensaje']) ?></div>
            </div>
            <i data-lucide="chevron-right" class="w-4 h-4 flex-shrink-0 opacity-50"></i>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- KPIs principales -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4"
         x-data="sparklinesDashboard()" x-init="cargar()">
        <div class="bg-white rounded-xl border border-zinc-200 p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
            <div class="w-9 h-9 rounded-lg bg-zinc-100 text-zinc-700 flex items-center justify-center mb-3">
                <i data-lucide="inbox" class="w-4 h-4"></i>
            </div>
            <div class="font-display text-3xl font-extrabold text-zinc-900 leading-none"><?= $kpi_total_mes ?></div>
            <div class="text-[11px] text-zinc-500 mt-2 uppercase tracking-wider font-bold">Total del mes</div>
            <!-- Sparkline -->
            <div class="absolute right-3 bottom-3 w-20 h-8 opacity-60" x-html="sparkSvg('creadas', '#71717a')"></div>
        </div>

        <div class="bg-white rounded-xl border border-zinc-200 p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
            <div class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center mb-3">
                <i data-lucide="circle-dot" class="w-4 h-4"></i>
            </div>
            <div class="font-display text-3xl font-extrabold text-zinc-900 leading-none"><?= $kpi_abiertas ?></div>
            <div class="text-[11px] text-zinc-500 mt-2 uppercase tracking-wider font-bold">Abiertas</div>
            <div class="absolute right-3 bottom-3 w-20 h-8 opacity-60" x-html="sparkSvg('abiertas', '#F59E0B')"></div>
        </div>

        <div class="bg-white rounded-xl border <?= $kpi_criticas > 0 ? 'border-bacal-300 bg-gradient-to-br from-bacal-50 to-white' : 'border-zinc-200' ?> p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
            <div class="w-9 h-9 rounded-lg bg-bacal-100 text-bacal-700 flex items-center justify-center mb-3">
                <i data-lucide="zap" class="w-4 h-4"></i>
            </div>
            <div class="font-display text-3xl font-extrabold <?= $kpi_criticas > 0 ? 'text-bacal-700' : 'text-zinc-900' ?> leading-none"><?= $kpi_criticas ?></div>
            <div class="text-[11px] text-zinc-500 mt-2 uppercase tracking-wider font-bold">Críticas abiertas</div>
        </div>

        <div class="bg-white rounded-xl border border-zinc-200 p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
            <div class="w-9 h-9 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center mb-3">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
            </div>
            <div class="font-display text-3xl font-extrabold text-zinc-900 leading-none"><?= $kpi_reincidencias ?></div>
            <div class="text-[11px] text-zinc-500 mt-2 uppercase tracking-wider font-bold">Reincidencias</div>
        </div>

        <div class="bg-white rounded-xl border border-zinc-200 p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden">
            <div class="w-9 h-9 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center mb-3">
                <i data-lucide="timer" class="w-4 h-4"></i>
            </div>
            <div class="font-display text-2xl font-extrabold text-zinc-900 leading-none">
                <?= $kpi_tiempo_prom !== null ? e(fmt_duracion($kpi_tiempo_prom)) : '—' ?>
            </div>
            <div class="text-[11px] text-zinc-500 mt-2 uppercase tracking-wider font-bold">Resol. promedio</div>
            <!-- Sparkline de resueltas -->
            <div class="absolute right-3 bottom-3 w-20 h-8 opacity-60" x-html="sparkSvg('resueltas', '#16A34A')"></div>
        </div>

        <div class="bg-white rounded-xl border border-zinc-200 p-5 shadow-sm hover:shadow-md transition-shadow">
            <div class="w-9 h-9 rounded-lg <?= ($kpi_sla_pct ?? 0) >= 80 ? 'bg-emerald-100 text-emerald-700' : (($kpi_sla_pct ?? 0) >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-bacal-100 text-bacal-700') ?> flex items-center justify-center mb-3">
                <i data-lucide="target" class="w-4 h-4"></i>
            </div>
            <div class="font-display text-3xl font-extrabold text-zinc-900 leading-none">
                <?= $kpi_sla_pct !== null ? $kpi_sla_pct . '%' : '—' ?>
            </div>
            <div class="text-[11px] text-zinc-500 mt-2 uppercase tracking-wider font-bold">SLA cumplido</div>
        </div>
    </div>

    <!-- Widget: Costos del mes (solo ver_reportes / admin) -->
    <?php if ($puede_ver_costos && $costos_mes && $costos_mes['total'] > 0): ?>
    <?php $ver_moi_dash = puede_ver_mano_obra_interna();
          $total_dash = $ver_moi_dash ? $costos_mes['total'] : $costos_mes['total_visible']; ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="hand-coins" class="w-4 h-4 text-bacal-700"></i> Costos del mes
            </h3>
            <a href="<?= url('reportes/reporte_costos.php') ?>" class="text-xs font-semibold text-bacal-700 hover:underline flex items-center gap-1">
                Ver análisis <i data-lucide="arrow-right" class="w-3 h-3"></i>
            </a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <div class="font-display text-2xl font-extrabold text-bacal-700"><?= e(fmt_dinero_corto($total_dash)) ?></div>
                <div class="text-[10px] text-zinc-500 uppercase tracking-wider mt-1">Gasto total</div>
            </div>
            <div>
                <div class="font-display text-2xl font-extrabold text-zinc-900"><?= e(fmt_dinero_corto($costos_mes['externo'])) ?></div>
                <div class="text-[10px] text-zinc-500 uppercase tracking-wider mt-1">Proveedores</div>
            </div>
            <div>
                <div class="font-display text-2xl font-extrabold text-zinc-900"><?= $costos_mes['con_costo'] ?></div>
                <div class="text-[10px] text-zinc-500 uppercase tracking-wider mt-1">Con costo</div>
            </div>
            <div>
                <div class="font-display text-2xl font-extrabold text-zinc-900"><?= e(fmt_dinero_corto($costos_mes['promedio'])) ?></div>
                <div class="text-[10px] text-zinc-500 uppercase tracking-wider mt-1">Promedio</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Mi trabajo pendiente -->
    <?php if (tiene_permiso('resolver')): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-zinc-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="briefcase" class="w-5 h-5 text-bacal-700"></i>
                <h3 class="font-display text-lg font-bold text-zinc-900">Tu trabajo pendiente</h3>
                <span class="bg-zinc-100 text-zinc-600 text-xs font-semibold px-2 py-0.5 rounded-full"><?= count($mis_pendientes) ?></span>
            </div>
            <a href="<?= url('bitacora.php?asignado_a=' . $u['id'] . '&abiertas=1') ?>"
               class="text-xs text-zinc-500 hover:text-bacal-700 font-medium flex items-center gap-1">
                Ver todas <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>
        <?php if (empty($mis_pendientes)): ?>
        <div class="px-6 py-12 text-center">
            <div class="w-12 h-12 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mb-3">
                <i data-lucide="check-circle-2" class="w-6 h-6"></i>
            </div>
            <p class="text-sm text-zinc-600 font-medium">No tienes incidencias pendientes</p>
            <p class="text-xs text-zinc-400 mt-1">Excelente trabajo. Las nuevas asignaciones aparecerán aquí.</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-zinc-100">
            <?php foreach ($mis_pendientes as $i): ?>
            <a href="<?= url('incidencia_ver.php?id=' . $i['id']) ?>" class="flex items-center gap-4 px-6 py-3 hover:bg-zinc-50 transition-colors group">
                <div class="w-1 h-12 rounded-full flex-shrink-0" style="background-color: <?= e($i['severidad_color']) ?>"></div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <span class="font-mono text-[10px] text-zinc-400 font-semibold"><?= e($i['folio']) ?></span>
                        <?= badge($i['area_nombre'], $i['area_color']) ?>
                        <?= badge($i['severidad_nombre'], $i['severidad_color']) ?>
                    </div>
                    <div class="font-semibold text-sm text-zinc-900 truncate group-hover:text-bacal-700"><?= e($i['titulo']) ?></div>
                    <div class="text-xs text-zinc-500 mt-0.5">
                        <?= e($i['sucursal_nombre']) ?> · <?= e(fmt_tiempo_relativo($i['fecha_evento'])) ?>
                    </div>
                </div>
                <div class="flex-shrink-0"><?= badge($i['estado_nombre'], $i['estado_color']) ?></div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-zinc-300 group-hover:text-zinc-500 flex-shrink-0"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Widget: Próximos mantenimientos -->
    <?php if (!empty($mantenimientos_widget)): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center">
                    <i data-lucide="calendar-clock" class="w-4 h-4 text-amber-700"></i>
                </div>
                <div>
                    <h3 class="font-display text-lg font-bold text-zinc-900">Próximos mantenimientos</h3>
                    <p class="text-xs text-zinc-500">Próximos 14 días</p>
                </div>
                <span class="bg-amber-100 text-amber-800 text-xs font-semibold px-2 py-0.5 rounded-full"><?= count($mantenimientos_widget) ?></span>
            </div>
            <a href="<?= url('mantenimientos.php') ?>" class="text-xs font-semibold text-bacal-700 hover:text-bacal-800 flex items-center gap-1">
                Ver todos <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>

        <div class="space-y-2">
            <?php foreach (array_slice($mantenimientos_widget, 0, 6) as $mw):
                $dias_m = (strtotime($mw['fecha_programada']) - strtotime(date('Y-m-d'))) / 86400;
                $color_m = $dias_m < 0 ? '#DC2626' : ($dias_m <= 3 ? '#D97706' : '#0EA5E9');
                $etiqueta_dias = $dias_m < 0 ? 'Vencido' : ($dias_m == 0 ? 'Hoy' : ($dias_m == 1 ? 'Mañana' : "En " . (int) $dias_m . " días"));
            ?>
            <a href="<?= url('mantenimiento_ver.php?id=' . $mw['id']) ?>" class="flex items-center gap-3 p-3 rounded-lg border border-zinc-200 hover:bg-zinc-50 transition-colors group">
                <div class="w-11 h-11 rounded-lg flex flex-col items-center justify-center text-white flex-shrink-0" style="background-color: <?= $color_m ?>">
                    <div class="text-[9px] font-bold uppercase opacity-90"><?= e(date('M', strtotime($mw['fecha_programada']))) ?></div>
                    <div class="text-sm font-extrabold leading-none"><?= e(date('d', strtotime($mw['fecha_programada']))) ?></div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                        <span class="font-mono text-[10px] font-bold text-zinc-500"><?= e($mw['equipo_codigo']) ?></span>
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase" style="color: <?= $color_m ?>; background-color: <?= $color_m ?>15"><?= e($etiqueta_dias) ?></span>
                    </div>
                    <div class="font-semibold text-sm text-zinc-900 truncate group-hover:text-bacal-700"><?= e($mw['titulo']) ?></div>
                    <div class="text-[11px] text-zinc-500 mt-0.5">
                        <?= e($mw['sucursal_nombre']) ?>
                        <?php if ($mw['asignado_nombre']): ?> · <?= e($mw['asignado_nombre']) ?><?php endif; ?>
                        <?php if ($mw['proveedor_nombre']): ?> · <span class="text-bacal-700"><?= e($mw['proveedor_nombre']) ?></span><?php endif; ?>
                    </div>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-zinc-300 group-hover:text-zinc-500 flex-shrink-0"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Widget: Equipos problemáticos (predicción de fallas recurrentes) -->
    <?php if (!empty($equipos_problema)): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-bacal-100 flex items-center justify-center">
                    <i data-lucide="flame" class="w-4 h-4 text-bacal-700"></i>
                </div>
                <div>
                    <h3 class="font-display text-lg font-bold text-zinc-900">Equipos con fallas recurrentes</h3>
                    <p class="text-xs text-zinc-500">Considera mantenimiento preventivo o reemplazo</p>
                </div>
                <span class="bg-bacal-100 text-bacal-800 text-xs font-semibold px-2 py-0.5 rounded-full"><?= count($equipos_problema) ?></span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <?php foreach ($equipos_problema as $eq):
                $clas = clasificar_problema_equipo((int) $eq['inc_30d'], (int) $eq['inc_90d']);
            ?>
            <a href="<?= url('equipo_ver.php?id=' . $eq['id']) ?>"
               class="flex items-center gap-3 p-3 rounded-lg border border-zinc-200 hover:border-zinc-300 hover:bg-zinc-50 transition-colors group">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                     style="background-color: <?= $clas['color'] ?>15">
                    <i data-lucide="<?= $clas['icono'] ?>" class="w-5 h-5" style="color: <?= $clas['color'] ?>"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                        <span class="font-mono text-[10px] font-bold text-zinc-500"><?= e($eq['codigo_inventario']) ?></span>
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase"
                              style="color: <?= $clas['color'] ?>; background-color: <?= $clas['color'] ?>15">
                            <?= e($clas['etiqueta']) ?>
                        </span>
                    </div>
                    <div class="font-semibold text-sm text-zinc-900 truncate group-hover:text-bacal-700"><?= e($eq['nombre']) ?></div>
                    <div class="text-[11px] text-zinc-500 mt-0.5">
                        <strong class="text-zinc-700"><?= (int) $eq['inc_30d'] ?></strong> en 30d ·
                        <strong class="text-zinc-700"><?= (int) $eq['inc_90d'] ?></strong> en 90d
                        <?php if ($eq['area_nombre']): ?>
                        · <?= e($eq['area_nombre']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-zinc-300 group-hover:text-zinc-500 flex-shrink-0"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Gráficas: tendencia + categorías -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-display text-lg font-bold text-zinc-900">Tendencia · últimos 30 días</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">Incidencias por día y reincidencias detectadas</p>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-bacal-700"></span>
                        <span class="text-zinc-600 font-medium">Total</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
                        <span class="text-zinc-600 font-medium">Reincidencias</span>
                    </div>
                </div>
            </div>
            <div class="h-64"><canvas id="chartTendencia"></canvas></div>
        </div>

        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <div class="mb-4">
                <h3 class="font-display text-lg font-bold text-zinc-900">Por categoría</h3>
                <p class="text-xs text-zinc-500 mt-0.5">Distribución del mes</p>
            </div>
            <?php if (empty($rows_cat)): ?>
            <div class="flex flex-col items-center justify-center h-64 text-center">
                <div class="w-12 h-12 rounded-full bg-zinc-100 flex items-center justify-center mb-3">
                    <i data-lucide="pie-chart" class="w-6 h-6 text-zinc-400"></i>
                </div>
                <p class="text-sm text-zinc-500">Sin datos este mes</p>
            </div>
            <?php else: ?>
            <div class="h-48 relative"><canvas id="chartCategorias"></canvas></div>
            <div class="mt-4 space-y-1.5 max-h-32 overflow-y-auto">
                <?php foreach ($rows_cat as $c): ?>
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: <?= e($c['color']) ?>"></span>
                        <span class="text-zinc-700 truncate"><?= e($c['nombre']) ?></span>
                    </div>
                    <span class="font-semibold text-zinc-900 ml-2"><?= $c['total'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Comparativa sucursales + Top áreas -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <?php if ($ver_todas && !empty($rows_comparativa)): ?>
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <div class="mb-4">
                <h3 class="font-display text-lg font-bold text-zinc-900">Comparativa por sucursal</h3>
                <p class="text-xs text-zinc-500 mt-0.5">Mes en curso</p>
            </div>
            <div class="space-y-4">
                <?php
                $max_total = max(array_map('intval', array_column($rows_comparativa, 'total'))) ?: 1;
                foreach ($rows_comparativa as $s):
                    $pct = ((int) $s['total'] / $max_total) * 100;
                ?>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-[10px] bg-zinc-100 text-zinc-600 px-1.5 py-0.5 rounded font-bold"><?= e($s['codigo']) ?></span>
                            <span class="font-semibold text-sm text-zinc-900"><?= e($s['nombre']) ?></span>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <?php if ((int)$s['criticas'] > 0): ?>
                            <span class="text-bacal-700 font-bold flex items-center gap-1">
                                <i data-lucide="zap" class="w-3 h-3"></i> <?= $s['criticas'] ?>
                            </span>
                            <?php endif; ?>
                            <span class="text-zinc-500"><?= $s['abiertas'] ?> abiertas</span>
                            <span class="font-bold text-zinc-900"><?= $s['total'] ?> total</span>
                        </div>
                    </div>
                    <div class="h-2 bg-zinc-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-bacal-700 to-bacal-600 rounded-full transition-all duration-500"
                             style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 <?= !$ver_todas || empty($rows_comparativa) ? 'lg:col-span-2' : '' ?>">
            <div class="mb-4">
                <h3 class="font-display text-lg font-bold text-zinc-900">Áreas con más reportes</h3>
                <p class="text-xs text-zinc-500 mt-0.5">Mes en curso</p>
            </div>
            <?php if (empty($top_areas)): ?>
            <div class="flex flex-col items-center justify-center py-12 text-center">
                <div class="w-12 h-12 rounded-full bg-zinc-100 flex items-center justify-center mb-3">
                    <i data-lucide="bar-chart-3" class="w-6 h-6 text-zinc-400"></i>
                </div>
                <p class="text-sm text-zinc-500">Sin reportes este mes</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($top_areas as $a):
                    $pct = ((int) $a['total'] / $max_top_areas) * 100;
                ?>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <?= badge($a['nombre'], $a['color']) ?>
                        <span class="font-bold text-sm text-zinc-900"><?= $a['total'] ?></span>
                    </div>
                    <div class="h-1.5 bg-zinc-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500"
                             style="width: <?= $pct ?>%; background-color: <?= e($a['color']) ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Últimas incidencias + Top equipos -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-zinc-100 flex items-center justify-between">
                <div>
                    <h3 class="font-display text-lg font-bold text-zinc-900">Últimas incidencias</h3>
                    <p class="text-xs text-zinc-500 mt-0.5">Las 8 más recientes</p>
                </div>
                <a href="<?= url('bitacora.php') ?>" class="text-xs text-zinc-500 hover:text-bacal-700 font-medium flex items-center gap-1">
                    Ver bitácora <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>
            <?php if (empty($ultimas)): ?>
            <div class="px-6 py-12 text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-3">
                    <i data-lucide="book-text" class="w-6 h-6 text-zinc-400"></i>
                </div>
                <p class="text-sm text-zinc-600 font-medium">Aún no hay incidencias registradas</p>
                <?php if (tiene_permiso('crear_solicitud')): ?>
                <a href="<?= url('incidencia_nueva.php') ?>" class="inline-flex items-center gap-1.5 mt-3 px-3 py-1.5 bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold rounded-lg">
                    <i data-lucide="plus" class="w-4 h-4"></i> Registrar la primera
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="divide-y divide-zinc-100">
                <?php foreach ($ultimas as $i): ?>
                <a href="<?= url('incidencia_ver.php?id=' . $i['id']) ?>" class="flex items-center gap-3 px-6 py-3 hover:bg-zinc-50 transition-colors group">
                    <div class="w-1 h-10 rounded-full flex-shrink-0" style="background-color: <?= e($i['severidad_color']) ?>"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                            <span class="font-mono text-[10px] text-zinc-400 font-semibold"><?= e($i['folio']) ?></span>
                            <?= badge($i['sucursal_codigo'], '#6B7280') ?>
                            <?php if ($i['es_reincidencia']): ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-purple-700 bg-purple-50 border border-purple-200 px-1.5 py-0.5 rounded-md">
                                    <i data-lucide="rotate-ccw" class="w-2.5 h-2.5"></i> Reincidencia
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="font-semibold text-sm text-zinc-900 truncate group-hover:text-bacal-700"><?= e($i['titulo']) ?></div>
                        <div class="text-xs text-zinc-500 mt-0.5">
                            <?= e($i['area_nombre']) ?> · <?= e($i['reportado_por_nombre']) ?> · <?= e(fmt_tiempo_relativo($i['fecha_evento'])) ?>
                        </div>
                    </div>
                    <?= badge($i['estado_nombre'], $i['estado_color']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <div class="mb-4">
                <h3 class="font-display text-lg font-bold text-zinc-900">Equipos con más fallas</h3>
                <p class="text-xs text-zinc-500 mt-0.5">Últimos 90 días</p>
            </div>
            <?php if (empty($top_equipos)): ?>
            <div class="flex flex-col items-center justify-center py-8 text-center">
                <div class="w-10 h-10 rounded-full bg-zinc-100 flex items-center justify-center mb-2">
                    <i data-lucide="monitor" class="w-5 h-5 text-zinc-400"></i>
                </div>
                <p class="text-xs text-zinc-500">Sin equipos con incidencias</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($top_equipos as $idx => $eq): ?>
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-md flex items-center justify-center font-bold text-xs flex-shrink-0
                                <?= $idx === 0 ? 'bg-bacal-700 text-white' : 'bg-zinc-100 text-zinc-600' ?>">
                        <?= $idx + 1 ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm text-zinc-900 truncate"><?= e($eq['nombre']) ?></div>
                        <div class="text-[11px] text-zinc-500 truncate">
                            <span class="font-mono"><?= e($eq['codigo_inventario']) ?></span> · <?= e($eq['sucursal_nombre']) ?>
                        </div>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        <div class="font-display text-lg font-extrabold text-zinc-900 leading-none"><?= $eq['total'] ?></div>
                        <div class="text-[10px] text-zinc-400 uppercase tracking-wide font-semibold">fallas</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
    const datosTendencia = {
        labels: <?= json_encode($labels_tendencia, JSON_UNESCAPED_UNICODE) ?>,
        total: <?= json_encode($datos_tendencia) ?>,
        reincidencias: <?= json_encode($datos_reincidencias) ?>
    };
    const datosCategorias = {
        labels: <?= json_encode($labels_cat, JSON_UNESCAPED_UNICODE) ?>,
        datos: <?= json_encode($datos_cat) ?>,
        colores: <?= json_encode($colores_cat, JSON_UNESCAPED_UNICODE) ?>
    };

    const ctxTend = document.getElementById('chartTendencia');
    if (ctxTend) {
        new Chart(ctxTend, {
            type: 'line',
            data: {
                labels: datosTendencia.labels,
                datasets: [
                    {
                        label: 'Total', data: datosTendencia.total,
                        borderColor: '#C8102E', backgroundColor: 'rgba(200, 16, 46, 0.08)',
                        borderWidth: 2.5, tension: 0.35, fill: true,
                        pointRadius: 0, pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#C8102E', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2
                    },
                    {
                        label: 'Reincidencias', data: datosTendencia.reincidencias,
                        borderColor: '#A855F7', backgroundColor: 'rgba(168, 85, 247, 0.05)',
                        borderWidth: 2, borderDash: [4, 4], tension: 0.35, fill: false,
                        pointRadius: 0, pointHoverRadius: 4
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#18181b', titleFont: { weight: 'bold', size: 12 }, bodyFont: { size: 12 }, padding: 10, cornerRadius: 8 }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#a1a1aa', font: { size: 10 } } },
                    y: { beginAtZero: true, grid: { color: '#f4f4f5' }, ticks: { color: '#a1a1aa', font: { size: 10 }, precision: 0 } }
                }
            }
        });
    }

    const ctxCat = document.getElementById('chartCategorias');
    if (ctxCat && datosCategorias.datos.length > 0) {
        new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: datosCategorias.labels,
                datasets: [{
                    data: datosCategorias.datos,
                    backgroundColor: datosCategorias.colores,
                    borderWidth: 2, borderColor: '#fff', hoverOffset: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: { backgroundColor: '#18181b', padding: 10, cornerRadius: 8 }
                }
            }
        });
    }
</script>

<script>
function sparklinesDashboard() {
    return {
        series: { creadas: [], resueltas: [], abiertas: [] },
        cargado: false,

        async cargar() {
            try {
                const resp = await fetch('<?= url('api/sparklines_dashboard.php') ?>', {
                    credentials: 'same-origin'
                });
                const data = await resp.json();
                if (data.ok) {
                    this.series = data.series;
                    this.cargado = true;
                }
            } catch (e) {
                console.error('Error sparklines:', e);
            }
        },

        sparkSvg(serie, color) {
            if (!this.cargado) return '';
            const datos = this.series[serie] || [];
            if (datos.length === 0 || datos.every(v => v === 0)) return '';

            const ancho = 80, alto = 32;
            const max = Math.max(...datos, 1);
            const min = Math.min(...datos, 0);
            const rango = Math.max(max - min, 1);
            const pasoX = ancho / (datos.length - 1);

            const puntos = datos.map((v, i) => {
                const x = i * pasoX;
                const y = alto - ((v - min) / rango) * (alto - 4) - 2;
                return `${x},${y}`;
            });

            // Línea principal
            const path = 'M ' + puntos.join(' L ');

            // Área debajo de la línea
            const areaPath = path + ` L ${ancho},${alto} L 0,${alto} Z`;

            // Último punto destacado
            const ultimo = puntos[puntos.length - 1].split(',');

            return `
                <svg viewBox="0 0 ${ancho} ${alto}" class="w-full h-full" preserveAspectRatio="none">
                    <path d="${areaPath}" fill="${color}" fill-opacity="0.12" />
                    <path d="${path}" fill="none" stroke="${color}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    <circle cx="${ultimo[0]}" cy="${ultimo[1]}" r="2" fill="${color}" />
                </svg>
            `;
        },
    }
}
</script>

<?php require_once __DIR__ . '/config/footer.php'; ?>
