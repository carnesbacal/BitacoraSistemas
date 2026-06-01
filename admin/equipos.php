<?php
/**
 * ============================================================================
 * admin/equipos.php - Gestión de equipos/activos
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin_helpers.php';
require_once __DIR__ . '/../config/equipos_helpers.php';

$accion = (string) input('accion', 'listar');
$id     = (int) input('id', 0);

$equipo_edit = null;
if (in_array($accion, ['editar', 'toggle'], true) && $id > 0) {
    $equipo_edit = db_one("SELECT * FROM equipos WHERE id = :id", ['id' => $id]);
    if (!$equipo_edit) {
        flash_set('error', 'Equipo no encontrado.');
        header('Location: ' . url('admin/equipos.php'));
        exit;
    }
}

$errores = [];

if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token de seguridad inválido.';
    } else {
        $op = (string) input('op', '');
        try {
            if ($op === 'crear' || $op === 'editar') {
                $codigo = strtoupper(trim((string) input('codigo_inventario', '')));
                $nombre = trim((string) input('nombre', ''));
                $tipo   = trim((string) input('tipo', ''));
                $marca  = trim((string) input('marca', ''));
                $modelo = trim((string) input('modelo', ''));
                $serie  = trim((string) input('numero_serie', ''));
                $sid    = (int) input('sucursal_id', 0);
                $aid    = input('area_id', '') !== '' ? (int) input('area_id') : null;
                $ubic   = trim((string) input('ubicacion', ''));
                $notas  = trim((string) input('notas', ''));
                $proveedor_id = input('proveedor_id', '') !== '' ? (int) input('proveedor_id') : null;
                $fecha_compra = trim((string) input('fecha_compra', '')) ?: null;
                $costo_compra = trim((string) input('costo_compra', '')) !== '' ? (float) input('costo_compra') : null;
                $estado_vida  = (string) input('estado_vida', 'en_uso');
                $vida_util_meses = trim((string) input('vida_util_meses', '')) !== '' ? (int) input('vida_util_meses') : null;
                $fecha_baja  = trim((string) input('fecha_baja', '')) ?: null;
                $motivo_baja = trim((string) input('motivo_baja', '')) ?: null;

                // Validar estado_vida
                if (!in_array($estado_vida, ['nuevo','en_uso','en_reparacion','dado_de_baja'], true)) {
                    $estado_vida = 'en_uso';
                }

                if ($codigo === '') $errores[] = 'El código de inventario es obligatorio.';
                if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
                if ($sid <= 0)      $errores[] = 'La sucursal es obligatoria.';

                $check_id = $op === 'editar' ? (int) $equipo_edit['id'] : 0;
                $dup = db_one("SELECT id FROM equipos WHERE codigo_inventario = :c AND id <> :id",
                    ['c' => $codigo, 'id' => $check_id]);
                if ($dup) $errores[] = 'Ya existe un equipo con ese código de inventario.';

                if (empty($errores)) {
                    if ($op === 'crear') {
                        db_exec(
                            "INSERT INTO equipos
                             (codigo_inventario, nombre, tipo, marca, modelo, numero_serie,
                              sucursal_id, area_id, proveedor_id, fecha_compra, costo_compra,
                              vida_util_meses, estado_vida, fecha_baja, motivo_baja,
                              ubicacion, notas, activo)
                             VALUES (:c, :n, :t, :m, :mo, :ns, :s, :a, :pid, :fc, :cc,
                                     :vum, :ev, :fb, :mb, :u, :no, 1)",
                            ['c' => $codigo, 'n' => $nombre, 't' => $tipo ?: null, 'm' => $marca ?: null,
                             'mo' => $modelo ?: null, 'ns' => $serie ?: null,
                             's' => $sid, 'a' => $aid,
                             'pid' => $proveedor_id, 'fc' => $fecha_compra, 'cc' => $costo_compra,
                             'vum' => $vida_util_meses, 'ev' => $estado_vida,
                             'fb' => $fecha_baja, 'mb' => $motivo_baja,
                             'u' => $ubic ?: null, 'no' => $notas ?: null]
                        );
                        $new_id = db_last_id();
                        registrar_auditoria('crear_equipo', 'equipos', $new_id, "Equipo $codigo");
                        flash_set('success', "Equipo \"$nombre\" creado.");
                    } else {
                        db_exec(
                            "UPDATE equipos SET
                                codigo_inventario=:c, nombre=:n, tipo=:t, marca=:m, modelo=:mo,
                                numero_serie=:ns, sucursal_id=:s, area_id=:a,
                                proveedor_id=:pid, fecha_compra=:fc, costo_compra=:cc,
                                vida_util_meses=:vum, estado_vida=:ev, fecha_baja=:fb, motivo_baja=:mb,
                                ubicacion=:u, notas=:no
                             WHERE id=:id",
                            ['c' => $codigo, 'n' => $nombre, 't' => $tipo ?: null, 'm' => $marca ?: null,
                             'mo' => $modelo ?: null, 'ns' => $serie ?: null,
                             's' => $sid, 'a' => $aid,
                             'pid' => $proveedor_id, 'fc' => $fecha_compra, 'cc' => $costo_compra,
                             'vum' => $vida_util_meses, 'ev' => $estado_vida,
                             'fb' => $fecha_baja, 'mb' => $motivo_baja,
                             'u' => $ubic ?: null, 'no' => $notas ?: null,
                             'id' => $equipo_edit['id']]
                        );
                        registrar_auditoria('editar_equipo', 'equipos', $equipo_edit['id'], "Equipo $codigo");
                        flash_set('success', 'Equipo actualizado.');
                    }
                    header('Location: ' . url('admin/equipos.php'));
                    exit;
                }
            } elseif ($op === 'toggle' && $equipo_edit) {
                admin_toggle_activo('equipos', $equipo_edit['id'], "Equipo {$equipo_edit['codigo_inventario']}");
                header('Location: ' . url('admin/equipos.php'));
                exit;
            }
        } catch (Throwable $e) {
            $errores[] = 'Error: ' . $e->getMessage();
        }
    }
}

$sucursales = db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo=1 ORDER BY nombre");
$areas      = db_all("SELECT id, nombre FROM areas WHERE activo=1 ORDER BY nombre");
$tipos_existentes = db_all("SELECT DISTINCT tipo FROM equipos WHERE tipo IS NOT NULL AND tipo <> '' ORDER BY tipo");
$proveedores_lista = db_all("SELECT id, nombre, servicio FROM proveedores WHERE activo=1 ORDER BY nombre");

$titulo_pagina = 'Equipos';
$pagina_activa = 'admin_equipos';
require_once __DIR__ . '/../config/header.php';

if ($accion === 'nuevo' || ($accion === 'editar' && $equipo_edit)):
    $es_edicion = ($accion === 'editar');
    $eq = $equipo_edit;
?>
<div class="max-w-3xl mx-auto animate-fade-in">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= url('admin/equipos.php') ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h2 class="font-display text-2xl font-extrabold text-zinc-900"><?= $es_edicion ? 'Editar equipo' : 'Nuevo equipo' ?></h2>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="mb-4 px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <ul class="list-disc list-inside text-xs"><?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="<?= $es_edicion ? 'editar' : 'crear' ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Código de inventario *</label>
                <input type="text" name="codigo_inventario" required maxlength="50"
                       value="<?= e($es_edicion ? $eq['codigo_inventario'] : (string) input('codigo_inventario', '')) ?>"
                       placeholder="ej. BAC-001"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm font-mono uppercase focus:outline-none focus:border-bacal-700"
                       style="text-transform: uppercase;">
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nombre descriptivo *</label>
                <input type="text" name="nombre" required maxlength="150"
                       value="<?= e($es_edicion ? $eq['nombre'] : (string) input('nombre', '')) ?>"
                       placeholder="ej. PC Caja 1 Bacal"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Tipo</label>
                <input type="text" name="tipo" list="tipos-equipo" maxlength="50"
                       value="<?= e($es_edicion ? (string) $eq['tipo'] : (string) input('tipo', '')) ?>"
                       placeholder="ej. PC, Impresora, Cámara IP"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                <datalist id="tipos-equipo">
                    <?php foreach ($tipos_existentes as $t): ?>
                    <option value="<?= e($t['tipo']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Marca</label>
                <input type="text" name="marca" maxlength="100"
                       value="<?= e($es_edicion ? (string) $eq['marca'] : (string) input('marca', '')) ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Modelo</label>
                <input type="text" name="modelo" maxlength="100"
                       value="<?= e($es_edicion ? (string) $eq['modelo'] : (string) input('modelo', '')) ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Número de serie</label>
                <input type="text" name="numero_serie" maxlength="100"
                       value="<?= e($es_edicion ? (string) $eq['numero_serie'] : (string) input('numero_serie', '')) ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm font-mono focus:outline-none focus:border-bacal-700">
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Sucursal *</label>
                <select name="sucursal_id" required class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">— Selecciona —</option>
                    <?php foreach ($sucursales as $s):
                        $sel = $es_edicion ? $eq['sucursal_id'] : (int) input('sucursal_id', 0);
                    ?>
                    <option value="<?= $s['id'] ?>" <?= $sel == $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Área (opcional)</label>
                <select name="area_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">— Sin área —</option>
                    <?php foreach ($areas as $a):
                        $sel = $es_edicion ? $eq['area_id'] : (string) input('area_id', '');
                    ?>
                    <option value="<?= $a['id'] ?>" <?= (string) $sel === (string) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Proveedor (opcional)</label>
                <select name="proveedor_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">— Sin proveedor —</option>
                    <?php foreach ($proveedores_lista as $pr):
                        $sel_pr = $es_edicion ? $eq['proveedor_id'] : (string) input('proveedor_id', '');
                    ?>
                    <option value="<?= $pr['id'] ?>" <?= (string) $sel_pr === (string) $pr['id'] ? 'selected' : '' ?>>
                        <?= e($pr['nombre']) ?><?= $pr['servicio'] ? ' — ' . e($pr['servicio']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-zinc-500 mt-1">¿Quién nos vendió o da servicio a este equipo?</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Fecha de compra</label>
                <input type="date" name="fecha_compra"
                       value="<?= e($es_edicion && $eq['fecha_compra'] ? $eq['fecha_compra'] : (string) input('fecha_compra', '')) ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Costo de compra (MXN)</label>
                <input type="number" name="costo_compra" step="0.01" min="0"
                       value="<?= e($es_edicion && $eq['costo_compra'] !== null ? $eq['costo_compra'] : (string) input('costo_compra', '')) ?>"
                       placeholder="0.00"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Vida útil estimada</label>
                <select name="vida_util_meses" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <?php
                    $vum_actual = $es_edicion ? (int) ($eq['vida_util_meses'] ?? 0) : 0;
                    $opciones_vida = [
                        '' => '— Sin especificar —',
                        '12' => '1 año (12 meses)',
                        '24' => '2 años (24 meses)',
                        '36' => '3 años (36 meses)',
                        '48' => '4 años (48 meses)',
                        '60' => '5 años (60 meses)',
                        '72' => '6 años (72 meses)',
                        '84' => '7 años (84 meses)',
                        '120' => '10 años (120 meses)',
                    ];
                    foreach ($opciones_vida as $val => $label):
                    ?>
                    <option value="<?= $val ?>" <?= (string) $vum_actual === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-zinc-500 mt-1">Para calcular depreciación.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Estado del equipo</label>
                <select name="estado_vida" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <?php
                    $ev_actual = $es_edicion ? ($eq['estado_vida'] ?? 'en_uso') : 'en_uso';
                    $opciones_estado = [
                        'nuevo' => '🆕 Nuevo',
                        'en_uso' => '✅ En uso',
                        'en_reparacion' => '🔧 En reparación',
                        'dado_de_baja' => '📦 Dado de baja',
                    ];
                    foreach ($opciones_estado as $val => $label):
                    ?>
                    <option value="<?= $val ?>" <?= $ev_actual === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div></div>

            <div x-data="{ esBaja: <?= ($es_edicion && ($eq['estado_vida'] ?? '') === 'dado_de_baja') ? 'true' : 'false' ?> }"
                 x-init="$watch('$el.parentElement.querySelector(\'select[name=estado_vida]\').value', v => esBaja = v === 'dado_de_baja')"
                 class="md:col-span-2"
                 @change="if ($event.target.name === 'estado_vida') esBaja = $event.target.value === 'dado_de_baja'">
                <div x-show="esBaja" x-transition class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-zinc-50 border border-zinc-200 rounded-lg">
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Fecha de baja</label>
                        <input type="date" name="fecha_baja"
                               value="<?= e($es_edicion && $eq['fecha_baja'] ? $eq['fecha_baja'] : '') ?>"
                               class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Motivo de baja</label>
                        <input type="text" name="motivo_baja" maxlength="255"
                               value="<?= e($es_edicion ? (string) ($eq['motivo_baja'] ?? '') : '') ?>"
                               placeholder="ej. Equipo obsoleto, daño irreparable, robo"
                               class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                    </div>
                </div>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Ubicación física</label>
                <input type="text" name="ubicacion" maxlength="255"
                       value="<?= e($es_edicion ? (string) $eq['ubicacion'] : (string) input('ubicacion', '')) ?>"
                       placeholder="ej. Planta baja, sala de cajas, posición 1"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Notas</label>
                <textarea name="notas" rows="2"
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"><?= e($es_edicion ? (string) $eq['notas'] : (string) input('notas', '')) ?></textarea>
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-zinc-100">
            <a href="<?= url('admin/equipos.php') ?>" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</a>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                <?= $es_edicion ? 'Guardar' : 'Crear equipo' ?>
            </button>
        </div>
    </form>
</div>

<?php else:
    // Filtros
    $f_sucursal = (int) input('sucursal', 0);
    $f_tipo     = trim((string) input('tipo', ''));
    $f_q        = trim((string) input('q', ''));
    $f_estado   = trim((string) input('estado_vida', ''));

    $where = [];
    $params = [];
    if ($f_sucursal > 0) { $where[] = "e.sucursal_id = :sid"; $params['sid'] = $f_sucursal; }
    if ($f_tipo !== '')  { $where[] = "e.tipo = :t"; $params['t'] = $f_tipo; }
    if ($f_estado !== '' && in_array($f_estado, ['nuevo','en_uso','en_reparacion','dado_de_baja'], true)) {
        $where[] = "e.estado_vida = :ev";
        $params['ev'] = $f_estado;
    }
    if ($f_q !== '')     {
        $where[] = "(e.codigo_inventario LIKE :q1 OR e.nombre LIKE :q2 OR e.marca LIKE :q3 OR e.modelo LIKE :q4)";
        $params['q1'] = "%$f_q%"; $params['q2'] = "%$f_q%"; $params['q3'] = "%$f_q%"; $params['q4'] = "%$f_q%";
    }
    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $equipos = db_all(
        "SELECT e.*, s.nombre sucursal_nombre, s.codigo sucursal_codigo, a.nombre area_nombre,
                pr.nombre proveedor_nombre,
                (SELECT COUNT(*) FROM incidencias WHERE equipo_id = e.id) AS incidencias_count
         FROM equipos e
         INNER JOIN sucursales s ON e.sucursal_id = s.id
         LEFT JOIN areas a ON e.area_id = a.id
         LEFT JOIN proveedores pr ON e.proveedor_id = pr.id
         $where_sql
         ORDER BY e.activo DESC, e.codigo_inventario ASC",
        $params
    );
?>

<?php render_admin_header('Equipos / activos', count($equipos) . ' equipo(s) en inventario', url('admin/equipos.php?accion=nuevo'), 'Nuevo equipo'); ?>

<?php
// KPIs por estado (con mismos filtros excepto estado_vida)
$where_kpi = $where;
$params_kpi = $params;
if (isset($params_kpi['ev'])) {
    $where_kpi = array_filter($where_kpi, fn($w) => !str_contains($w, ':ev'));
    unset($params_kpi['ev']);
}
$where_kpi_sql = !empty($where_kpi) ? 'WHERE ' . implode(' AND ', $where_kpi) : '';
$kpi_estados = db_all(
    "SELECT estado_vida, COUNT(*) c
     FROM equipos e
     $where_kpi_sql
     GROUP BY estado_vida",
    $params_kpi
);
$kpi_map = ['nuevo' => 0, 'en_uso' => 0, 'en_reparacion' => 0, 'dado_de_baja' => 0];
foreach ($kpi_estados as $k) $kpi_map[$k['estado_vida']] = (int) $k['c'];
$kpi_total = array_sum($kpi_map);
?>

<!-- KPIs por estado -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <a href="<?= url('admin/equipos.php') . ($f_sucursal || $f_tipo || $f_q ? '?' . http_build_query(array_filter(['sucursal' => $f_sucursal ?: null, 'tipo' => $f_tipo ?: null, 'q' => $f_q ?: null])) : '') ?>"
       class="bg-white rounded-xl border <?= $f_estado === '' ? 'border-bacal-300 bg-bacal-50' : 'border-zinc-200' ?> p-3 hover:shadow-sm transition-shadow">
        <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold">Total</div>
        <div class="font-display text-2xl font-extrabold text-zinc-900"><?= $kpi_total ?></div>
    </a>
    <?php
    $kpis_def = [
        'nuevo' => ['label' => 'Nuevos', 'color' => 'emerald', 'colorHex' => '#16A34A'],
        'en_uso' => ['label' => 'En uso', 'color' => 'blue', 'colorHex' => '#0EA5E9'],
        'en_reparacion' => ['label' => 'En reparación', 'color' => 'amber', 'colorHex' => '#D97706'],
        'dado_de_baja' => ['label' => 'Dado de baja', 'color' => 'zinc', 'colorHex' => '#6B7280'],
    ];
    foreach ($kpis_def as $est => $def):
        $params_link = array_filter([
            'estado_vida' => $est,
            'sucursal' => $f_sucursal ?: null,
            'tipo' => $f_tipo ?: null,
            'q' => $f_q ?: null,
        ]);
        $url_filtro = url('admin/equipos.php') . '?' . http_build_query($params_link);
        $activo = ($f_estado === $est);
    ?>
    <a href="<?= e($url_filtro) ?>"
       class="bg-white rounded-xl border <?= $activo ? 'border-bacal-300 bg-bacal-50' : 'border-zinc-200' ?> p-3 hover:shadow-sm transition-shadow">
        <div class="text-[10px] uppercase tracking-wider font-bold" style="color: <?= e($def['colorHex']) ?>"><?= e($def['label']) ?></div>
        <div class="font-display text-2xl font-extrabold" style="color: <?= e($def['colorHex']) ?>"><?= $kpi_map[$est] ?></div>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filtros -->
<form method="GET" class="flex flex-wrap gap-2 mb-4">
    <div class="relative flex-1 min-w-[200px] max-w-md">
        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400"></i>
        <input type="text" name="q" value="<?= e($f_q) ?>" placeholder="Código, nombre, marca, modelo..."
               class="w-full pl-9 pr-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
    </div>
    <select name="sucursal" onchange="this.form.submit()"
            class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
        <option value="">Todas las sucursales</option>
        <?php foreach ($sucursales as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $f_sucursal == $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="tipo" onchange="this.form.submit()"
            class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
        <option value="">Todos los tipos</option>
        <?php foreach ($tipos_existentes as $t): ?>
        <option value="<?= e($t['tipo']) ?>" <?= $f_tipo === $t['tipo'] ? 'selected' : '' ?>><?= e($t['tipo']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="estado_vida" onchange="this.form.submit()"
            class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
        <option value="">Todos los estados</option>
        <option value="nuevo" <?= $f_estado === 'nuevo' ? 'selected' : '' ?>>🟢 Nuevo</option>
        <option value="en_uso" <?= $f_estado === 'en_uso' ? 'selected' : '' ?>>🔵 En uso</option>
        <option value="en_reparacion" <?= $f_estado === 'en_reparacion' ? 'selected' : '' ?>>🟠 En reparación</option>
        <option value="dado_de_baja" <?= $f_estado === 'dado_de_baja' ? 'selected' : '' ?>>⚫ Dado de baja</option>
    </select>
    <?php if ($f_q !== '' || $f_sucursal > 0 || $f_tipo !== '' || $f_estado !== ''): ?>
    <a href="<?= url('admin/equipos.php') ?>" class="px-3 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm hover:bg-zinc-50">Limpiar</a>
    <?php endif; ?>
</form>

<div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 border-b border-zinc-200">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Código</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Equipo</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Estado</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Sucursal</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Área</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Proveedor</th>
                    <th class="px-4 py-2.5 text-center text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Fallas</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                <?php foreach ($equipos as $eq): ?>
                <tr class="hover:bg-zinc-50 group <?= !$eq['activo'] ? 'opacity-50' : '' ?>">
                    <td class="px-4 py-2.5 font-mono text-xs font-bold">
                        <a href="<?= url('equipo_ver.php?id=' . $eq['id']) ?>" class="text-zinc-700 hover:text-bacal-700 hover:underline"><?= e($eq['codigo_inventario']) ?></a>
                    </td>
                    <td class="px-4 py-2.5">
                        <div class="font-semibold text-sm text-zinc-900"><?= e($eq['nombre']) ?></div>
                        <?php if ($eq['marca'] || $eq['modelo']): ?>
                        <div class="text-[10px] text-zinc-500"><?= e(trim(($eq['marca'] ?? '') . ' ' . ($eq['modelo'] ?? ''))) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2.5 text-xs text-zinc-700"><?= e((string) $eq['tipo']) ?: '—' ?></td>
                    <td class="px-4 py-2.5"><?= badge_estado_vida($eq['estado_vida'] ?? 'en_uso') ?></td>
                    <td class="px-4 py-2.5">
                        <span class="font-mono text-[10px] bg-zinc-100 text-zinc-600 px-1.5 py-0.5 rounded font-bold"><?= e($eq['sucursal_codigo']) ?></span>
                    </td>
                    <td class="px-4 py-2.5 text-xs text-zinc-700"><?= e($eq['area_nombre'] ?? '—') ?></td>
                    <td class="px-4 py-2.5 text-xs">
                        <?php if ($eq['proveedor_nombre']): ?>
                        <a href="<?= url('proveedor_ver.php?id=' . $eq['proveedor_id']) ?>"
                           class="text-zinc-700 hover:text-bacal-700 hover:underline truncate">
                            <?= e($eq['proveedor_nombre']) ?>
                        </a>
                        <?php else: ?>
                        <span class="text-zinc-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2.5 text-center">
                        <?php if ((int) $eq['incidencias_count'] > 0): ?>
                        <a href="<?= url('bitacora.php?equipo=' . $eq['id']) ?>"
                           class="inline-flex items-center gap-1 text-xs font-bold text-bacal-700 hover:underline">
                            <?= $eq['incidencias_count'] ?>
                            <i data-lucide="arrow-up-right" class="w-3 h-3"></i>
                        </a>
                        <?php else: ?>
                        <span class="text-zinc-400 text-xs">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-2.5 text-right">
                        <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="<?= url('equipo_ver.php?id=' . $eq['id']) ?>"
                               class="p-1.5 rounded text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700" title="Ver detalle">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </a>
                            <a href="<?= url('admin/equipos.php?accion=editar&id=' . $eq['id']) ?>"
                               class="p-1.5 rounded text-zinc-500 hover:bg-zinc-100 hover:text-zinc-700" title="Editar">
                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                            </a>
                            <form method="POST" action="<?= url('admin/equipos.php?accion=toggle&id=' . $eq['id']) ?>"
                                  onsubmit="return confirm('¿<?= $eq['activo'] ? 'Desactivar' : 'Activar' ?> este equipo?');">
                                <?= csrf_input() ?>
                                <input type="hidden" name="op" value="toggle">
                                <button type="submit" class="p-1.5 rounded text-zinc-500 hover:bg-bacal-50 hover:text-bacal-700">
                                    <i data-lucide="<?= $eq['activo'] ? 'power' : 'power-off' ?>" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($equipos)): ?>
                <tr><td colspan="8" class="px-4 py-12 text-center text-sm text-zinc-500 italic">Sin equipos que coincidan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
