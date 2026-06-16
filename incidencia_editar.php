<?php
/**
 * ============================================================================
 * incidencia_editar.php - Editar incidencia existente
 * ============================================================================
 * Formulario completo para editar todos los campos de una incidencia.
 * Registra el historial de cambios automáticamente comparando antes/después.
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/incidencias_helpers.php';
require_once __DIR__ . '/config/incidencia_costos_helpers.php';

requerir_login();

$u  = usuario_actual();
$id = (int) input('id', 0);
$incidencia = $id > 0 ? cargar_incidencia($id) : null;

if (!$incidencia) {
    flash_set('error', 'Incidencia no encontrada.');
    header('Location: ' . url('bitacora.php'));
    exit;
}

if (!puede_editar_incidencia($incidencia)) {
    http_response_code(403);
    die('No tienes permiso para editar esta incidencia.');
}

$titulo_pagina = 'Editar · ' . $incidencia['folio'];
$pagina_activa = 'bitacora';

// Catálogos
$sucursales  = cat_sucursales();
$areas       = cat_areas();
$categorias  = cat_categorias_con_subs();
$tipos       = cat_tipos_trabajo();
$severidades = cat_severidades();
$estados     = cat_estados();
$origenes    = cat_origenes();
$tecnicos    = cat_tecnicos();

$errores = [];
$valores = [
    'titulo' => $incidencia['titulo'],
    'descripcion' => $incidencia['descripcion'],
    'sucursal_id' => $incidencia['sucursal_id'],
    'area_id' => $incidencia['area_id'],
    'categoria_id' => $incidencia['categoria_id'],
    'subcategoria_id' => $incidencia['subcategoria_id'],
    'tipo_trabajo_id' => $incidencia['tipo_trabajo_id'],
    'severidad_id' => $incidencia['severidad_id'],
    'estado_id' => $incidencia['estado_id'],
    'origen_reporte_id' => $incidencia['origen_reporte_id'],
    'equipo_id' => $incidencia['equipo_id'],
    'reportante_nombre' => $incidencia['reportante_nombre'],
    'reportante_puesto' => $incidencia['reportante_puesto'],
    'asignado_a_id' => $incidencia['asignado_a_id'],
    'es_reincidencia' => (int) $incidencia['es_reincidencia'],
    'incidencia_padre_id' => $incidencia['incidencia_padre_id'],
    'fecha_evento' => $incidencia['fecha_evento'] ? date('Y-m-d\TH:i', strtotime($incidencia['fecha_evento'])) : '',
    'causa_raiz' => $incidencia['causa_raiz'],
    'solucion' => $incidencia['solucion'],
    'recomendaciones' => $incidencia['recomendaciones'],
    'proveedor_modo' => 'interno',
    'proveedor_escalado_id' => $incidencia['proveedor_escalado_id'] ?? '',
    'proveedor_externo_info' => $incidencia['proveedor_externo_info'] ?? '',
    'prov_nuevo_nombre' => '', 'prov_nuevo_servicio' => '', 'prov_nuevo_telefono' => '',
    'costo_mano_obra' => $incidencia['costo_mano_obra'] ?? '',
    'costo_materiales_proveedor' => $incidencia['costo_materiales_proveedor'] ?? '',
    'costo_notas' => $incidencia['costo_notas'] ?? '',
    'horas_trabajadas' => $incidencia['horas_trabajadas'] ?? '',
    'costo_materiales_comprados' => $incidencia['costo_materiales_comprados'] ?? '',
];

// Detectar modo inicial según datos guardados
if (!empty($incidencia['proveedor_escalado_id'])) {
    $valores['proveedor_modo'] = 'catalogo';
} elseif (!empty($incidencia['proveedor_externo_info'])
          || (float) ($incidencia['costo_mano_obra'] ?? 0) > 0
          || (float) ($incidencia['costo_materiales_proveedor'] ?? 0) > 0) {
    $valores['proveedor_modo'] = 'otro';
}
$proveedores = listar_proveedores_activos();

// ----------------------------------------------------------------------------
// Procesar POST
// ----------------------------------------------------------------------------
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token de seguridad inválido. Recarga la página.';
    } else {
        foreach ($valores as $k => $_v) {
            $valores[$k] = input($k, $valores[$k]);
        }
        $valores['es_reincidencia'] = (int) (input('es_reincidencia', 0) ? 1 : 0);

        // Horas de trabajo del técnico: se capturan como horas + minutos
        $_h = max(0, (int) input('horas_h', 0));
        $_m = max(0, min(59, (int) input('horas_m', 0)));
        $valores['horas_trabajadas'] = ($_h > 0 || $_m > 0) ? round($_h + $_m / 60, 4) : '';

        // Validaciones
        if (trim((string) $valores['titulo']) === '')        $errores[] = 'El título es obligatorio.';
        if (trim((string) $valores['descripcion']) === '')   $errores[] = 'La descripción es obligatoria.';
        if (!$valores['sucursal_id'])                        $errores[] = 'La sucursal es obligatoria.';
        if (!$valores['area_id'])                            $errores[] = 'El área es obligatoria.';
        if (!$valores['severidad_id'])                       $errores[] = 'La severidad es obligatoria.';
        if (!$valores['estado_id'])                          $errores[] = 'El estado es obligatorio.';

        // No permitir mover de sucursal si el usuario no tiene permiso
        if (!tiene_permiso('ver_todas_sucursales')) {
            $valores['sucursal_id'] = $incidencia['sucursal_id'];
        }

        // Verificar equipo de la sucursal correcta
        if ($valores['equipo_id']) {
            $eq = db_one("SELECT sucursal_id FROM equipos WHERE id=:id", ['id' => $valores['equipo_id']]);
            if (!$eq || (int) $eq['sucursal_id'] !== (int) $valores['sucursal_id']) {
                $errores[] = 'El equipo seleccionado no pertenece a la sucursal elegida.';
            }
        }

        if (empty($errores)) {
            try {
                db()->beginTransaction();

                // Snapshot antes (los IDs como strings para comparar)
                $antes = [];
                foreach ($valores as $k => $_v) {
                    $antes[$k] = (string) ($incidencia[$k] ?? '');
                }

                // Recalcular SLA si cambió severidad o fecha_evento
                $nueva_sla = $incidencia['fecha_limite_sla'];
                if (
                    (int) $incidencia['severidad_id'] !== (int) $valores['severidad_id'] ||
                    $incidencia['fecha_evento'] !== date('Y-m-d H:i:s', strtotime($valores['fecha_evento']))
                ) {
                    $sev = db_one("SELECT sla_horas FROM severidades WHERE id=:id", ['id' => $valores['severidad_id']]);
                    if ($sev && $sev['sla_horas']) {
                        $ts = strtotime($valores['fecha_evento']) + ((int) $sev['sla_horas']) * 3600;
                        $nueva_sla = date('Y-m-d H:i:s', $ts);
                    } else {
                        $nueva_sla = null;
                    }
                }

                // Auto-completar: si hay solución y el estado actual NO es final, marcar Completada
                $tiene_solucion = trim((string) $valores['solucion']) !== '';
                if ($tiene_solucion) {
                    $est_sel = db_one("SELECT es_final FROM estados WHERE id=:id", ['id' => $valores['estado_id']]);
                    if (!$est_sel || (int) $est_sel['es_final'] !== 1) {
                        $est_comp = db_one("SELECT id FROM estados WHERE nombre LIKE 'Complet%' AND es_final=1 AND activo=1 ORDER BY orden LIMIT 1");
                        if ($est_comp) {
                            $valores['estado_id'] = (int) $est_comp['id'];
                        }
                    }
                }

                // Si se cambió el estado y el nuevo es final, registrar resolución
                $nuevo_estado = db_one("SELECT es_final FROM estados WHERE id=:id", ['id' => $valores['estado_id']]);
                $fecha_resolucion = $incidencia['fecha_resolucion'];
                $fecha_cierre     = $incidencia['fecha_cierre'];
                $resuelto_por_id  = $incidencia['resuelto_por_id'];

                if ($nuevo_estado && (int) $nuevo_estado['es_final'] === 1) {
                    if (!$fecha_resolucion) $fecha_resolucion = date('Y-m-d H:i:s');
                    if (!$fecha_cierre) $fecha_cierre = date('Y-m-d H:i:s');
                    if (!$resuelto_por_id) $resuelto_por_id = $u['id'];
                }

                // Si se asigna técnico por primera vez, registrar fecha_atencion
                $fecha_atencion = $incidencia['fecha_atencion'];
                if ($valores['asignado_a_id'] && !$fecha_atencion) {
                    $fecha_atencion = date('Y-m-d H:i:s');
                }

                db_exec(
                    "UPDATE incidencias SET
                        titulo = :tit, descripcion = :desc,
                        sucursal_id = :sid, area_id = :aid,
                        categoria_id = :cid, subcategoria_id = :scid,
                        tipo_trabajo_id = :ttid, severidad_id = :sevid, estado_id = :estid,
                        origen_reporte_id = :origen, equipo_id = :eqid,
                        reportante_nombre = :repn, reportante_puesto = :repp,
                        asignado_a_id = :asig,
                        es_reincidencia = :reinc, incidencia_padre_id = :padre,
                        fecha_evento = :fe, fecha_atencion = :fa,
                        fecha_resolucion = :fr, fecha_cierre = :fc,
                        fecha_limite_sla = :sla, resuelto_por_id = :res,
                        causa_raiz = :cr, solucion = :sol, recomendaciones = :rec,
                        actualizado_por_id = :auid
                     WHERE id = :id",
                    [
                        'tit' => trim((string) $valores['titulo']),
                        'desc' => trim((string) $valores['descripcion']),
                        'sid' => $valores['sucursal_id'],
                        'aid' => $valores['area_id'],
                        'cid' => $valores['categoria_id'] ?: null,
                        'scid' => $valores['subcategoria_id'] ?: null,
                        'ttid' => $valores['tipo_trabajo_id'] ?: null,
                        'sevid' => $valores['severidad_id'],
                        'estid' => $valores['estado_id'],
                        'origen' => $valores['origen_reporte_id'] ?: null,
                        'eqid' => $valores['equipo_id'] ?: null,
                        'repn' => trim((string) $valores['reportante_nombre']) ?: null,
                        'repp' => trim((string) $valores['reportante_puesto']) ?: null,
                        'asig' => $valores['asignado_a_id'] ?: null,
                        'reinc' => $valores['es_reincidencia'],
                        'padre' => $valores['incidencia_padre_id'] ?: null,
                        'fe' => date('Y-m-d H:i:s', strtotime($valores['fecha_evento'])),
                        'fa' => $fecha_atencion,
                        'fr' => $fecha_resolucion,
                        'fc' => $fecha_cierre,
                        'sla' => $nueva_sla,
                        'res' => $resuelto_por_id,
                        'cr' => trim((string) $valores['causa_raiz']) ?: null,
                        'sol' => trim((string) $valores['solucion']) ?: null,
                        'rec' => trim((string) $valores['recomendaciones']) ?: null,
                        'auid' => $u['id'],
                        'id' => $id,
                    ]
                );

                // === Proveedor y costos ===
                $modo = (string) $valores['proveedor_modo'];
                $prov_id = null;
                $prov_info = null;
                if ($modo === 'catalogo' && $valores['proveedor_escalado_id']) {
                    $prov_id = (int) $valores['proveedor_escalado_id'];
                } elseif ($modo === 'otro') {
                    if (trim((string) $valores['prov_nuevo_nombre']) !== '') {
                        $prov_id = crear_proveedor_rapido([
                            'nombre' => $valores['prov_nuevo_nombre'],
                            'servicio' => $valores['prov_nuevo_servicio'],
                            'telefono' => $valores['prov_nuevo_telefono'],
                        ], (int) $u['id']);
                    } else {
                        $prov_info = trim((string) $valores['proveedor_externo_info']) ?: null;
                    }
                }
                guardar_costos_incidencia($id, [
                    'proveedor_escalado_id' => $prov_id,
                    'proveedor_externo_info' => $prov_info,
                    'costo_mano_obra' => $modo === 'interno' ? null : $valores['costo_mano_obra'],
                    'costo_materiales_proveedor' => $modo === 'interno' ? null : $valores['costo_materiales_proveedor'],
                    'costo_notas' => $valores['costo_notas'],
                    'horas_trabajadas' => $modo === 'interno' ? $valores['horas_trabajadas'] : null,
                    'costo_materiales_comprados' => $modo === 'interno' ? $valores['costo_materiales_comprados'] : null,
                ]);

                recalcular_tiempos_incidencia($id);
                $despues = [];
                foreach ($valores as $k => $v) {
                    $despues[$k] = (string) ($v ?? '');
                }
                registrar_diferencias($id, $u['id'], $antes, $despues);

                // Adjuntos nuevos (si los hay)
                if (!empty($_FILES['adjuntos']['name'][0])) {
                    [$exitos, $errs] = procesar_adjuntos($id, $_FILES['adjuntos'], $u['id']);
                    if (count($exitos) > 0) {
                        registrar_historial($id, $u['id'], 'adjuntos_subidos', 'adjuntos', null,
                            count($exitos) . ' archivo(s)', count($exitos) . ' archivo(s) adjuntados');
                    }
                }

                db()->commit();
                registrar_auditoria('editar_incidencia', 'incidencias', $id, "Folio {$incidencia['folio']}");
                flash_set('success', 'Incidencia actualizada correctamente.');
                header('Location: ' . url('incidencia_ver.php?id=' . $id));
                exit;
            } catch (Throwable $e) {
                if (db()->inTransaction()) db()->rollBack();
                $errores[] = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/config/header.php';

// Descomponer horas decimales en horas + minutos para mostrar en el formulario
$_ht   = (float) ($valores['horas_trabajadas'] ?: 0);
$_ht_h = (int) floor($_ht);
$_ht_m = (int) round(($_ht - $_ht_h) * 60);
?>

<div class="max-w-5xl mx-auto animate-fade-in" x-data="formEditar()">
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= url('incidencia_ver.php?id=' . $id) ?>"
           class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500 hover:text-zinc-700">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">Editar incidencia</h2>
            <p class="text-xs text-zinc-500 mt-0.5">
                <span class="font-mono font-semibold"><?= e($incidencia['folio']) ?></span> ·
                Los cambios quedan registrados en el historial.
            </p>
        </div>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="mb-5 px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <div class="font-semibold mb-1 flex items-center gap-2">
            <i data-lucide="alert-circle" class="w-4 h-4"></i> Revisa lo siguiente:
        </div>
        <ul class="list-disc list-inside space-y-0.5 text-xs">
            <?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-5">
        <?= csrf_input() ?>

        <!-- Sección: Información básica -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4 text-bacal-700"></i> Información básica
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Título *</label>
                    <input type="text" name="titulo" required maxlength="255"
                           value="<?= e((string) $valores['titulo']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Descripción *</label>
                    <textarea name="descripcion" required rows="4"
                              class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700"><?= e((string) $valores['descripcion']) ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Fecha y hora del evento *</label>
                    <input type="datetime-local" name="fecha_evento" required
                           value="<?= e((string) $valores['fecha_evento']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Origen del reporte</label>
                    <select name="origen_reporte_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin especificar —</option>
                        <?php foreach ($origenes as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $valores['origen_reporte_id'] == $o['id'] ? 'selected' : '' ?>><?= e($o['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Sección: Clasificación -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="tags" class="w-4 h-4 text-bacal-700"></i> Clasificación
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Sucursal *</label>
                    <?php if (tiene_permiso('ver_todas_sucursales')): ?>
                    <select name="sucursal_id" required x-model="sucursalId" @change="cargarEquipos()"
                            class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $valores['sucursal_id'] == $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="hidden" name="sucursal_id" value="<?= $valores['sucursal_id'] ?>">
                    <div class="px-3 py-2 rounded-lg border border-zinc-200 bg-zinc-50 text-sm text-zinc-700"><?= e($incidencia['sucursal_nombre']) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Área *</label>
                    <select name="area_id" required class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <?php foreach ($areas as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $valores['area_id'] == $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Categoría</label>
                    <select name="categoria_id" x-model="categoriaId" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin especificar —</option>
                        <?php foreach ($categorias as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $valores['categoria_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Subcategoría</label>
                    <select name="subcategoria_id" x-model="subcategoriaId" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin especificar —</option>
                        <template x-for="sub in subcategoriasFiltradas" :key="sub.id">
                            <option :value="sub.id" x-text="sub.nombre" :selected="String(sub.id) === String(subcategoriaId)"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Tipo de trabajo</label>
                    <select name="tipo_trabajo_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin especificar —</option>
                        <?php foreach ($tipos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $valores['tipo_trabajo_id'] == $t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Severidad *</label>
                    <select name="severidad_id" required class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <?php foreach ($severidades as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $valores['severidad_id'] == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['nombre']) ?><?= $s['sla_horas'] ? " (SLA {$s['sla_horas']}h)" : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Estado *</label>
                    <select name="estado_id" required class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <?php foreach ($estados as $est): ?>
                        <option value="<?= $est['id'] ?>" <?= $valores['estado_id'] == $est['id'] ? 'selected' : '' ?>><?= e($est['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Sección: Equipo y personas -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="monitor" class="w-4 h-4 text-bacal-700"></i> Equipo y personas
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Equipo / activo</label>
                    <select name="equipo_id" x-model="equipoId"
                            class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin equipo específico —</option>
                        <template x-for="eq in equipos" :key="eq.id">
                            <option :value="eq.id" :selected="String(eq.id) === String(equipoId)">
                                <span x-text="eq.codigo_inventario + ' - ' + eq.nombre"></span>
                            </option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nombre del reportante</label>
                    <input type="text" name="reportante_nombre" maxlength="150"
                           value="<?= e((string) $valores['reportante_nombre']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Puesto del reportante</label>
                    <input type="text" name="reportante_puesto" maxlength="100"
                           value="<?= e((string) $valores['reportante_puesto']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Asignar a técnico</label>
                    <select name="asignado_a_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($tecnicos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $valores['asignado_a_id'] == $t['id'] ? 'selected' : '' ?>><?= e($t['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Sección: Reincidencia -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-3 flex items-center gap-2">
                <i data-lucide="rotate-ccw" class="w-4 h-4 text-purple-600"></i> Reincidencia
            </h3>
            <label class="flex items-center gap-2 text-sm cursor-pointer mb-3">
                <input type="checkbox" name="es_reincidencia" value="1" <?= $valores['es_reincidencia'] ? 'checked' : '' ?>
                       class="rounded border-zinc-300 text-purple-600 focus:ring-purple-500">
                <span class="text-zinc-700">Esta incidencia es una reincidencia</span>
            </label>
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Folio o ID de la incidencia original (opcional)</label>
                <input type="text" name="incidencia_padre_id"
                       value="<?= e((string) $valores['incidencia_padre_id']) ?>"
                       placeholder="ID numérico"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
            </div>
        </div>

        <!-- Sección: Proveedor y costos -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6"
             x-data="{
                 modo: '<?= e((string) $valores['proveedor_modo']) ?>',
                 get esExterno() { return this.modo === 'catalogo' || this.modo === 'otro'; }
             }">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-1 flex items-center gap-2">
                <i data-lucide="hand-coins" class="w-4 h-4 text-bacal-700"></i>
                ¿Quién atendió? · Costos
                <span class="text-xs font-normal text-zinc-500">(opcional)</span>
            </h3>
            <p class="text-xs text-zinc-500 mb-4">Si lo resolvió personal interno, deja en "Interno". Si fue un proveedor, registra quién y cuánto costó.</p>

            <!-- Selector de modo -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mb-4">
                <label class="flex items-center gap-2 p-3 rounded-lg border-2 cursor-pointer transition-colors"
                       :class="modo === 'interno' ? 'border-bacal-700 bg-bacal-50' : 'border-zinc-200 hover:border-zinc-300'">
                    <input type="radio" name="proveedor_modo" value="interno" x-model="modo" class="text-bacal-700">
                    <div><div class="text-sm font-semibold text-zinc-900">Interno</div><div class="text-[10px] text-zinc-500">Técnicos propios</div></div>
                </label>
                <label class="flex items-center gap-2 p-3 rounded-lg border-2 cursor-pointer transition-colors"
                       :class="modo === 'catalogo' ? 'border-bacal-700 bg-bacal-50' : 'border-zinc-200 hover:border-zinc-300'">
                    <input type="radio" name="proveedor_modo" value="catalogo" x-model="modo" class="text-bacal-700">
                    <div><div class="text-sm font-semibold text-zinc-900">Proveedor</div><div class="text-[10px] text-zinc-500">Del catálogo</div></div>
                </label>
                <label class="flex items-center gap-2 p-3 rounded-lg border-2 cursor-pointer transition-colors"
                       :class="modo === 'otro' ? 'border-bacal-700 bg-bacal-50' : 'border-zinc-200 hover:border-zinc-300'">
                    <input type="radio" name="proveedor_modo" value="otro" x-model="modo" class="text-bacal-700">
                    <div><div class="text-sm font-semibold text-zinc-900">Otro</div><div class="text-[10px] text-zinc-500">Escribir / dar de alta</div></div>
                </label>
            </div>

            <!-- Interno: horas + materiales comprados -->
            <div x-show="modo === 'interno'" x-collapse class="mb-4 space-y-3">
                <div class="bg-zinc-50 rounded-lg p-3">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide flex items-center gap-1.5">
                        <i data-lucide="clock" class="w-3.5 h-3.5 text-bacal-700"></i> Tiempo de trabajo del técnico
                    </label>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <input type="number" name="horas_h" min="0" step="1"
                                   value="<?= $_ht_h ?: '' ?>" placeholder="0"
                                   class="w-20 pl-3 pr-7 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-zinc-400">h</span>
                        </div>
                        <div class="relative">
                            <input type="number" name="horas_m" min="0" max="59" step="1"
                                   value="<?= $_ht_m ?: '' ?>" placeholder="0"
                                   class="w-24 pl-3 pr-10 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                            <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-zinc-400">min</span>
                        </div>
                    </div>
                    <p class="text-[10px] text-zinc-500 mt-1">Cuánto tardó el técnico. Para una tarea corta, deja las horas en 0 y pon los minutos (ej. 0 h 10 min).</p>
                </div>
                <div class="bg-zinc-50 rounded-lg p-3">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide flex items-center gap-1.5">
                        <i data-lucide="shopping-cart" class="w-3.5 h-3.5 text-bacal-700"></i> Materiales comprados
                    </label>
                    <div class="relative w-48">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-sm">$</span>
                        <input type="number" name="costo_materiales_comprados" min="0" step="0.01"
                               value="<?= e((string) $valores['costo_materiales_comprados']) ?>" placeholder="0.00"
                               class="w-full pl-7 pr-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    </div>
                    <p class="text-[10px] text-zinc-500 mt-1">Material comprado especialmente para esta incidencia que NO estaba en inventario.</p>
                </div>
            </div>

            <!-- Catálogo -->
            <div x-show="modo === 'catalogo'" x-collapse class="mb-4">
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Proveedor registrado</label>
                <select name="proveedor_escalado_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <option value="">— Selecciona un proveedor —</option>
                    <?php foreach ($proveedores as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= (int) $valores['proveedor_escalado_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= e($p['nombre']) ?><?= $p['servicio'] ? ' · ' . e($p['servicio']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-zinc-500 mt-1">¿No está en la lista? Cámbialo a "Otro" para darlo de alta rápido.</p>
            </div>

            <!-- Otro -->
            <div x-show="modo === 'otro'" x-collapse class="mb-4 space-y-3">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <p class="text-xs font-semibold text-amber-800 mb-2">Opción A — Dar de alta el proveedor (queda en el catálogo)</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <input type="text" name="prov_nuevo_nombre" maxlength="150" value="<?= e((string) $valores['prov_nuevo_nombre']) ?>" placeholder="Nombre *"
                               class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <input type="text" name="prov_nuevo_servicio" maxlength="255" value="<?= e((string) $valores['prov_nuevo_servicio']) ?>" placeholder="Servicio (ej. Redes)"
                               class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <input type="text" name="prov_nuevo_telefono" maxlength="50" value="<?= e((string) $valores['prov_nuevo_telefono']) ?>" placeholder="Teléfono"
                               class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    </div>
                </div>
                <div class="text-center text-[10px] text-zinc-400 uppercase tracking-wider">— o —</div>
                <div>
                    <p class="text-xs font-semibold text-zinc-600 mb-1">Opción B — Solo anotar (sin dar de alta)</p>
                    <input type="text" name="proveedor_externo_info" maxlength="300" value="<?= e((string) $valores['proveedor_externo_info']) ?>"
                           placeholder="Ej. Soporte externo Juan, tel 664-123-4567"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                    <p class="text-[10px] text-zinc-500 mt-1">Si llenas el nombre arriba (Opción A) se usa ese y se ignora esto.</p>
                </div>
            </div>

            <!-- Costos proveedor -->
            <div x-show="esExterno" x-collapse>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-zinc-100">
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Costo mano de obra</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-sm">$</span>
                            <input type="number" name="costo_mano_obra" min="0" step="0.01" value="<?= e((string) $valores['costo_mano_obra']) ?>" placeholder="0.00"
                                   class="w-full pl-7 pr-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Costo materiales / piezas</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 text-sm">$</span>
                            <input type="number" name="costo_materiales_proveedor" min="0" step="0.01" value="<?= e((string) $valores['costo_materiales_proveedor']) ?>" placeholder="0.00"
                                   class="w-full pl-7 pr-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Notas del costo</label>
                    <input type="text" name="costo_notas" maxlength="300" value="<?= e((string) $valores['costo_notas']) ?>"
                           placeholder="Ej. Incluye IVA, factura A-123, garantía 6 meses"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>
        </div>

        <!-- Sección: Resolución -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="wrench" class="w-4 h-4 text-bacal-700"></i> Resolución
            </h3>
            <div class="space-y-4">
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 text-xs text-emerald-800 flex items-center gap-1.5">
                    <i data-lucide="info" class="w-3.5 h-3.5"></i>
                    Si registras una solución, la incidencia se marcará automáticamente como <strong>Completada</strong>.
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Causa raíz</label>
                    <textarea name="causa_raiz" rows="2" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700"><?= e((string) $valores['causa_raiz']) ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Solución aplicada</label>
                    <textarea name="solucion" rows="3" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700"><?= e((string) $valores['solucion']) ?></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Recomendaciones</label>
                    <textarea name="recomendaciones" rows="2" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700"><?= e((string) $valores['recomendaciones']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Sección: Adjuntos nuevos -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-3 flex items-center gap-2">
                <i data-lucide="paperclip" class="w-4 h-4 text-bacal-700"></i> Agregar adjuntos
            </h3>
            <p class="text-xs text-zinc-500 mb-3">Los adjuntos existentes se mantienen. Para eliminar, ve al detalle de la incidencia.</p>
            <input type="file" name="adjuntos[]" multiple
                   class="block w-full text-sm text-zinc-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-bacal-50 file:text-bacal-700 file:text-xs file:font-semibold hover:file:bg-bacal-100">
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="<?= url('incidencia_ver.php?id=' . $id) ?>"
               class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 font-medium text-sm hover:bg-zinc-50">Cancelar</a>
            <button type="submit"
                    class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white font-semibold text-sm shadow-sm flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i> Guardar cambios
            </button>
        </div>
    </form>
</div>

<script>
function formEditar() {
    return {
        sucursalId: '<?= e((string) $valores['sucursal_id']) ?>',
        categoriaId: '<?= e((string) $valores['categoria_id']) ?>',
        subcategoriaId: '<?= e((string) $valores['subcategoria_id']) ?>',
        equipoId: '<?= e((string) $valores['equipo_id']) ?>',
        equipos: [],
        categorias: <?= json_encode($categorias, JSON_UNESCAPED_UNICODE) ?>,

        get subcategoriasFiltradas() {
            if (!this.categoriaId) return [];
            const c = this.categorias.find(x => String(x.id) === String(this.categoriaId));
            return c ? c.subcategorias : [];
        },

        async cargarEquipos() {
            this.equipos = [];
            if (!this.sucursalId) return;
            try {
                const resp = await fetch('<?= url('api/equipos_de_sucursal.php') ?>?sucursal=' + this.sucursalId, { credentials: 'same-origin' });
                if (resp.ok) this.equipos = await resp.json();
            } catch (e) { console.error(e); }
        },

        init() { if (this.sucursalId) this.cargarEquipos(); }
    }
}
</script>

<?php require_once __DIR__ . '/config/footer.php'; ?>