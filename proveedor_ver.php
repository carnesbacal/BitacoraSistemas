<?php
/**
 * ============================================================================
 * proveedor_ver.php - Vista detallada de un proveedor
 * ============================================================================
 * Muestra toda la información del proveedor con sus contactos, equipos
 * asociados, marcas/tipos que maneja e historial de incidencias escaladas.
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requerir_login();

$u_actual = usuario_actual();
$id = (int) input('id', 0);

$p = $id > 0 ? db_one("SELECT * FROM proveedores WHERE id = :id", ['id' => $id]) : null;

if (!$p) {
    $titulo_pagina = 'Proveedor no encontrado';
    require_once __DIR__ . '/config/header.php';
    ?>
    <div class="max-w-md mx-auto text-center py-20">
        <div class="w-16 h-16 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-4">
            <i data-lucide="search-x" class="w-8 h-8 text-zinc-400"></i>
        </div>
        <h2 class="font-display text-xl font-bold text-zinc-900 mb-2">Proveedor no encontrado</h2>
        <a href="<?= url('proveedores.php') ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold rounded-lg">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Volver al directorio
        </a>
    </div>
    <?php
    require_once __DIR__ . '/config/footer.php';
    exit;
}

// Cargar relacionados
$contactos = db_all("SELECT * FROM proveedor_contactos WHERE proveedor_id = :pid ORDER BY es_principal DESC, orden ASC", ['pid' => $id]);
$marcas    = db_all("SELECT marca FROM proveedor_marcas WHERE proveedor_id = :pid ORDER BY marca", ['pid' => $id]);
$tipos     = db_all("SELECT tipo FROM proveedor_tipos_equipo WHERE proveedor_id = :pid ORDER BY tipo", ['pid' => $id]);
$sucursales_prov = db_all(
    "SELECT s.nombre, s.codigo FROM proveedor_sucursales ps
     JOIN sucursales s ON ps.sucursal_id = s.id
     WHERE ps.proveedor_id = :pid ORDER BY s.nombre",
    ['pid' => $id]
);

// Equipos vinculados
$equipos = db_all(
    "SELECT e.id, e.codigo_inventario, e.nombre, e.tipo, e.marca, e.modelo, e.fecha_compra,
            s.nombre sucursal_nombre, s.codigo sucursal_codigo,
            a.nombre area_nombre,
            (SELECT COUNT(*) FROM incidencias WHERE equipo_id = e.id) AS incidencias_count
     FROM equipos e
     INNER JOIN sucursales s ON e.sucursal_id = s.id
     LEFT JOIN areas a ON e.area_id = a.id
     WHERE e.proveedor_id = :pid AND e.activo = 1
     ORDER BY s.nombre, e.codigo_inventario",
    ['pid' => $id]
);

// Incidencias donde se ha escalado a este proveedor
$incidencias = db_all(
    "SELECT i.id, i.folio, i.titulo, i.fecha_evento, i.fecha_resolucion,
            est.nombre estado_nombre, est.color estado_color,
            sev.nombre severidad_nombre, sev.color severidad_color,
            s.nombre sucursal_nombre
     FROM incidencias i
     INNER JOIN estados est ON i.estado_id = est.id
     INNER JOIN severidades sev ON i.severidad_id = sev.id
     INNER JOIN sucursales s ON i.sucursal_id = s.id
     WHERE i.proveedor_escalado_id = :pid
     ORDER BY i.fecha_evento DESC
     LIMIT 20",
    ['pid' => $id]
);

$titulo_pagina = $p['nombre'];
$pagina_activa = 'proveedores';
require_once __DIR__ . '/config/header.php';
?>

<div class="max-w-6xl mx-auto animate-fade-in space-y-5">

    <!-- Breadcrumb -->
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-sm">
            <a href="<?= url('proveedores.php') ?>" class="text-zinc-500 hover:text-bacal-700 flex items-center gap-1.5">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Proveedores
            </a>
            <i data-lucide="chevron-right" class="w-3 h-3 text-zinc-300"></i>
            <span class="text-zinc-700 font-medium"><?= e($p['nombre']) ?></span>
        </div>
        <?php if (tiene_permiso('administrar') || tiene_permiso('resolver')): ?>
        <a href="<?= url('proveedores.php?accion=editar&id=' . $id) ?>"
           class="px-3 py-1.5 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50 flex items-center gap-1.5">
            <i data-lucide="edit-3" class="w-4 h-4"></i> Editar
        </a>
        <?php endif; ?>
    </div>

    <!-- Header principal -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
        <div class="flex items-start gap-4">
            <div class="w-16 h-16 rounded-2xl bg-bacal-700 text-white flex items-center justify-center font-display font-extrabold text-2xl flex-shrink-0">
                <?= e(strtoupper(substr($p['nombre'], 0, 2))) ?>
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="font-display text-2xl font-extrabold text-zinc-900 leading-tight"><?= e($p['nombre']) ?></h1>
                <?php if ($p['razon_social']): ?>
                <p class="text-xs text-zinc-500 mt-0.5"><?= e($p['razon_social']) ?><?php if ($p['rfc']): ?> · <span class="font-mono"><?= e($p['rfc']) ?></span><?php endif; ?></p>
                <?php endif; ?>
                <?php if ($p['servicio']): ?>
                <p class="text-sm text-zinc-700 mt-2"><?= e($p['servicio']) ?></p>
                <?php endif; ?>

                <?php if ($p['calificacion']): ?>
                <div class="flex items-center gap-0.5 mt-2">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i data-lucide="star" class="w-4 h-4 <?= $i <= (int) $p['calificacion'] ? 'fill-amber-400 text-amber-400' : 'text-zinc-300' ?>"
                       style="<?= $i <= (int) $p['calificacion'] ? 'fill: #FBBF24' : '' ?>"></i>
                    <?php endfor; ?>
                    <span class="text-xs text-zinc-500 ml-1"><?= $p['calificacion'] ?>/5</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Estado activo/inactivo -->
            <div>
                <?php if ($p['activo']): ?>
                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-1 rounded-md">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Activo
                </span>
                <?php else: ?>
                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-zinc-500 bg-zinc-100 border border-zinc-200 px-2 py-1 rounded-md">
                    <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span> Inactivo
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Contactos</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= count($contactos) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Equipos asignados</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= count($equipos) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Incidencias escaladas</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= count($incidencias) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Tipos que maneja</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= count($tipos) ?></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Columna principal -->
        <div class="lg:col-span-2 space-y-5">

            <!-- Contactos -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
                <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                    <i data-lucide="users" class="w-4 h-4 text-bacal-700"></i> Contactos
                </h3>
                <?php if (empty($contactos)): ?>
                <p class="text-xs text-zinc-400 italic text-center py-6">Sin contactos individuales registrados.</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($contactos as $c): ?>
                    <div class="flex items-start gap-3 p-3 rounded-lg <?= $c['es_principal'] ? 'bg-bacal-50/30 border border-bacal-200' : 'bg-zinc-50' ?>">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                             style="background-color: <?= color_avatar($c['nombre']) ?>">
                            <?= e(iniciales($c['nombre'])) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-sm text-zinc-900"><?= e($c['nombre']) ?></span>
                                <?php if ($c['es_principal']): ?>
                                <span class="text-[9px] font-bold text-bacal-700 bg-bacal-50 border border-bacal-200 px-1.5 py-0.5 rounded">PRINCIPAL</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($c['puesto']): ?>
                            <div class="text-[11px] text-zinc-500 mb-1"><?= e($c['puesto']) ?></div>
                            <?php endif; ?>
                            <div class="space-y-0.5 mt-1.5">
                                <?php if ($c['telefono']): ?>
                                <a href="tel:<?= e($c['telefono']) ?>" class="flex items-center gap-1.5 text-xs text-zinc-700 hover:text-bacal-700">
                                    <i data-lucide="phone" class="w-3 h-3 text-zinc-400"></i> <?= e($c['telefono']) ?>
                                </a>
                                <?php endif; ?>
                                <?php if ($c['email']): ?>
                                <a href="mailto:<?= e($c['email']) ?>" class="flex items-center gap-1.5 text-xs text-zinc-700 hover:text-bacal-700">
                                    <i data-lucide="mail" class="w-3 h-3 text-zinc-400"></i> <?= e($c['email']) ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php if ($c['notas']): ?>
                            <div class="text-[11px] text-zinc-500 italic mt-1.5"><?= e($c['notas']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Equipos vinculados -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
                <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                    <i data-lucide="monitor" class="w-4 h-4 text-bacal-700"></i> Equipos suministrados
                </h3>
                <?php if (empty($equipos)): ?>
                <p class="text-xs text-zinc-400 italic text-center py-6">Aún no hay equipos vinculados a este proveedor.</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-zinc-200">
                            <tr>
                                <th class="px-2 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">Código</th>
                                <th class="px-2 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">Equipo</th>
                                <th class="px-2 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">Sucursal</th>
                                <th class="px-2 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">Compra</th>
                                <th class="px-2 py-2 text-right text-[10px] font-bold text-zinc-500 uppercase">Fallas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            <?php foreach ($equipos as $eq): ?>
                            <tr class="hover:bg-zinc-50">
                                <td class="px-2 py-2 font-mono text-xs font-bold text-zinc-700"><?= e($eq['codigo_inventario']) ?></td>
                                <td class="px-2 py-2">
                                    <div class="font-semibold text-sm text-zinc-900"><?= e($eq['nombre']) ?></div>
                                    <?php if ($eq['marca'] || $eq['modelo']): ?>
                                    <div class="text-[10px] text-zinc-500"><?= e(trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? ''))) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 py-2">
                                    <span class="font-mono text-[10px] bg-zinc-100 text-zinc-600 px-1.5 py-0.5 rounded font-bold"><?= e($eq['sucursal_codigo']) ?></span>
                                </td>
                                <td class="px-2 py-2 text-xs text-zinc-600">
                                    <?= $eq['fecha_compra'] ? e(date('d/m/Y', strtotime($eq['fecha_compra']))) : '—' ?>
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <?php if ((int) $eq['incidencias_count'] > 0): ?>
                                    <span class="inline-flex items-center text-xs font-bold text-bacal-700"><?= $eq['incidencias_count'] ?></span>
                                    <?php else: ?>
                                    <span class="text-xs text-zinc-400">0</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Incidencias escaladas -->
            <?php if (!empty($incidencias)): ?>
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
                <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                    <i data-lucide="phone-call" class="w-4 h-4 text-bacal-700"></i> Incidencias escaladas a este proveedor
                </h3>
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
                                    <?= e($inc['sucursal_nombre']) ?> · <?= e(fmt_fecha($inc['fecha_evento'], false)) ?>
                                </div>
                            </div>
                            <i data-lucide="arrow-up-right" class="w-4 h-4 text-zinc-300 flex-shrink-0"></i>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="space-y-5">

            <!-- Contacto rápido -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-zinc-600 uppercase tracking-wide mb-3">Contacto rápido</h3>
                <div class="space-y-2">
                    <?php if ($p['telefono']): ?>
                    <a href="tel:<?= e($p['telefono']) ?>"
                       class="flex items-center gap-2 px-3 py-2 rounded-lg bg-zinc-50 hover:bg-zinc-100 text-sm text-zinc-900">
                        <i data-lucide="phone" class="w-4 h-4 text-emerald-600"></i>
                        <span class="font-medium"><?= e($p['telefono']) ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($p['email']): ?>
                    <a href="mailto:<?= e($p['email']) ?>"
                       class="flex items-center gap-2 px-3 py-2 rounded-lg bg-zinc-50 hover:bg-zinc-100 text-sm text-zinc-900">
                        <i data-lucide="mail" class="w-4 h-4 text-blue-600"></i>
                        <span class="font-medium truncate"><?= e($p['email']) ?></span>
                    </a>
                    <?php endif; ?>
                    <?php if ($p['sitio_web']): ?>
                    <a href="<?= e($p['sitio_web']) ?>" target="_blank" rel="noopener"
                       class="flex items-center gap-2 px-3 py-2 rounded-lg bg-zinc-50 hover:bg-zinc-100 text-sm text-zinc-900">
                        <i data-lucide="globe" class="w-4 h-4 text-purple-600"></i>
                        <span class="font-medium truncate"><?= e(preg_replace('#^https?://(www\.)?#', '', $p['sitio_web'])) ?></span>
                    </a>
                    <?php endif; ?>
                </div>

                <?php if ($p['direccion']): ?>
                <div class="mt-3 pt-3 border-t border-zinc-100">
                    <div class="text-[10px] text-zinc-500 uppercase font-bold mb-1">Dirección</div>
                    <p class="text-xs text-zinc-700"><?= e($p['direccion']) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($p['horario_atencion']): ?>
                <div class="mt-3 pt-3 border-t border-zinc-100">
                    <div class="text-[10px] text-zinc-500 uppercase font-bold mb-1">Horario de atención</div>
                    <p class="text-xs text-zinc-700 flex items-center gap-1.5">
                        <i data-lucide="clock" class="w-3.5 h-3.5 text-zinc-400"></i>
                        <?= e($p['horario_atencion']) ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sucursales que atiende -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-zinc-600 uppercase tracking-wide mb-3 flex items-center gap-1.5">
                    <i data-lucide="store" class="w-3.5 h-3.5"></i> Sucursales que atiende
                </h3>
                <?php if (empty($sucursales_prov)): ?>
                <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-50 border border-emerald-200">
                    <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                    <span class="text-sm font-medium text-emerald-800">Todas las sucursales</span>
                </div>
                <?php else: ?>
                <div class="flex flex-wrap gap-1.5">
                    <?php foreach ($sucursales_prov as $s): ?>
                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-zinc-700 bg-zinc-100 px-2.5 py-1 rounded-lg">
                        <i data-lucide="map-pin" class="w-3 h-3 text-bacal-700"></i>
                        <?= e($s['nombre']) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Marcas y tipos -->
            <?php if (!empty($marcas) || !empty($tipos)): ?>
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-zinc-600 uppercase tracking-wide mb-3">Productos / Servicios</h3>

                <?php if (!empty($tipos)): ?>
                <div class="mb-3">
                    <div class="text-[10px] text-zinc-500 uppercase font-bold mb-1.5">Tipos de equipo</div>
                    <div class="flex flex-wrap gap-1">
                        <?php foreach ($tipos as $t): ?>
                        <span class="inline-block text-[10px] font-medium text-zinc-700 bg-zinc-100 px-2 py-0.5 rounded">
                            <?= e($t['tipo']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($marcas)): ?>
                <div>
                    <div class="text-[10px] text-zinc-500 uppercase font-bold mb-1.5">Marcas</div>
                    <div class="flex flex-wrap gap-1">
                        <?php foreach ($marcas as $m): ?>
                        <span class="inline-block text-[10px] font-medium text-bacal-700 bg-bacal-50 border border-bacal-200 px-2 py-0.5 rounded">
                            <?= e($m['marca']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Notas -->
            <?php if ($p['notas']): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
                <h3 class="text-xs font-bold text-amber-800 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                    <i data-lucide="sticky-note" class="w-3.5 h-3.5"></i> Notas internas
                </h3>
                <p class="text-xs text-amber-900 whitespace-pre-wrap"><?= e($p['notas']) ?></p>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>
