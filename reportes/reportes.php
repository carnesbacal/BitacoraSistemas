<?php
/**
 * ============================================================================
 * reportes/reportes.php - Página principal de reportes
 * ============================================================================
 * Hub central con tarjetas para acceder a cada tipo de reporte disponible.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/reportes_helpers.php';

$titulo_pagina = 'Reportes';
$pagina_activa = 'reportes';
require_once __DIR__ . '/../config/header.php';

$reportes = [
    [
        'titulo' => 'Reporte mensual ejecutivo',
        'descripcion' => 'KPIs principales, tendencias, distribuciones y comparativa entre sucursales del período seleccionado.',
        'icono' => 'bar-chart-3',
        'color' => '#2563EB',
        'url' => url('reportes/reporte_mensual.php'),
        'tags' => ['KPIs', 'Gráficas', 'Comparativas'],
    ],
    [
        'titulo' => 'Reporte de reincidencias',
        'descripcion' => 'Problemas recurrentes detectados. Identifica equipos, áreas y categorías que generan repetidamente las mismas incidencias.',
        'icono' => 'rotate-ccw',
        'color' => '#7C3AED',
        'url' => url('reportes/reporte_reincidencias.php'),
        'tags' => ['Recurrencias', 'Análisis'],
    ],
    [
        'titulo' => 'Productividad por técnico',
        'descripcion' => 'Carga de trabajo, tiempos promedio y cumplimiento de SLA por cada ingeniero/técnico.',
        'icono' => 'users',
        'color' => '#16A34A',
        'url' => url('reportes/reporte_tecnicos.php'),
        'tags' => ['Equipo', 'Desempeño'],
    ],
    [
        'titulo' => 'Análisis de SLA',
        'descripcion' => 'Cumplimiento de acuerdos de nivel de servicio. Detecta puntos débiles en tiempos de respuesta y resolución.',
        'icono' => 'target',
        'color' => '#DC2626',
        'url' => url('reportes/reporte_sla.php'),
        'tags' => ['SLA', 'Calidad'],
    ],
    [
        'titulo' => 'Bitácora completa (CSV)',
        'descripcion' => 'Exporta todas las incidencias del período en formato CSV para análisis en Excel u otras herramientas.',
        'icono' => 'download',
        'color' => '#0EA5E9',
        'url' => url('bitacora.php?exportar=csv'),
        'tags' => ['Exportar', 'Excel'],
    ],
];

// Análisis de costos: solo administradores (incluye mano de obra interna confidencial)
if (tiene_permiso('administrar')) {
    array_splice($reportes, 3, 0, [[
        'titulo' => 'Análisis de costos',
        'descripcion' => 'Gasto en proveedores, materiales y mano de obra interna. Tendencia, incidencias más caras, proveedores y costos por sucursal.',
        'icono' => 'hand-coins',
        'color' => '#36454F',
        'url' => url('reportes/reporte_costos.php'),
        'tags' => ['Costos', 'Presupuesto', 'Confidencial'],
    ]]);
}
?>

<div class="animate-fade-in">
    <div class="mb-8">
        <h2 class="font-display text-3xl font-extrabold text-zinc-900 leading-tight">Reportes</h2>
        <p class="text-sm text-zinc-500 mt-1">Análisis e información detallada del sistema</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($reportes as $r): ?>
        <a href="<?= e($r['url']) ?>" class="group bg-white rounded-xl border border-zinc-200 shadow-sm hover:shadow-md hover:border-zinc-300 transition-all p-6">
            <div class="flex items-start justify-between mb-4">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center transition-transform group-hover:scale-110"
                     style="background-color: <?= e($r['color']) ?>15">
                    <i data-lucide="<?= e($r['icono']) ?>" class="w-5 h-5" style="color: <?= e($r['color']) ?>"></i>
                </div>
                <i data-lucide="arrow-up-right" class="w-4 h-4 text-zinc-300 group-hover:text-bacal-700 transition-colors"></i>
            </div>

            <h3 class="font-display text-base font-bold text-zinc-900 mb-2 group-hover:text-bacal-700 transition-colors">
                <?= e($r['titulo']) ?>
            </h3>
            <p class="text-xs text-zinc-500 leading-relaxed mb-4">
                <?= e($r['descripcion']) ?>
            </p>

            <div class="flex flex-wrap gap-1.5">
                <?php foreach ($r['tags'] as $tag): ?>
                <span class="inline-flex items-center text-[10px] font-semibold text-zinc-600 bg-zinc-100 px-2 py-0.5 rounded-md">
                    <?= e($tag) ?>
                </span>
                <?php endforeach; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Sección de exportación de incidencia individual -->
    <div class="mt-8 bg-gradient-to-br from-zinc-50 to-white rounded-xl border border-zinc-200 p-6">
        <h3 class="font-display text-lg font-bold text-zinc-900 mb-2 flex items-center gap-2">
            <i data-lucide="file-text" class="w-5 h-5 text-bacal-700"></i>
            Imprimir / exportar incidencias individuales
        </h3>
        <p class="text-sm text-zinc-600 leading-relaxed">
            Para exportar una incidencia específica en PDF (ideal para imprimir, enviar por correo o archivar),
            ve al detalle de la incidencia desde la <a href="<?= url('bitacora.php') ?>" class="text-bacal-700 hover:underline font-semibold">bitácora</a> y usa el botón <strong>"Exportar PDF"</strong> que aparece en la cabecera.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
