<?php
/**
 * ============================================================================
 * incidencia_ver.php - Vista detallada de incidencia
 * ============================================================================
 * Muestra toda la información de una incidencia, con:
 *   - Header con folio, título, estado, severidad, sucursal
 *   - Información completa en panel lateral
 *   - Timeline de comentarios e historial
 *   - Galería de adjuntos
 *   - Acciones rápidas: cambiar estado, asignar técnico, agregar comentario,
 *     marcar como resuelta con solución/recomendaciones, editar
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/incidencias_helpers.php';
require_once __DIR__ . '/config/notificaciones_helpers.php';
require_once __DIR__ . '/config/comunicacion_helpers.php';
require_once __DIR__ . '/config/incidencia_costos_helpers.php';

requerir_login();

$u  = usuario_actual();
$id = (int) input('id', 0);

$incidencia = $id > 0 ? cargar_incidencia($id) : null;

if (!$incidencia) {
    $titulo_pagina = 'Incidencia no encontrada';
    require_once __DIR__ . '/config/header.php';
    ?>
    <div class="max-w-md mx-auto text-center py-20">
        <div class="w-16 h-16 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-4">
            <i data-lucide="file-x" class="w-8 h-8 text-zinc-400"></i>
        </div>
        <h2 class="font-display text-xl font-bold text-zinc-900 mb-2">Incidencia no encontrada</h2>
        <p class="text-sm text-zinc-500 mb-6">El identificador solicitado no existe o fue eliminado.</p>
        <a href="<?= url('bitacora.php') ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold rounded-lg">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Volver a la bitácora
        </a>
    </div>
    <?php
    require_once __DIR__ . '/config/footer.php';
    exit;
}

if (!puede_ver_incidencia($incidencia)) {
    http_response_code(403);
    die('No tienes permiso para ver esta incidencia.');
}

$puede_editar = puede_editar_incidencia($incidencia);

// Costos de la incidencia
$costos = costo_incidencia($id);
$proveedor_nombre = null;
if (!empty($incidencia['proveedor_escalado_id'])) {
    $prov_row = db_one("SELECT nombre, telefono FROM proveedores WHERE id = :id", ['id' => (int) $incidencia['proveedor_escalado_id']]);
    $proveedor_nombre = $prov_row['nombre'] ?? null;
    $proveedor_telefono = $prov_row['telefono'] ?? null;
}

// ----------------------------------------------------------------------------
// Procesar acciones POST (acciones rápidas)
// ----------------------------------------------------------------------------
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        flash_set('error', 'Token de seguridad inválido.');
        header('Location: ' . url('incidencia_ver.php?id=' . $id));
        exit;
    }

    $accion = (string) input('accion', '');

    try {
        db()->beginTransaction();

        // Snapshot antes para auditoría
        $antes = $incidencia;

        switch ($accion) {
            // --------------------------------------------------------------
            case 'comentar':
                $texto = trim((string) input('comentario', ''));
                if ($texto !== '') {
                    db_exec(
                        "INSERT INTO incidencias_comentarios (incidencia_id, usuario_id, comentario)
                         VALUES (:iid, :uid, :c)",
                        ['iid' => $id, 'uid' => $u['id'], 'c' => $texto]
                    );
                    notificar_comentario($id, (int) $u['id'], $texto);

                    // Notificar menciones (@usuario) en el comentario
                    notificar_menciones(
                        $texto,
                        (int) $u['id'],
                        "Te mencionaron en {$incidencia['folio']}",
                        url_relativa('incidencia_ver.php?id=' . $id) . '#comentarios',
                        'incidencias',
                        $id
                    );

                    flash_set('success', 'Comentario agregado.');
                }
                break;

            // --------------------------------------------------------------
            case 'cambiar_estado':
                if (!$puede_editar) throw new Exception('Sin permiso');
                $nuevo_estado = (int) input('estado_id', 0);
                if ($nuevo_estado <= 0) throw new Exception('Estado inválido');

                $estado_info = db_one("SELECT nombre, es_final FROM estados WHERE id=:id", ['id' => $nuevo_estado]);
                if (!$estado_info) throw new Exception('Estado no existe');

                $updates = ['estado_id' => $nuevo_estado];
                $params  = ['estado' => $nuevo_estado, 'id' => $id];

                // Si pasa a "En proceso" o similar y no había fecha_atencion, registrarla
                if (!$incidencia['fecha_atencion']) {
                    $updates['fecha_atencion'] = date('Y-m-d H:i:s');
                    $params['fatencion'] = $updates['fecha_atencion'];
                }

                // Si es estado final, registrar fecha_resolucion (si no la había)
                if ((int) $estado_info['es_final'] === 1) {
                    if (!$incidencia['fecha_resolucion']) {
                        $updates['fecha_resolucion'] = date('Y-m-d H:i:s');
                        $params['fresolucion'] = $updates['fecha_resolucion'];
                    }
                    if (!$incidencia['fecha_cierre']) {
                        $updates['fecha_cierre'] = date('Y-m-d H:i:s');
                        $params['fcierre'] = $updates['fecha_cierre'];
                    }
                    // Si no había resuelto_por, asignarlo al usuario actual
                    if (!$incidencia['resuelto_por_id']) {
                        $updates['resuelto_por_id'] = $u['id'];
                        $params['ruid'] = $u['id'];
                    }
                }

                // Construir SQL dinámico
                $sets = [];
                foreach ($updates as $k => $_v) {
                    $tok = $k === 'estado_id' ? 'estado'
                        : ($k === 'fecha_atencion' ? 'fatencion'
                        : ($k === 'fecha_resolucion' ? 'fresolucion'
                        : ($k === 'fecha_cierre' ? 'fcierre' : 'ruid')));
                    $sets[] = "$k = :$tok";
                }
                $sets[] = "actualizado_por_id = :auid";
                $params['auid'] = $u['id'];

                db_exec("UPDATE incidencias SET " . implode(', ', $sets) . " WHERE id = :id", $params);
                recalcular_tiempos_incidencia($id);

                registrar_historial(
                    $id, $u['id'], 'estado_cambiado', 'estado_id',
                    (string) $incidencia['estado_id'], (string) $nuevo_estado,
                    "Estado cambiado a {$estado_info['nombre']}"
                );
                notificar_cambio_estado($id, $nuevo_estado, (int) $u['id']);
                flash_set('success', "Estado actualizado a {$estado_info['nombre']}.");
                break;

            // --------------------------------------------------------------
            case 'asignar':
                if (!$puede_editar) throw new Exception('Sin permiso');
                $tecnico_id = (int) input('asignado_a_id', 0) ?: null;

                $updates = ['asignado_a_id' => $tecnico_id];
                $params  = ['tid' => $tecnico_id, 'id' => $id, 'auid' => $u['id']];

                // Si se asigna y no había fecha_atencion, registrarla
                $set_fatencion = '';
                if ($tecnico_id && !$incidencia['fecha_atencion']) {
                    $set_fatencion = ', fecha_atencion = NOW()';
                }

                db_exec(
                    "UPDATE incidencias SET asignado_a_id = :tid, actualizado_por_id = :auid $set_fatencion WHERE id = :id",
                    $params
                );
                recalcular_tiempos_incidencia($id);

                $nombre_tec = $tecnico_id ? db_one("SELECT nombre_completo n FROM usuarios WHERE id=:id", ['id' => $tecnico_id])['n'] : 'sin asignar';
                registrar_historial(
                    $id, $u['id'], 'asignado', 'asignado_a_id',
                    (string) $incidencia['asignado_a_id'], (string) $tecnico_id,
                    "Asignado a $nombre_tec"
                );
                if ($tecnico_id) {
                    notificar_asignacion($id, $tecnico_id, (int) $u['id']);
                }
                flash_set('success', "Incidencia asignada a $nombre_tec.");
                break;

            // --------------------------------------------------------------
            case 'agregar_resolucion':
                if (!$puede_editar) throw new Exception('Sin permiso');
                $solucion = trim((string) input('solucion', ''));
                $recomendaciones = trim((string) input('recomendaciones', ''));
                $causa_raiz = trim((string) input('causa_raiz', ''));

                db_exec(
                    "UPDATE incidencias
                     SET solucion = :sol, recomendaciones = :rec, causa_raiz = :cr,
                         actualizado_por_id = :auid
                     WHERE id = :id",
                    [
                        'sol' => $solucion ?: null,
                        'rec' => $recomendaciones ?: null,
                        'cr' => $causa_raiz ?: null,
                        'auid' => $u['id'], 'id' => $id
                    ]
                );
                registrar_diferencias($id, $u['id'], $antes, array_merge($antes, [
                    'solucion' => $solucion, 'recomendaciones' => $recomendaciones, 'causa_raiz' => $causa_raiz
                ]));
                flash_set('success', 'Resolución actualizada.');
                break;

            // --------------------------------------------------------------
            case 'subir_adjuntos':
                if (!$puede_editar) throw new Exception('Sin permiso');
                if (!empty($_FILES['adjuntos']['name'][0])) {
                    [$exitos, $errs] = procesar_adjuntos($id, $_FILES['adjuntos'], $u['id']);
                    if (count($exitos) > 0) {
                        registrar_historial($id, $u['id'], 'adjuntos_subidos', 'adjuntos', null,
                            count($exitos) . ' archivo(s)', count($exitos) . ' archivo(s) adjuntados');
                        flash_set('success', count($exitos) . ' archivo(s) adjuntados.');
                    }
                    if (!empty($errs)) {
                        flash_set('warning', 'Algunos adjuntos no se pudieron procesar: ' . implode(' ', $errs));
                    }
                }
                break;

            // --------------------------------------------------------------
            case 'eliminar_adjunto':
                if (!$puede_editar) throw new Exception('Sin permiso');
                $aid = (int) input('adjunto_id', 0);
                $adj = db_one("SELECT * FROM incidencias_adjuntos WHERE id=:id AND incidencia_id=:iid",
                    ['id' => $aid, 'iid' => $id]);
                if ($adj) {
                    $ruta_disco = __DIR__ . '/assets/' . $adj['ruta'];
                    if (file_exists($ruta_disco)) @unlink($ruta_disco);
                    db_exec("DELETE FROM incidencias_adjuntos WHERE id=:id", ['id' => $aid]);
                    registrar_historial($id, $u['id'], 'adjunto_eliminado', 'adjuntos',
                        $adj['nombre_original'], null, 'Adjunto eliminado: ' . $adj['nombre_original']);
                    flash_set('success', 'Adjunto eliminado.');
                }
                break;
        }

        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        flash_set('error', 'Error: ' . $e->getMessage());
    }

    header('Location: ' . url('incidencia_ver.php?id=' . $id));
    exit;
}

// ----------------------------------------------------------------------------
// Cargar datos relacionados para mostrar
// ----------------------------------------------------------------------------
$adjuntos     = cargar_adjuntos($id);
$comentarios  = cargar_comentarios($id);
$historial    = cargar_historial($id);
$relacionadas = cargar_incidencias_relacionadas($id, $incidencia['incidencia_padre_id'] ? (int) $incidencia['incidencia_padre_id'] : null);

$estados_disponibles = cat_estados();
$tecnicos_disponibles = cat_tecnicos();

// Formato amigable de horas de trabajo (decimal -> "X h Y min")
if (!function_exists('fmt_horas_min')) {
    function fmt_horas_min($horas_decimal): string {
        $horas_decimal = (float) $horas_decimal;
        if ($horas_decimal <= 0) return '—';
        $total_min = (int) round($horas_decimal * 60);
        $h = intdiv($total_min, 60);
        $m = $total_min % 60;
        if ($h > 0 && $m > 0) return "{$h} h {$m} min";
        if ($h > 0)           return "{$h} h";
        return "{$m} min";
    }
}

// SLA actual
function calcular_sla_estado(array $i): array {
    if (!empty($i['estado_es_final'])) {
        if ($i['sla_cumplido'] === '1' || $i['sla_cumplido'] === 1) {
            return ['cumplido', 'SLA cumplido', '#10B981', 'check-circle-2'];
        } elseif ($i['sla_cumplido'] === '0' || $i['sla_cumplido'] === 0) {
            return ['incumplido', 'SLA incumplido', '#DC2626', 'x-circle'];
        }
        return ['na', 'Sin SLA', '#9CA3AF', 'minus-circle'];
    }
    if (empty($i['fecha_limite_sla'])) return ['sinsla', 'Sin SLA configurado', '#9CA3AF', 'minus-circle'];
    $limite = strtotime($i['fecha_limite_sla']);
    $ahora = time();
    if ($limite < $ahora) {
        $horas_vencido = floor(($ahora - $limite) / 3600);
        return ['vencido', "SLA vencido (hace {$horas_vencido}h)", '#DC2626', 'flame'];
    }
    if ($limite - $ahora < 7200) {
        $min_restantes = floor(($limite - $ahora) / 60);
        return ['riesgo', "Vence en {$min_restantes} min", '#D97706', 'clock-alert'];
    }
    $horas = floor(($limite - $ahora) / 3600);
    return ['ok', "Vence en {$horas}h", '#10B981', 'clock'];
}
$sla_info = calcular_sla_estado($incidencia);

$titulo_pagina = $incidencia['folio'];
$pagina_activa = 'bitacora';
require_once __DIR__ . '/config/header.php';
?>

<div class="max-w-6xl mx-auto animate-fade-in space-y-4">

    <!-- Breadcrumb + acciones -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-2 text-sm">
            <a href="<?= url('bitacora.php') ?>" class="text-zinc-500 hover:text-bacal-700 flex items-center gap-1.5">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Bitácora
            </a>
            <i data-lucide="chevron-right" class="w-3 h-3 text-zinc-300"></i>
            <span class="font-mono text-xs font-semibold text-zinc-700"><?= e($incidencia['folio']) ?></span>
        </div>

        <div class="flex items-center gap-2" x-data="{ menuAcciones: false }">
            <?php if ($puede_editar): ?>
            <a href="<?= url('incidencia_editar.php?id=' . $id) ?>"
               class="px-3 py-1.5 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50 flex items-center gap-1.5">
                <i data-lucide="edit-3" class="w-4 h-4"></i> Editar
            </a>
            <?php endif; ?>

            <?php if (tiene_permiso('crear_solicitud')): ?>
            <a href="<?= url('incidencia_nueva.php?duplicar_de=' . $id) ?>"
               class="px-3 py-1.5 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50 flex items-center gap-1.5"
               title="Crear nueva basada en esta">
                <i data-lucide="copy-plus" class="w-4 h-4"></i> Duplicar
            </a>
            <?php endif; ?>

            <a href="<?= url('reportes/incidencia_imprimir.php?id=' . $id) ?>" target="_blank"
               class="px-3 py-1.5 rounded-lg border border-zinc-300 bg-white text-sm font-medium text-zinc-700 hover:bg-zinc-50 flex items-center gap-1.5"
               title="Imprimir o guardar como PDF">
                <i data-lucide="printer" class="w-4 h-4"></i> Imprimir / PDF
            </a>
        </div>
    </div>

    <!-- Header principal -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
        <div class="flex items-start gap-4">
            <!-- Barra de severidad -->
            <div class="w-1.5 self-stretch rounded-full" style="background-color: <?= e($incidencia['severidad_color']) ?>; min-height: 80px;"></div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <span class="font-mono text-[11px] font-bold text-zinc-500"><?= e($incidencia['folio']) ?></span>
                    <span class="text-zinc-300">·</span>
                    <span class="text-xs text-zinc-500">Creada <?= e(fmt_fecha($incidencia['creado_en'])) ?></span>
                    <?php if ($incidencia['es_reincidencia']): ?>
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-purple-700 bg-purple-50 border border-purple-200 px-2 py-0.5 rounded-md">
                        <i data-lucide="rotate-ccw" class="w-3 h-3"></i> Reincidencia
                    </span>
                    <?php endif; ?>
                </div>

                <h1 class="font-display text-2xl font-extrabold text-zinc-900 leading-tight mb-3"><?= e($incidencia['titulo']) ?></h1>

                <div class="flex items-center gap-2 flex-wrap">
                    <?= badge($incidencia['sucursal_nombre'], '#6B7280') ?>
                    <?= badge($incidencia['area_nombre'], $incidencia['area_color']) ?>
                    <?= badge($incidencia['severidad_nombre'], $incidencia['severidad_color']) ?>
                    <?= badge($incidencia['estado_nombre'], $incidencia['estado_color']) ?>
                    <?php if ($incidencia['categoria_nombre']): ?>
                    <?= badge($incidencia['categoria_nombre'], $incidencia['categoria_color']) ?>
                    <?php endif; ?>
                    <?php if ($incidencia['tipo_trabajo_nombre']): ?>
                    <?= badge($incidencia['tipo_trabajo_nombre'], $incidencia['tipo_trabajo_color']) ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SLA indicator -->
            <div class="text-right flex-shrink-0">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold"
                     style="background-color: <?= e($sla_info[2]) ?>15; color: <?= e($sla_info[2]) ?>; border: 1px solid <?= e($sla_info[2]) ?>40;">
                    <i data-lucide="<?= e($sla_info[3]) ?>" class="w-3.5 h-3.5"></i>
                    <?= e($sla_info[1]) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Grid: contenido principal + sidebar -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- ============================================== -->
        <!-- COLUMNA PRINCIPAL -->
        <!-- ============================================== -->
        <div class="lg:col-span-2 space-y-4">

            <!-- Descripción -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
                <h3 class="font-display text-base font-bold text-zinc-900 mb-3 flex items-center gap-2">
                    <i data-lucide="align-left" class="w-4 h-4 text-bacal-700"></i> Descripción
                </h3>
                <div class="text-sm text-zinc-700 whitespace-pre-wrap leading-relaxed"><?= e($incidencia['descripcion']) ?></div>
            </div>

            <!-- Resolución (si existe o el usuario puede editar) -->
            <?php if ($incidencia['solucion'] || $incidencia['causa_raiz'] || $incidencia['recomendaciones'] || $puede_editar): ?>
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6"
                 x-data="{ editando: false }">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                        <i data-lucide="wrench" class="w-4 h-4 text-bacal-700"></i> Resolución
                    </h3>
                    <?php if ($puede_editar): ?>
                    <button @click="editando = !editando"
                            class="text-xs font-semibold text-bacal-700 hover:text-bacal-800 flex items-center gap-1">
                        <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                        <span x-text="editando ? 'Cancelar' : (<?= ($incidencia['solucion'] || $incidencia['causa_raiz']) ? 'true' : 'false' ?> ? 'Editar' : 'Agregar')"></span>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Vista de solo lectura -->
                <div x-show="!editando" class="space-y-4">
                    <?php if ($incidencia['causa_raiz']): ?>
                    <div>
                        <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1">Causa raíz</div>
                        <div class="text-sm text-zinc-700 whitespace-pre-wrap"><?= e($incidencia['causa_raiz']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($incidencia['solucion']): ?>
                    <div>
                        <div class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1">Solución aplicada</div>
                        <div class="text-sm text-zinc-700 whitespace-pre-wrap"><?= e($incidencia['solucion']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($incidencia['recomendaciones']): ?>
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                        <div class="text-[10px] font-bold text-amber-700 uppercase tracking-wider mb-1 flex items-center gap-1">
                            <i data-lucide="lightbulb" class="w-3 h-3"></i> Recomendaciones
                        </div>
                        <div class="text-sm text-amber-900 whitespace-pre-wrap"><?= e($incidencia['recomendaciones']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!$incidencia['solucion'] && !$incidencia['causa_raiz'] && !$incidencia['recomendaciones']): ?>
                    <p class="text-xs text-zinc-400 italic">Sin información de resolución aún.</p>
                    <?php endif; ?>
                </div>

                <!-- Formulario de edición -->
                <?php if ($puede_editar): ?>
                <form x-show="editando" x-cloak method="POST" class="space-y-3">
                    <?= csrf_input() ?>
                    <input type="hidden" name="accion" value="agregar_resolucion">

                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Causa raíz</label>
                        <textarea name="causa_raiz" rows="2" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700"><?= e((string) $incidencia['causa_raiz']) ?></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Solución</label>
                        <textarea name="solucion" rows="3" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700"><?= e((string) $incidencia['solucion']) ?></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Recomendaciones</label>
                        <textarea name="recomendaciones" rows="2" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700"><?= e((string) $incidencia['recomendaciones']) ?></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="editando = false" class="px-3 py-1.5 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Guardar</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Adjuntos -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6"
                 x-data="{ subiendo: false }">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                        <i data-lucide="paperclip" class="w-4 h-4 text-bacal-700"></i>
                        Adjuntos
                        <span class="bg-zinc-100 text-zinc-600 text-xs font-bold px-2 py-0.5 rounded-full"><?= count($adjuntos) ?></span>
                    </h3>
                    <?php if ($puede_editar && count($adjuntos) < ADJUNTOS_MAX_ARCHIVOS): ?>
                    <button @click="subiendo = !subiendo"
                            class="text-xs font-semibold text-bacal-700 hover:text-bacal-800 flex items-center gap-1">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        <span x-text="subiendo ? 'Cancelar' : 'Subir'"></span>
                    </button>
                    <?php endif; ?>
                </div>

                <?php if ($puede_editar): ?>
                <form x-show="subiendo" x-cloak method="POST" enctype="multipart/form-data" class="mb-4 p-3 bg-zinc-50 rounded-lg">
                    <?= csrf_input() ?>
                    <input type="hidden" name="accion" value="subir_adjuntos">
                    <input type="file" name="adjuntos[]" multiple class="w-full text-xs mb-2">
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Subir</button>
                </form>
                <?php endif; ?>

                <?php if (empty($adjuntos)): ?>
                <p class="text-xs text-zinc-400 italic text-center py-4">Sin adjuntos.</p>
                <?php else: ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <?php foreach ($adjuntos as $a):
                        $es_img = str_starts_with((string) $a['tipo_mime'], 'image/');
                        $ruta_url = url('assets/' . $a['ruta']);
                    ?>
                    <div class="border border-zinc-200 rounded-lg overflow-hidden group relative">
                        <?php if ($es_img): ?>
                        <a href="<?= e($ruta_url) ?>" target="_blank" class="block aspect-square bg-zinc-50">
                            <img src="<?= e($ruta_url) ?>" alt="<?= e($a['nombre_original']) ?>" class="w-full h-full object-cover">
                        </a>
                        <?php else: ?>
                        <a href="<?= e($ruta_url) ?>" target="_blank" class="block aspect-square bg-zinc-50 flex items-center justify-center">
                            <i data-lucide="file-text" class="w-10 h-10 text-zinc-400"></i>
                        </a>
                        <?php endif; ?>
                        <div class="p-2 bg-white border-t border-zinc-100">
                            <div class="text-[10px] font-medium text-zinc-700 truncate" title="<?= e($a['nombre_original']) ?>">
                                <?= e($a['nombre_original']) ?>
                            </div>
                            <div class="text-[9px] text-zinc-400"><?= number_format($a['tamano_bytes'] / 1024, 0) ?> KB</div>
                        </div>
                        <?php if ($puede_editar): ?>
                        <form method="POST" class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity"
                              onsubmit="return confirm('¿Eliminar este adjunto?');">
                            <?= csrf_input() ?>
                            <input type="hidden" name="accion" value="eliminar_adjunto">
                            <input type="hidden" name="adjunto_id" value="<?= $a['id'] ?>">
                            <button type="submit" class="p-1 rounded bg-bacal-700 text-white hover:bg-bacal-800" title="Eliminar">
                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Comentarios -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
                <h3 class="font-display text-base font-bold text-zinc-900 mb-3 flex items-center gap-2">
                    <i data-lucide="message-square" class="w-4 h-4 text-bacal-700"></i>
                    Comentarios
                    <span class="bg-zinc-100 text-zinc-600 text-xs font-bold px-2 py-0.5 rounded-full"><?= count($comentarios) ?></span>
                </h3>

                <!-- Formulario nuevo comentario con autocompletado @ -->
                <form method="POST" class="mb-5"
                      x-data="comentarioForm()">
                    <?= csrf_input() ?>
                    <input type="hidden" name="accion" value="comentar">
                    <div class="flex gap-2 items-start">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                             style="background-color: <?= color_avatar($u['nombre']) ?>">
                            <?= e(iniciales($u['nombre'])) ?>
                        </div>
                        <div class="flex-1 relative">
                            <textarea name="comentario" rows="2" required
                                      x-ref="textarea"
                                      x-model="texto"
                                      @input="detectarMencion()"
                                      @keydown="manejarTeclas($event)"
                                      placeholder="Agrega un comentario… Usa @ para mencionar a un compañero"
                                      class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700"></textarea>

                            <!-- Dropdown de autocompletado -->
                            <div x-show="sugerencias.length > 0" x-cloak
                                 class="absolute z-50 mt-1 w-72 bg-white border border-zinc-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                <template x-for="(usr, idx) in sugerencias" :key="usr.id">
                                    <button type="button"
                                            @click="seleccionarUsuario(usr)"
                                            :class="idx === sugIndex ? 'bg-bacal-50' : 'hover:bg-zinc-50'"
                                            class="w-full px-3 py-2 text-left flex items-center gap-2 border-b border-zinc-100 last:border-0">
                                        <template x-if="usr.avatar_full_url">
                                            <img :src="usr.avatar_full_url" class="w-7 h-7 rounded-full object-cover">
                                        </template>
                                        <template x-if="!usr.avatar_full_url">
                                            <span class="w-7 h-7 rounded-full bg-zinc-300 text-white text-[10px] font-bold flex items-center justify-center"
                                                  x-text="usr.iniciales"></span>
                                        </template>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs font-semibold text-zinc-900 truncate" x-text="usr.nombre_completo"></div>
                                            <div class="text-[10px] text-zinc-500 font-mono">@<span x-text="usr.usuario"></span></div>
                                        </div>
                                    </button>
                                </template>
                            </div>

                            <div class="flex items-center justify-between mt-2">
                                <div class="text-[10px] text-zinc-400">
                                    💡 Tip: escribe <code class="font-mono bg-zinc-100 px-1 rounded">@nombre</code> para mencionar a alguien
                                </div>
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
                                    <i data-lucide="send" class="w-3.5 h-3.5"></i> Publicar
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <?php if (empty($comentarios)): ?>
                <p class="text-xs text-zinc-400 italic text-center py-4">Sin comentarios aún.</p>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($comentarios as $c): ?>
                    <div class="flex gap-2.5 items-start group" data-comentario-id="<?= (int) $c['id'] ?>">
                        <?= render_avatar([
                            'nombre_completo' => $c['usuario_nombre'],
                            'avatar_url' => $c['usuario_avatar'] ?? null,
                        ], 'w-8 h-8') ?>
                        <div class="flex-1 min-w-0">
                            <div class="bg-zinc-50 rounded-lg p-3">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-semibold text-sm text-zinc-900"><?= e($c['usuario_nombre']) ?></span>
                                    <span class="text-[11px] text-zinc-500"><?= e(fmt_tiempo_relativo($c['creado_en'])) ?></span>
                                </div>
                                <div class="text-sm text-zinc-700 whitespace-pre-wrap"><?= renderizar_menciones($c['comentario']) ?></div>
                            </div>

                            <!-- Reacciones existentes + botón agregar -->
                            <div class="flex items-center gap-1.5 mt-1.5 flex-wrap"
                                 x-data="reaccionesComentario(<?= (int) $c['id'] ?>, <?= htmlspecialchars(json_encode($c['reacciones'] ?? []), ENT_QUOTES) ?>)">

                                <!-- Reacciones existentes (con conteo) -->
                                <template x-for="r in reacciones" :key="r.emoji">
                                    <button type="button"
                                            @click="toggleReaccion(r.emoji)"
                                            :class="r.usuarios_ids.includes(<?= (int) $u['id'] ?>) ? 'bg-blue-100 border-blue-300 text-blue-800' : 'bg-zinc-100 border-zinc-200 text-zinc-700 hover:bg-zinc-200'"
                                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full border text-xs transition-colors">
                                        <span x-text="r.emoji"></span>
                                        <span class="font-semibold" x-text="r.total"></span>
                                    </button>
                                </template>

                                <!-- Botón para agregar reacción -->
                                <div class="relative opacity-0 group-hover:opacity-100 transition-opacity" x-data="{ abierto: false }" @click.outside="abierto = false">
                                    <button type="button" @click="abierto = !abierto"
                                            class="inline-flex items-center px-1.5 py-0.5 rounded-full border border-dashed border-zinc-300 text-zinc-400 hover:border-zinc-500 hover:text-zinc-700 text-xs">
                                        <i data-lucide="smile-plus" class="w-3 h-3"></i>
                                    </button>
                                    <div x-show="abierto" x-cloak x-transition
                                         class="absolute z-40 bottom-full mb-1 left-0 bg-white border border-zinc-200 rounded-lg shadow-lg p-1 flex gap-0.5">
                                        <?php foreach (EMOJIS_REACCION as $emoji): ?>
                                        <button type="button"
                                                @click="toggleReaccion('<?= e($emoji) ?>'); abierto = false"
                                                class="hover:bg-zinc-100 rounded p-1 text-base"><?= $emoji ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        // === Form de comentario con autocompletado de menciones ===
        function comentarioForm() {
            return {
                texto: '',
                sugerencias: [],
                sugIndex: 0,
                timerBuscar: null,
                inicioMencion: -1, // posición del @ en el textarea

                detectarMencion() {
                    const ta = this.$refs.textarea;
                    const pos = ta.selectionStart;
                    const texto = this.texto.substring(0, pos);

                    // Buscar @ más reciente que no tenga espacio antes
                    const match = texto.match(/(?:^|\s)@([a-zA-Z0-9._-]*)$/);
                    if (match) {
                        const query = match[1];
                        this.inicioMencion = pos - match[0].length + match[0].indexOf('@');
                        this.buscarUsuarios(query);
                    } else {
                        this.sugerencias = [];
                        this.inicioMencion = -1;
                    }
                },

                async buscarUsuarios(q) {
                    clearTimeout(this.timerBuscar);
                    this.timerBuscar = setTimeout(async () => {
                        try {
                            const resp = await fetch('<?= url('api/usuarios_buscar.php') ?>?q=' + encodeURIComponent(q), {
                                credentials: 'same-origin'
                            });
                            if (resp.ok) {
                                const data = await resp.json();
                                this.sugerencias = data.usuarios || [];
                                this.sugIndex = 0;
                            }
                        } catch (e) {}
                    }, 150);
                },

                seleccionarUsuario(usr) {
                    const ta = this.$refs.textarea;
                    const posActual = ta.selectionStart;
                    const antes = this.texto.substring(0, this.inicioMencion);
                    const despues = this.texto.substring(posActual);
                    const reemplazo = '@' + usr.usuario + ' ';
                    this.texto = antes + reemplazo + despues;
                    this.sugerencias = [];

                    // Reposicionar cursor
                    this.$nextTick(() => {
                        const nuevaPos = this.inicioMencion + reemplazo.length;
                        ta.setSelectionRange(nuevaPos, nuevaPos);
                        ta.focus();
                    });
                },

                manejarTeclas(e) {
                    if (this.sugerencias.length === 0) return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        this.sugIndex = (this.sugIndex + 1) % this.sugerencias.length;
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        this.sugIndex = (this.sugIndex - 1 + this.sugerencias.length) % this.sugerencias.length;
                    } else if (e.key === 'Enter' || e.key === 'Tab') {
                        if (this.sugerencias[this.sugIndex]) {
                            e.preventDefault();
                            this.seleccionarUsuario(this.sugerencias[this.sugIndex]);
                        }
                    } else if (e.key === 'Escape') {
                        this.sugerencias = [];
                    }
                }
            }
        }

        // === Reacciones a comentarios ===
        function reaccionesComentario(comentarioId, reaccionesIniciales) {
            return {
                comentarioId: comentarioId,
                reacciones: reaccionesIniciales,

                async toggleReaccion(emoji) {
                    try {
                        const fd = new FormData();
                        fd.append('_csrf', '<?= e(csrf_token()) ?>');
                        fd.append('comentario_id', this.comentarioId);
                        fd.append('emoji', emoji);

                        const resp = await fetch('<?= url('api/comentario_reaccionar.php') ?>', {
                            method: 'POST', body: fd, credentials: 'same-origin'
                        });
                        const data = await resp.json();

                        if (!data.ok) return;

                        // Actualizar estado local sin recargar
                        const userId = <?= (int) $u['id'] ?>;
                        const idx = this.reacciones.findIndex(r => r.emoji === emoji);

                        if (data.estado === 'agregada') {
                            if (idx >= 0) {
                                if (!this.reacciones[idx].usuarios_ids.includes(userId)) {
                                    this.reacciones[idx].usuarios_ids.push(userId);
                                }
                                this.reacciones[idx].total = data.nuevo_total;
                            } else {
                                this.reacciones.push({
                                    emoji: emoji,
                                    total: data.nuevo_total,
                                    usuarios_ids: [userId],
                                });
                            }
                        } else {
                            // eliminada
                            if (idx >= 0) {
                                this.reacciones[idx].usuarios_ids = this.reacciones[idx].usuarios_ids.filter(u => u !== userId);
                                this.reacciones[idx].total = data.nuevo_total;
                                if (data.nuevo_total === 0) {
                                    this.reacciones.splice(idx, 1);
                                }
                            }
                        }
                    } catch (e) {
                        console.error('Error reaccionar:', e);
                    }
                }
            }
        }
        </script>

        <!-- ============================================== -->
        <!-- SIDEBAR DERECHO -->
        <!-- ============================================== -->
        <div class="space-y-4">

            <!-- Costos -->
            <?php
            $ver_moi = puede_ver_mano_obra_interna();
            $mostrar_costos = $ver_moi ? $costos['tiene_costo'] : $costos['tiene_costo_visible'];
            $hay_proveedor = $proveedor_nombre || !empty($incidencia['proveedor_externo_info']);
            ?>
            <?php if ($mostrar_costos || $hay_proveedor): ?>
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-zinc-600 uppercase tracking-wide mb-3 flex items-center gap-1.5">
                    <i data-lucide="hand-coins" class="w-3.5 h-3.5 text-bacal-700"></i> Costos
                </h3>

                <?php if ($hay_proveedor): ?>
                <div class="flex items-center gap-2 mb-3 pb-3 border-b border-zinc-100">
                    <div class="w-8 h-8 rounded-lg bg-bacal-50 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="truck" class="w-4 h-4 text-bacal-700"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-zinc-900 truncate">
                            <?= e($proveedor_nombre ?: $incidencia['proveedor_externo_info']) ?>
                        </div>
                        <?php if ($proveedor_nombre && !empty($proveedor_telefono)): ?>
                        <div class="text-[10px] text-zinc-500"><?= e($proveedor_telefono) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                $total_mostrar = $ver_moi ? $costos['total'] : $costos['total_visible'];
                ?>
                <dl class="space-y-1.5 text-xs">
                    <?php if ($costos['mano_obra'] > 0): ?>
                    <div class="flex justify-between gap-2">
                        <dt class="text-zinc-500">Mano de obra proveedor</dt>
                        <dd class="font-semibold text-zinc-900"><?= e(fmt_dinero($costos['mano_obra'])) ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($costos['materiales_proveedor'] > 0): ?>
                    <div class="flex justify-between gap-2">
                        <dt class="text-zinc-500">Materiales proveedor</dt>
                        <dd class="font-semibold text-zinc-900"><?= e(fmt_dinero($costos['materiales_proveedor'])) ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($costos['materiales_comprados'] > 0): ?>
                    <div class="flex justify-between gap-2">
                        <dt class="text-zinc-500">Materiales comprados</dt>
                        <dd class="font-semibold text-zinc-900"><?= e(fmt_dinero($costos['materiales_comprados'])) ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($ver_moi && $costos['mano_obra_interna'] > 0): ?>
                    <div class="flex justify-between gap-2">
                        <dt class="text-zinc-500 flex items-center gap-1">
                            Mano de obra interna
                            <span class="text-[9px] text-bacal-700 bg-bacal-50 px-1 rounded">confid.</span>
                        </dt>
                        <dd class="font-semibold text-zinc-900"><?= e(fmt_dinero($costos['mano_obra_interna'])) ?></dd>
                    </div>
                    <?php if ($costos['horas_trabajadas'] > 0): ?>
                    <div class="flex justify-between gap-2 text-[10px] text-zinc-400 -mt-1">
                        <dt><?= e(fmt_horas_min($costos['horas_trabajadas'])) ?> × <?= e(fmt_dinero($costos['tarifa_aplicada'])) ?>/h</dt>
                        <dd></dd>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <div class="flex justify-between gap-2 pt-2 mt-1 border-t border-zinc-200">
                        <dt class="text-zinc-900 font-bold uppercase tracking-wide">Total</dt>
                        <dd class="font-display text-base font-extrabold text-bacal-700"><?= e(fmt_dinero($total_mostrar)) ?></dd>
                    </div>
                </dl>

                <?php if ($costos['horas_trabajadas'] > 0): ?>
                <div class="flex items-center gap-1.5 mt-2 text-[11px] text-zinc-500">
                    <i data-lucide="clock" class="w-3 h-3"></i>
                    Tiempo activo: <strong class="text-zinc-700"><?= e(fmt_horas_min($costos['horas_trabajadas'])) ?></strong>
                </div>
                <?php endif; ?>

                <?php if (!empty($incidencia['costo_notas'])): ?>
                <p class="text-[10px] text-zinc-500 mt-2 pt-2 border-t border-zinc-100 italic">
                    <?= e($incidencia['costo_notas']) ?>
                </p>
                <?php endif; ?>

                <?php if ($total_mostrar > 0): ?>
                <?php
                $pct_ext = $total_mostrar > 0 ? round(($costos['externo'] / $total_mostrar) * 100) : 0;
                $pct_int = 100 - $pct_ext;
                ?>
                <div class="flex gap-1 mt-3">
                    <?php if ($pct_ext > 0): ?>
                    <div class="h-1.5 rounded-l-full bg-bacal-600" style="width: <?= $pct_ext ?>%"></div>
                    <?php endif; ?>
                    <?php if ($pct_int > 0): ?>
                    <div class="h-1.5 <?= $pct_ext > 0 ? 'rounded-r-full' : 'rounded-full' ?> bg-zinc-400" style="width: <?= $pct_int ?>%"></div>
                    <?php endif; ?>
                </div>
                <div class="flex justify-between text-[9px] text-zinc-400 mt-1">
                    <span>Externo <?= $pct_ext ?>%</span>
                    <span>Interno <?= $pct_int ?>%</span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Acciones rápidas -->
            <?php if ($puede_editar): ?>
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-zinc-600 uppercase tracking-wide mb-3">Acciones rápidas</h3>

                <!-- Cambiar estado -->
                <form method="POST" class="mb-3">
                    <?= csrf_input() ?>
                    <input type="hidden" name="accion" value="cambiar_estado">
                    <label class="block text-[10px] font-bold text-zinc-500 mb-1 uppercase">Estado</label>
                    <div class="flex gap-1">
                        <select name="estado_id" class="flex-1 px-2 py-1.5 rounded-md border border-zinc-300 bg-white text-xs focus:outline-none focus:border-bacal-700">
                            <?php foreach ($estados_disponibles as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= $incidencia['estado_id'] == $e['id'] ? 'selected' : '' ?>>
                                <?= e($e['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="px-2 py-1.5 rounded-md bg-bacal-700 hover:bg-bacal-800 text-white text-xs font-semibold">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </form>

                <!-- Asignar técnico -->
                <form method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="accion" value="asignar">
                    <label class="block text-[10px] font-bold text-zinc-500 mb-1 uppercase">Asignado a</label>
                    <div class="flex gap-1">
                        <select name="asignado_a_id" class="flex-1 px-2 py-1.5 rounded-md border border-zinc-300 bg-white text-xs focus:outline-none focus:border-bacal-700">
                            <option value="">— Sin asignar —</option>
                            <?php foreach ($tecnicos_disponibles as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $incidencia['asignado_a_id'] == $t['id'] ? 'selected' : '' ?>>
                                <?= e($t['nombre_completo']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="px-2 py-1.5 rounded-md bg-bacal-700 hover:bg-bacal-800 text-white text-xs font-semibold">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Personas -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-zinc-600 uppercase tracking-wide mb-3">Personas</h3>
                <div class="space-y-3">
                    <div>
                        <div class="text-[10px] text-zinc-500 mb-1">Reportó</div>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0"
                                 style="background-color: <?= color_avatar($incidencia['reportado_por_nombre']) ?>">
                                <?= e(iniciales($incidencia['reportado_por_nombre'])) ?>
                            </div>
                            <div class="text-sm font-medium text-zinc-700 truncate"><?= e($incidencia['reportado_por_nombre']) ?></div>
                        </div>
                        <?php if ($incidencia['reportante_nombre']): ?>
                        <div class="text-[11px] text-zinc-500 mt-1 ml-9">a nombre de: <?= e($incidencia['reportante_nombre']) ?><?= $incidencia['reportante_puesto'] ? ' (' . e($incidencia['reportante_puesto']) . ')' : '' ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="text-[10px] text-zinc-500 mb-1">Asignado a</div>
                        <?php if ($incidencia['asignado_a_nombre']): ?>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0"
                                 style="background-color: <?= color_avatar($incidencia['asignado_a_nombre']) ?>">
                                <?= e(iniciales($incidencia['asignado_a_nombre'])) ?>
                            </div>
                            <div class="text-sm font-medium text-zinc-700 truncate"><?= e($incidencia['asignado_a_nombre']) ?></div>
                        </div>
                        <?php else: ?>
                        <div class="text-xs text-zinc-400 italic flex items-center gap-1.5">
                            <i data-lucide="user-x" class="w-3.5 h-3.5"></i> Sin asignar
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($incidencia['resuelto_por_nombre']): ?>
                    <div>
                        <div class="text-[10px] text-zinc-500 mb-1">Resolvió</div>
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold flex-shrink-0"
                                 style="background-color: <?= color_avatar($incidencia['resuelto_por_nombre']) ?>">
                                <?= e(iniciales($incidencia['resuelto_por_nombre'])) ?>
                            </div>
                            <div class="text-sm font-medium text-zinc-700 truncate"><?= e($incidencia['resuelto_por_nombre']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Equipo (si aplica) -->
            <?php if ($incidencia['equipo_id']): ?>
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-zinc-600 uppercase tracking-wide mb-3">Equipo</h3>
                <div class="flex items-start gap-2.5">
                    <div class="w-9 h-9 rounded-lg bg-zinc-100 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="monitor" class="w-5 h-5 text-zinc-600"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm text-zinc-900 truncate"><?= e($incidencia['equipo_nombre']) ?></div>
                        <div class="text-[11px] text-zinc-500 mt-0.5">
                            <span class="font-mono"><?= e($incidencia['equipo_codigo']) ?></span>
                            <?php if ($incidencia['equipo_tipo']): ?>
                            · <?= e($incidencia['equipo_tipo']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($incidencia['equipo_marca'] || $incidencia['equipo_modelo']): ?>
                        <div class="text-[11px] text-zinc-500"><?= e(trim(($incidencia['equipo_marca'] ?? '') . ' ' . ($incidencia['equipo_modelo'] ?? ''))) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tiempos -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-zinc-600 uppercase tracking-wide mb-3">Tiempos</h3>
                <dl class="space-y-2 text-xs">
                    <div class="flex justify-between gap-2">
                        <dt class="text-zinc-500">Evento ocurrió</dt>
                        <dd class="text-zinc-900 font-medium text-right"><?= e(fmt_fecha($incidencia['fecha_evento'])) ?></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-zinc-500">Atención iniciada</dt>
                        <dd class="text-zinc-900 font-medium text-right"><?= $incidencia['fecha_atencion'] ? e(fmt_fecha($incidencia['fecha_atencion'])) : '—' ?></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-zinc-500">Resuelta</dt>
                        <dd class="text-zinc-900 font-medium text-right"><?= $incidencia['fecha_resolucion'] ? e(fmt_fecha($incidencia['fecha_resolucion'])) : '—' ?></dd>
                    </div>
                    <?php if ($incidencia['fecha_limite_sla']): ?>
                    <div class="flex justify-between gap-2">
                        <dt class="text-zinc-500">Límite SLA</dt>
                        <dd class="text-zinc-900 font-medium text-right"><?= e(fmt_fecha($incidencia['fecha_limite_sla'])) ?></dd>
                    </div>
                    <?php endif; ?>
                    <div class="border-t border-zinc-100 pt-2 mt-2"></div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-zinc-500">Tiempo de respuesta</dt>
                        <dd class="text-zinc-900 font-semibold text-right"><?= e(fmt_duracion($incidencia['tiempo_respuesta_min'])) ?></dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-zinc-500">Tiempo de resolución</dt>
                        <dd class="text-zinc-900 font-semibold text-right"><?= e(fmt_duracion($incidencia['tiempo_resolucion_min'])) ?></dd>
                    </div>
                </dl>
            </div>

            <!-- Reincidencia -->
            <?php if ($incidencia['es_reincidencia'] && $incidencia['incidencia_padre_id']): ?>
            <div class="bg-purple-50 rounded-xl border border-purple-200 p-5">
                <h3 class="text-xs font-bold text-purple-700 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Reincidencia de
                </h3>
                <a href="<?= url('incidencia_ver.php?id=' . $incidencia['incidencia_padre_id']) ?>"
                   class="block bg-white rounded-lg p-3 hover:shadow-sm transition-shadow">
                    <div class="font-mono text-[10px] font-bold text-purple-600 mb-1"><?= e($incidencia['incidencia_padre_folio']) ?></div>
                    <div class="text-sm font-semibold text-zinc-900 truncate"><?= e($incidencia['incidencia_padre_titulo']) ?></div>
                </a>
            </div>
            <?php endif; ?>

            <!-- Incidencias relacionadas -->
            <?php if (!empty($relacionadas)): ?>
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-zinc-600 uppercase tracking-wide mb-3">Relacionadas</h3>
                <div class="space-y-2">
                    <?php foreach (array_slice($relacionadas, 0, 5) as $r): ?>
                    <a href="<?= url('incidencia_ver.php?id=' . $r['id']) ?>"
                       class="block border border-zinc-100 rounded-lg p-2.5 hover:bg-zinc-50 transition-colors">
                        <div class="flex items-center gap-1.5 mb-0.5">
                            <span class="font-mono text-[10px] font-bold text-zinc-500"><?= e($r['folio']) ?></span>
                            <?= badge($r['estado_nombre'], $r['estado_color']) ?>
                        </div>
                        <div class="text-xs font-medium text-zinc-700 truncate"><?= e($r['titulo']) ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Historial -->
            <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-zinc-600 uppercase tracking-wide mb-3">Historial</h3>
                <?php if (empty($historial)): ?>
                <p class="text-xs text-zinc-400 italic">Sin actividad registrada.</p>
                <?php else: ?>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach ($historial as $h): ?>
                    <div class="flex gap-2 text-xs">
                        <div class="w-1.5 h-1.5 rounded-full bg-bacal-600 mt-1.5 flex-shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <div class="text-zinc-700"><?= e($h['descripcion'] ?? $h['accion']) ?></div>
                            <div class="text-[10px] text-zinc-400 mt-0.5">
                                <?= e($h['usuario_nombre']) ?> · <?= e(fmt_tiempo_relativo($h['creado_en'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/config/footer.php'; ?>