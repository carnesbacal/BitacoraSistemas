<?php
/**
 * ============================================================================
 * mantenimiento_nuevo.php - Crear nuevo mantenimiento programado
 * ============================================================================
 * Soporta UNO o VARIOS equipos por mantenimiento. Al elegir varios se puede:
 *   - "agrupado": un solo mantenimiento que cubre todos los equipos.
 *   - "separados": un mantenimiento independiente por cada equipo.
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/mantenimientos_helpers.php';
require_once __DIR__ . '/config/notificaciones_helpers.php';

requerir_login();

if (!puede_administrar_mantenimientos()) {
    flash_set('error', 'No tienes permiso para programar mantenimientos.');
    header('Location: ' . url('mantenimientos.php'));
    exit;
}

$u = usuario_actual();
$equipo_id_pre = (int) input('equipo_id', 0);

$errores = [];

// ----------------------------------------------------------------------------
// Procesar POST
// ----------------------------------------------------------------------------
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token de seguridad inválido.';
    } else {
        // Equipos seleccionados (varios)
        $equipo_ids_raw = input('equipo_ids', []);
        if (!is_array($equipo_ids_raw)) $equipo_ids_raw = [$equipo_ids_raw];
        $equipo_ids = array_values(array_unique(array_filter(
            array_map('intval', $equipo_ids_raw),
            fn($v) => $v > 0
        )));

        $modo         = input('modo') === 'separados' ? 'separados' : 'agrupado';
        $titulo       = trim((string) input('titulo', ''));
        $descripcion  = trim((string) input('descripcion', ''));
        $fecha        = (string) input('fecha_programada', '');
        $hora         = (string) input('hora_programada', '');
        $asignado_id  = (int) input('asignado_a_id', 0);
        $proveedor_id = (int) input('proveedor_id', 0);
        $es_recurrente = input('es_recurrente') ? 1 : 0;
        $recurrencia_tipo = (string) input('recurrencia_tipo', '');
        $recurrencia_valor = (int) input('recurrencia_valor', 0);

        // Validar que los equipos existan y estén activos
        if ($equipo_ids) {
            $ph = implode(',', array_fill(0, count($equipo_ids), '?'));
            $validos = db_all(
                "SELECT id FROM equipos WHERE id IN ($ph) AND activo = 1 AND estado_vida != 'dado_de_baja'",
                $equipo_ids
            );
            $equipo_ids = array_map(fn($r) => (int) $r['id'], $validos);
        }

        // Validaciones
        if (empty($equipo_ids)) $errores[] = 'Selecciona al menos un equipo.';
        if ($titulo === '') $errores[] = 'El título es obligatorio.';
        if ($fecha === '') $errores[] = 'La fecha programada es obligatoria.';
        elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $errores[] = 'La fecha no tiene formato válido.';

        if ($es_recurrente) {
            if (!in_array($recurrencia_tipo, ['dias','semanas','meses','anios'], true)) {
                $errores[] = 'Tipo de recurrencia inválido.';
            }
            if ($recurrencia_valor < 1) {
                $errores[] = 'El valor de recurrencia debe ser al menos 1.';
            }
        }

        if (empty($errores)) {
            // Si la fecha es <= 3 días, marcar como próximo
            $dias_hasta = (strtotime($fecha) - strtotime(date('Y-m-d'))) / 86400;
            $estado_inicial = $dias_hasta <= 3 ? 'proximo' : 'programado';
            if ($dias_hasta < 0) $estado_inicial = 'vencido';

            // Closure que crea UN mantenimiento para un conjunto de equipos.
            // El primer equipo queda como equipo principal; todos van a la puente.
            $insertar = function(array $eids) use (
                $titulo, $descripcion, $fecha, $hora, $asignado_id, $proveedor_id,
                $estado_inicial, $es_recurrente, $recurrencia_tipo, $recurrencia_valor, $u
            ): int {
                db_exec(
                    "INSERT INTO mantenimientos
                     (equipo_id, titulo, descripcion, fecha_programada, hora_programada,
                      asignado_a_id, proveedor_id, estado,
                      es_recurrente, recurrencia_tipo, recurrencia_valor, creado_por_id)
                     VALUES (:eid, :tit, :desc, :fp, :hp, :aid, :pid, :est,
                             :rec, :rt, :rv, :cid)",
                    [
                        'eid'  => $eids[0],
                        'tit'  => mb_substr($titulo, 0, 200),
                        'desc' => $descripcion ?: null,
                        'fp'   => $fecha,
                        'hp'   => $hora ?: null,
                        'aid'  => $asignado_id ?: null,
                        'pid'  => $proveedor_id ?: null,
                        'est'  => $estado_inicial,
                        'rec'  => $es_recurrente,
                        'rt'   => $es_recurrente ? $recurrencia_tipo : null,
                        'rv'   => $es_recurrente ? $recurrencia_valor : null,
                        'cid'  => $u['id'],
                    ]
                );
                $id = (int) db_last_id();
                sincronizar_mantenimiento_equipos($id, $eids);
                return $id;
            };

            $creados = [];
            try {
                db()->beginTransaction();

                if ($modo === 'separados' && count($equipo_ids) > 1) {
                    // Un mantenimiento independiente por cada equipo
                    foreach ($equipo_ids as $eid) {
                        $creados[] = $insertar([$eid]);
                    }
                } else {
                    // Un solo mantenimiento (agrupado, o un único equipo)
                    $creados[] = $insertar($equipo_ids);
                }

                db()->commit();
            } catch (Throwable $ex) {
                if (db()->inTransaction()) db()->rollBack();
                $errores[] = 'Error al guardar: ' . $ex->getMessage();
            }

            if (empty($errores)) {
                $n_equipos = count($equipo_ids);
                $n_mant    = count($creados);

                registrar_auditoria(
                    'crear_mantenimiento', 'mantenimientos', $creados[0],
                    "Mantenimiento: $titulo" .
                    ($n_mant > 1 ? " ($n_mant mantenimientos, 1 por equipo)" : ($n_equipos > 1 ? " ($n_equipos equipos)" : ''))
                );

                // Notificar al técnico asignado (una sola notificación resumen)
                if ($asignado_id > 0 && $asignado_id !== (int) $u['id']) {
                    if ($n_equipos === 1) {
                        $eq = db_one("SELECT codigo_inventario FROM equipos WHERE id = :id", ['id' => $equipo_ids[0]]);
                        $detalle = "Equipo " . ($eq['codigo_inventario'] ?? '') . " · " . date('d/m/Y', strtotime($fecha));
                    } else {
                        $detalle = "$n_equipos equipos · " . date('d/m/Y', strtotime($fecha));
                    }
                    crear_notificacion(
                        $asignado_id,
                        'asignacion',
                        "Mantenimiento asignado: $titulo",
                        $detalle,
                        url('mantenimiento_ver.php?id=' . $creados[0]),
                        'mantenimientos',
                        $creados[0]
                    );
                }

                if ($n_mant === 1) {
                    $msg = $n_equipos > 1
                        ? "Mantenimiento programado para $n_equipos equipos · " . date('d/m/Y', strtotime($fecha))
                        : "Mantenimiento programado para " . date('d/m/Y', strtotime($fecha));
                    flash_set('success', $msg);
                    header('Location: ' . url('mantenimiento_ver.php?id=' . $creados[0]));
                    exit;
                } else {
                    flash_set('success', "Se programaron $n_mant mantenimientos (uno por equipo) para " . date('d/m/Y', strtotime($fecha)) . ".");
                    header('Location: ' . url('mantenimientos.php'));
                    exit;
                }
            }
        }
    }

    // Restaurar valor del equipo si hubo error
    $equipo_id_pre = (int) input('equipo_id', 0);
}

// ----------------------------------------------------------------------------
// Catálogos
// ----------------------------------------------------------------------------
$equipos_list = db_all(
    "SELECT e.id, e.codigo_inventario, e.nombre, e.tipo, e.sucursal_id, s.nombre sucursal_nombre,
            e.area_id, a.nombre area_nombre
     FROM equipos e
     INNER JOIN sucursales s ON e.sucursal_id = s.id
     LEFT JOIN areas a ON e.area_id = a.id
     WHERE e.activo = 1 AND e.estado_vida != 'dado_de_baja'
     ORDER BY s.nombre, e.codigo_inventario"
);

$tecnicos = db_all(
    "SELECT u.id, u.nombre_completo FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE u.activo = 1 AND r.puede_resolver = 1
     ORDER BY u.nombre_completo"
);

$proveedores = db_all("SELECT id, nombre FROM proveedores WHERE activo = 1 ORDER BY nombre");

// ----------------------------------------------------------------------------
// Datos para el selector (JSON para Alpine)
// ----------------------------------------------------------------------------
$equipos_js = array_map(fn($e) => [
    'id'          => (int) $e['id'],
    'codigo'      => (string) $e['codigo_inventario'],
    'nombre'      => (string) $e['nombre'],
    'sucursal_id' => (int) $e['sucursal_id'],
    'sucursal'    => (string) $e['sucursal_nombre'],
    'area_id'     => (int) ($e['area_id'] ?? 0),
    'area'        => (string) ($e['area_nombre'] ?? ''),
    'tipo'        => (string) ($e['tipo'] ?? ''),
], $equipos_list);

$areas_map = [];
$tipos_map = [];
foreach ($equipos_list as $e) {
    $aid = (int) ($e['area_id'] ?? 0);
    if ($aid > 0 && !isset($areas_map[$aid])) {
        $areas_map[$aid] = ['id' => $aid, 'nombre' => (string) ($e['area_nombre'] ?? '')];
    }
    $tp = trim((string) ($e['tipo'] ?? ''));
    if ($tp !== '') $tipos_map[$tp] = ($tipos_map[$tp] ?? 0) + 1;
}
$areas_js = array_values($areas_map);
usort($areas_js, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
$tipos_js = [];
foreach ($tipos_map as $nombre => $count) $tipos_js[] = ['nombre' => $nombre, 'count' => $count];
usort($tipos_js, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

// Preselección: tras un error usa lo enviado; si viene de un equipo, ese.
$preseleccionados = [];
if (es_post()) {
    $pp = input('equipo_ids', []);
    if (!is_array($pp)) $pp = [$pp];
    $preseleccionados = array_values(array_filter(array_map('intval', $pp), fn($v) => $v > 0));
} elseif ($equipo_id_pre > 0) {
    $preseleccionados = [$equipo_id_pre];
}
$modo_pre = input('modo') === 'separados' ? 'separados' : 'agrupado';

// json_encode + e() (htmlspecialchars) escapa las comillas como &quot; para el
// atributo x-data; el navegador las decodifica antes de que Alpine evalúe el JS.
// (No usar JSON_HEX_QUOT/APOS aquí: romperían la estructura como expresión JS.)
$_json_flags = JSON_HEX_TAG | JSON_UNESCAPED_UNICODE;

$titulo_pagina = 'Nuevo mantenimiento';
$pagina_activa = 'mantenimientos';
require_once __DIR__ . '/config/header.php';
?>

<div class="max-w-3xl mx-auto animate-fade-in">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= url('mantenimientos.php') ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">Nuevo mantenimiento</h2>
            <p class="text-xs text-zinc-500">Programa un mantenimiento preventivo o correctivo</p>
        </div>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="mb-5 px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <ul class="list-disc list-inside text-xs">
            <?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5"
          x-data="{ esRecurrente: <?= input('es_recurrente') ? 'true' : 'false' ?> }">
        <?= csrf_input() ?>

        <!-- Equipos -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4"
             x-data="equipoPicker(
                <?= e(json_encode($equipos_js, $_json_flags)) ?>,
                <?= e(json_encode($areas_js, $_json_flags)) ?>,
                <?= e(json_encode($tipos_js, $_json_flags)) ?>,
                <?= e(json_encode($preseleccionados, $_json_flags)) ?>,
                '<?= e($modo_pre) ?>'
             )">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="monitor" class="w-4 h-4 text-bacal-700"></i> Equipos *
            </h3>
            <p class="text-[11px] text-zinc-500 -mt-2">
                Puedes elegir uno o varios. Agrega rápido por sucursal/zona o por tipo, o búscalos y márcalos.
            </p>

            <!-- Agregar rápido -->
            <div class="flex flex-wrap gap-2">
                <select @change="agregarPorArea($event.target.value); $event.target.value=''"
                        class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-xs focus:outline-none focus:border-bacal-700">
                    <option value="">+ Agregar por área…</option>
                    <template x-for="a in areas" :key="'a'+a.id">
                        <option :value="a.id" x-text="a.nombre + ' (' + countArea(a.id) + ')'"></option>
                    </template>
                </select>
                <select @change="agregarPorTipo($event.target.value); $event.target.value=''"
                        class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-xs focus:outline-none focus:border-bacal-700">
                    <option value="">+ Agregar por tipo…</option>
                    <template x-for="t in tipos" :key="'t'+t.nombre">
                        <option :value="t.nombre" x-text="t.nombre + ' (' + t.count + ')'"></option>
                    </template>
                </select>
                <button type="button" @click="seleccionados = []" x-show="seleccionados.length > 0"
                        class="px-3 py-2 rounded-lg border border-zinc-300 text-zinc-600 text-xs hover:bg-zinc-50">
                    Quitar todos
                </button>
            </div>

            <!-- Buscador -->
            <input type="text" x-model="busqueda" placeholder="Buscar por código, nombre o tipo…"
                   class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">

            <!-- Seleccionados (chips) -->
            <div x-show="seleccionados.length > 0" x-cloak class="flex flex-wrap gap-1.5">
                <template x-for="id in seleccionados" :key="'chip'+id">
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold bg-bacal-50 text-bacal-800 border border-bacal-200 px-2 py-0.5 rounded">
                        <span x-text="labelDe(id)"></span>
                        <button type="button" @click="quitar(id)" class="text-bacal-500 hover:text-bacal-800 leading-none">&times;</button>
                    </span>
                </template>
            </div>

            <!-- Lista con casillas -->
            <div class="max-h-64 overflow-y-auto border border-zinc-200 rounded-lg divide-y divide-zinc-100">
                <template x-for="eq in filtrados" :key="eq.id">
                    <label class="flex items-center gap-2 px-3 py-2 hover:bg-zinc-50 cursor-pointer">
                        <input type="checkbox" :value="eq.id" :checked="seleccionados.includes(eq.id)"
                               @change="toggle(eq.id)"
                               class="rounded text-bacal-700 focus:ring-bacal-500">
                        <span class="font-mono text-xs font-bold text-zinc-700" x-text="eq.codigo"></span>
                        <span class="text-sm text-zinc-800 truncate" x-text="eq.nombre"></span>
                        <span class="text-[10px] text-zinc-400 ml-auto whitespace-nowrap"
                              x-text="[eq.sucursal, eq.area, eq.tipo].filter(Boolean).join(' · ')"></span>
                    </label>
                </template>
                <div x-show="filtrados.length === 0" class="px-3 py-4 text-center text-xs text-zinc-400">
                    Sin resultados
                </div>
            </div>

            <!-- Inputs ocultos enviados al servidor -->
            <template x-for="id in seleccionados" :key="'h'+id">
                <input type="hidden" name="equipo_ids[]" :value="id">
            </template>

            <p class="text-[11px] text-zinc-500">
                <span class="font-bold text-zinc-700" x-text="seleccionados.length"></span> equipo(s) seleccionado(s)
            </p>

            <!-- Modo: solo cuando hay más de uno -->
            <div x-show="seleccionados.length > 1" x-cloak
                 class="border-t border-zinc-100 pt-4 space-y-2">
                <p class="text-xs font-bold text-zinc-700 uppercase tracking-wide">¿Cómo registrar estos equipos?</p>
                <label class="flex items-start gap-2 cursor-pointer p-2 rounded-lg hover:bg-zinc-50">
                    <input type="radio" name="modo" value="agrupado" x-model="modo" class="mt-0.5 text-bacal-700 focus:ring-bacal-500">
                    <span class="text-sm text-zinc-700">
                        <span class="font-semibold">Un solo mantenimiento</span> que cubre los
                        <span x-text="seleccionados.length"></span> equipos
                        <span class="block text-[11px] text-zinc-500">Una fecha, un técnico; se completa de una vez. Ideal para limpiezas/revisiones por zona o tipo.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 cursor-pointer p-2 rounded-lg hover:bg-zinc-50">
                    <input type="radio" name="modo" value="separados" x-model="modo" class="mt-0.5 text-bacal-700 focus:ring-bacal-500">
                    <span class="text-sm text-zinc-700">
                        <span class="font-semibold">Uno por equipo</span> (registros independientes)
                        <span class="block text-[11px] text-zinc-500">Crea <span x-text="seleccionados.length"></span> mantenimientos iguales, cada uno con su propio costo, resultado y estado.</span>
                    </span>
                </label>
            </div>
        </div>

        <!-- Información básica -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-bacal-700"></i> Información del mantenimiento
            </h3>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Título *</label>
                <input type="text" name="titulo" required maxlength="200"
                       value="<?= e((string) input('titulo', '')) ?>"
                       placeholder="ej. Calibración trimestral, Cambio de tóner, Limpieza interna"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Descripción</label>
                <textarea name="descripcion" rows="3"
                          placeholder="Detalles del trabajo a realizar"
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"><?= e((string) input('descripcion', '')) ?></textarea>
            </div>
        </div>

        <!-- Programación -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="calendar-clock" class="w-4 h-4 text-bacal-700"></i> Programación
            </h3>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Fecha *</label>
                    <input type="date" name="fecha_programada" required
                           value="<?= e((string) input('fecha_programada', '')) ?>"
                           min="<?= date('Y-m-d') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Hora (opcional)</label>
                    <input type="time" name="hora_programada"
                           value="<?= e((string) input('hora_programada', '')) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>

            <!-- Recurrencia -->
            <div class="border-t border-zinc-100 pt-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="es_recurrente" value="1"
                           x-model="esRecurrente"
                           class="rounded text-bacal-700 focus:ring-bacal-500">
                    <span class="text-sm font-semibold text-zinc-700">Mantenimiento recurrente</span>
                </label>
                <p class="text-[10px] text-zinc-500 mt-1 ml-6">Cuando este mantenimiento se complete, el sistema generará automáticamente el siguiente.</p>

                <div x-show="esRecurrente" x-cloak x-transition class="mt-3 grid grid-cols-2 gap-4 pl-6">
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Cada</label>
                        <input type="number" name="recurrencia_valor" min="1" max="365"
                               value="<?= e((string) input('recurrencia_valor', '3')) ?>"
                               class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Unidad</label>
                        <select name="recurrencia_tipo" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                            <?php $rt = (string) input('recurrencia_tipo', 'meses'); ?>
                            <option value="dias" <?= $rt === 'dias' ? 'selected' : '' ?>>Días</option>
                            <option value="semanas" <?= $rt === 'semanas' ? 'selected' : '' ?>>Semanas</option>
                            <option value="meses" <?= $rt === 'meses' ? 'selected' : '' ?>>Meses</option>
                            <option value="anios" <?= $rt === 'anios' ? 'selected' : '' ?>>Años</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Asignación -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6 space-y-4">
            <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="users" class="w-4 h-4 text-bacal-700"></i> Quién lo hace
            </h3>
            <p class="text-[11px] text-zinc-500">Puede ser un técnico interno o un proveedor externo (o ambos).</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Técnico asignado</label>
                    <select name="asignado_a_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($tecnicos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= (string) input('asignado_a_id') === (string) $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['nombre_completo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Proveedor externo</label>
                    <select name="proveedor_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Ninguno —</option>
                        <?php foreach ($proveedores as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= (string) input('proveedor_id') === (string) $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="<?= url('mantenimientos.php') ?>" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</a>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
                <i data-lucide="calendar-plus" class="w-4 h-4"></i> Programar mantenimiento
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('equipoPicker', (equipos, areas, tipos, preseleccionados, modoInicial) => ({
        equipos: equipos,
        areas: areas,
        tipos: tipos,
        seleccionados: (preseleccionados || []).map(Number),
        busqueda: '',
        modo: modoInicial || 'agrupado',

        get filtrados() {
            const q = this.busqueda.trim().toLowerCase();
            if (!q) return this.equipos;
            return this.equipos.filter(e =>
                (e.codigo + ' ' + e.nombre + ' ' + (e.tipo || '')).toLowerCase().includes(q)
            );
        },
        toggle(id) {
            id = Number(id);
            const i = this.seleccionados.indexOf(id);
            if (i >= 0) this.seleccionados.splice(i, 1);
            else this.seleccionados.push(id);
        },
        quitar(id) {
            const i = this.seleccionados.indexOf(Number(id));
            if (i >= 0) this.seleccionados.splice(i, 1);
        },
        agregarPorArea(aid) {
            if (!aid) return;
            aid = Number(aid);
            this.equipos.filter(e => e.area_id === aid).forEach(e => {
                if (!this.seleccionados.includes(e.id)) this.seleccionados.push(e.id);
            });
        },
        agregarPorTipo(tp) {
            if (!tp) return;
            this.equipos.filter(e => e.tipo === tp).forEach(e => {
                if (!this.seleccionados.includes(e.id)) this.seleccionados.push(e.id);
            });
        },
        countArea(aid) {
            aid = Number(aid);
            return this.equipos.filter(e => e.area_id === aid).length;
        },
        labelDe(id) {
            const e = this.equipos.find(x => x.id === Number(id));
            return e ? e.codigo : id;
        },
    }));
});
</script>

<?php require_once __DIR__ . '/config/footer.php'; ?>
