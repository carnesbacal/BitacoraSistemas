<?php
/**
 * ============================================================================
 * bitacora.php - Listado completo de incidencias
 * ============================================================================
 * Página principal de operación. Permite ver, filtrar, buscar y gestionar
 * todas las incidencias del sistema. Tiene dos vistas:
 *   - Tabla: vista densa con muchas columnas y ordenamiento
 *   - Kanban: tablero por estado, ideal para ver el flujo de trabajo
 *
 * Soporta filtros pre-aplicados vía GET desde el dashboard (alertas).
 * Respeta permisos: usuarios sin "ver_todas_sucursales" solo ven la suya.
 * Permite exportar el resultado filtrado a CSV.
 * ============================================================================
 */

// Cargar configuración primero (necesario para usar funciones helper)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/incidencia_costos_helpers.php';
requerir_login();

$titulo_pagina = 'Bitácora';
$pagina_activa = 'bitacora';

// Si es exportación a CSV, no cargamos el header (debe enviar binario)
$es_exportacion = (input('exportar') === 'csv');

if (!$es_exportacion) {
    require_once __DIR__ . '/config/header.php';
}

$u = usuario_actual();

// ----------------------------------------------------------------------------
// Captura de filtros desde GET
// ----------------------------------------------------------------------------
$f_busqueda     = trim((string) input('q', ''));
$f_sucursal     = (int) input('sucursal', 0);
$f_area         = (int) input('area', 0);
$f_categoria    = (int) input('categoria', 0);
$f_tipo_trabajo = (int) input('tipo_trabajo', 0);
$f_severidad    = (int) input('severidad', 0);
$f_estado       = (int) input('estado', 0);
$f_asignado_a   = (int) input('asignado_a', 0);
$f_equipo       = (int) input('equipo', 0);
$f_fecha_desde  = (string) input('fecha_desde', '');
$f_fecha_hasta  = (string) input('fecha_hasta', '');
$f_reincidencia = (int) input('reincidencia', 0);
$f_abiertas     = (int) input('abiertas', 0);            // solo no-finales
$f_sin_asignar  = (int) input('sin_asignar', 0);
$f_sla_riesgo   = (int) input('sla_riesgo', 0);
$f_sla_vencido  = (int) input('sla_vencido', 0);
$f_sin_actualizar = (int) input('sin_actualizar', 0);
$f_archivadas   = (int) input('archivadas', 0);          // 0=ocultar archivadas, 1=incluir, 2=solo archivadas

$orden_campo    = (string) input('orden', 'creado_en');
$orden_dir      = strtolower((string) input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
$vista          = (string) input('vista', 'tabla');     // 'tabla' o 'kanban'
$pagina         = max(1, (int) input('p', 1));
$por_pagina     = 25;

// Whitelist de columnas para ordenamiento (seguridad SQL)
$columnas_ordenables = [
    'creado_en'   => 'i.creado_en',
    'folio'       => 'i.folio',
    'fecha_evento'=> 'i.fecha_evento',
    'severidad'   => 'sev.nivel',
    'estado'      => 'est.orden',
    'titulo'      => 'i.titulo',
];
$columna_sql = $columnas_ordenables[$orden_campo] ?? 'i.creado_en';

// ----------------------------------------------------------------------------
// Aplicar restricción de sucursal por rol
// ----------------------------------------------------------------------------
$ver_todas = tiene_permiso('ver_todas_sucursales');
if (!$ver_todas && $u['sucursal_id']) {
    $f_sucursal = (int) $u['sucursal_id']; // Forzar a su sucursal
}

// ----------------------------------------------------------------------------
// Construir cláusulas WHERE dinámicas
// ----------------------------------------------------------------------------
$where  = [];
$params = [];

if ($f_busqueda !== '') {
    $where[] = "(i.folio LIKE :q OR i.titulo LIKE :q2 OR i.descripcion LIKE :q3 OR i.solucion LIKE :q4)";
    $params['q']  = "%{$f_busqueda}%";
    $params['q2'] = "%{$f_busqueda}%";
    $params['q3'] = "%{$f_busqueda}%";
    $params['q4'] = "%{$f_busqueda}%";
}
if ($f_sucursal > 0)     { $where[] = "i.sucursal_id = :sid";     $params['sid'] = $f_sucursal; }
if ($f_area > 0)         { $where[] = "i.area_id = :aid";         $params['aid'] = $f_area; }
if ($f_categoria > 0)    { $where[] = "i.categoria_id = :cid";    $params['cid'] = $f_categoria; }
if ($f_tipo_trabajo > 0) { $where[] = "i.tipo_trabajo_id = :ttid"; $params['ttid'] = $f_tipo_trabajo; }
if ($f_severidad > 0)    { $where[] = "sev.nivel = :sevnivel";    $params['sevnivel'] = $f_severidad; }
if ($f_estado > 0)       { $where[] = "i.estado_id = :estid";     $params['estid'] = $f_estado; }
if ($f_asignado_a > 0)   { $where[] = "i.asignado_a_id = :auid";  $params['auid'] = $f_asignado_a; }
if ($f_equipo > 0)       { $where[] = "i.equipo_id = :eqid";      $params['eqid'] = $f_equipo; }
if ($f_fecha_desde !== ''){ $where[] = "DATE(i.fecha_evento) >= :fdesde"; $params['fdesde'] = $f_fecha_desde; }
if ($f_fecha_hasta !== ''){ $where[] = "DATE(i.fecha_evento) <= :fhasta"; $params['fhasta'] = $f_fecha_hasta; }
if ($f_reincidencia)     { $where[] = "i.es_reincidencia = 1"; }
if ($f_abiertas)         { $where[] = "est.es_final = 0"; }
if ($f_sin_asignar)      { $where[] = "i.asignado_a_id IS NULL"; }
if ($f_sla_riesgo)       { $where[] = "i.fecha_limite_sla BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 HOUR) AND est.es_final = 0"; }
if ($f_sla_vencido)      { $where[] = "i.fecha_limite_sla < NOW() AND est.es_final = 0"; }
if ($f_sin_actualizar)   { $where[] = "i.actualizado_en < DATE_SUB(NOW(), INTERVAL 7 DAY) AND est.es_final = 0"; }

// Filtro de archivadas (Fase 15): por default solo NO archivadas
if ($f_archivadas === 2)      { $where[] = "i.archivada = 1"; }
elseif ($f_archivadas === 1)  { /* incluir todas */ }
else                          { $where[] = "i.archivada = 0"; }

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// ----------------------------------------------------------------------------
// Cargar catálogos para los selectores
// ----------------------------------------------------------------------------
$cat_sucursales = $ver_todas
    ? db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo=1 ORDER BY nombre")
    : [];
$cat_areas      = db_all("SELECT id, nombre, color FROM areas WHERE activo=1 ORDER BY nombre");
$cat_categorias = db_all("SELECT id, nombre, color FROM categorias WHERE activo=1 ORDER BY nombre");
$cat_tipos      = db_all("SELECT id, nombre, color FROM tipos_trabajo WHERE activo=1 ORDER BY nombre");
$cat_severidades= db_all("SELECT id, nombre, nivel, color FROM severidades WHERE activo=1 ORDER BY nivel");
$cat_estados    = db_all("SELECT id, nombre, color, orden, es_final FROM estados WHERE activo=1 ORDER BY orden");
$cat_tecnicos   = db_all(
    "SELECT u.id, u.nombre_completo
     FROM usuarios u INNER JOIN roles r ON u.rol_id = r.id
     WHERE u.activo=1 AND r.puede_resolver=1
     ORDER BY u.nombre_completo"
);

// ----------------------------------------------------------------------------
// Conteo total para paginación
// ----------------------------------------------------------------------------
$row_total = db_one(
    "SELECT COUNT(*) c
     FROM incidencias i
     INNER JOIN estados est ON i.estado_id = est.id
     INNER JOIN severidades sev ON i.severidad_id = sev.id
     $where_sql",
    $params
);
$total_resultados = (int) ($row_total['c'] ?? 0);
$total_paginas    = max(1, (int) ceil($total_resultados / $por_pagina));
$pagina           = min($pagina, $total_paginas);
$offset           = ($pagina - 1) * $por_pagina;

// ----------------------------------------------------------------------------
// SQL principal — usado tanto para tabla, kanban y exportación
// ----------------------------------------------------------------------------
$sql_base = "
    SELECT
        i.id, i.folio, i.titulo, i.descripcion, i.es_reincidencia,
        i.fecha_evento, i.fecha_atencion, i.fecha_resolucion, i.fecha_cierre,
        i.fecha_limite_sla, i.sla_cumplido, i.creado_en,
        i.tiempo_respuesta_min, i.tiempo_resolucion_min,
        s.id sucursal_id, s.nombre sucursal_nombre, s.codigo sucursal_codigo,
        a.id area_id, a.nombre area_nombre, a.color area_color,
        c.id categoria_id, c.nombre categoria_nombre, c.color categoria_color,
        tt.id tipo_trabajo_id, tt.nombre tipo_trabajo_nombre, tt.color tipo_trabajo_color,
        sev.id severidad_id, sev.nombre severidad_nombre, sev.color severidad_color, sev.nivel severidad_nivel,
        est.id estado_id, est.nombre estado_nombre, est.color estado_color, est.orden estado_orden, est.es_final estado_es_final,
        eq.id equipo_id, eq.codigo_inventario equipo_codigo, eq.nombre equipo_nombre,
        rep.id reportado_por_id, rep.nombre_completo reportado_por_nombre,
        asig.id asignado_a_id, asig.nombre_completo asignado_a_nombre,
        i.horas_trabajadas,
        (COALESCE(i.costo_mano_obra,0) + COALESCE(i.costo_materiales_proveedor,0)
         + COALESCE(i.costo_materiales_comprados,0)
         + (COALESCE(i.horas_trabajadas,0) * COALESCE(i.tarifa_hora_aplicada,0))) AS costo_total,
        (COALESCE(i.costo_mano_obra,0) + COALESCE(i.costo_materiales_proveedor,0)
         + COALESCE(i.costo_materiales_comprados,0)) AS costo_total_visible
    FROM incidencias i
    INNER JOIN sucursales s ON i.sucursal_id = s.id
    INNER JOIN areas a ON i.area_id = a.id
    LEFT JOIN categorias c ON i.categoria_id = c.id
    LEFT JOIN tipos_trabajo tt ON i.tipo_trabajo_id = tt.id
    INNER JOIN severidades sev ON i.severidad_id = sev.id
    INNER JOIN estados est ON i.estado_id = est.id
    LEFT JOIN equipos eq ON i.equipo_id = eq.id
    INNER JOIN usuarios rep ON i.reportado_por_id = rep.id
    LEFT JOIN usuarios asig ON i.asignado_a_id = asig.id
    $where_sql
";

// ----------------------------------------------------------------------------
// EXPORTACIÓN A CSV
// ----------------------------------------------------------------------------
if ($es_exportacion) {
    // Para exportar, no paginar — traer todo lo filtrado
    $sql_export = $sql_base . " ORDER BY i.creado_en DESC";
    $rows = db_all($sql_export, $params);

    $filename = 'bitacora_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");

    $out = fopen('php://output', 'w');
    // BOM para que Excel abra UTF-8 correctamente
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'Folio', 'Título', 'Sucursal', 'Área', 'Categoría', 'Tipo de trabajo',
        'Severidad', 'Estado', 'Equipo', 'Reportó',
        'Asignado a', 'Reincidencia',
        'Fecha del evento', 'Fecha de atención', 'Fecha de resolución',
        'Tiempo respuesta (min)', 'Tiempo resolución (min)',
        'SLA cumplido', 'Horas trabajadas', 'Costo total', 'Descripción',
    ]);

    $ver_moi = puede_ver_mano_obra_interna();
    foreach ($rows as $r) {
        $costo_csv = $ver_moi ? (float) ($r['costo_total'] ?? 0) : (float) ($r['costo_total_visible'] ?? 0);
        fputcsv($out, [
            $r['folio'], $r['titulo'], $r['sucursal_nombre'], $r['area_nombre'],
            $r['categoria_nombre'] ?? '', $r['tipo_trabajo_nombre'] ?? '',
            $r['severidad_nombre'], $r['estado_nombre'], $r['equipo_nombre'] ?? '',
            $r['reportado_por_nombre'],
            $r['asignado_a_nombre'] ?? '', $r['es_reincidencia'] ? 'Sí' : 'No',
            $r['fecha_evento'], $r['fecha_atencion'] ?? '', $r['fecha_resolucion'] ?? '',
            $r['tiempo_respuesta_min'] ?? '', $r['tiempo_resolucion_min'] ?? '',
            $r['sla_cumplido'] === null ? '' : ($r['sla_cumplido'] ? 'Sí' : 'No'),
            $r['horas_trabajadas'] ?? '',
            number_format($costo_csv, 2, '.', ''),
            mb_substr((string)$r['descripcion'], 0, 500),
        ]);
    }
    fclose($out);
    registrar_auditoria('exportar_bitacora', null, null, "Exportó $total_resultados incidencias a CSV");
    exit;
}

// ----------------------------------------------------------------------------
// Cargar incidencias (con paginación o todas si es kanban)
// ----------------------------------------------------------------------------
if ($vista === 'kanban') {
    // En kanban traemos hasta 200 para evitar problemas de rendimiento
    $sql = $sql_base . " ORDER BY sev.nivel ASC, i.fecha_evento DESC LIMIT 200";
    $incidencias = db_all($sql, $params);
} else {
    $sql = $sql_base . " ORDER BY $columna_sql $orden_dir LIMIT $por_pagina OFFSET $offset";
    $incidencias = db_all($sql, $params);
}

// ----------------------------------------------------------------------------
// Helpers locales
// ----------------------------------------------------------------------------
/** Genera una URL con los filtros actuales preservados, sustituyendo claves */
function url_filtros(array $cambios = []): string {
    $params = array_merge($_GET, $cambios);
    foreach ($params as $k => $v) {
        if ($v === '' || $v === 0 || $v === '0' || $v === null) unset($params[$k]);
    }
    return url('bitacora.php') . (empty($params) ? '' : '?' . http_build_query($params));
}

/** Saber si la incidencia tiene SLA en riesgo */
function sla_estado(array $i): string {
    if (!empty($i['estado_es_final'])) return 'cerrada';
    if (empty($i['fecha_limite_sla'])) return 'sin_sla';
    $limite = strtotime($i['fecha_limite_sla']);
    $ahora  = time();
    if ($limite < $ahora) return 'vencido';
    if ($limite - $ahora < 7200) return 'riesgo'; // < 2 horas
    return 'ok';
}

// Determinar si hay filtros activos para mostrar chip "Limpiar"
$hay_filtros = !empty($f_busqueda) || $f_sucursal || $f_area || $f_categoria
    || $f_tipo_trabajo || $f_severidad || $f_estado || $f_asignado_a
    || $f_equipo || $f_fecha_desde || $f_fecha_hasta || $f_reincidencia
    || $f_abiertas || $f_sin_asignar || $f_sla_riesgo || $f_sla_vencido
    || $f_sin_actualizar || $f_archivadas;
?>

<div class="space-y-4 animate-fade-in"
     x-data="{ panelFiltros: false }">

    <!-- ============================================================ -->
    <!-- Header con controles -->
    <!-- ============================================================ -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h2 class="font-display text-2xl font-extrabold text-zinc-900">Bitácora</h2>
                <?php if (tiene_permiso('ver_todas_sucursales') && count($cat_sucursales) > 1 && usuario_prefiere_radio_sucursal()): ?>
                <form method="GET" class="flex items-center gap-2 flex-wrap">
                    <?php foreach ($_GET as $k => $v): if ($k === 'sucursal' || $k === 'p') continue; if ($v !== '' && $v !== '0') echo '<input type="hidden" name="' . e($k) . '" value="' . e((string)$v) . '">'; endforeach; ?>
                    <label class="flex items-center gap-1.5 cursor-pointer text-sm font-medium text-zinc-600">
                        <input type="radio" name="sucursal" value="" onchange="this.form.submit()"
                               <?= !$f_sucursal ? 'checked' : '' ?>>
                        Todas
                    </label>
                    <?php foreach ($cat_sucursales as $s): ?>
                    <label class="flex items-center gap-1.5 cursor-pointer text-sm font-medium text-zinc-600">
                        <input type="radio" name="sucursal" value="<?= $s['id'] ?>" onchange="this.form.submit()"
                               <?= $f_sucursal == $s['id'] ? 'checked' : '' ?>>
                        <?= e($s['nombre']) ?>
                    </label>
                    <?php endforeach; ?>
                </form>
                <?php endif; ?>
                <span class="bg-zinc-100 text-zinc-600 text-xs font-bold px-2 py-1 rounded-md">
                    <?= number_format($total_resultados) ?>
                    <?= $total_resultados === 1 ? 'registro' : 'registros' ?>
                </span>
            </div>
            <p class="text-xs text-zinc-500 mt-0.5">
                <?= $hay_filtros ? 'Resultados filtrados' : 'Todas las incidencias' ?>
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">

            <!-- Búsqueda rápida -->
            <form method="GET" class="relative">
                <?php
                // Preservar filtros actuales
                foreach ($_GET as $k => $v) {
                    if ($k === 'q' || $k === 'p') continue;
                    if ($v !== '' && $v !== '0') {
                        echo '<input type="hidden" name="' . e($k) . '" value="' . e((string)$v) . '">';
                    }
                }
                ?>
                <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400"></i>
                <input type="text" name="q" value="<?= e($f_busqueda) ?>"
                       placeholder="Buscar folio, título, descripción..."
                       class="pl-9 pr-3 py-2 w-72 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700 focus:ring-2 focus:ring-bacal-100">
            </form>

            <!-- Selector rápido de sucursal (como el dashboard) -->
            <?php if ($ver_todas && !usuario_prefiere_radio_sucursal()): ?>
            <form method="GET" class="relative">
                <?php
                // Preservar el resto de filtros al cambiar de sucursal (menos la paginación)
                foreach ($_GET as $k => $v) {
                    if ($k === 'sucursal' || $k === 'p') continue;
                    if ($v !== '' && $v !== '0') {
                        echo '<input type="hidden" name="' . e($k) . '" value="' . e((string)$v) . '">';
                    }
                }
                ?>
                <i data-lucide="store" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400"></i>
                <select name="sucursal" onchange="this.form.submit()"
                        class="pl-9 pr-8 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 focus:outline-none focus:border-bacal-700 focus:ring-2 focus:ring-bacal-100 appearance-none cursor-pointer">
                    <option value="">Todas las sucursales</option>
                    <?php foreach ($cat_sucursales as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $f_sucursal == $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <i data-lucide="chevron-down" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none"></i>
            </form>
            <?php endif; ?>

            <!-- Toggle vista tabla / kanban -->
            <div class="flex bg-zinc-100 rounded-lg p-0.5 border border-zinc-200">
                <a href="<?= url_filtros(['vista' => 'tabla']) ?>"
                   class="px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors
                          <?= $vista === 'tabla' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-700' ?>">
                    <i data-lucide="table" class="w-3.5 h-3.5"></i>
                    Tabla
                </a>
                <a href="<?= url_filtros(['vista' => 'kanban']) ?>"
                   class="px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors
                          <?= $vista === 'kanban' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500 hover:text-zinc-700' ?>">
                    <i data-lucide="kanban" class="w-3.5 h-3.5"></i>
                    Kanban
                </a>
            </div>

            <!-- Botón filtros -->
            <button @click="panelFiltros = !panelFiltros"
                    class="flex items-center gap-1.5 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50 transition-colors"
                    :class="panelFiltros ? 'border-bacal-700 text-bacal-700 bg-bacal-50' : ''">
                <i data-lucide="filter" class="w-4 h-4"></i>
                Filtros
                <?php if ($hay_filtros): ?>
                <span class="bg-bacal-700 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">●</span>
                <?php endif; ?>
            </button>

            <!-- Exportar -->
            <a href="<?= url_filtros(['exportar' => 'csv']) ?>"
               class="flex items-center gap-1.5 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50 transition-colors"
               title="Exportar resultado actual a CSV">
                <i data-lucide="download" class="w-4 h-4"></i>
                Exportar
            </a>

            <!-- Nueva incidencia -->
            <?php if (tiene_permiso('crear_solicitud')): ?>
            <a href="<?= url('incidencia_nueva.php') ?>"
               class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold shadow-sm transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Nueva
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- Chips de filtros activos -->
    <!-- ============================================================ -->
    <?php if ($hay_filtros): ?>
    <div class="flex items-center gap-2 flex-wrap text-xs">
        <span class="text-zinc-500 font-medium">Filtros activos:</span>

        <?php if ($f_busqueda !== ''): ?>
        <a href="<?= url_filtros(['q' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-medium">
            "<?= e($f_busqueda) ?>" <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_sucursal > 0 && $ver_todas):
            $s = array_filter($cat_sucursales, fn($x) => $x['id'] == $f_sucursal);
            $s = reset($s); ?>
        <a href="<?= url_filtros(['sucursal' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-medium">
            Sucursal: <?= e($s['nombre'] ?? '') ?> <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_severidad > 0):
            $s = array_filter($cat_severidades, fn($x) => $x['nivel'] == $f_severidad);
            $s = reset($s); ?>
        <a href="<?= url_filtros(['severidad' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full hover:opacity-80 font-medium"
           style="background-color: <?= e($s['color'] ?? '#e4e4e7') ?>20; color: <?= e($s['color'] ?? '#52525b') ?>">
            Severidad: <?= e($s['nombre'] ?? '') ?> <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_estado > 0):
            $s = array_filter($cat_estados, fn($x) => $x['id'] == $f_estado);
            $s = reset($s); ?>
        <a href="<?= url_filtros(['estado' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full hover:opacity-80 font-medium"
           style="background-color: <?= e($s['color'] ?? '#e4e4e7') ?>20; color: <?= e($s['color'] ?? '#52525b') ?>">
            Estado: <?= e($s['nombre'] ?? '') ?> <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_area > 0):
            $a = array_filter($cat_areas, fn($x) => $x['id'] == $f_area);
            $a = reset($a); ?>
        <a href="<?= url_filtros(['area' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full hover:opacity-80 font-medium"
           style="background-color: <?= e($a['color'] ?? '#e4e4e7') ?>20; color: <?= e($a['color'] ?? '#52525b') ?>">
            Área: <?= e($a['nombre'] ?? '') ?> <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_categoria > 0):
            $c = array_filter($cat_categorias, fn($x) => $x['id'] == $f_categoria);
            $c = reset($c); ?>
        <a href="<?= url_filtros(['categoria' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-medium">
            Categoría: <?= e($c['nombre'] ?? '') ?> <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_tipo_trabajo > 0):
            $t = array_filter($cat_tipos, fn($x) => $x['id'] == $f_tipo_trabajo);
            $t = reset($t); ?>
        <a href="<?= url_filtros(['tipo_trabajo' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-medium">
            Tipo: <?= e($t['nombre'] ?? '') ?> <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_asignado_a > 0):
            $u_asig = array_filter($cat_tecnicos, fn($x) => $x['id'] == $f_asignado_a);
            $u_asig = reset($u_asig); ?>
        <a href="<?= url_filtros(['asignado_a' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-medium">
            Técnico: <?= e($u_asig['nombre_completo'] ?? '') ?> <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_fecha_desde !== '' || $f_fecha_hasta !== ''): ?>
        <a href="<?= url_filtros(['fecha_desde' => '', 'fecha_hasta' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-medium">
            Fecha: <?= e($f_fecha_desde ?: '...') ?> a <?= e($f_fecha_hasta ?: '...') ?> <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_reincidencia): ?>
        <a href="<?= url_filtros(['reincidencia' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-purple-100 hover:bg-purple-200 text-purple-800 font-medium">
            <i data-lucide="rotate-ccw" class="w-3 h-3"></i> Solo reincidencias <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_abiertas): ?>
        <a href="<?= url_filtros(['abiertas' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-100 hover:bg-amber-200 text-amber-800 font-medium">
            <i data-lucide="circle-dot" class="w-3 h-3"></i> Solo abiertas <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_sin_asignar): ?>
        <a href="<?= url_filtros(['sin_asignar' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-bacal-100 hover:bg-bacal-200 text-bacal-800 font-medium">
            <i data-lucide="user-x" class="w-3 h-3"></i> Sin asignar <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_sla_riesgo): ?>
        <a href="<?= url_filtros(['sla_riesgo' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-100 hover:bg-amber-200 text-amber-800 font-medium">
            <i data-lucide="clock-alert" class="w-3 h-3"></i> SLA en riesgo <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_sla_vencido): ?>
        <a href="<?= url_filtros(['sla_vencido' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-bacal-100 hover:bg-bacal-200 text-bacal-800 font-medium">
            <i data-lucide="flame" class="w-3 h-3"></i> SLA vencido <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <?php if ($f_sin_actualizar): ?>
        <a href="<?= url_filtros(['sin_actualizar' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-800 font-medium">
            <i data-lucide="clock" class="w-3 h-3"></i> Sin actualizar 7+ días <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>
        <?php if ($f_archivadas === 1): ?>
        <a href="<?= url_filtros(['archivadas' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-zinc-200 hover:bg-zinc-300 text-zinc-700 font-medium">
            <i data-lucide="archive" class="w-3 h-3"></i> Incluye archivadas <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php elseif ($f_archivadas === 2): ?>
        <a href="<?= url_filtros(['archivadas' => '']) ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-100 hover:bg-amber-200 text-amber-800 font-medium">
            <i data-lucide="archive" class="w-3 h-3"></i> Solo archivadas <i data-lucide="x" class="w-3 h-3"></i>
        </a>
        <?php endif; ?>

        <a href="<?= url('bitacora.php') ?>" class="text-bacal-700 hover:text-bacal-800 font-semibold ml-1">Limpiar todo</a>
    </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- Panel de filtros avanzados (colapsable) -->
    <!-- ============================================================ -->
    <div x-show="panelFiltros" x-cloak x-transition
         class="bg-white border border-zinc-200 rounded-xl shadow-sm p-5">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Preservar vista actual -->
            <input type="hidden" name="vista" value="<?= e($vista) ?>">
            <?php if ($f_busqueda !== ''): ?>
            <input type="hidden" name="q" value="<?= e($f_busqueda) ?>">
            <?php endif; ?>

            <?php if ($ver_todas && $f_sucursal > 0): ?>
            <!-- La sucursal se elige con el botón de arriba; aquí la preservamos -->
            <input type="hidden" name="sucursal" value="<?= (int) $f_sucursal ?>">
            <?php endif; ?>

            <div>
                <label class="block text-[11px] font-bold text-zinc-600 mb-1 uppercase tracking-wide">Área</label>
                <select name="area" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">Todas</option>
                    <?php foreach ($cat_areas as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $f_area == $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-zinc-600 mb-1 uppercase tracking-wide">Categoría</label>
                <select name="categoria" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">Todas</option>
                    <?php foreach ($cat_categorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $f_categoria == $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-zinc-600 mb-1 uppercase tracking-wide">Tipo de trabajo</label>
                <select name="tipo_trabajo" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">Todos</option>
                    <?php foreach ($cat_tipos as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $f_tipo_trabajo == $t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-zinc-600 mb-1 uppercase tracking-wide">Severidad</label>
                <select name="severidad" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">Todas</option>
                    <?php foreach ($cat_severidades as $s): ?>
                    <option value="<?= $s['nivel'] ?>" <?= $f_severidad == $s['nivel'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-zinc-600 mb-1 uppercase tracking-wide">Estado</label>
                <select name="estado" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">Todos</option>
                    <?php foreach ($cat_estados as $est): ?>
                    <option value="<?= $est['id'] ?>" <?= $f_estado == $est['id'] ? 'selected' : '' ?>><?= e($est['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-zinc-600 mb-1 uppercase tracking-wide">Asignado a</label>
                <select name="asignado_a" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">Cualquiera</option>
                    <?php foreach ($cat_tecnicos as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $f_asignado_a == $t['id'] ? 'selected' : '' ?>><?= e($t['nombre_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-zinc-600 mb-1 uppercase tracking-wide">Fecha desde</label>
                <input type="date" name="fecha_desde" value="<?= e($f_fecha_desde) ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-zinc-600 mb-1 uppercase tracking-wide">Fecha hasta</label>
                <input type="date" name="fecha_hasta" value="<?= e($f_fecha_hasta) ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-zinc-600 mb-1 uppercase tracking-wide">Archivadas (>1 año)</label>
                <select name="archivadas" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="0" <?= $f_archivadas === 0 ? 'selected' : '' ?>>Ocultar archivadas</option>
                    <option value="1" <?= $f_archivadas === 1 ? 'selected' : '' ?>>Incluir archivadas</option>
                    <option value="2" <?= $f_archivadas === 2 ? 'selected' : '' ?>>Solo archivadas</option>
                </select>
            </div>

            <div class="md:col-span-2 lg:col-span-4 flex flex-wrap gap-4 items-center pt-2 border-t border-zinc-100">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="reincidencia" value="1" <?= $f_reincidencia ? 'checked' : '' ?>
                           class="rounded border-zinc-300 text-bacal-700 focus:ring-bacal-700">
                    <span class="text-zinc-700">Solo reincidencias</span>
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="abiertas" value="1" <?= $f_abiertas ? 'checked' : '' ?>
                           class="rounded border-zinc-300 text-bacal-700 focus:ring-bacal-700">
                    <span class="text-zinc-700">Solo abiertas</span>
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="sin_asignar" value="1" <?= $f_sin_asignar ? 'checked' : '' ?>
                           class="rounded border-zinc-300 text-bacal-700 focus:ring-bacal-700">
                    <span class="text-zinc-700">Sin asignar</span>
                </label>
            </div>

            <div class="md:col-span-2 lg:col-span-4 flex justify-end gap-2 pt-3 border-t border-zinc-100">
                <a href="<?= url('bitacora.php') ?>"
                   class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 font-medium text-sm hover:bg-zinc-50">
                    Limpiar
                </a>
                <button type="submit"
                        class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white font-semibold text-sm">
                    Aplicar filtros
                </button>
            </div>
        </form>
    </div>

    <!-- ============================================================ -->
    <!-- VISTA: TABLA -->
    <!-- ============================================================ -->
    <?php if ($vista === 'tabla'): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <?php if (empty($incidencias)): ?>
        <div class="px-6 py-20 text-center">
            <div class="w-14 h-14 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-4">
                <i data-lucide="search-x" class="w-7 h-7 text-zinc-400"></i>
            </div>
            <h3 class="font-display text-lg font-bold text-zinc-900 mb-1">Sin resultados</h3>
            <p class="text-sm text-zinc-500 max-w-sm mx-auto">
                <?= $hay_filtros ? 'No hay incidencias que coincidan con los filtros aplicados. Prueba con otros criterios.' : 'Aún no hay incidencias registradas en el sistema.' ?>
            </p>
            <?php if ($hay_filtros): ?>
            <a href="<?= url('bitacora.php') ?>" class="inline-flex items-center gap-1.5 mt-4 px-3 py-1.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-sm font-semibold rounded-lg">
                Limpiar filtros
            </a>
            <?php elseif (tiene_permiso('crear_solicitud')): ?>
            <a href="<?= url('incidencia_nueva.php') ?>" class="inline-flex items-center gap-1.5 mt-4 px-3 py-1.5 bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold rounded-lg">
                <i data-lucide="plus" class="w-4 h-4"></i> Crear primera incidencia
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 border-b border-zinc-200">
                    <tr>
                        <?php
                        $columnas = [
                            ['folio', 'Folio', 'w-32'],
                            ['titulo', 'Incidencia', ''],
                            [null, 'Sucursal', 'w-24'],
                            [null, 'Área', 'w-28'],
                            ['severidad', 'Severidad', 'w-28'],
                            ['estado', 'Estado', 'w-28'],
                            [null, 'Asignado a', 'w-32'],
                            ['fecha_evento', 'Fecha evento', 'w-32'],
                            [null, 'Tiempo', 'w-20 text-right'],
                            [null, 'Costo', 'w-24 text-right'],
                            [null, 'SLA', 'w-16 text-center'],
                        ];
                        foreach ($columnas as [$campo, $label, $clase_extra]):
                            $es_ordenable = $campo !== null;
                            $es_activa = $es_ordenable && $orden_campo === $campo;
                        ?>
                        <th class="px-3 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider <?= $clase_extra ?>">
                            <?php if ($es_ordenable):
                                $nueva_dir = $es_activa && $orden_dir === 'desc' ? 'asc' : 'desc';
                            ?>
                            <a href="<?= url_filtros(['orden' => $campo, 'dir' => $nueva_dir]) ?>"
                               class="inline-flex items-center gap-1 hover:text-zinc-900 <?= $es_activa ? 'text-bacal-700' : '' ?>">
                                <?= e($label) ?>
                                <?php if ($es_activa): ?>
                                <i data-lucide="<?= $orden_dir === 'desc' ? 'chevron-down' : 'chevron-up' ?>" class="w-3 h-3"></i>
                                <?php endif; ?>
                            </a>
                            <?php else: ?>
                            <?= e($label) ?>
                            <?php endif; ?>
                        </th>
                        <?php endforeach; ?>
                        <th class="px-3 py-2.5 w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach ($incidencias as $i):
                        $sla = sla_estado($i);
                    ?>
                    <tr class="hover:bg-zinc-50 transition-colors group">
                        <!-- Folio -->
                        <td class="px-3 py-2.5 align-top">
                            <a href="<?= url('incidencia_ver.php?id=' . $i['id']) ?>"
                               class="font-mono text-xs font-bold text-zinc-700 hover:text-bacal-700">
                                <?= e($i['folio']) ?>
                            </a>
                        </td>

                        <!-- Título + descripción truncada -->
                        <td class="px-3 py-2.5 align-top max-w-md">
                            <a href="<?= url('incidencia_ver.php?id=' . $i['id']) ?>" class="block group/title">
                                <div class="flex items-start gap-1.5">
                                    <div class="w-1 self-stretch rounded-full flex-shrink-0 mt-0.5" style="background-color: <?= e($i['severidad_color']) ?>; min-height: 28px;"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 mb-0.5 flex-wrap">
                                            <span class="font-semibold text-zinc-900 group-hover/title:text-bacal-700 truncate">
                                                <?= e($i['titulo']) ?>
                                            </span>
                                            <?php if ($i['es_reincidencia']): ?>
                                                <span class="inline-flex items-center gap-0.5 text-[9px] font-bold text-purple-700 bg-purple-50 border border-purple-200 px-1 py-0.5 rounded">
                                                    <i data-lucide="rotate-ccw" class="w-2.5 h-2.5"></i> R
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($i['equipo_nombre']): ?>
                                        <div class="text-[10px] text-zinc-400 truncate">
                                            <i data-lucide="monitor" class="w-2.5 h-2.5 inline mr-0.5"></i>
                                            <?= e($i['equipo_nombre']) ?>
                                            <span class="font-mono"><?= e($i['equipo_codigo']) ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </td>

                        <!-- Sucursal -->
                        <td class="px-3 py-2.5 align-top">
                            <span class="inline-flex items-center font-mono text-[10px] font-bold bg-zinc-100 text-zinc-600 px-1.5 py-0.5 rounded">
                                <?= e($i['sucursal_codigo']) ?>
                            </span>
                        </td>

                        <!-- Área -->
                        <td class="px-3 py-2.5 align-top">
                            <?= badge($i['area_nombre'], $i['area_color']) ?>
                        </td>

                        <!-- Severidad -->
                        <td class="px-3 py-2.5 align-top">
                            <?= badge($i['severidad_nombre'], $i['severidad_color']) ?>
                        </td>

                        <!-- Estado -->
                        <td class="px-3 py-2.5 align-top">
                            <?= badge($i['estado_nombre'], $i['estado_color']) ?>
                        </td>

                        <!-- Asignado -->
                        <td class="px-3 py-2.5 align-top">
                            <?php if ($i['asignado_a_nombre']): ?>
                            <div class="flex items-center gap-1.5">
                                <div class="w-5 h-5 rounded-full flex items-center justify-center text-white text-[9px] font-bold flex-shrink-0"
                                     style="background-color: <?= color_avatar($i['asignado_a_nombre']) ?>">
                                    <?= e(iniciales($i['asignado_a_nombre'])) ?>
                                </div>
                                <span class="text-xs text-zinc-700 truncate"><?= e(explode(' ', $i['asignado_a_nombre'])[0]) ?></span>
                            </div>
                            <?php else: ?>
                            <span class="text-[10px] text-zinc-400 italic flex items-center gap-1">
                                <i data-lucide="user-x" class="w-3 h-3"></i> Sin asignar
                            </span>
                            <?php endif; ?>
                        </td>

                        <!-- Fecha evento -->
                        <td class="px-3 py-2.5 align-top">
                            <div class="text-xs text-zinc-700"><?= e(fmt_fecha($i['fecha_evento'], false)) ?></div>
                            <div class="text-[10px] text-zinc-400"><?= e(fmt_tiempo_relativo($i['fecha_evento'])) ?></div>
                        </td>

                        <!-- Tiempo activo -->
                        <td class="px-3 py-2.5 align-top text-right">
                            <?php if ((float) ($i['horas_trabajadas'] ?? 0) > 0): ?>
                            <span class="text-sm text-zinc-700"><?= e(rtrim(rtrim(number_format((float) $i['horas_trabajadas'], 2), '0'), '.')) ?> h</span>
                            <?php else: ?>
                            <span class="text-zinc-300">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Costo -->
                        <?php
                        $costo_mostrar = puede_ver_mano_obra_interna()
                            ? (float) $i['costo_total']
                            : (float) $i['costo_total_visible'];
                        ?>
                        <td class="px-3 py-2.5 align-top text-right">
                            <?php if ($costo_mostrar > 0): ?>
                            <span class="font-semibold text-sm text-zinc-900"><?= e(fmt_dinero_corto($costo_mostrar)) ?></span>
                            <?php else: ?>
                            <span class="text-zinc-300">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- SLA -->
                        <td class="px-3 py-2.5 align-top text-center">
                            <?php if ($sla === 'cerrada'):
                                if ($i['sla_cumplido'] === '1' || $i['sla_cumplido'] === 1): ?>
                                <span class="inline-flex items-center text-emerald-600" title="SLA cumplido">
                                    <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                </span>
                                <?php elseif ($i['sla_cumplido'] === '0' || $i['sla_cumplido'] === 0): ?>
                                <span class="inline-flex items-center text-bacal-600" title="SLA incumplido">
                                    <i data-lucide="x-circle" class="w-4 h-4"></i>
                                </span>
                                <?php else: ?>
                                <span class="text-zinc-300">—</span>
                                <?php endif;
                            elseif ($sla === 'vencido'): ?>
                                <span class="inline-flex items-center text-bacal-700" title="SLA vencido">
                                    <i data-lucide="flame" class="w-4 h-4"></i>
                                </span>
                            <?php elseif ($sla === 'riesgo'): ?>
                                <span class="inline-flex items-center text-amber-600" title="SLA en riesgo">
                                    <i data-lucide="clock-alert" class="w-4 h-4"></i>
                                </span>
                            <?php elseif ($sla === 'ok'): ?>
                                <span class="inline-flex items-center text-emerald-500" title="SLA en tiempo">
                                    <i data-lucide="clock" class="w-4 h-4"></i>
                                </span>
                            <?php else: ?>
                                <span class="text-zinc-300">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Acciones -->
                        <td class="px-3 py-2.5 align-top text-right">
                            <a href="<?= url('incidencia_ver.php?id=' . $i['id']) ?>"
                               class="opacity-0 group-hover:opacity-100 inline-flex items-center p-1 rounded text-zinc-400 hover:text-bacal-700 hover:bg-bacal-50 transition-all">
                                <i data-lucide="arrow-up-right" class="w-4 h-4"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="border-t border-zinc-200 px-4 py-3 flex items-center justify-between flex-wrap gap-2">
            <div class="text-xs text-zinc-500">
                Mostrando <strong class="text-zinc-700"><?= ($offset + 1) ?>-<?= min($offset + $por_pagina, $total_resultados) ?></strong>
                de <strong class="text-zinc-700"><?= number_format($total_resultados) ?></strong> registros
            </div>
            <div class="flex items-center gap-1">
                <?php
                $pag_url = function($p) { return url_filtros(['p' => $p]); };
                ?>
                <a href="<?= $pagina > 1 ? $pag_url($pagina - 1) : '#' ?>"
                   class="px-2.5 py-1.5 rounded-md text-xs font-medium border <?= $pagina > 1 ? 'border-zinc-300 text-zinc-700 hover:bg-zinc-50' : 'border-zinc-200 text-zinc-300 pointer-events-none' ?>">
                    <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i>
                </a>

                <?php
                // Mostrar máximo 7 páginas: primera, última y 5 alrededor de la actual
                $rango = [];
                $rango[] = 1;
                for ($i = max(2, $pagina - 2); $i <= min($total_paginas - 1, $pagina + 2); $i++) $rango[] = $i;
                if ($total_paginas > 1) $rango[] = $total_paginas;
                $rango = array_unique($rango);
                sort($rango);
                $prev = 0;
                foreach ($rango as $p):
                    if ($prev > 0 && $p - $prev > 1): ?>
                        <span class="px-1 text-xs text-zinc-400">…</span>
                    <?php endif; ?>
                    <a href="<?= $pag_url($p) ?>"
                       class="px-2.5 py-1.5 rounded-md text-xs font-semibold border <?= $p == $pagina ? 'border-bacal-700 bg-bacal-700 text-white' : 'border-zinc-300 text-zinc-700 hover:bg-zinc-50' ?>">
                        <?= $p ?>
                    </a>
                <?php
                    $prev = $p;
                endforeach;
                ?>

                <a href="<?= $pagina < $total_paginas ? $pag_url($pagina + 1) : '#' ?>"
                   class="px-2.5 py-1.5 rounded-md text-xs font-medium border <?= $pagina < $total_paginas ? 'border-zinc-300 text-zinc-700 hover:bg-zinc-50' : 'border-zinc-200 text-zinc-300 pointer-events-none' ?>">
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ============================================================ -->
    <!-- VISTA: KANBAN -->
    <!-- ============================================================ -->
    <?php else: ?>
    <?php
        // Agrupar incidencias por estado
        $por_estado = [];
        foreach ($cat_estados as $est) $por_estado[$est['id']] = [];
        foreach ($incidencias as $i) {
            if (isset($por_estado[$i['estado_id']])) {
                $por_estado[$i['estado_id']][] = $i;
            }
        }
    ?>
    <div class="overflow-x-auto pb-3">
        <div class="flex gap-3 min-w-min">
            <?php foreach ($cat_estados as $est):
                $items = $por_estado[$est['id']] ?? [];
            ?>
            <div class="flex-shrink-0 w-72 bg-zinc-50 border border-zinc-200 rounded-xl flex flex-col" style="max-height: calc(100vh - 280px);">
                <!-- Header de columna -->
                <div class="px-3 py-2.5 border-b border-zinc-200 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full" style="background-color: <?= e($est['color']) ?>"></span>
                        <span class="font-semibold text-sm text-zinc-900"><?= e($est['nombre']) ?></span>
                        <span class="bg-white text-zinc-600 text-[10px] font-bold px-1.5 py-0.5 rounded border border-zinc-200">
                            <?= count($items) ?>
                        </span>
                    </div>
                </div>

                <!-- Tarjetas -->
                <div class="p-2 space-y-2 overflow-y-auto flex-1">
                    <?php if (empty($items)): ?>
                    <div class="text-center py-6 text-xs text-zinc-400 italic">Sin incidencias</div>
                    <?php else:
                        foreach ($items as $i):
                            $sla = sla_estado($i);
                    ?>
                    <a href="<?= url('incidencia_ver.php?id=' . $i['id']) ?>"
                       class="block bg-white border border-zinc-200 rounded-lg p-3 hover:shadow-md hover:border-bacal-200 transition-all group">
                        <!-- Severidad como barra superior -->
                        <div class="h-1 -mx-3 -mt-3 mb-2 rounded-t-lg" style="background-color: <?= e($i['severidad_color']) ?>"></div>

                        <!-- Folio + indicadores -->
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="font-mono text-[10px] font-bold text-zinc-500"><?= e($i['folio']) ?></span>
                            <div class="flex items-center gap-1">
                                <?php if ($i['es_reincidencia']): ?>
                                <span class="inline-flex items-center text-purple-600" title="Reincidencia">
                                    <i data-lucide="rotate-ccw" class="w-3 h-3"></i>
                                </span>
                                <?php endif; ?>
                                <?php if ($sla === 'vencido'): ?>
                                <span class="inline-flex items-center text-bacal-700" title="SLA vencido">
                                    <i data-lucide="flame" class="w-3 h-3"></i>
                                </span>
                                <?php elseif ($sla === 'riesgo'): ?>
                                <span class="inline-flex items-center text-amber-600" title="SLA en riesgo">
                                    <i data-lucide="clock-alert" class="w-3 h-3"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Título -->
                        <div class="font-semibold text-sm text-zinc-900 leading-snug mb-2 group-hover:text-bacal-700 line-clamp-2">
                            <?= e($i['titulo']) ?>
                        </div>

                        <!-- Badges -->
                        <div class="flex items-center gap-1 flex-wrap mb-2">
                            <?= badge($i['area_nombre'], $i['area_color']) ?>
                            <span class="font-mono text-[9px] bg-zinc-100 text-zinc-500 px-1 py-0.5 rounded"><?= e($i['sucursal_codigo']) ?></span>
                        </div>

                        <!-- Footer: asignado + fecha -->
                        <div class="flex items-center justify-between text-[10px] text-zinc-500 pt-2 border-t border-zinc-100">
                            <?php if ($i['asignado_a_nombre']): ?>
                            <div class="flex items-center gap-1">
                                <div class="w-4 h-4 rounded-full flex items-center justify-center text-white text-[8px] font-bold"
                                     style="background-color: <?= color_avatar($i['asignado_a_nombre']) ?>">
                                    <?= e(iniciales($i['asignado_a_nombre'])) ?>
                                </div>
                                <span><?= e(explode(' ', $i['asignado_a_nombre'])[0]) ?></span>
                            </div>
                            <?php else: ?>
                            <span class="italic">Sin asignar</span>
                            <?php endif; ?>
                            <span><?= e(fmt_tiempo_relativo($i['fecha_evento'])) ?></span>
                        </div>
                    </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if (count($incidencias) >= 200): ?>
    <div class="text-xs text-zinc-500 text-center mt-2 italic">
        Mostrando los primeros 200 registros. Aplica filtros para reducir el resultado.
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>
