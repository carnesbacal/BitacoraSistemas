<?php
/**
 * ============================================================================
 * reportes/incidencia_imprimir.php - Vista de impresión / PDF
 * ============================================================================
 * Vista limpia, optimizada para imprimir o guardar como PDF con Ctrl+P.
 * Incluye toda la información relevante de la incidencia en formato profesional.
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/incidencias_helpers.php';
require_once __DIR__ . '/../config/incidencia_costos_helpers.php';

requerir_login();

$id = (int) input('id', 0);
$i = $id > 0 ? cargar_incidencia($id) : null;

if (!$i || !puede_ver_incidencia($i)) {
    http_response_code(404);
    die('Incidencia no encontrada o sin permiso.');
}

$adjuntos    = cargar_adjuntos($id);
$comentarios = cargar_comentarios($id);
$historial   = cargar_historial($id);

$costos = costo_incidencia($id);
$proveedor_nombre_pdf = null;
if (!empty($i['proveedor_escalado_id'])) {
    $prov_pdf = db_one("SELECT nombre FROM proveedores WHERE id = :id", ['id' => (int) $i['proveedor_escalado_id']]);
    $proveedor_nombre_pdf = $prov_pdf['nombre'] ?? null;
}

// Registrar la generación del PDF en auditoría
registrar_auditoria('exportar_incidencia_pdf', 'incidencias', $id, "Exportó {$i['folio']} a PDF");
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= e($i['folio']) ?> · Carnes Bacal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .font-display { font-family: 'Bricolage Grotesque', sans-serif; letter-spacing: -0.02em; }

        @page { size: A4; margin: 15mm; }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .pagina { box-shadow: none !important; border: none !important; padding: 0 !important; }
        }

        @media screen {
            body { background: #f4f4f5; padding: 20px 0; }
            .pagina { box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin: 0 auto; padding: 40px; background: white; }
        }
    </style>
</head>
<body>

<!-- Barra de acciones (solo en pantalla) -->
<div class="no-print max-w-4xl mx-auto mb-4 flex items-center justify-between px-4">
    <a href="<?= url('incidencia_ver.php?id=' . $id) ?>" class="text-sm text-zinc-600 hover:text-bacal-700 flex items-center gap-1.5">
        ← Volver al detalle
    </a>
    <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-2">
        🖨️ Imprimir / Guardar como PDF
    </button>
</div>

<!-- Documento -->
<div class="pagina max-w-4xl">

    <!-- Encabezado corporativo -->
    <div class="border-b-2 border-zinc-900 pb-4 mb-6 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-lg bg-bacal-700 flex items-center justify-center text-white font-display font-extrabold text-2xl">
                B
            </div>
            <div>
                <div class="font-display text-2xl font-extrabold text-zinc-900">Carnes Bacal</div>
                <div class="text-[11px] text-zinc-500 uppercase tracking-widest">Reporte de Incidencia</div>
            </div>
        </div>
        <div class="text-right">
            <div class="font-mono text-lg font-bold text-zinc-900"><?= e($i['folio']) ?></div>
            <div class="text-[10px] text-zinc-500">Generado: <?= date('d/m/Y H:i') ?></div>
        </div>
    </div>

    <!-- Título y badges principales -->
    <div class="mb-6">
        <h1 class="font-display text-2xl font-extrabold text-zinc-900 leading-tight mb-3"><?= e($i['titulo']) ?></h1>
        <div class="flex flex-wrap gap-2">
            <?= badge($i['sucursal_nombre'], '#6B7280') ?>
            <?= badge($i['area_nombre'], $i['area_color']) ?>
            <?= badge($i['severidad_nombre'], $i['severidad_color']) ?>
            <?= badge($i['estado_nombre'], $i['estado_color']) ?>
            <?php if ($i['categoria_nombre']): ?><?= badge($i['categoria_nombre'], $i['categoria_color']) ?><?php endif; ?>
            <?php if ($i['tipo_trabajo_nombre']): ?><?= badge($i['tipo_trabajo_nombre'], $i['tipo_trabajo_color']) ?><?php endif; ?>
            <?php if ($i['es_reincidencia']): ?>
            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-purple-700 bg-purple-50 border border-purple-200 px-2 py-1 rounded-md">
                ↻ Reincidencia
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Información clave (2 columnas) -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div>
            <h3 class="font-display text-sm font-bold text-zinc-700 uppercase tracking-wide mb-3 border-b border-zinc-200 pb-1">Personas</h3>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-[10px] text-zinc-500 uppercase font-bold">Reportó</dt>
                    <dd class="text-zinc-900 font-medium"><?= e($i['reportado_por_nombre']) ?></dd>
                    <?php if ($i['reportante_nombre']): ?>
                    <dd class="text-xs text-zinc-500">A nombre de: <?= e($i['reportante_nombre']) ?><?= $i['reportante_puesto'] ? ' (' . e($i['reportante_puesto']) . ')' : '' ?></dd>
                    <?php endif; ?>
                </div>
                <div>
                    <dt class="text-[10px] text-zinc-500 uppercase font-bold">Asignado a</dt>
                    <dd class="text-zinc-900 font-medium"><?= e($i['asignado_a_nombre'] ?? '— Sin asignar —') ?></dd>
                </div>
                <?php if ($i['resuelto_por_nombre']): ?>
                <div>
                    <dt class="text-[10px] text-zinc-500 uppercase font-bold">Resolvió</dt>
                    <dd class="text-zinc-900 font-medium"><?= e($i['resuelto_por_nombre']) ?></dd>
                </div>
                <?php endif; ?>
            </dl>
        </div>

        <div>
            <h3 class="font-display text-sm font-bold text-zinc-700 uppercase tracking-wide mb-3 border-b border-zinc-200 pb-1">Tiempos</h3>
            <dl class="space-y-1.5 text-xs">
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500">Evento ocurrió:</dt>
                    <dd class="text-zinc-900 font-medium"><?= e(fmt_fecha($i['fecha_evento'])) ?></dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500">Atención iniciada:</dt>
                    <dd class="text-zinc-900 font-medium"><?= $i['fecha_atencion'] ? e(fmt_fecha($i['fecha_atencion'])) : '—' ?></dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500">Resuelta:</dt>
                    <dd class="text-zinc-900 font-medium"><?= $i['fecha_resolucion'] ? e(fmt_fecha($i['fecha_resolucion'])) : '—' ?></dd>
                </div>
                <?php if ($i['fecha_limite_sla']): ?>
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500">Límite SLA:</dt>
                    <dd class="text-zinc-900 font-medium"><?= e(fmt_fecha($i['fecha_limite_sla'])) ?></dd>
                </div>
                <?php endif; ?>
                <div class="flex justify-between gap-2 pt-1.5 border-t border-zinc-100 mt-1.5">
                    <dt class="text-zinc-500 font-semibold">T. respuesta:</dt>
                    <dd class="text-zinc-900 font-bold"><?= e(fmt_duracion($i['tiempo_respuesta_min'])) ?></dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500 font-semibold">T. resolución:</dt>
                    <dd class="text-zinc-900 font-bold"><?= e(fmt_duracion($i['tiempo_resolucion_min'])) ?></dd>
                </div>
                <?php if ($i['sla_cumplido'] !== null): ?>
                <div class="flex justify-between gap-2">
                    <dt class="text-zinc-500 font-semibold">SLA:</dt>
                    <dd class="font-bold <?= $i['sla_cumplido'] ? 'text-emerald-700' : 'text-bacal-700' ?>">
                        <?= $i['sla_cumplido'] ? '✓ Cumplido' : '✗ Incumplido' ?>
                    </dd>
                </div>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <!-- Equipo si aplica -->
    <?php if ($i['equipo_id']): ?>
    <div class="mb-6 bg-zinc-50 border border-zinc-200 rounded-lg p-4">
        <h3 class="font-display text-sm font-bold text-zinc-700 uppercase tracking-wide mb-2">Equipo / activo</h3>
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <div class="text-[10px] text-zinc-500 uppercase font-bold">Código</div>
                <div class="font-mono font-bold text-zinc-900"><?= e($i['equipo_codigo']) ?></div>
            </div>
            <div>
                <div class="text-[10px] text-zinc-500 uppercase font-bold">Nombre</div>
                <div class="text-zinc-900"><?= e($i['equipo_nombre']) ?></div>
            </div>
            <?php if ($i['equipo_tipo']): ?>
            <div>
                <div class="text-[10px] text-zinc-500 uppercase font-bold">Tipo</div>
                <div class="text-zinc-900"><?= e($i['equipo_tipo']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($i['equipo_marca'] || $i['equipo_modelo']): ?>
            <div>
                <div class="text-[10px] text-zinc-500 uppercase font-bold">Marca / Modelo</div>
                <div class="text-zinc-900"><?= e(trim(($i['equipo_marca'] ?? '') . ' ' . ($i['equipo_modelo'] ?? ''))) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Descripción -->
    <div class="mb-6">
        <h3 class="font-display text-sm font-bold text-zinc-700 uppercase tracking-wide mb-2 border-b border-zinc-200 pb-1">Descripción del problema</h3>
        <p class="text-sm text-zinc-700 leading-relaxed whitespace-pre-wrap"><?= e($i['descripcion']) ?></p>
    </div>

    <!-- Causa raíz -->
    <?php if ($i['causa_raiz']): ?>
    <div class="mb-6">
        <h3 class="font-display text-sm font-bold text-zinc-700 uppercase tracking-wide mb-2 border-b border-zinc-200 pb-1">Causa raíz</h3>
        <p class="text-sm text-zinc-700 leading-relaxed whitespace-pre-wrap"><?= e($i['causa_raiz']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Solución -->
    <?php if ($i['solucion']): ?>
    <div class="mb-6">
        <h3 class="font-display text-sm font-bold text-zinc-700 uppercase tracking-wide mb-2 border-b border-zinc-200 pb-1">Solución aplicada</h3>
        <p class="text-sm text-zinc-700 leading-relaxed whitespace-pre-wrap"><?= e($i['solucion']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Recomendaciones -->
    <?php if ($i['recomendaciones']): ?>
    <div class="mb-6 bg-amber-50 border-l-4 border-amber-400 p-4">
        <h3 class="font-display text-sm font-bold text-amber-800 uppercase tracking-wide mb-2">Recomendaciones para evitar recurrencia</h3>
        <p class="text-sm text-amber-900 leading-relaxed whitespace-pre-wrap"><?= e($i['recomendaciones']) ?></p>
    </div>
    <?php endif; ?>

    <!-- Costos -->
    <?php
    $ver_moi = puede_ver_mano_obra_interna();
    $mostrar_costos = $ver_moi ? $costos['tiene_costo'] : $costos['tiene_costo_visible'];
    $hay_prov = $proveedor_nombre_pdf || !empty($i['proveedor_externo_info']);
    ?>
    <?php if ($mostrar_costos || $hay_prov): ?>
    <div class="mb-6">
        <h3 class="font-display text-sm font-bold text-zinc-700 uppercase tracking-wide mb-2 border-b border-zinc-200 pb-1">Costos</h3>
        <?php if ($hay_prov): ?>
        <p class="text-sm text-zinc-600 mb-2">Atendió: <strong><?= e($proveedor_nombre_pdf ?: $i['proveedor_externo_info']) ?></strong></p>
        <?php endif; ?>
        <?php $total_pdf = $ver_moi ? $costos['total'] : $costos['total_visible']; ?>
        <table class="w-full text-sm border border-zinc-200">
            <tbody>
                <?php if ($costos['mano_obra'] > 0): ?>
                <tr class="border-b border-zinc-100">
                    <td class="px-3 py-1.5 text-zinc-600">Mano de obra proveedor</td>
                    <td class="px-3 py-1.5 text-right font-medium text-zinc-900"><?= e(fmt_dinero($costos['mano_obra'])) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($costos['materiales_proveedor'] > 0): ?>
                <tr class="border-b border-zinc-100">
                    <td class="px-3 py-1.5 text-zinc-600">Materiales del proveedor</td>
                    <td class="px-3 py-1.5 text-right font-medium text-zinc-900"><?= e(fmt_dinero($costos['materiales_proveedor'])) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($costos['materiales_comprados'] > 0): ?>
                <tr class="border-b border-zinc-100">
                    <td class="px-3 py-1.5 text-zinc-600">Materiales comprados</td>
                    <td class="px-3 py-1.5 text-right font-medium text-zinc-900"><?= e(fmt_dinero($costos['materiales_comprados'])) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($ver_moi && $costos['mano_obra_interna'] > 0): ?>
                <tr class="border-b border-zinc-100">
                    <td class="px-3 py-1.5 text-zinc-600">Mano de obra interna
                        <?php if ($costos['horas_trabajadas'] > 0): ?>
                        <span class="text-[10px] text-zinc-400">(<?= e(rtrim(rtrim(number_format($costos['horas_trabajadas'], 2), '0'), '.')) ?> h × <?= e(fmt_dinero($costos['tarifa_aplicada'])) ?>/h)</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-1.5 text-right font-medium text-zinc-900"><?= e(fmt_dinero($costos['mano_obra_interna'])) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="bg-zinc-50">
                    <td class="px-3 py-2 font-bold text-zinc-900 uppercase text-xs tracking-wide">Total</td>
                    <td class="px-3 py-2 text-right font-display font-extrabold text-base text-zinc-900"><?= e(fmt_dinero($total_pdf)) ?></td>
                </tr>
            </tbody>
        </table>
        <?php if ($costos['horas_trabajadas'] > 0): ?>
        <p class="text-xs text-zinc-500 mt-1.5">Tiempo activo: <strong><?= e(rtrim(rtrim(number_format($costos['horas_trabajadas'], 2), '0'), '.')) ?> h</strong></p>
        <?php endif; ?>
        <?php if (!empty($i['costo_notas'])): ?>
        <p class="text-xs text-zinc-500 mt-1.5 italic"><?= e($i['costo_notas']) ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Comentarios (timeline) -->
    <?php if (!empty($comentarios)): ?>
    <div class="mb-6">
        <h3 class="font-display text-sm font-bold text-zinc-700 uppercase tracking-wide mb-3 border-b border-zinc-200 pb-1">
            Comentarios y notas (<?= count($comentarios) ?>)
        </h3>
        <div class="space-y-3">
            <?php foreach ($comentarios as $c): ?>
            <div class="border-l-2 border-zinc-200 pl-3">
                <div class="text-[10px] text-zinc-500 mb-0.5">
                    <strong class="text-zinc-700"><?= e($c['usuario_nombre']) ?></strong>
                    · <?= e(fmt_fecha($c['creado_en'])) ?>
                </div>
                <div class="text-sm text-zinc-700 whitespace-pre-wrap"><?= e($c['comentario']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Adjuntos -->
    <?php if (!empty($adjuntos)): ?>
    <div class="mb-6">
        <h3 class="font-display text-sm font-bold text-zinc-700 uppercase tracking-wide mb-3 border-b border-zinc-200 pb-1">
            Adjuntos (<?= count($adjuntos) ?>)
        </h3>
        <?php
        $imagenes = array_filter($adjuntos, fn($a) => str_starts_with((string) $a['tipo_mime'], 'image/'));
        $otros = array_filter($adjuntos, fn($a) => !str_starts_with((string) $a['tipo_mime'], 'image/'));
        ?>

        <?php if (!empty($imagenes)): ?>
        <div class="grid grid-cols-3 gap-2 mb-3">
            <?php foreach (array_slice($imagenes, 0, 6) as $img): ?>
            <div class="border border-zinc-200 rounded p-1">
                <img src="<?= e(url('assets/' . $img['ruta'])) ?>" alt="" class="w-full h-32 object-cover rounded">
                <div class="text-[9px] text-zinc-500 truncate mt-1" title="<?= e($img['nombre_original']) ?>">
                    <?= e($img['nombre_original']) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($otros)): ?>
        <ul class="text-xs text-zinc-600 space-y-0.5">
            <?php foreach ($otros as $a): ?>
            <li>📎 <?= e($a['nombre_original']) ?> <span class="text-zinc-400">(<?= number_format($a['tamano_bytes'] / 1024, 0) ?> KB)</span></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Historial breve -->
    <?php if (!empty($historial)): ?>
    <div class="mb-6">
        <h3 class="font-display text-sm font-bold text-zinc-700 uppercase tracking-wide mb-3 border-b border-zinc-200 pb-1">
            Historial de cambios (últimos 10)
        </h3>
        <ul class="space-y-1 text-xs">
            <?php foreach (array_slice($historial, 0, 10) as $h): ?>
            <li class="flex gap-2 text-zinc-600">
                <span class="text-zinc-400 font-mono whitespace-nowrap"><?= e(fmt_fecha($h['creado_en'], false)) ?></span>
                <span><?= e($h['descripcion'] ?? $h['accion']) ?></span>
                <span class="text-zinc-400">— <?= e($h['usuario_nombre']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Pie -->
    <div class="mt-8 pt-4 border-t-2 border-zinc-900 flex items-center justify-between text-[10px] text-zinc-500">
        <div>Carnes Bacal · Sistema de Bitácora de Incidencias</div>
        <div>Documento generado automáticamente · <?= date('Y-m-d H:i') ?></div>
    </div>
</div>

</body>
</html>
