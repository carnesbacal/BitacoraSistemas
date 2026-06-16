<?php
/**
 * ============================================================================
 * mantenimientos.php - Listado y calendario de mantenimientos
 * ============================================================================
 * Dos vistas: lista (con filtros) y calendario mensual.
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/mantenimientos_helpers.php';

requerir_login();

// Actualizar estados antes de mostrar (próximo/vencido)
actualizar_estados_mantenimientos();

$u = usuario_actual();

// ----------------------------------------------------------------------------
// Filtros
// ----------------------------------------------------------------------------
$vista       = (string) input('vista', 'lista'); // lista | calendario
$f_estado    = (string) input('estado', '');
$f_sucursal  = (int) input('sucursal_id', 0);
$f_equipo    = (int) input('equipo_id', 0);
$f_asignado  = (int) input('asignado_a', 0);

// Permisos por sucursal: si no es admin/ingeniero, limitamos a su sucursal
if (!tiene_permiso('ver_todas_sucursales') && !tiene_permiso('administrar') && $u['sucursal_id']) {
    $f_sucursal = (int) $u['sucursal_id'];
}

// Calendario: año y mes
$cal_anio = (int) input('anio', (int) date('Y'));
$cal_mes  = (int) input('mes', (int) date('n'));
if ($cal_mes < 1) { $cal_mes = 12; $cal_anio--; }
if ($cal_mes > 12) { $cal_mes = 1; $cal_anio++; }

// ----------------------------------------------------------------------------
// Construir WHERE común
// ----------------------------------------------------------------------------
$where = ['1=1'];
$params = [];

if ($f_estado !== '' && isset(MANTENIMIENTO_ESTADOS[$f_estado])) {
    $where[] = "m.estado = :est";
    $params['est'] = $f_estado;
}
if ($f_sucursal > 0) {
    $where[] = "e.sucursal_id = :sid";
    $params['sid'] = $f_sucursal;
}
if ($f_equipo > 0) {
    $where[] = "m.equipo_id = :eid";
    $params['eid'] = $f_equipo;
}
if ($f_asignado > 0) {
    $where[] = "m.asignado_a_id = :aid";
    $params['aid'] = $f_asignado;
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// ----------------------------------------------------------------------------
// Datos según vista
// ----------------------------------------------------------------------------
if ($vista === 'calendario') {
    // Rango: inicio del mes hasta fin
    $primer_dia = sprintf('%04d-%02d-01', $cal_anio, $cal_mes);
    $ultimo_dia = date('Y-m-t', strtotime($primer_dia));

    $where[] = "m.fecha_programada BETWEEN :fa AND :fb";
    $params['fa'] = $primer_dia;
    $params['fb'] = $ultimo_dia;
    $where_sql = 'WHERE ' . implode(' AND ', $where);

    $mantenimientos = db_all(
        "SELECT m.*, e.codigo_inventario equipo_codigo, e.nombre equipo_nombre,
                s.nombre sucursal_nombre, u.nombre_completo asignado_nombre,
                p.nombre proveedor_nombre,
                (SELECT COUNT(*) FROM mantenimiento_equipos me WHERE me.mantenimiento_id = m.id) AS num_equipos
         FROM mantenimientos m
         INNER JOIN equipos e ON m.equipo_id = e.id
         INNER JOIN sucursales s ON e.sucursal_id = s.id
         LEFT JOIN usuarios u ON m.asignado_a_id = u.id
         LEFT JOIN proveedores p ON m.proveedor_id = p.id
         $where_sql
         ORDER BY m.fecha_programada ASC, m.hora_programada ASC",
        $params
    );

    // Agrupar por día
    $por_dia = [];
    foreach ($mantenimientos as $m) {
        $dia = (int) date('j', strtotime($m['fecha_programada']));
        if (!isset($por_dia[$dia])) $por_dia[$dia] = [];
        $por_dia[$dia][] = $m;
    }
} else {
    // Vista lista
    $mantenimientos = db_all(
        "SELECT m.*, e.codigo_inventario equipo_codigo, e.nombre equipo_nombre,
                s.nombre sucursal_nombre, u.nombre_completo asignado_nombre,
                p.nombre proveedor_nombre,
                (SELECT COUNT(*) FROM mantenimiento_equipos me WHERE me.mantenimiento_id = m.id) AS num_equipos
         FROM mantenimientos m
         INNER JOIN equipos e ON m.equipo_id = e.id
         INNER JOIN sucursales s ON e.sucursal_id = s.id
         LEFT JOIN usuarios u ON m.asignado_a_id = u.id
         LEFT JOIN proveedores p ON m.proveedor_id = p.id
         $where_sql
         ORDER BY
            CASE m.estado
                WHEN 'vencido' THEN 1
                WHEN 'proximo' THEN 2
                WHEN 'en_progreso' THEN 3
                WHEN 'programado' THEN 4
                WHEN 'completado' THEN 5
                WHEN 'cancelado' THEN 6
            END,
            m.fecha_programada ASC, m.hora_programada ASC
         LIMIT 200",
        $params
    );
}

// Contadores para los tabs (ignorando filtro de estado para que muestre todos)
$where_sin_estado = array_filter($where, fn($w) => strpos($w, 'm.estado') === false);
$params_sin_estado = array_diff_key($params, ['est' => '']);
$where_sin_estado_sql = 'WHERE ' . implode(' AND ', $where_sin_estado);
$contadores = [];
foreach (array_keys(MANTENIMIENTO_ESTADOS) as $est) {
    $r = db_one(
        "SELECT COUNT(*) c FROM mantenimientos m
         INNER JOIN equipos e ON m.equipo_id = e.id
         $where_sin_estado_sql AND m.estado = :est",
        array_merge($params_sin_estado, ['est' => $est])
    );
    $contadores[$est] = (int) ($r['c'] ?? 0);
}

// Catálogos para filtros
$sucursales = db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo=1 ORDER BY nombre");
$tecnicos = db_all(
    "SELECT u.id, u.nombre_completo FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE u.activo = 1 AND r.puede_resolver = 1 ORDER BY u.nombre_completo"
);

$titulo_pagina = 'Mantenimientos';
$pagina_activa = 'mantenimientos';
require_once __DIR__ . '/config/header.php';
?>

<div class="animate-fade-in space-y-5">

    <!-- Header -->
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">Mantenimientos</h2>
            <p class="text-xs text-zinc-500 mt-0.5">Programa, da seguimiento y registra mantenimientos a los equipos.</p>
        </div>
        <?php if (puede_administrar_mantenimientos()): ?>
        <a href="<?= url('mantenimiento_nuevo.php') ?>"
           class="flex items-center gap-1.5 px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold shadow-sm transition-colors">
            <i data-lucide="plus" class="w-4 h-4"></i> Programar mantenimiento
        </a>
        <?php endif; ?>
    </div>

    <!-- Selector de vista -->
    <div class="flex items-center justify-between gap-3">
        <div class="inline-flex rounded-lg border border-zinc-300 overflow-hidden">
            <a href="?vista=lista<?= $f_sucursal ? '&sucursal_id=' . $f_sucursal : '' ?>"
               class="px-4 py-1.5 text-sm font-semibold flex items-center gap-1.5 <?= $vista === 'lista' ? 'bg-bacal-700 text-white' : 'bg-white text-zinc-700 hover:bg-zinc-50' ?>">
                <i data-lucide="list" class="w-4 h-4"></i> Lista
            </a>
            <a href="?vista=calendario<?= $f_sucursal ? '&sucursal_id=' . $f_sucursal : '' ?>"
               class="px-4 py-1.5 text-sm font-semibold flex items-center gap-1.5 <?= $vista === 'calendario' ? 'bg-bacal-700 text-white' : 'bg-white text-zinc-700 hover:bg-zinc-50 border-l border-zinc-300' ?>">
                <i data-lucide="calendar" class="w-4 h-4"></i> Calendario
            </a>
        </div>
    </div>

    <?php if ($vista === 'lista'): ?>
    <!-- =====================================================
         VISTA LISTA
         ===================================================== -->

    <!-- Tabs de estado -->
    <div class="border-b border-zinc-200">
        <div class="flex gap-1 -mb-px overflow-x-auto">
            <?php
            $base_url = $f_sucursal ? "?vista=lista&sucursal_id=$f_sucursal" : "?vista=lista";
            ?>
            <a href="<?= e($base_url) ?>"
               class="flex items-center gap-1.5 px-3 py-2 text-sm font-semibold border-b-2 whitespace-nowrap <?= $f_estado === '' ? 'border-bacal-700 text-bacal-700' : 'border-transparent text-zinc-500 hover:text-zinc-700' ?>">
                Todos <span class="text-[10px] font-bold bg-zinc-100 text-zinc-700 px-1.5 py-0.5 rounded ml-1"><?= array_sum($contadores) ?></span>
            </a>
            <?php foreach (MANTENIMIENTO_ESTADOS as $key => $cfg):
                if ($contadores[$key] === 0) continue;
            ?>
            <a href="<?= e($base_url . '&estado=' . $key) ?>"
               class="flex items-center gap-1.5 px-3 py-2 text-sm font-semibold border-b-2 whitespace-nowrap transition-colors"
               style="<?= $f_estado === $key ? "border-color: {$cfg['color']}; color: {$cfg['color']};" : 'border-color: transparent; color: #71717a;' ?>">
                <i data-lucide="<?= e($cfg['icono']) ?>" class="w-3.5 h-3.5"></i>
                <?= e($cfg['nombre']) ?>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded ml-1"
                      style="background-color: <?= e($cfg['color']) ?>15; color: <?= e($cfg['color']) ?>">
                    <?= $contadores[$key] ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Filtros adicionales -->
    <?php if (tiene_permiso('ver_todas_sucursales') || count($tecnicos) > 1): ?>
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="vista" value="lista">
        <?php if ($f_estado): ?><input type="hidden" name="estado" value="<?= e($f_estado) ?>"><?php endif; ?>

        <?php if (tiene_permiso('ver_todas_sucursales')): ?>
        <select name="sucursal_id" onchange="this.form.submit()"
                class="px-3 py-1.5 rounded-lg border border-zinc-300 bg-white text-xs">
            <option value="">Todas las sucursales</option>
            <?php foreach ($sucursales as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $f_sucursal == $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <select name="asignado_a" onchange="this.form.submit()"
                class="px-3 py-1.5 rounded-lg border border-zinc-300 bg-white text-xs">
            <option value="">Todos los técnicos</option>
            <?php foreach ($tecnicos as $t): ?>
            <option value="<?= $t['id'] ?>" <?= $f_asignado == $t['id'] ? 'selected' : '' ?>><?= e($t['nombre_completo']) ?></option>
            <?php endforeach; ?>
        </select>

        <?php if ($f_sucursal || $f_asignado || $f_estado): ?>
        <a href="<?= url('mantenimientos.php') ?>" class="px-3 py-1.5 rounded-lg border border-zinc-300 text-zinc-700 text-xs hover:bg-zinc-50">Limpiar</a>
        <?php endif; ?>
    </form>
    <?php endif; ?>

    <!-- Tabla -->
    <?php if (empty($mantenimientos)): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-12 text-center">
        <div class="w-16 h-16 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-3">
            <i data-lucide="wrench" class="w-8 h-8 text-zinc-400"></i>
        </div>
        <p class="text-sm font-medium text-zinc-700">Sin mantenimientos <?= $f_estado ? 'en este estado' : 'registrados' ?></p>
        <?php if (puede_administrar_mantenimientos()): ?>
        <a href="<?= url('mantenimiento_nuevo.php') ?>"
           class="mt-4 inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
            <i data-lucide="plus" class="w-4 h-4"></i> Programar el primero
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 border-b border-zinc-200">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Fecha</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Estado</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Equipo</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Mantenimiento</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Asignado</th>
                        <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Sucursal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($mantenimientos as $m):
                        $dias = (strtotime($m['fecha_programada']) - strtotime(date('Y-m-d'))) / 86400;
                    ?>
                    <tr class="hover:bg-zinc-50 cursor-pointer" onclick="window.location='<?= url('mantenimiento_ver.php?id=' . $m['id']) ?>'">
                        <td class="px-4 py-2.5">
                            <div class="text-sm font-semibold text-zinc-900"><?= e(date('d/M/Y', strtotime($m['fecha_programada']))) ?></div>
                            <?php if ($m['hora_programada']): ?>
                            <div class="text-[10px] text-zinc-500"><?= e(date('H:i', strtotime($m['hora_programada']))) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2.5">
                            <?= badge_estado_mant($m['estado']) ?>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1.5">
                                <a href="<?= url('equipo_ver.php?id=' . $m['equipo_id']) ?>" onclick="event.stopPropagation()"
                                   class="font-mono text-xs font-bold text-zinc-700 hover:text-bacal-700"><?= e($m['equipo_codigo']) ?></a>
                                <?php if ((int) ($m['num_equipos'] ?? 1) > 1): ?>
                                <span class="inline-flex items-center gap-0.5 text-[10px] font-bold text-bacal-700 bg-bacal-50 border border-bacal-200 px-1.5 py-0.5 rounded"
                                      title="<?= (int) $m['num_equipos'] ?> equipos en este mantenimiento">
                                    +<?= (int) $m['num_equipos'] - 1 ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[11px] text-zinc-500 truncate">
                                <?php if ((int) ($m['num_equipos'] ?? 1) > 1): ?>
                                <?= (int) $m['num_equipos'] ?> equipos
                                <?php else: ?>
                                <?= e($m['equipo_nombre']) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="font-semibold text-sm text-zinc-900"><?= e($m['titulo']) ?></div>
                            <?php if ($m['es_recurrente']): ?>
                            <div class="text-[10px] text-purple-700 mt-0.5 flex items-center gap-1">
                                <i data-lucide="repeat" class="w-3 h-3"></i> <?= e(fmt_recurrencia($m['recurrencia_tipo'], (int) $m['recurrencia_valor'])) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2.5 text-xs">
                            <?php if ($m['asignado_nombre']): ?>
                            <span class="text-zinc-700"><?= e($m['asignado_nombre']) ?></span>
                            <?php elseif ($m['proveedor_nombre']): ?>
                            <span class="text-bacal-700"><i data-lucide="truck" class="w-3 h-3 inline -mt-0.5"></i> <?= e($m['proveedor_nombre']) ?></span>
                            <?php else: ?>
                            <span class="text-zinc-400 italic">Sin asignar</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-zinc-600"><?= e($m['sucursal_nombre']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- =====================================================
         VISTA CALENDARIO
         ===================================================== -->

    <?php
    $nombres_meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $primer_dia_mes = mktime(0, 0, 0, $cal_mes, 1, $cal_anio);
    $dia_semana_inicio = (int) date('w', $primer_dia_mes); // 0=domingo
    $total_dias = (int) date('t', $primer_dia_mes);
    ?>

    <!-- Navegación de calendario -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-4 flex items-center justify-between">
        <a href="?vista=calendario&anio=<?= $cal_mes === 1 ? $cal_anio - 1 : $cal_anio ?>&mes=<?= $cal_mes === 1 ? 12 : $cal_mes - 1 ?><?= $f_sucursal ? '&sucursal_id=' . $f_sucursal : '' ?>"
           class="p-2 rounded-lg text-zinc-600 hover:bg-zinc-100">
            <i data-lucide="chevron-left" class="w-5 h-5"></i>
        </a>
        <div class="text-center">
            <h3 class="font-display text-lg font-extrabold text-zinc-900"><?= e($nombres_meses[$cal_mes - 1]) ?> <?= $cal_anio ?></h3>
            <a href="?vista=calendario<?= $f_sucursal ? '&sucursal_id=' . $f_sucursal : '' ?>" class="text-[11px] text-bacal-700 hover:underline">Hoy</a>
        </div>
        <a href="?vista=calendario&anio=<?= $cal_mes === 12 ? $cal_anio + 1 : $cal_anio ?>&mes=<?= $cal_mes === 12 ? 1 : $cal_mes + 1 ?><?= $f_sucursal ? '&sucursal_id=' . $f_sucursal : '' ?>"
           class="p-2 rounded-lg text-zinc-600 hover:bg-zinc-100">
            <i data-lucide="chevron-right" class="w-5 h-5"></i>
        </a>
    </div>

    <!-- Cuadrícula del calendario -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <!-- Cabecera de días -->
        <div class="grid grid-cols-7 border-b border-zinc-200 bg-zinc-50">
            <?php foreach (['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'] as $d): ?>
            <div class="px-2 py-2 text-center text-[10px] font-bold text-zinc-500 uppercase"><?= e($d) ?></div>
            <?php endforeach; ?>
        </div>

        <!-- Días -->
        <div class="grid grid-cols-7">
            <?php
            // Espacios vacíos al inicio
            for ($i = 0; $i < $dia_semana_inicio; $i++):
            ?>
            <div class="min-h-[100px] border-r border-b border-zinc-100 bg-zinc-50/30"></div>
            <?php endfor;

            // Días del mes
            for ($dia = 1; $dia <= $total_dias; $dia++):
                $es_hoy = ($cal_anio === (int) date('Y') && $cal_mes === (int) date('n') && $dia === (int) date('j'));
                $eventos_dia = $por_dia[$dia] ?? [];
            ?>
            <div class="min-h-[100px] border-r border-b border-zinc-100 p-1.5 <?= $es_hoy ? 'bg-bacal-50/30' : '' ?>">
                <div class="text-xs font-bold mb-1 <?= $es_hoy ? 'text-bacal-700' : 'text-zinc-500' ?>">
                    <?= $dia ?>
                    <?php if ($es_hoy): ?>
                    <span class="ml-1 text-[8px] font-normal">HOY</span>
                    <?php endif; ?>
                </div>
                <?php foreach (array_slice($eventos_dia, 0, 3) as $ev):
                    $cfg = MANTENIMIENTO_ESTADOS[$ev['estado']] ?? MANTENIMIENTO_ESTADOS['programado'];
                ?>
                <a href="<?= url('mantenimiento_ver.php?id=' . $ev['id']) ?>"
                   class="block text-[10px] truncate px-1 py-0.5 rounded mb-0.5 hover:opacity-80 transition-opacity"
                   style="background-color: <?= e($cfg['color']) ?>15; color: <?= e($cfg['color']) ?>; border-left: 2px solid <?= e($cfg['color']) ?>"
                   title="<?= e($ev['titulo']) ?> · <?= e($ev['equipo_codigo']) ?><?= (int) ($ev['num_equipos'] ?? 1) > 1 ? ' (+' . ((int) $ev['num_equipos'] - 1) . ')' : '' ?>">
                    <span class="font-mono font-bold"><?= e($ev['equipo_codigo']) ?></span><?php if ((int) ($ev['num_equipos'] ?? 1) > 1): ?><span class="font-bold">+<?= (int) $ev['num_equipos'] - 1 ?></span><?php endif; ?>
                    <span class="opacity-75 truncate"><?= e($ev['titulo']) ?></span>
                </a>
                <?php endforeach; ?>
                <?php if (count($eventos_dia) > 3): ?>
                <div class="text-[9px] text-zinc-500 px-1 mt-0.5">+ <?= count($eventos_dia) - 3 ?> más</div>
                <?php endif; ?>
            </div>
            <?php endfor;

            // Espacios vacíos al final para completar la grid (múltiplo de 7)
            $celdas_usadas = $dia_semana_inicio + $total_dias;
            $celdas_faltantes = (7 - ($celdas_usadas % 7)) % 7;
            for ($i = 0; $i < $celdas_faltantes; $i++):
            ?>
            <div class="min-h-[100px] border-r border-b border-zinc-100 bg-zinc-50/30"></div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Leyenda -->
    <div class="flex flex-wrap items-center gap-3 text-xs">
        <?php foreach (MANTENIMIENTO_ESTADOS as $key => $cfg): ?>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded" style="background-color: <?= e($cfg['color']) ?>"></span>
            <span class="text-zinc-600"><?= e($cfg['nombre']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>
