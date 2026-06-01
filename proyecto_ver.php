<?php
/**
 * ============================================================================
 * proyecto_ver.php - Ficha individual de un proyecto con tabs
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/proyectos_helpers.php';

requerir_login();
$u = usuario_actual();
$es_admin = tiene_permiso('administrar');

$id = (int) input('id', 0);
$proyecto = $id > 0 ? obtener_proyecto($id) : null;

if (!$proyecto) {
    flash_set('error', 'Proyecto no encontrado.');
    header('Location: ' . url('proyectos.php'));
    exit;
}

$puede_editar = puede_editar_proyecto($proyecto, $u);
$puede_eliminar = puede_eliminar_proyecto();
$puede_aprobar = puede_aprobar_proyecto($proyecto, $u);

$errores = [];

// ============================================================================
// Procesar POST
// ============================================================================
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token inválido.';
    } else {
        $op = (string) input('op', '');

        try {
            // -- Editar info principal --
            if ($op === 'actualizar' && $puede_editar) {
                $datos = [
                    'codigo' => trim((string) input('codigo', '')),
                    'nombre' => trim((string) input('nombre', '')),
                    'descripcion' => trim((string) input('descripcion', '')) ?: null,
                    'tipo' => trim((string) input('tipo', 'Otro')) ?: 'Otro',
                    'prioridad' => (string) input('prioridad', 'media'),
                    'sucursal_id' => (int) input('sucursal_id', 0) ?: null,
                    'area_id' => (int) input('area_id', 0) ?: null,
                    'lider_id' => (int) input('lider_id_form', 0) ?: null,
                    'fecha_inicio_plan' => trim((string) input('fecha_inicio_plan', '')) ?: null,
                    'fecha_fin_plan' => trim((string) input('fecha_fin_plan', '')) ?: null,
                    'fecha_inicio_real' => trim((string) input('fecha_inicio_real', '')) ?: null,
                    'fecha_fin_real' => trim((string) input('fecha_fin_real', '')) ?: null,
                    'avance' => (int) input('avance', 0),
                    'presupuesto' => trim((string) input('presupuesto', '')) ?: null,
                    'costo_real' => trim((string) input('costo_real', '')) ?: null,
                    'cliente_interno' => trim((string) input('cliente_interno', '')) ?: null,
                    'proveedor_externo' => trim((string) input('proveedor_externo', '')) ?: null,
                    'tecnologias' => trim((string) input('tecnologias', '')) ?: null,
                    'enlaces' => trim((string) input('enlaces', '')) ?: null,
                    'riesgos' => trim((string) input('riesgos', '')) ?: null,
                    'notas' => trim((string) input('notas', '')) ?: null,
                ];
                if ($datos['codigo'] === '' || $datos['nombre'] === '') {
                    $errores[] = 'Código y nombre son obligatorios.';
                } else {
                    actualizar_proyecto($id, $datos);
                    flash_set('success', 'Proyecto actualizado.');
                    header('Location: ' . url("proyecto_ver.php?id=$id"));
                    exit;
                }
            }

            // -- Cambiar estado --
            elseif ($op === 'cambiar_estado' && $puede_aprobar) {
                $nuevo = (string) input('nuevo_estado', '');
                $nota = trim((string) input('nota_estado', '')) ?: null;
                cambiar_estado_proyecto($id, $nuevo, (int) $u['id'], $nota);
                flash_set('success', 'Estado del proyecto actualizado.');
                header('Location: ' . url("proyecto_ver.php?id=$id&tab=comentarios"));
                exit;
            }

            // -- Agregar comentario --
            elseif ($op === 'agregar_comentario' && $puede_editar) {
                $contenido = trim((string) input('contenido', ''));
                if ($contenido === '') {
                    $errores[] = 'El comentario no puede estar vacío.';
                } else {
                    $cid = agregar_comentario_proyecto($id, (int) $u['id'], $contenido);

                    // Si vienen adjuntos, asociarlos al comentario
                    if (!empty($_FILES['adjuntos']['name'][0])) {
                        for ($i = 0; $i < count($_FILES['adjuntos']['name']); $i++) {
                            if ($_FILES['adjuntos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                            $archivo = [
                                'name' => $_FILES['adjuntos']['name'][$i],
                                'type' => $_FILES['adjuntos']['type'][$i],
                                'tmp_name' => $_FILES['adjuntos']['tmp_name'][$i],
                                'error' => $_FILES['adjuntos']['error'][$i],
                                'size' => $_FILES['adjuntos']['size'][$i],
                            ];
                            try {
                                guardar_adjunto_proyecto($id, $cid, $archivo, (int) $u['id']);
                            } catch (Throwable $e) {
                                $errores[] = 'Adjunto ' . $archivo['name'] . ': ' . $e->getMessage();
                            }
                        }
                    }

                    flash_set('success', 'Comentario agregado.');
                    header('Location: ' . url("proyecto_ver.php?id=$id&tab=comentarios"));
                    exit;
                }
            }

            // -- Editar comentario --
            elseif ($op === 'editar_comentario') {
                $cid = (int) input('comentario_id', 0);
                $com = db_one("SELECT * FROM proyecto_comentarios WHERE id = :id", ['id' => $cid]);
                if (!$com) throw new RuntimeException('Comentario no encontrado.');
                if (!$es_admin && (int) $com['usuario_id'] !== (int) $u['id']) {
                    throw new RuntimeException('Solo puedes editar tus propios comentarios.');
                }
                $nuevo = trim((string) input('contenido_edit', ''));
                if ($nuevo === '') throw new RuntimeException('El contenido no puede quedar vacío.');
                editar_comentario_proyecto($cid, $nuevo);
                flash_set('success', 'Comentario actualizado.');
                header('Location: ' . url("proyecto_ver.php?id=$id&tab=comentarios"));
                exit;
            }

            // -- Eliminar comentario --
            elseif ($op === 'eliminar_comentario') {
                $cid = (int) input('comentario_id', 0);
                $com = db_one("SELECT * FROM proyecto_comentarios WHERE id = :id", ['id' => $cid]);
                if (!$com) throw new RuntimeException('Comentario no encontrado.');
                if (!$es_admin && (int) $com['usuario_id'] !== (int) $u['id']) {
                    throw new RuntimeException('Solo puedes eliminar tus propios comentarios.');
                }
                eliminar_comentario_proyecto($cid);
                flash_set('success', 'Comentario eliminado.');
                header('Location: ' . url("proyecto_ver.php?id=$id&tab=comentarios"));
                exit;
            }

            // -- Crear tarea --
            elseif ($op === 'crear_tarea' && $puede_editar) {
                $datos_t = [
                    'titulo' => trim((string) input('titulo_tarea', '')),
                    'descripcion' => trim((string) input('descripcion_tarea', '')) ?: null,
                    'es_hito' => (int) input('es_hito', 0),
                    'asignada_a_id' => (int) input('asignada_a_id', 0) ?: null,
                    'fecha_inicio' => trim((string) input('fecha_inicio_tarea', '')) ?: null,
                    'fecha_fin_plan' => trim((string) input('fecha_fin_tarea', '')) ?: null,
                ];
                if ($datos_t['titulo'] === '') {
                    $errores[] = 'El título de la tarea es obligatorio.';
                } else {
                    crear_tarea_proyecto($id, $datos_t, (int) $u['id']);
                    flash_set('success', 'Tarea agregada.');
                    header('Location: ' . url("proyecto_ver.php?id=$id&tab=tareas"));
                    exit;
                }
            }

            // -- Cambiar estado de tarea --
            elseif ($op === 'cambiar_estado_tarea' && $puede_editar) {
                $tid = (int) input('tarea_id', 0);
                $nuevo = (string) input('nuevo_estado_tarea', '');
                cambiar_estado_tarea($tid, $nuevo);
                flash_set('success', 'Estado de tarea actualizado.');
                header('Location: ' . url("proyecto_ver.php?id=$id&tab=tareas"));
                exit;
            }

            // -- Eliminar tarea --
            elseif ($op === 'eliminar_tarea' && ($puede_editar || $es_admin)) {
                $tid = (int) input('tarea_id', 0);
                eliminar_tarea_proyecto($tid);
                flash_set('success', 'Tarea eliminada.');
                header('Location: ' . url("proyecto_ver.php?id=$id&tab=tareas"));
                exit;
            }

            // -- Agregar participante --
            elseif ($op === 'agregar_participante' && $puede_editar) {
                $uid = (int) input('participante_id', 0);
                $rol_p = trim((string) input('rol_participante', '')) ?: null;
                if ($uid > 0) {
                    agregar_participante($id, $uid, $rol_p, (int) $u['id']);
                    flash_set('success', 'Participante agregado.');
                    header('Location: ' . url("proyecto_ver.php?id=$id&tab=participantes"));
                    exit;
                }
            }

            // -- Quitar participante --
            elseif ($op === 'quitar_participante' && $puede_editar) {
                $uid = (int) input('participante_id', 0);
                quitar_participante($id, $uid);
                flash_set('success', 'Participante removido.');
                header('Location: ' . url("proyecto_ver.php?id=$id&tab=participantes"));
                exit;
            }

            // -- Subir adjunto al proyecto --
            elseif ($op === 'subir_adjunto' && $puede_editar) {
                if (empty($_FILES['archivo']['name'])) {
                    throw new RuntimeException('No se seleccionó archivo.');
                }
                $desc = trim((string) input('descripcion_adjunto', '')) ?: null;
                guardar_adjunto_proyecto($id, null, $_FILES['archivo'], (int) $u['id'], $desc);
                flash_set('success', 'Archivo subido.');
                header('Location: ' . url("proyecto_ver.php?id=$id&tab=adjuntos"));
                exit;
            }

            // -- Eliminar adjunto --
            elseif ($op === 'eliminar_adjunto' && ($puede_editar || $es_admin)) {
                $aid = (int) input('adjunto_id', 0);
                eliminar_adjunto_proyecto($aid);
                flash_set('success', 'Archivo eliminado.');
                $tab_ret = (string) input('tab_ret', 'adjuntos');
                header('Location: ' . url("proyecto_ver.php?id=$id&tab=$tab_ret"));
                exit;
            }

            // -- Eliminar proyecto --
            elseif ($op === 'eliminar_proyecto' && $puede_eliminar) {
                eliminar_proyecto($id);
                registrar_auditoria('eliminar_proyecto', 'proyectos', $id, $proyecto['nombre']);
                flash_set('success', 'Proyecto eliminado.');
                header('Location: ' . url('proyectos.php'));
                exit;
            }
        } catch (Throwable $e) {
            $errores[] = 'Error: ' . $e->getMessage();
        }
    }
}

// ============================================================================
// Datos para render
// ============================================================================
$tab = (string) input('tab', 'info');
$participantes = listar_participantes($id);
$tareas = listar_tareas_proyecto($id);
$comentarios = listar_comentarios_proyecto($id);
$adjuntos_proyecto = listar_adjuntos_proyecto($id, null);

// Mapeo de adjuntos por comentario
$adjuntos_por_comentario = [];
foreach ($comentarios as $c) {
    if ((int) $c['num_adjuntos'] > 0) {
        $adjuntos_por_comentario[(int) $c['id']] = listar_adjuntos_proyecto($id, (int) $c['id']);
    }
}

$est_cfg = etiqueta_estado_proyecto($proyecto['estado']);
$pri_cfg = etiqueta_prioridad_proyecto($proyecto['prioridad']);

// Stats tareas
$tareas_total = count($tareas);
$tareas_completadas = count(array_filter($tareas, fn($t) => $t['estado'] === 'completada'));
$tareas_en_progreso = count(array_filter($tareas, fn($t) => $t['estado'] === 'en_progreso'));
$tareas_bloqueadas = count(array_filter($tareas, fn($t) => $t['estado'] === 'bloqueada'));

$sucursales = db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo=1 ORDER BY nombre");
$areas = db_all("SELECT id, nombre FROM areas WHERE activo=1 ORDER BY nombre");
$usuarios_lista = db_all("SELECT id, nombre_completo, usuario FROM usuarios WHERE activo=1 ORDER BY nombre_completo");
$tipos_lista = array_values(array_unique(array_merge(PROYECTO_TIPOS_SUGERIDOS, tipos_proyecto_usados())));
sort($tipos_lista);

$titulo_pagina = $proyecto['nombre'];
$pagina_activa = 'proyectos';
require_once __DIR__ . '/config/header.php';
?>

<div class="max-w-6xl mx-auto animate-fade-in space-y-4">

    <!-- Header -->
    <div class="flex items-start gap-3 flex-wrap">
        <a href="<?= url('proyectos.php') ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500 mt-1">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 text-xs text-zinc-500 mb-0.5 flex-wrap">
                <span class="font-mono font-bold"><?= e($proyecto['codigo']) ?></span>
                <span>·</span>
                <span><?= e($proyecto['tipo']) ?></span>
                <?php if (!empty($proyecto['sucursal_codigo'])): ?>
                <span>·</span><span><?= e($proyecto['sucursal_codigo']) ?></span>
                <?php endif; ?>
            </div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900"><?= e($proyecto['nombre']) ?></h2>
        </div>

        <!-- Badges -->
        <div class="flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-lg uppercase tracking-wider"
                  style="color: <?= e($est_cfg['color']) ?>; background-color: <?= e($est_cfg['color']) ?>15">
                <i data-lucide="<?= e($est_cfg['icono']) ?>" class="w-4 h-4"></i>
                <?= e($est_cfg['label']) ?>
            </span>
            <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-1 rounded uppercase"
                  style="color: <?= e($pri_cfg['color']) ?>; background-color: <?= e($pri_cfg['color']) ?>15">
                <?= e($pri_cfg['label']) ?>
            </span>
        </div>
    </div>

    <!-- Acciones de estado -->
    <?php if ($puede_aprobar): ?>
    <div class="flex flex-wrap gap-2">
        <?php
        // Transiciones permitidas por estado actual
        $transiciones = match ($proyecto['estado']) {
            'propuesto' => ['aprobado' => '✓ Aprobar', 'cancelado' => '✗ Rechazar'],
            'aprobado'  => ['en_curso' => '▶ Iniciar', 'cancelado' => '✗ Cancelar'],
            'en_curso'  => ['pausado' => '⏸ Pausar', 'completado' => '✓ Completar', 'cancelado' => '✗ Cancelar'],
            'pausado'   => ['en_curso' => '▶ Reanudar', 'cancelado' => '✗ Cancelar'],
            'completado'=> ['en_curso' => '↩ Reabrir'],
            'cancelado' => ['propuesto' => '↩ Reactivar'],
            default => [],
        };
        foreach ($transiciones as $estado_destino => $label):
            $cfg_dest = etiqueta_estado_proyecto($estado_destino);
        ?>
        <button onclick="document.getElementById('modal_estado').showModal(); document.getElementById('campo_nuevo_estado').value='<?= e($estado_destino) ?>'; document.getElementById('label_nuevo_estado').textContent='<?= e($cfg_dest['label']) ?>';"
                class="px-3 py-1.5 rounded-lg border-2 text-xs font-bold flex items-center gap-1.5"
                style="border-color: <?= e($cfg_dest['color']) ?>; color: <?= e($cfg_dest['color']) ?>;">
            <?= e($label) ?>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Errores -->
    <?php if (!empty($errores)): ?>
    <div class="px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <ul class="list-disc list-inside text-xs">
            <?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white rounded-xl border border-zinc-200 p-3">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold">Avance</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= (int) $proyecto['avance'] ?>%</div>
            <div class="w-full bg-zinc-100 rounded-full h-1 mt-1 overflow-hidden">
                <div class="h-full rounded-full" style="width: <?= (int) $proyecto['avance'] ?>%; background-color: <?= e($est_cfg['color']) ?>"></div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-3">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold">Participantes</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= count($participantes) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-3">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold">Tareas</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900">
                <?= $tareas_completadas ?><span class="text-zinc-400 text-base">/<?= $tareas_total ?></span>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-3">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold">Comentarios</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= count($comentarios) ?></div>
        </div>
        <div class="bg-white rounded-xl border border-zinc-200 p-3">
            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold">Adjuntos</div>
            <div class="font-display text-2xl font-extrabold text-zinc-900"><?= count($adjuntos_proyecto) ?></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <div class="flex border-b border-zinc-200 overflow-x-auto">
            <?php
            $tabs = [
                'info' => ['label' => 'Información', 'icono' => 'info', 'badge' => null],
                'tareas' => ['label' => 'Tareas y hitos', 'icono' => 'list-checks', 'badge' => $tareas_total > 0 ? $tareas_total : null],
                'comentarios' => ['label' => 'Bitácora', 'icono' => 'message-square', 'badge' => count($comentarios) > 0 ? count($comentarios) : null],
                'adjuntos' => ['label' => 'Adjuntos', 'icono' => 'paperclip', 'badge' => count($adjuntos_proyecto) > 0 ? count($adjuntos_proyecto) : null],
                'participantes' => ['label' => 'Equipo', 'icono' => 'users', 'badge' => count($participantes) > 0 ? count($participantes) : null],
            ];
            foreach ($tabs as $tab_key => $tab_cfg):
                $activo = $tab === $tab_key;
            ?>
            <a href="<?= url("proyecto_ver.php?id=$id&tab=$tab_key") ?>"
               class="px-4 py-3 text-sm font-semibold border-b-2 flex items-center gap-1.5 whitespace-nowrap transition-colors
                      <?= $activo ? 'border-bacal-700 text-bacal-700 bg-bacal-50' : 'border-transparent text-zinc-500 hover:text-zinc-900 hover:bg-zinc-50' ?>">
                <i data-lucide="<?= e($tab_cfg['icono']) ?>" class="w-4 h-4"></i>
                <?= e($tab_cfg['label']) ?>
                <?php if ($tab_cfg['badge'] !== null): ?>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full <?= $activo ? 'bg-bacal-700 text-white' : 'bg-zinc-200 text-zinc-700' ?>">
                    <?= e((string) $tab_cfg['badge']) ?>
                </span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="p-5">
        <!-- ====================================================================
             TAB: INFORMACIÓN
             ==================================================================== -->
        <?php if ($tab === 'info'): ?>

        <?php if ($puede_editar): ?>
        <form method="POST" class="space-y-4">
            <?= csrf_input() ?>
            <input type="hidden" name="op" value="actualizar">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Código *</label>
                    <input type="text" name="codigo" required value="<?= e($proyecto['codigo']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm font-mono">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Nombre *</label>
                    <input type="text" name="nombre" required value="<?= e($proyecto['nombre']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Descripción / Objetivo</label>
                <textarea name="descripcion" rows="3"
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm"><?= e($proyecto['descripcion'] ?? '') ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Tipo</label>
                    <input type="text" name="tipo" list="tipos_proyecto" maxlength="80"
                           value="<?= e($proyecto['tipo']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm">
                    <datalist id="tipos_proyecto">
                        <?php foreach ($tipos_lista as $tp): ?>
                        <option value="<?= e($tp) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Prioridad</label>
                    <select name="prioridad" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm">
                        <?php foreach (PROYECTO_PRIORIDADES as $key => $cfg): ?>
                        <option value="<?= e($key) ?>" <?= $proyecto['prioridad'] === $key ? 'selected' : '' ?>><?= e($cfg['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Líder</label>
                    <select name="lider_id_form" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($usuarios_lista as $usr): ?>
                        <option value="<?= $usr['id'] ?>" <?= (int) $proyecto['lider_id'] === (int) $usr['id'] ? 'selected' : '' ?>><?= e($usr['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Sucursal</label>
                    <select name="sucursal_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm">
                        <option value="">— Todas las sucursales —</option>
                        <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (int) $proyecto['sucursal_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Área</label>
                    <select name="area_id" class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm">
                        <option value="">— Sin área —</option>
                        <?php foreach ($areas as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= (int) $proyecto['area_id'] === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Inicio planeado</label>
                    <input type="date" name="fecha_inicio_plan" value="<?= e($proyecto['fecha_inicio_plan'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Fin planeado</label>
                    <input type="date" name="fecha_fin_plan" value="<?= e($proyecto['fecha_fin_plan'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Inicio real</label>
                    <input type="date" name="fecha_inicio_real" value="<?= e($proyecto['fecha_inicio_real'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Fin real</label>
                    <input type="date" name="fecha_fin_real" value="<?= e($proyecto['fecha_fin_real'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Avance (%)</label>
                    <input type="number" name="avance" min="0" max="100" value="<?= (int) $proyecto['avance'] ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Presupuesto ($)</label>
                    <input type="number" name="presupuesto" min="0" step="0.01" value="<?= e($proyecto['presupuesto'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Costo real ($)</label>
                    <input type="number" name="costo_real" min="0" step="0.01" value="<?= e($proyecto['costo_real'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Cliente interno</label>
                    <input type="text" name="cliente_interno" maxlength="150" value="<?= e($proyecto['cliente_interno'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Proveedor externo</label>
                    <input type="text" name="proveedor_externo" maxlength="150" value="<?= e($proyecto['proveedor_externo'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Tecnologías / stack</label>
                <input type="text" name="tecnologias" maxlength="255" value="<?= e($proyecto['tecnologias'] ?? '') ?>"
                       class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Enlaces</label>
                <textarea name="enlaces" rows="2" placeholder="URLs relacionadas (una por línea)"
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm"><?= e($proyecto['enlaces'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Riesgos</label>
                <textarea name="riesgos" rows="2"
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm"><?= e($proyecto['riesgos'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Notas</label>
                <textarea name="notas" rows="2"
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm"><?= e($proyecto['notas'] ?? '') ?></textarea>
            </div>

            <div class="flex justify-end pt-3 border-t border-zinc-100">
                <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                    Guardar cambios
                </button>
            </div>
        </form>
        <?php else: ?>
        <!-- Vista solo lectura para no editores -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div>
                <div class="text-[10px] font-bold text-zinc-500 uppercase">Descripción</div>
                <div class="text-zinc-900 whitespace-pre-wrap"><?= e($proyecto['descripcion'] ?? '—') ?></div>
            </div>
            <div>
                <div class="text-[10px] font-bold text-zinc-500 uppercase">Tipo</div>
                <div class="text-zinc-900"><?= e($proyecto['tipo']) ?></div>
            </div>
            <div>
                <div class="text-[10px] font-bold text-zinc-500 uppercase">Líder</div>
                <div class="text-zinc-900"><?= e($proyecto['lider_nombre'] ?? '—') ?></div>
            </div>
            <div>
                <div class="text-[10px] font-bold text-zinc-500 uppercase">Avance</div>
                <div class="text-zinc-900"><?= (int) $proyecto['avance'] ?>%</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Metadata + eliminar -->
        <div class="mt-6 pt-4 border-t border-zinc-100 flex items-center justify-between flex-wrap gap-3">
            <div class="text-xs text-zinc-500 space-y-0.5">
                <?php if (!empty($proyecto['sugerido_por_nombre'])): ?>
                <div>Sugerido por <strong><?= e($proyecto['sugerido_por_nombre']) ?></strong> · <?= e(fmt_tiempo_relativo($proyecto['creado_en'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($proyecto['aprobado_por_nombre'])): ?>
                <div>Aprobado por <strong><?= e($proyecto['aprobado_por_nombre']) ?></strong> · <?= e(fmt_tiempo_relativo($proyecto['aprobado_en'])) ?></div>
                <?php endif; ?>
                <div>Actualizado · <?= e(fmt_tiempo_relativo($proyecto['actualizado_en'])) ?></div>
            </div>
            <?php if ($puede_eliminar): ?>
            <form method="POST" onsubmit="return confirm('¿Eliminar este proyecto? Esta acción solo la pueden hacer administradores.');">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="eliminar_proyecto">
                <button type="submit" class="text-xs text-zinc-500 hover:text-bacal-700 inline-flex items-center gap-1">
                    <i data-lucide="trash-2" class="w-3 h-3"></i> Eliminar proyecto
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- ====================================================================
             TAB: TAREAS Y HITOS
             ==================================================================== -->
        <?php elseif ($tab === 'tareas'): ?>

        <?php if ($puede_editar): ?>
        <div class="bg-zinc-50 rounded-xl p-4 mb-4">
            <h3 class="font-display text-sm font-bold text-zinc-900 mb-3 flex items-center gap-1.5">
                <i data-lucide="plus-circle" class="w-4 h-4 text-bacal-700"></i> Nueva tarea / hito
            </h3>
            <form method="POST" class="space-y-3">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="crear_tarea">

                <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                    <input type="text" name="titulo_tarea" required maxlength="200"
                           placeholder="Título de la tarea..."
                           class="md:col-span-2 px-3 py-2 rounded-lg border border-zinc-300 text-sm">
                    <select name="asignada_a_id" class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($usuarios_lista as $usr): ?>
                        <option value="<?= $usr['id'] ?>"><?= e($usr['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="inline-flex items-center gap-2 text-xs">
                        <input type="checkbox" name="es_hito" value="1" class="w-4 h-4 text-bacal-700">
                        <span class="font-semibold">⭐ Es hito</span>
                    </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                    <input type="text" name="descripcion_tarea" placeholder="Descripción opcional..."
                           class="md:col-span-1 px-3 py-2 rounded-lg border border-zinc-300 text-sm">
                    <input type="date" name="fecha_inicio_tarea"
                           class="px-3 py-2 rounded-lg border border-zinc-300 text-sm"
                           title="Fecha inicio">
                    <input type="date" name="fecha_fin_tarea"
                           class="px-3 py-2 rounded-lg border border-zinc-300 text-sm"
                           title="Fecha fin planeado">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                        Agregar
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if (empty($tareas)): ?>
        <div class="text-center py-10">
            <i data-lucide="list-checks" class="w-12 h-12 text-zinc-300 mx-auto mb-2"></i>
            <p class="text-sm text-zinc-500">Sin tareas registradas aún.</p>
        </div>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($tareas as $t):
                $est_t = etiqueta_estado_tarea($t['estado']);
                $vencida = !empty($t['fecha_fin_plan']) &&
                          strtotime($t['fecha_fin_plan']) < time() &&
                          !in_array($t['estado'], ['completada','cancelada'], true);
            ?>
            <div class="bg-white rounded-lg border <?= $vencida ? 'border-bacal-300 bg-bacal-50' : 'border-zinc-200' ?> p-3 flex items-start gap-3">

                <!-- Selector estado -->
                <?php if ($puede_editar): ?>
                <form method="POST" class="flex-shrink-0">
                    <?= csrf_input() ?>
                    <input type="hidden" name="op" value="cambiar_estado_tarea">
                    <input type="hidden" name="tarea_id" value="<?= $t['id'] ?>">
                    <select name="nuevo_estado_tarea" onchange="this.form.submit()"
                            class="text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded border bg-white"
                            style="color: <?= e($est_t['color']) ?>; border-color: <?= e($est_t['color']) ?>;">
                        <?php foreach (PROYECTO_TAREA_ESTADOS as $key => $cfg): ?>
                        <option value="<?= e($key) ?>" <?= $t['estado'] === $key ? 'selected' : '' ?>><?= e($cfg['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php else: ?>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded"
                      style="color: <?= e($est_t['color']) ?>; background-color: <?= e($est_t['color']) ?>15;">
                    <?= e($est_t['label']) ?>
                </span>
                <?php endif; ?>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <?php if ((int) $t['es_hito'] === 1): ?>
                        <span class="text-xs">⭐</span>
                        <?php endif; ?>
                        <span class="font-semibold text-sm text-zinc-900 <?= $t['estado'] === 'completada' ? 'line-through opacity-60' : '' ?>">
                            <?= e($t['titulo']) ?>
                        </span>
                    </div>
                    <?php if (!empty($t['descripcion'])): ?>
                    <div class="text-xs text-zinc-600 mt-0.5"><?= e($t['descripcion']) ?></div>
                    <?php endif; ?>
                    <div class="flex items-center gap-3 text-[10px] text-zinc-500 mt-1">
                        <?php if (!empty($t['asignada_a_nombre'])): ?>
                        <span class="flex items-center gap-1">
                            <i data-lucide="user" class="w-3 h-3"></i> <?= e($t['asignada_a_nombre']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($t['fecha_fin_plan'])): ?>
                        <span class="flex items-center gap-1 <?= $vencida ? 'text-bacal-700 font-bold' : '' ?>">
                            <i data-lucide="calendar" class="w-3 h-3"></i>
                            <?= e(date('d/M/Y', strtotime($t['fecha_fin_plan']))) ?>
                            <?php if ($vencida): ?>· VENCIDA<?php endif; ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($t['fecha_completada'])): ?>
                        <span class="flex items-center gap-1 text-emerald-700">
                            <i data-lucide="check" class="w-3 h-3"></i>
                            Completada <?= e(fmt_tiempo_relativo($t['fecha_completada'])) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($puede_editar || $es_admin): ?>
                <form method="POST" onsubmit="return confirm('¿Eliminar esta tarea?');" class="flex-shrink-0">
                    <?= csrf_input() ?>
                    <input type="hidden" name="op" value="eliminar_tarea">
                    <input type="hidden" name="tarea_id" value="<?= $t['id'] ?>">
                    <button type="submit" class="p-1.5 rounded text-zinc-400 hover:text-bacal-700 hover:bg-zinc-100">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ====================================================================
             TAB: BITÁCORA / COMENTARIOS
             ==================================================================== -->
        <?php elseif ($tab === 'comentarios'): ?>

        <?php if ($puede_editar): ?>
        <div class="bg-zinc-50 rounded-xl p-4 mb-4">
            <form method="POST" enctype="multipart/form-data" class="space-y-2">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="agregar_comentario">

                <textarea name="contenido" rows="3" required
                          placeholder="Escribe una actualización del proyecto, decisión tomada, problema encontrado, etc."
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></textarea>

                <div class="flex items-center justify-between flex-wrap gap-2">
                    <label class="cursor-pointer inline-flex items-center gap-1.5 text-xs text-bacal-700 hover:underline">
                        <i data-lucide="paperclip" class="w-3.5 h-3.5"></i>
                        Adjuntar archivos
                        <input type="file" name="adjuntos[]" multiple class="hidden"
                               onchange="document.getElementById('archivos_count').textContent = this.files.length + ' archivo(s) seleccionado(s)'">
                    </label>
                    <span id="archivos_count" class="text-[10px] text-zinc-500"></span>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                        Publicar
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <?php if (empty($comentarios)): ?>
        <div class="text-center py-10">
            <i data-lucide="message-square" class="w-12 h-12 text-zinc-300 mx-auto mb-2"></i>
            <p class="text-sm text-zinc-500">Sin comentarios aún.</p>
        </div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($comentarios as $c):
                $es_propio = (int) $c['usuario_id'] === (int) $u['id'];
                $tipo_cfg = match ($c['tipo']) {
                    'cambio_estado' => ['icono' => 'arrow-right-circle', 'color' => '#0EA5E9', 'bg' => 'bg-blue-50 border-blue-200'],
                    'hito' => ['icono' => 'flag', 'color' => '#F59E0B', 'bg' => 'bg-amber-50 border-amber-200'],
                    'nota_admin' => ['icono' => 'shield', 'color' => '#DC2626', 'bg' => 'bg-bacal-50 border-bacal-200'],
                    default => ['icono' => 'message-square', 'color' => '#71717A', 'bg' => 'bg-white border-zinc-200'],
                };
                $adjs_com = $adjuntos_por_comentario[(int) $c['id']] ?? [];
            ?>
            <div class="rounded-lg border <?= $tipo_cfg['bg'] ?> p-4">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                         style="background-color: <?= e($tipo_cfg['color']) ?>15">
                        <i data-lucide="<?= e($tipo_cfg['icono']) ?>" class="w-4 h-4" style="color: <?= e($tipo_cfg['color']) ?>"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 flex-wrap mb-1">
                            <div class="text-xs">
                                <strong class="text-zinc-900"><?= e($c['nombre_completo']) ?></strong>
                                <span class="text-zinc-500">· <?= e(fmt_tiempo_relativo($c['creado_en'])) ?></span>
                                <?php if (!empty($c['editado_en'])): ?>
                                <span class="text-zinc-400 italic">(editado)</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($es_propio || $es_admin): ?>
                            <div class="flex items-center gap-1">
                                <button onclick="document.getElementById('edit_com_<?= $c['id'] ?>').classList.toggle('hidden')"
                                        class="text-[10px] text-zinc-500 hover:text-bacal-700">Editar</button>
                                <form method="POST" onsubmit="return confirm('¿Eliminar este comentario?');" class="inline">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="op" value="eliminar_comentario">
                                    <input type="hidden" name="comentario_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="text-[10px] text-zinc-500 hover:text-bacal-700">· Eliminar</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="text-sm text-zinc-800 whitespace-pre-wrap"><?= e($c['contenido']) ?></div>

                        <!-- Form de edición oculto -->
                        <?php if ($es_propio || $es_admin): ?>
                        <form method="POST" id="edit_com_<?= $c['id'] ?>" class="hidden mt-2 space-y-2">
                            <?= csrf_input() ?>
                            <input type="hidden" name="op" value="editar_comentario">
                            <input type="hidden" name="comentario_id" value="<?= $c['id'] ?>">
                            <textarea name="contenido_edit" rows="2" required
                                      class="w-full px-3 py-2 rounded border border-zinc-300 text-sm"><?= e($c['contenido']) ?></textarea>
                            <div class="flex justify-end gap-2">
                                <button type="button" onclick="document.getElementById('edit_com_<?= $c['id'] ?>').classList.add('hidden')"
                                        class="px-3 py-1 rounded text-xs border border-zinc-300">Cancelar</button>
                                <button type="submit" class="px-3 py-1 rounded bg-bacal-700 text-white text-xs font-semibold">Guardar</button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <!-- Adjuntos del comentario -->
                        <?php if (!empty($adjs_com)): ?>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <?php foreach ($adjs_com as $adj): ?>
                            <a href="<?= url('uploads/proyectos/' . $adj['nombre_archivo']) ?>" target="_blank"
                               class="inline-flex items-center gap-1.5 text-xs text-bacal-700 hover:underline bg-white border border-zinc-200 rounded px-2 py-1">
                                <i data-lucide="paperclip" class="w-3 h-3"></i>
                                <?= e($adj['nombre_original']) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ====================================================================
             TAB: ADJUNTOS
             ==================================================================== -->
        <?php elseif ($tab === 'adjuntos'): ?>

        <?php if ($puede_editar): ?>
        <div class="bg-zinc-50 rounded-xl p-4 mb-4">
            <h3 class="font-display text-sm font-bold text-zinc-900 mb-3 flex items-center gap-1.5">
                <i data-lucide="upload" class="w-4 h-4 text-bacal-700"></i> Subir archivo al proyecto
            </h3>
            <form method="POST" enctype="multipart/form-data" class="flex flex-wrap gap-2 items-center">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="subir_adjunto">
                <input type="file" name="archivo" required class="text-sm flex-1 min-w-[200px]">
                <input type="text" name="descripcion_adjunto" maxlength="255"
                       placeholder="Descripción opcional..."
                       class="px-3 py-2 rounded-lg border border-zinc-300 text-sm flex-1 min-w-[200px]">
                <button type="submit" class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                    Subir
                </button>
            </form>
            <p class="text-[10px] text-zinc-500 mt-2">Máximo 20 MB. Tipos bloqueados: php, sh, exe, bat, etc.</p>
        </div>
        <?php endif; ?>

        <?php if (empty($adjuntos_proyecto)): ?>
        <div class="text-center py-10">
            <i data-lucide="paperclip" class="w-12 h-12 text-zinc-300 mx-auto mb-2"></i>
            <p class="text-sm text-zinc-500">Sin archivos adjuntos al proyecto.</p>
            <p class="text-xs text-zinc-400 mt-1">Los archivos también se pueden adjuntar a comentarios individuales.</p>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($adjuntos_proyecto as $a):
                $ext = strtolower(pathinfo($a['nombre_original'], PATHINFO_EXTENSION));
                $icono = match (true) {
                    in_array($ext, ['jpg','jpeg','png','gif','webp'], true) => 'image',
                    in_array($ext, ['pdf'], true) => 'file-text',
                    in_array($ext, ['doc','docx','odt'], true) => 'file-text',
                    in_array($ext, ['xls','xlsx','csv'], true) => 'file-spreadsheet',
                    in_array($ext, ['ppt','pptx'], true) => 'presentation',
                    in_array($ext, ['zip','rar','7z'], true) => 'archive',
                    default => 'file',
                };
                $tam_kb = round((int) $a['tamano_bytes'] / 1024, 1);
                $tam_str = $tam_kb > 1024 ? round($tam_kb / 1024, 1) . ' MB' : $tam_kb . ' KB';
            ?>
            <div class="bg-white rounded-lg border border-zinc-200 p-3 flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg bg-bacal-50 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="<?= e($icono) ?>" class="w-5 h-5 text-bacal-700"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <a href="<?= url('uploads/proyectos/' . $a['nombre_archivo']) ?>" target="_blank"
                       class="text-sm font-semibold text-zinc-900 hover:text-bacal-700 truncate block">
                        <?= e($a['nombre_original']) ?>
                    </a>
                    <?php if (!empty($a['descripcion'])): ?>
                    <div class="text-[10px] text-zinc-600 truncate"><?= e($a['descripcion']) ?></div>
                    <?php endif; ?>
                    <div class="text-[10px] text-zinc-500 mt-0.5">
                        <?= e($tam_str) ?> ·
                        <?= e($a['subido_por_nombre'] ?? 'desconocido') ?> ·
                        <?= e(fmt_tiempo_relativo($a['subido_en'])) ?>
                    </div>
                </div>
                <?php if ($puede_editar || $es_admin): ?>
                <form method="POST" onsubmit="return confirm('¿Eliminar este archivo?');" class="flex-shrink-0">
                    <?= csrf_input() ?>
                    <input type="hidden" name="op" value="eliminar_adjunto">
                    <input type="hidden" name="adjunto_id" value="<?= $a['id'] ?>">
                    <input type="hidden" name="tab_ret" value="adjuntos">
                    <button type="submit" class="p-1 rounded text-zinc-400 hover:text-bacal-700 hover:bg-zinc-100">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ====================================================================
             TAB: PARTICIPANTES
             ==================================================================== -->
        <?php elseif ($tab === 'participantes'): ?>

        <?php if ($puede_editar): ?>
        <div class="bg-zinc-50 rounded-xl p-4 mb-4">
            <h3 class="font-display text-sm font-bold text-zinc-900 mb-3 flex items-center gap-1.5">
                <i data-lucide="user-plus" class="w-4 h-4 text-bacal-700"></i> Agregar participante
            </h3>
            <form method="POST" class="flex flex-wrap gap-2 items-center">
                <?= csrf_input() ?>
                <input type="hidden" name="op" value="agregar_participante">
                <select name="participante_id" required class="px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm flex-1 min-w-[200px]">
                    <option value="">— Selecciona usuario —</option>
                    <?php
                    $ya_participantes = array_column($participantes, 'usuario_id');
                    foreach ($usuarios_lista as $usr):
                        if (in_array((int) $usr['id'], array_map('intval', $ya_participantes), true)) continue;
                    ?>
                    <option value="<?= $usr['id'] ?>"><?= e($usr['nombre_completo']) ?> (<?= e($usr['usuario']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="rol_participante" maxlength="80"
                       placeholder="Rol en el proyecto (opcional, ej. Desarrollador)"
                       class="px-3 py-2 rounded-lg border border-zinc-300 text-sm flex-1 min-w-[200px]">
                <button type="submit" class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                    Agregar
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Líder destacado -->
        <?php if (!empty($proyecto['lider_nombre'])): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-amber-500 flex items-center justify-center text-white">
                <i data-lucide="crown" class="w-5 h-5"></i>
            </div>
            <div class="flex-1">
                <div class="text-[10px] font-bold text-amber-700 uppercase tracking-wider">Líder del proyecto</div>
                <div class="font-semibold text-zinc-900"><?= e($proyecto['lider_nombre']) ?></div>
                <div class="text-xs text-zinc-600">@<?= e($proyecto['lider_usuario'] ?? '') ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($participantes)): ?>
        <div class="text-center py-10">
            <i data-lucide="users" class="w-12 h-12 text-zinc-300 mx-auto mb-2"></i>
            <p class="text-sm text-zinc-500">Sin participantes registrados además del líder.</p>
        </div>
        <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($participantes as $p): ?>
            <div class="bg-white rounded-lg border border-zinc-200 p-3 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-zinc-100 flex items-center justify-center text-zinc-600 font-bold">
                    <?= e(strtoupper(mb_substr($p['nombre_completo'], 0, 1))) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-sm text-zinc-900"><?= e($p['nombre_completo']) ?></div>
                    <div class="text-[10px] text-zinc-500">
                        @<?= e($p['usuario']) ?>
                        <?php if (!empty($p['rol_sistema'])): ?>· <?= e($p['rol_sistema']) ?><?php endif; ?>
                    </div>
                    <?php if (!empty($p['rol_en_proyecto'])): ?>
                    <div class="text-xs text-bacal-700 font-semibold mt-0.5">
                        <i data-lucide="briefcase" class="w-3 h-3 inline"></i> <?= e($p['rol_en_proyecto']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($puede_editar): ?>
                <form method="POST" onsubmit="return confirm('¿Quitar a este participante?');" class="flex-shrink-0">
                    <?= csrf_input() ?>
                    <input type="hidden" name="op" value="quitar_participante">
                    <input type="hidden" name="participante_id" value="<?= $p['usuario_id'] ?>">
                    <button type="submit" class="p-1.5 rounded text-zinc-400 hover:text-bacal-700 hover:bg-zinc-100">
                        <i data-lucide="user-minus" class="w-3.5 h-3.5"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: confirmar cambio de estado -->
<?php if ($puede_aprobar): ?>
<dialog id="modal_estado" class="rounded-xl shadow-2xl backdrop:bg-black/50 w-full max-w-md p-0">
    <form method="POST" class="bg-white">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="cambiar_estado">
        <input type="hidden" name="nuevo_estado" id="campo_nuevo_estado" value="">

        <div class="px-5 py-3 border-b border-zinc-200 flex items-center justify-between">
            <h3 class="font-display text-base font-bold text-zinc-900">
                Cambiar a <span id="label_nuevo_estado" class="text-bacal-700"></span>
            </h3>
            <button type="button" onclick="document.getElementById('modal_estado').close()" class="p-1 rounded hover:bg-zinc-100 text-zinc-500">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="p-5 space-y-3">
            <div>
                <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase">Nota / motivo (opcional)</label>
                <textarea name="nota_estado" rows="3"
                          placeholder="Razón del cambio, próximos pasos, etc."
                          class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"></textarea>
                <p class="text-[10px] text-zinc-500 mt-1">Se registrará automáticamente en la bitácora.</p>
            </div>
        </div>

        <div class="px-5 py-3 border-t border-zinc-200 flex justify-end gap-2 bg-zinc-50">
            <button type="button" onclick="document.getElementById('modal_estado').close()" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</button>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">Confirmar</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php require_once __DIR__ . '/config/footer.php'; ?>
