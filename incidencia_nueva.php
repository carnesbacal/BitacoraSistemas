<?php
/**
 * ============================================================================
 * incidencia_nueva.php - Crear nueva incidencia
 * ============================================================================
 * Formulario para registrar una incidencia con:
 *   - Selección de sucursal/área/equipo (equipos se filtran por sucursal)
 *   - Detección automática de reincidencias en tiempo real (Alpine + fetch)
 *   - Severidad con cálculo automático de SLA
 *   - Subida de múltiples adjuntos
 *   - Pre-rellenado opcional: ?duplicar_de=ID para crear basado en otra
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/incidencias_helpers.php';
require_once __DIR__ . '/config/notificaciones_helpers.php';
require_once __DIR__ . '/config/inteligencia_helpers.php';
require_once __DIR__ . '/config/incidencia_costos_helpers.php';

requerir_login();

$u = usuario_actual();
if (!tiene_permiso('crear_solicitud')) {
    http_response_code(403);
    die('No tienes permiso para crear incidencias.');
}

$titulo_pagina = 'Nueva incidencia';
$pagina_activa = 'nueva';

// ----------------------------------------------------------------------------
// Catálogos
// ----------------------------------------------------------------------------
$sucursales  = cat_sucursales();
$areas       = cat_areas();
$categorias  = cat_categorias_con_subs();
$tipos       = cat_tipos_trabajo();
$severidades = cat_severidades();
$origenes    = cat_origenes();
$tecnicos    = cat_tecnicos();

// Estado inicial
$estado_inicial = db_one("SELECT id FROM estados WHERE es_inicial=1 AND activo=1 LIMIT 1");
$estado_inicial_id = $estado_inicial ? (int) $estado_inicial['id'] : null;
$estado_completada = db_one("SELECT id FROM estados WHERE nombre LIKE 'Complet%' AND es_final=1 AND activo=1 ORDER BY orden LIMIT 1");
$estado_completada_id = $estado_completada ? (int) $estado_completada['id'] : null;
$proveedores = listar_proveedores_activos();

// ----------------------------------------------------------------------------
// Valores por defecto: ya sea de duplicar o vacíos
// ----------------------------------------------------------------------------
$default = [
    'titulo' => '', 'descripcion' => '',
    'sucursal_id' => $u['sucursal_id'] ?? '',
    'area_id' => $u['area_id'] ?? '',
    'categoria_id' => '', 'subcategoria_id' => '',
    'tipo_trabajo_id' => '', 'severidad_id' => '',
    'origen_reporte_id' => '', 'equipo_id' => '',
    'reportante_nombre' => '', 'reportante_puesto' => '',
    'asignado_a_id' => '',
    'es_reincidencia' => 0, 'incidencia_padre_id' => '',
    'fecha_evento' => date('Y-m-d\TH:i'),
    'causa_raiz' => '', 'solucion' => '', 'recomendaciones' => '',
    'proveedor_modo' => 'interno',
    'proveedor_escalado_id' => '',
    'proveedor_externo_info' => '',
    'prov_nuevo_nombre' => '', 'prov_nuevo_servicio' => '', 'prov_nuevo_telefono' => '',
    'costo_mano_obra' => '', 'costo_materiales_proveedor' => '', 'costo_notas' => '',
    'costo_materiales_comprados' => '',
    'horas_trabajadas' => '',
];

$duplicar_de = (int) input('duplicar_de', 0);
if ($duplicar_de > 0) {
    $orig = cargar_incidencia($duplicar_de);
    if ($orig && puede_ver_incidencia($orig)) {
        $default = array_merge($default, [
            'titulo' => $orig['titulo'],
            'descripcion' => $orig['descripcion'],
            'sucursal_id' => $orig['sucursal_id'],
            'area_id' => $orig['area_id'],
            'categoria_id' => $orig['categoria_id'],
            'tipo_trabajo_id' => $orig['tipo_trabajo_id'],
            'severidad_id' => $orig['severidad_id'],
            'equipo_id' => $orig['equipo_id'],
            'es_reincidencia' => 1,
            'incidencia_padre_id' => $orig['id'],
        ]);
    }
}

$errores = [];
$valores = $default;

// ----------------------------------------------------------------------------
// PROCESAR FORMULARIO (POST)
// ----------------------------------------------------------------------------
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token de seguridad inválido. Recarga la página.';
    } else {
        // Capturar valores
        foreach ($default as $k => $_v) {
            $valores[$k] = input($k, $default[$k]);
        }
        $valores['es_reincidencia'] = (int) (input('es_reincidencia', 0) ? 1 : 0);

        // Horas de trabajo del técnico: se capturan como horas + minutos
        $_h = max(0, (int) input('horas_h', 0));
        $_m = max(0, min(59, (int) input('horas_m', 0)));
        $valores['horas_trabajadas'] = ($_h > 0 || $_m > 0) ? round($_h + $_m / 60, 4) : '';

        // Validaciones obligatorias
        if (trim((string) $valores['titulo']) === '')
            $errores[] = 'El título es obligatorio.';
        if (trim((string) $valores['descripcion']) === '')
            $errores[] = 'La descripción es obligatoria.';
        if (!$valores['sucursal_id'])
            $errores[] = 'La sucursal es obligatoria.';
        if (!$valores['area_id'])
            $errores[] = 'El área es obligatoria.';
        if (!$valores['severidad_id'])
            $errores[] = 'La severidad es obligatoria.';
        if (!$valores['fecha_evento'])
            $errores[] = 'La fecha del evento es obligatoria.';

        // Si no es admin/ingeniero, forzar su sucursal
        if (!tiene_permiso('ver_todas_sucursales') && $u['sucursal_id']) {
            $valores['sucursal_id'] = (int) $u['sucursal_id'];
        }

        // Verificar que el equipo (si se eligió) pertenezca a la sucursal
        if ($valores['equipo_id']) {
            $eq = db_one("SELECT sucursal_id FROM equipos WHERE id=:id", ['id' => $valores['equipo_id']]);
            if (!$eq || (int) $eq['sucursal_id'] !== (int) $valores['sucursal_id']) {
                $errores[] = 'El equipo seleccionado no pertenece a la sucursal elegida.';
            }
        }

        if (empty($errores)) {
            try {
                db()->beginTransaction();

                // Generar folio
                $folio = generar_folio((int) $valores['sucursal_id']);

                // Calcular fecha límite SLA
                $sev = db_one("SELECT sla_horas FROM severidades WHERE id=:id", ['id' => $valores['severidad_id']]);
                $fecha_limite_sla = null;
                if ($sev && $sev['sla_horas']) {
                    $ts = strtotime($valores['fecha_evento']) + ((int) $sev['sla_horas']) * 3600;
                    $fecha_limite_sla = date('Y-m-d H:i:s', $ts);
                }

                // Si hay técnico asignado, registrar fecha_atencion = ahora
                $fecha_atencion = null;
                $regla_aplicada = null;

                // Si NO eligió técnico manualmente, evaluar reglas de auto-asignación
                if (!$valores['asignado_a_id']) {
                    $regla_aplicada = evaluar_reglas_asignacion(
                        (int) $valores['sucursal_id'],
                        $valores['area_id'] ? (int) $valores['area_id'] : null,
                        $valores['categoria_id'] ? (int) $valores['categoria_id'] : null,
                        $valores['tipo_trabajo_id'] ? (int) $valores['tipo_trabajo_id'] : null,
                        $valores['severidad_id'] ? (int) $valores['severidad_id'] : null
                    );
                    if ($regla_aplicada) {
                        $valores['asignado_a_id'] = $regla_aplicada['asignar_a_id'];
                    }
                }

                if ($valores['asignado_a_id']) {
                    $fecha_atencion = date('Y-m-d H:i:s');
                }

                // Auto-completar: si registró solución, marcar como Completada
                $tiene_solucion = trim((string) $valores['solucion']) !== '';
                $estado_a_usar = ($tiene_solucion && $estado_completada_id) ? $estado_completada_id : $estado_inicial_id;
                $fecha_resolucion = $tiene_solucion ? date('Y-m-d H:i:s') : null;
                $resuelto_por = $tiene_solucion ? (int) $u['id'] : null;
                if ($tiene_solucion && !$fecha_atencion) {
                    $fecha_atencion = date('Y-m-d H:i:s');
                }

                // Insertar
                db_exec(
                    "INSERT INTO incidencias
                     (folio, titulo, descripcion, sucursal_id, area_id, categoria_id, subcategoria_id,
                      tipo_trabajo_id, severidad_id, estado_id, origen_reporte_id, equipo_id,
                      reportado_por_id, reportante_nombre, reportante_puesto, asignado_a_id,
                      es_reincidencia, incidencia_padre_id,
                      fecha_evento, fecha_atencion, fecha_limite_sla, fecha_resolucion,
                      causa_raiz, solucion, recomendaciones,
                      resuelto_por_id, creado_por_id)
                     VALUES
                     (:folio, :tit, :desc, :sid, :aid, :cid, :scid,
                      :ttid, :sevid, :estid, :origen, :eqid,
                      :rep, :repn, :repp, :asig,
                      :reinc, :padre,
                      :fe, :fa, :sla, :fr,
                      :cr, :sol, :rec,
                      :resby, :crid)",
                    [
                        'folio' => $folio,
                        'tit' => trim((string) $valores['titulo']),
                        'desc' => trim((string) $valores['descripcion']),
                        'sid' => $valores['sucursal_id'],
                        'aid' => $valores['area_id'],
                        'cid' => $valores['categoria_id'] ?: null,
                        'scid' => $valores['subcategoria_id'] ?: null,
                        'ttid' => $valores['tipo_trabajo_id'] ?: null,
                        'sevid' => $valores['severidad_id'],
                        'estid' => $estado_a_usar,
                        'origen' => $valores['origen_reporte_id'] ?: null,
                        'eqid' => $valores['equipo_id'] ?: null,
                        'rep' => $u['id'],
                        'repn' => trim((string) $valores['reportante_nombre']) ?: null,
                        'repp' => trim((string) $valores['reportante_puesto']) ?: null,
                        'asig' => $valores['asignado_a_id'] ?: null,
                        'reinc' => $valores['es_reincidencia'],
                        'padre' => $valores['incidencia_padre_id'] ?: null,
                        'fe' => date('Y-m-d H:i:s', strtotime($valores['fecha_evento'])),
                        'fa' => $fecha_atencion,
                        'sla' => $fecha_limite_sla,
                        'fr' => $fecha_resolucion,
                        'cr' => trim((string) $valores['causa_raiz']) ?: null,
                        'sol' => trim((string) $valores['solucion']) ?: null,
                        'rec' => trim((string) $valores['recomendaciones']) ?: null,
                        'resby' => $resuelto_por,
                        'crid' => $u['id'],
                    ]
                );
                $incidencia_id = db_last_id();

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
                guardar_costos_incidencia($incidencia_id, [
                    'proveedor_escalado_id' => $prov_id,
                    'proveedor_externo_info' => $prov_info,
                    'costo_mano_obra' => $modo === 'interno' ? null : $valores['costo_mano_obra'],
                    'costo_materiales_proveedor' => $modo === 'interno' ? null : $valores['costo_materiales_proveedor'],
                    'costo_notas' => $valores['costo_notas'],
                    'horas_trabajadas' => $modo === 'interno' ? $valores['horas_trabajadas'] : null,
                    'costo_materiales_comprados' => $modo === 'interno' ? $valores['costo_materiales_comprados'] : null,
                ]);

                // Registrar tiempos
                recalcular_tiempos_incidencia($incidencia_id);

                // Procesar adjuntos
                $errores_adjuntos = [];
                if (!empty($_FILES['adjuntos']['name'][0])) {
                    [$exitos, $errores_adjuntos] = procesar_adjuntos($incidencia_id, $_FILES['adjuntos'], $u['id']);
                    if (count($exitos) > 0) {
                        registrar_historial(
                            $incidencia_id, $u['id'], 'adjuntos_subidos', 'adjuntos',
                            null, count($exitos) . ' archivo(s)',
                            count($exitos) . ' archivo(s) adjuntados al crear'
                        );
                    }
                }

                // Si está marcada como reincidencia, actualizar contador en la padre
                if ($valores['es_reincidencia'] && $valores['incidencia_padre_id']) {
                    db_exec(
                        "UPDATE incidencias
                         SET veces_recurrida = veces_recurrida + 1
                         WHERE id = :id",
                        ['id' => $valores['incidencia_padre_id']]
                    );
                }

                // Historial inicial
                registrar_historial(
                    $incidencia_id, $u['id'], 'creada', null, null, $folio,
                    "Incidencia creada con folio $folio"
                );

                db()->commit();
                registrar_auditoria('crear_incidencia', 'incidencias', $incidencia_id, "Folio $folio");

                // Si se aplicó una regla de auto-asignación, registrarlo
                if ($regla_aplicada) {
                    registrar_uso_regla((int) $regla_aplicada['regla_id']);
                    registrar_auditoria('aplicar_regla', 'incidencias', $incidencia_id,
                        "Auto-asignado a {$regla_aplicada['asignado_nombre']} por regla \"{$regla_aplicada['regla_nombre']}\"");
                    flash_set('info', "Auto-asignado a {$regla_aplicada['asignado_nombre']} (regla: {$regla_aplicada['regla_nombre']})");
                }

                // === Disparo de notificaciones automáticas ===
                // Si se asignó técnico al crear, notificarle
                if ($valores['asignado_a_id']) {
                    notificar_asignacion($incidencia_id, (int) $valores['asignado_a_id'], (int) $u['id']);
                }
                // Si es reincidencia con padre identificada, notificar a involucrados de la padre
                if ($valores['es_reincidencia'] && $valores['incidencia_padre_id']) {
                    notificar_reincidencia($incidencia_id, (int) $valores['incidencia_padre_id']);
                }
                // Si es crítica (nivel 1), notificar a todos los ingenieros con acceso
                notificar_critica_nueva($incidencia_id);

                $msg = "Incidencia $folio creada correctamente.";
                if (!empty($errores_adjuntos)) {
                    $msg .= " Hubo problemas con algunos adjuntos: " . implode(' ', $errores_adjuntos);
                    flash_set('warning', $msg);
                } else {
                    flash_set('success', $msg);
                }

                header('Location: ' . url('incidencia_ver.php?id=' . $incidencia_id));
                exit;
            } catch (Throwable $e) {
                if (db()->inTransaction()) db()->rollBack();
                $errores[] = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
}

// ----------------------------------------------------------------------------
// Renderizar
// ----------------------------------------------------------------------------
require_once __DIR__ . '/config/header.php';

// Descomponer horas decimales en horas + minutos para mostrar en el formulario
$_ht      = (float) ($valores['horas_trabajadas'] ?: 0);
$_ht_h    = (int) floor($_ht);
$_ht_m    = (int) round(($_ht - $_ht_h) * 60);
?>

<div class="max-w-5xl mx-auto animate-fade-in"
     x-data="formIncidencia()">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
        <a href="<?= url('bitacora.php') ?>"
           class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500 hover:text-zinc-700">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">Nueva incidencia</h2>
            <p class="text-xs text-zinc-500 mt-0.5">Completa los datos para registrar el evento. Los campos con * son obligatorios.</p>
        </div>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="mb-5 px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <div class="font-semibold mb-1 flex items-center gap-2">
            <i data-lucide="alert-circle" class="w-4 h-4"></i> Revisa lo siguiente:
        </div>
        <ul class="list-disc list-inside space-y-0.5 text-xs">
            <?php foreach ($errores as $e): ?>
            <li><?= e($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Banner de borrador restaurado -->
    <div x-show="borradorRestaurado" x-cloak x-transition
         class="mb-4 flex items-center justify-between gap-3 px-4 py-3 rounded-lg bg-amber-50 border border-amber-300 text-amber-900 text-sm">
        <div class="flex items-center gap-2">
            <i data-lucide="history" class="w-4 h-4 flex-shrink-0"></i>
            <span>Se restauró un borrador guardado automáticamente.</span>
        </div>
        <button type="button" @click="descartarBorrador()"
                class="text-xs font-semibold underline hover:no-underline flex-shrink-0">
            Descartar borrador
        </button>
    </div>

    <!-- Selector de plantillas (acelera el llenado) -->
    <div class="bg-gradient-to-br from-bacal-50 to-white rounded-xl border border-bacal-200 shadow-sm p-5 mb-5"
         x-data="{ abierto: false, plantillas: [], cargadas: false }"
         x-init="async () => {
             try {
                 const resp = await fetch('<?= url('api/plantillas_listar.php') ?>', { credentials: 'same-origin' });
                 if (resp.ok) plantillas = await resp.json();
             } catch(e) { console.error(e); }
             cargadas = true;
             $nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
         }">
        <button type="button" @click="abierto = !abierto" class="w-full flex items-center justify-between">
            <div class="flex items-center gap-2 text-left">
                <div class="w-9 h-9 rounded-lg bg-bacal-700 text-white flex items-center justify-center flex-shrink-0">
                    <i data-lucide="layout-template" class="w-4 h-4"></i>
                </div>
                <div>
                    <div class="font-display font-bold text-sm text-zinc-900">¿Es un problema común?</div>
                    <div class="text-[11px] text-zinc-500">Usa una plantilla para pre-llenar el formulario y ahorrar tiempo</div>
                </div>
            </div>
            <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-400 transition-transform" :class="abierto ? 'rotate-180' : ''"></i>
        </button>

        <div x-show="abierto" x-cloak x-transition class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
            <template x-for="p in plantillas" :key="p.id">
                <button type="button" @click="window.__aplicarPlantilla && window.__aplicarPlantilla(p); abierto = false"
                        class="flex items-center gap-2.5 p-2.5 bg-white border border-zinc-200 rounded-lg hover:border-bacal-400 hover:shadow-sm transition-all text-left">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                         :style="`background-color: ${p.color}15`">
                        <i :data-lucide="p.icono" class="w-4 h-4" :style="`color: ${p.color}`"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-xs text-zinc-900 truncate" x-text="p.nombre"></div>
                        <div class="text-[10px] text-zinc-500 truncate" x-text="p.descripcion"></div>
                    </div>
                </button>
            </template>
            <template x-if="cargadas && plantillas.length === 0">
                <div class="col-span-full text-center py-4 text-xs text-zinc-400 italic">No hay plantillas configuradas. Pídele al admin que cree algunas.</div>
            </template>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" class="space-y-5" x-ref="formulario">
        <?= csrf_input() ?>
        <input type="hidden" name="incidencia_padre_id" :value="incidenciaPadreId" x-model="incidenciaPadreId">
        <input type="hidden" name="es_reincidencia" :value="esReincidencia ? 1 : 0">

        <!-- Sección 1: Información básica -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="file-text" class="w-4 h-4 text-bacal-700"></i> Información básica
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Título *</label>
                    <input type="text" name="titulo" required maxlength="255"
                           x-model="titulo"
                           @input.debounce.500ms="cargarSugerencias(); sugerirCategoria()"
                           placeholder="Ej. Falla en impresora de tickets caja 2"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700 focus:ring-2 focus:ring-bacal-100">

                    <!-- Bloque de sugerencias en vivo -->
                    <div x-show="mostrarSugerencias && (sugerencias.plantillas.length > 0 || sugerencias.soluciones.length > 0 || sugerencias.tecnicos.length > 0)"
                         x-cloak x-transition
                         class="mt-3 p-4 bg-gradient-to-br from-blue-50 to-purple-50 border border-blue-200 rounded-lg space-y-3">

                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-blue-900 flex items-center gap-1.5">
                                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                                Sugerencias basadas en lo que escribiste
                            </h4>
                            <button type="button" @click="mostrarSugerencias = false"
                                    class="text-blue-400 hover:text-blue-700 text-xs">
                                <i data-lucide="x" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>

                        <!-- Plantillas similares -->
                        <div x-show="sugerencias.plantillas.length > 0">
                            <div class="text-[10px] font-bold text-blue-700 uppercase tracking-wide mb-1.5">📋 Plantillas similares</div>
                            <div class="space-y-1.5">
                                <template x-for="p in sugerencias.plantillas" :key="'p' + p.id">
                                    <button type="button" @click="usarSugerenciaPlantilla(p)"
                                            class="block w-full text-left px-3 py-2 bg-white rounded border border-blue-200 hover:border-blue-400 hover:shadow-sm transition-all">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-semibold text-zinc-900" x-text="p.nombre"></div>
                                                <div class="text-[10px] text-zinc-500 truncate" x-text="p.titulo"></div>
                                            </div>
                                            <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-blue-500 flex-shrink-0"></i>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Soluciones de incidencias previas -->
                        <div x-show="sugerencias.soluciones.length > 0">
                            <div class="text-[10px] font-bold text-purple-700 uppercase tracking-wide mb-1.5">💡 Soluciones aplicadas en casos similares</div>
                            <div class="space-y-1.5">
                                <template x-for="s in sugerencias.soluciones" :key="'s' + s.id">
                                    <div class="block px-3 py-2 bg-white rounded border border-purple-200">
                                        <div class="flex items-start justify-between gap-2 mb-1">
                                            <a :href="'<?= url('incidencia_ver.php?id=') ?>' + s.id" target="_blank"
                                               class="font-mono text-[10px] font-bold text-purple-700 hover:underline" x-text="s.folio"></a>
                                            <span class="text-[10px] text-zinc-500" x-show="s.resuelto_por_nombre" x-text="s.resuelto_por_nombre"></span>
                                        </div>
                                        <div class="text-[11px] text-zinc-700 italic" x-text="s.solucion"></div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Técnicos expertos -->
                        <div x-show="sugerencias.tecnicos.length > 0">
                            <div class="text-[10px] font-bold text-emerald-700 uppercase tracking-wide mb-1.5">⭐ Técnicos con experiencia en este tipo</div>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="t in sugerencias.tecnicos" :key="'t' + t.id">
                                    <button type="button"
                                            @click="$refs.selectAsignado.value = t.id; mensajeBalanceo = '✓ Asignado a ' + t.nombre_completo"
                                            class="flex items-center gap-2 px-3 py-1.5 bg-white border border-emerald-200 rounded-lg hover:border-emerald-400 hover:shadow-sm transition-all">
                                        <template x-if="t.avatar_full_url">
                                            <img :src="t.avatar_full_url" :alt="t.nombre_completo" class="w-6 h-6 rounded-full object-cover">
                                        </template>
                                        <template x-if="!t.avatar_full_url">
                                            <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold flex items-center justify-center"
                                                  x-text="t.nombre_completo.split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase()"></span>
                                        </template>
                                        <div class="text-left">
                                            <div class="text-xs font-semibold text-zinc-900" x-text="t.nombre_completo"></div>
                                            <div class="text-[10px] text-zinc-500"><span x-text="t.resueltas"></span> casos resueltos</div>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Descripción detallada *</label>
                    <textarea name="descripcion" required rows="4"
                              x-model="descripcion"
                              @input.debounce.500ms="cargarSugerencias(); sugerirCategoria()"
                              placeholder="Describe qué pasó, cuándo lo notaron, qué intentaron, qué impacto está teniendo, etc."
                              class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700 focus:ring-2 focus:ring-bacal-100"><?= e((string) $valores['descripcion']) ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Fecha y hora del evento *</label>
                    <input type="datetime-local" name="fecha_evento" required
                           value="<?= e((string) $valores['fecha_evento']) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700 focus:ring-2 focus:ring-bacal-100">
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Origen del reporte</label>
                    <div class="flex gap-2">
                        <select name="origen_reporte_id"
                                class="flex-1 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                            <option value="">— Sin especificar —</option>
                            <?php foreach ($origenes as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= $valores['origen_reporte_id'] == $o['id'] ? 'selected' : '' ?>>
                                <?= e($o['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (tiene_permiso('administrar')): ?>
                        <button type="button" @click="abrirModalCatalogo('origen')"
                                title="Crear nuevo origen"
                                class="px-2.5 rounded-lg border border-zinc-300 text-zinc-500 hover:text-bacal-700 hover:border-bacal-400 transition-colors flex-shrink-0">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sección 2: Clasificación -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="tags" class="w-4 h-4 text-bacal-700"></i> Clasificación
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Sucursal *</label>
                    <?php if (tiene_permiso('ver_todas_sucursales')): ?>
                    <select name="sucursal_id" required x-model="sucursalId"
                            @change="cargarEquipos(); buscarReincidencias()"
                            class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Selecciona —</option>
                        <?php foreach ($sucursales as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $valores['sucursal_id'] == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else:
                        $s_actual = db_one("SELECT id, nombre FROM sucursales WHERE id=:id", ['id' => $u['sucursal_id']]);
                    ?>
                    <input type="hidden" name="sucursal_id" value="<?= $u['sucursal_id'] ?>" x-init="sucursalId = '<?= $u['sucursal_id'] ?>'; cargarEquipos()">
                    <div class="px-3 py-2 rounded-lg border border-zinc-200 bg-zinc-50 text-sm text-zinc-700">
                        <?= e($s_actual['nombre'] ?? 'Tu sucursal') ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Área *</label>
                    <div class="flex gap-2">
                        <select name="area_id" required x-model="areaId" @change="buscarReincidencias()"
                                class="flex-1 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                            <option value="">— Selecciona —</option>
                            <?php foreach ($areas as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= $valores['area_id'] == $a['id'] ? 'selected' : '' ?>>
                                <?= e($a['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (tiene_permiso('administrar')): ?>
                        <button type="button" @click="abrirModalCatalogo('area')"
                                title="Crear nueva área"
                                class="px-2.5 rounded-lg border border-zinc-300 text-zinc-500 hover:text-bacal-700 hover:border-bacal-400 transition-colors flex-shrink-0">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Categoría</label>
                    <div class="flex gap-2">
                        <select name="categoria_id" x-model="categoriaId" @change="buscarReincidencias()"
                                class="flex-1 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                            <option value="">— Selecciona —</option>
                            <?php foreach ($categorias as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $valores['categoria_id'] == $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (tiene_permiso('administrar')): ?>
                        <button type="button" @click="abrirModalCatalogo('categoria')"
                                title="Crear nueva categoría"
                                class="px-2.5 rounded-lg border border-zinc-300 text-zinc-500 hover:text-bacal-700 hover:border-bacal-400 transition-colors flex-shrink-0">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Sugerencia de categoría según palabras clave -->
                    <div x-show="sugerenciasCategoria.length > 0 && !categoriaId" x-cloak x-transition
                         class="mt-2 flex flex-wrap gap-1.5 items-center">
                        <span class="text-[10px] text-zinc-500 font-semibold">✨ Sugerido:</span>
                        <template x-for="sc in sugerenciasCategoria" :key="'sc' + sc.categoria_id">
                            <button type="button"
                                    @click="categoriaId = String(sc.categoria_id); buscarReincidencias()"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border hover:shadow-sm transition-all"
                                    :style="`color: ${sc.color}; background-color: ${sc.color}15; border-color: ${sc.color}40`"
                                    :title="'Palabras coincidentes: ' + sc.palabras_coincidentes.join(', ')">
                                <i data-lucide="sparkles" class="w-2.5 h-2.5"></i>
                                <span x-text="sc.categoria_nombre"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Subcategoría</label>
                    <div class="flex gap-2">
                        <select name="subcategoria_id" x-model="subcategoriaId"
                                class="flex-1 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                            <option value="">— Sin especificar —</option>
                            <template x-for="sub in subcategoriasFiltradas" :key="sub.id">
                                <option :value="sub.id" x-text="sub.nombre" :selected="String(sub.id) === String(subcategoriaId)"></option>
                            </template>
                        </select>
                        <?php if (tiene_permiso('administrar')): ?>
                        <button type="button" @click="abrirModalCatalogo('subcategoria')"
                                title="Crear nueva subcategoría"
                                :disabled="!categoriaId"
                                class="px-2.5 rounded-lg border border-zinc-300 text-zinc-500 hover:text-bacal-700 hover:border-bacal-400 transition-colors flex-shrink-0 disabled:opacity-40 disabled:cursor-not-allowed">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Tipo de trabajo</label>
                    <div class="flex gap-2">
                        <select name="tipo_trabajo_id"
                                class="flex-1 px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                            <option value="">— Sin especificar —</option>
                            <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $valores['tipo_trabajo_id'] == $t['id'] ? 'selected' : '' ?>>
                                <?= e($t['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (tiene_permiso('administrar')): ?>
                        <button type="button" @click="abrirModalCatalogo('tipo_trabajo')"
                                title="Crear nuevo tipo de trabajo"
                                class="px-2.5 rounded-lg border border-zinc-300 text-zinc-500 hover:text-bacal-700 hover:border-bacal-400 transition-colors flex-shrink-0">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Severidad *</label>
                    <select name="severidad_id" required x-model="severidadId"
                            class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Selecciona —</option>
                        <?php foreach ($severidades as $s): ?>
                        <option value="<?= $s['id'] ?>"
                                data-color="<?= e($s['color']) ?>"
                                data-sla="<?= $s['sla_horas'] ?>"
                                <?= $valores['severidad_id'] == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['nombre']) ?><?= $s['sla_horas'] ? " (SLA {$s['sla_horas']}h)" : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Sección 3: Equipo y reportante -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="monitor" class="w-4 h-4 text-bacal-700"></i> Equipo y personas involucradas
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Equipo / activo</label>
                    <select name="equipo_id" x-model="equipoId" @change="buscarReincidencias()"
                            class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700"
                            :disabled="!sucursalId">
                        <option value="">— Sin equipo específico —</option>
                        <template x-for="eq in equipos" :key="eq.id">
                            <option :value="eq.id" :selected="String(eq.id) === String(equipoId)">
                                <span x-text="eq.codigo_inventario + ' - ' + eq.nombre"></span>
                            </option>
                        </template>
                    </select>
                    <p class="text-[11px] text-zinc-500 mt-1" x-show="!sucursalId">Selecciona primero una sucursal para ver sus equipos.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Reportante (si aplica)</label>
                    <input type="text" name="reportante_nombre" maxlength="150"
                           value="<?= e((string) $valores['reportante_nombre']) ?>"
                           placeholder="Nombre de quien reportó (si no es usuario del sistema)"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Puesto del reportante</label>
                    <input type="text" name="reportante_puesto" maxlength="100"
                           value="<?= e((string) $valores['reportante_puesto']) ?>"
                           placeholder="Ej. Cajera, Encargado de almacén"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                </div>

                <?php if (tiene_permiso('administrar') || tiene_permiso('resolver')): ?>
                <div class="md:col-span-2">
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-zinc-700 uppercase tracking-wide">Asignar a técnico</label>
                        <button type="button" @click="asignarMenosCargado()"
                                :disabled="cargandoBalanceo"
                                class="text-[10px] font-semibold text-bacal-700 hover:text-bacal-800 flex items-center gap-1 disabled:opacity-50">
                            <template x-if="!cargandoBalanceo">
                                <span class="flex items-center gap-1"><i data-lucide="scale" class="w-3 h-3"></i> Asignar al menos cargado</span>
                            </template>
                            <template x-if="cargandoBalanceo">
                                <span class="flex items-center gap-1"><i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Buscando...</span>
                            </template>
                        </button>
                    </div>
                    <select name="asignado_a_id" x-ref="selectAsignado"
                            class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
                        <option value="">— Sin asignar (se aplicará regla automática si existe) —</option>
                        <?php foreach ($tecnicos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $valores['asignado_a_id'] == $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['nombre_completo']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div x-show="mensajeBalanceo" x-cloak class="text-[11px] text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-2 py-1 mt-1.5"
                         x-text="mensajeBalanceo"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sección 4: Reincidencias detectadas (dinámica) -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6"
             x-show="reincidencias.length > 0 || cargandoReincidencias"
             x-cloak>
            <h3 class="font-display text-base font-bold text-zinc-900 mb-1 flex items-center gap-2">
                <i data-lucide="rotate-ccw" class="w-4 h-4 text-purple-600"></i>
                Posibles incidencias relacionadas detectadas
            </h3>
            <p class="text-xs text-zinc-500 mb-4">
                Detectamos incidencias similares en los últimos 30 días en la misma área/equipo/categoría.
                Si esto se trata del mismo problema recurrente, márcalo como reincidencia.
            </p>

            <div x-show="cargandoReincidencias" class="text-sm text-zinc-500 italic">Buscando…</div>

            <div class="space-y-2 mb-3">
                <template x-for="r in reincidencias" :key="r.id">
                    <label class="block border border-zinc-200 rounded-lg p-3 hover:bg-zinc-50 cursor-pointer transition-colors"
                           :class="String(incidenciaPadreId) === String(r.id) ? 'border-purple-400 bg-purple-50' : ''">
                        <div class="flex items-start gap-3">
                            <input type="radio" name="_reincidencia_radio" :value="r.id"
                                   :checked="String(incidenciaPadreId) === String(r.id)"
                                   @change="incidenciaPadreId = r.id; esReincidencia = true"
                                   class="mt-1 text-purple-600 focus:ring-purple-500">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <span class="font-mono text-[10px] font-bold text-zinc-500" x-text="r.folio"></span>
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold"
                                          :style="`background-color: ${r.estado_color}20; color: ${r.estado_color}; border: 1px solid ${r.estado_color}40`"
                                          x-text="r.estado_nombre"></span>
                                    <span class="text-[10px] text-zinc-500" x-text="'hace ' + r.dias_atras + ' día(s)'"></span>
                                </div>
                                <div class="font-semibold text-sm text-zinc-900" x-text="r.titulo"></div>
                                <div class="text-xs text-zinc-500 mt-0.5" x-show="r.equipo_nombre">
                                    <span x-text="r.equipo_nombre"></span>
                                </div>
                            </div>
                        </div>
                    </label>
                </template>
            </div>

            <div x-show="incidenciaPadreId" class="flex items-center gap-2 text-xs">
                <button type="button" @click="incidenciaPadreId = ''; esReincidencia = false"
                        class="text-zinc-500 hover:text-bacal-700 underline">
                    Quitar marca de reincidencia
                </button>
            </div>
        </div>

        <!-- Sección 5: Proveedor y costos -->
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

        <!-- Sección 5.7: Resolución (opcional al crear) -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6"
             x-data="{ abierto: <?= !empty($valores['solucion']) ? 'true' : 'false' ?> }">
            <button type="button" @click="abierto = !abierto"
                    class="w-full flex items-center justify-between text-left">
                <div>
                    <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                        <i data-lucide="wrench" class="w-4 h-4 text-bacal-700"></i>
                        Resolución
                        <span class="text-xs font-normal text-zinc-500">(opcional, si ya se resolvió)</span>
                    </h3>
                </div>
                <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-400 transition-transform"
                   :class="abierto ? 'rotate-180' : ''"></i>
            </button>

            <div x-show="abierto" x-collapse class="mt-4 space-y-4">
                <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2 text-xs text-emerald-800 flex items-center gap-1.5">
                    <i data-lucide="info" class="w-3.5 h-3.5"></i>
                    Si registras una solución, la incidencia se marcará automáticamente como <strong>Completada</strong>.
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Causa raíz identificada</label>
                    <textarea name="causa_raiz" rows="2"
                              placeholder="¿Por qué ocurrió este incidente?"
                              class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700"><?= e((string) $valores['causa_raiz']) ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Solución aplicada</label>
                    <textarea name="solucion" rows="3"
                              placeholder="¿Qué se hizo para resolverlo?"
                              class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700"><?= e((string) $valores['solucion']) ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Recomendaciones para evitar recurrencia</label>
                    <textarea name="recomendaciones" rows="2"
                              placeholder="¿Qué se puede hacer para que no vuelva a pasar?"
                              class="w-full px-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700"><?= e((string) $valores['recomendaciones']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Sección 6: Adjuntos -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-1 flex items-center gap-2">
                <i data-lucide="paperclip" class="w-4 h-4 text-bacal-700"></i> Adjuntos / evidencias
            </h3>
            <p class="text-xs text-zinc-500 mb-4">Máximo <?= ADJUNTOS_MAX_ARCHIVOS ?> archivos, 10 MB cada uno. Formatos permitidos: imágenes, PDF, Word, Excel, ZIP, TXT.</p>

            <input type="file" name="adjuntos[]" multiple
                   x-ref="inputFiles" @change="archivosSeleccionados = Array.from($event.target.files)"
                   class="hidden">

            <div @click="$refs.inputFiles.click()"
                 @dragover.prevent="dragActivo = true" @dragleave.prevent="dragActivo = false"
                 @drop.prevent="dragActivo = false; agregarArchivos($event.dataTransfer.files)"
                 :class="dragActivo ? 'border-bacal-700 bg-bacal-50' : 'border-zinc-300 hover:border-zinc-400'"
                 class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors">
                <i data-lucide="upload-cloud" class="w-8 h-8 mx-auto text-zinc-400 mb-2"></i>
                <p class="text-sm font-medium text-zinc-700">Haz clic o arrastra archivos aquí</p>
                <p class="text-xs text-zinc-500 mt-1" x-show="archivosSeleccionados.length === 0">Sin archivos seleccionados</p>
                <p class="text-xs text-bacal-700 font-semibold mt-1" x-show="archivosSeleccionados.length > 0">
                    <span x-text="archivosSeleccionados.length"></span> archivo(s) seleccionado(s)
                </p>
            </div>

            <div class="mt-3 space-y-1.5" x-show="archivosSeleccionados.length > 0">
                <template x-for="(f, idx) in archivosSeleccionados" :key="idx">
                    <div class="flex items-center gap-2 px-3 py-2 bg-zinc-50 rounded-lg text-xs">
                        <i data-lucide="file" class="w-4 h-4 text-zinc-400"></i>
                        <span class="flex-1 truncate font-medium text-zinc-700" x-text="f.name"></span>
                        <span class="text-zinc-500" x-text="(f.size / 1024).toFixed(0) + ' KB'"></span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Botones de acción -->
        <div class="flex items-center justify-end gap-2">
            <a href="<?= url('bitacora.php') ?>"
               class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 font-medium text-sm hover:bg-zinc-50">
                Cancelar
            </a>
            <button type="submit"
                    class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white font-semibold text-sm shadow-sm flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i> Registrar incidencia
            </button>
        </div>

    </form>

    <!-- ============================================================ -->
    <!-- Modal: Catálogo rápido                                       -->
    <!-- ============================================================ -->
    <div x-show="modalAbierto" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="modalAbierto = false">
        <div class="absolute inset-0 bg-black/50" @click="modalAbierto = false"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm p-6" @click.stop
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                    <i data-lucide="plus-circle" class="w-4 h-4 text-bacal-700"></i>
                    <span x-text="modalTituloLabel"></span>
                </h3>
                <button type="button" @click="modalAbierto = false"
                        class="text-zinc-400 hover:text-zinc-600 p-1 rounded">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Nombre</label>
                    <input type="text" x-ref="modalInput" x-model="modalNombre"
                           @keydown.enter.prevent="guardarModalCatalogo()"
                           maxlength="100"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:ring-2 focus:ring-bacal-500">
                </div>
                <div x-show="modalMostrarColor">
                    <label class="block text-xs font-bold text-zinc-700 mb-1">Color</label>
                    <div class="flex items-center gap-2">
                        <input type="color" x-model="modalColor"
                               class="h-9 w-16 rounded border border-zinc-300 cursor-pointer p-0.5">
                        <span class="text-xs text-zinc-500 font-mono" x-text="modalColor"></span>
                    </div>
                </div>
                <p x-show="modalError" x-text="modalError"
                   class="text-xs text-red-600 font-medium"></p>
            </div>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" @click="modalAbierto = false"
                        class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm font-medium hover:bg-zinc-50">
                    Cancelar
                </button>
                <button type="button" @click="guardarModalCatalogo()"
                        :disabled="modalCargando || !modalNombre.trim()"
                        class="px-4 py-2 rounded-lg bg-bacal-700 text-white text-sm font-semibold hover:bg-bacal-800 disabled:opacity-50">
                    <span x-show="!modalCargando">Crear</span>
                    <span x-show="modalCargando">Guardando…</span>
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function formIncidencia() {
    return {
        sucursalId: '<?= e((string) $valores['sucursal_id']) ?>',
        areaId: '<?= e((string) $valores['area_id']) ?>',
        categoriaId: '<?= e((string) $valores['categoria_id']) ?>',
        subcategoriaId: '<?= e((string) $valores['subcategoria_id']) ?>',
        equipoId: '<?= e((string) $valores['equipo_id']) ?>',
        severidadId: '<?= e((string) $valores['severidad_id']) ?>',
        esReincidencia: <?= $valores['es_reincidencia'] ? 'true' : 'false' ?>,
        incidenciaPadreId: '<?= e((string) $valores['incidencia_padre_id']) ?>',

        equipos: [],
        cargandoEquipos: false,

        reincidencias: [],
        cargandoReincidencias: false,
        timerReincidencias: null,

        archivosSeleccionados: [],
        dragActivo: false,

        categorias: <?= json_encode($categorias, JSON_UNESCAPED_UNICODE) ?>,

        // === Fase 14: Sugerencias en vivo ===
        titulo: '<?= e((string) $valores['titulo']) ?>',
        descripcion: '<?= e((string) $valores['descripcion']) ?>',
        tipoTrabajoId: '<?= e((string) $valores['tipo_trabajo_id']) ?>',
        sugerencias: { plantillas: [], soluciones: [], tecnicos: [] },
        timerSugerencias: null,
        cargandoSugerencias: false,
        mostrarSugerencias: true,

        // === Fase 14: Balanceo de carga ===
        cargandoBalanceo: false,
        mensajeBalanceo: '',

        // === Fase 15: Sugerencia de categoría ===
        sugerenciasCategoria: [],
        timerCategoria: null,

        // === Borrador automático ===
        borradorRestaurado: false,

        // === Modal catálogo rápido ===
        modalAbierto: false,
        modalTipo: '',
        modalNombre: '',
        modalColor: '#6B7280',
        modalCargando: false,
        modalError: '',
        get modalTituloLabel() {
            const labels = {
                'area': 'Nueva área',
                'categoria': 'Nueva categoría',
                'subcategoria': 'Nueva subcategoría',
                'tipo_trabajo': 'Nuevo tipo de trabajo',
                'origen': 'Nuevo origen de reporte',
            };
            return labels[this.modalTipo] || 'Nuevo elemento';
        },
        get modalMostrarColor() {
            return ['categoria', 'tipo_trabajo'].includes(this.modalTipo);
        },

        get subcategoriasFiltradas() {
            if (!this.categoriaId) return [];
            const c = this.categorias.find(x => String(x.id) === String(this.categoriaId));
            return c ? c.subcategorias : [];
        },

        async cargarEquipos() {
            this.equipos = [];
            if (!this.sucursalId) return;
            this.cargandoEquipos = true;
            try {
                const resp = await fetch('<?= url('api/equipos_de_sucursal.php') ?>?sucursal=' + this.sucursalId, {
                    credentials: 'same-origin'
                });
                if (resp.ok) {
                    this.equipos = await resp.json();
                }
            } catch (e) { console.error(e); }
            this.cargandoEquipos = false;
        },

        buscarReincidencias() {
            clearTimeout(this.timerReincidencias);
            this.timerReincidencias = setTimeout(() => this._buscarReincidenciasNow(), 400);
        },

        async _buscarReincidenciasNow() {
            if (!this.areaId) {
                this.reincidencias = [];
                return;
            }
            this.cargandoReincidencias = true;
            try {
                const params = new URLSearchParams({
                    area: this.areaId,
                    equipo: this.equipoId || '',
                    categoria: this.categoriaId || '',
                });
                const resp = await fetch('<?= url('api/buscar_reincidencias.php') ?>?' + params.toString(), {
                    credentials: 'same-origin'
                });
                if (resp.ok) {
                    this.reincidencias = await resp.json();
                }
            } catch (e) { console.error(e); }
            this.cargandoReincidencias = false;
        },

        agregarArchivos(fileList) {
            const dt = new DataTransfer();
            // Mantener los previos
            this.archivosSeleccionados.forEach(f => dt.items.add(f));
            // Sumar nuevos
            Array.from(fileList).forEach(f => dt.items.add(f));
            this.$refs.inputFiles.files = dt.files;
            this.archivosSeleccionados = Array.from(dt.files);
        },

        // === Fase 14: Sugerencias en vivo con debounce 500ms ===
        cargarSugerencias() {
            clearTimeout(this.timerSugerencias);
            const titulo = this.titulo.trim();
            if (titulo.length < 3) {
                this.sugerencias = { plantillas: [], soluciones: [], tecnicos: [] };
                return;
            }
            this.timerSugerencias = setTimeout(async () => {
                this.cargandoSugerencias = true;
                try {
                    const params = new URLSearchParams();
                    params.append('titulo', titulo);
                    if (this.categoriaId) params.append('categoria_id', this.categoriaId);
                    if (this.tipoTrabajoId) params.append('tipo_trabajo_id', this.tipoTrabajoId);
                    if (this.areaId) params.append('area_id', this.areaId);

                    const resp = await fetch('<?= url('api/sugerencias_incidencia.php') ?>?' + params.toString(), {
                        credentials: 'same-origin'
                    });
                    if (resp.ok) {
                        this.sugerencias = await resp.json();
                    }
                } catch (e) {
                    console.error('Error sugerencias:', e);
                }
                this.cargandoSugerencias = false;
            }, 500);
        },

        // === Fase 14: Asignar al técnico con menos carga ===
        async asignarMenosCargado() {
            this.cargandoBalanceo = true;
            this.mensajeBalanceo = '';
            try {
                const params = new URLSearchParams();
                if (this.sucursalId) params.append('sucursal_id', this.sucursalId);
                const resp = await fetch('<?= url('api/tecnico_menos_cargado.php') ?>?' + params.toString(), {
                    credentials: 'same-origin'
                });
                const data = await resp.json();
                if (data.ok) {
                    this.$refs.selectAsignado.value = data.id;
                    this.mensajeBalanceo = `✓ Asignado a ${data.nombre} (${data.abiertas} incidencia(s) abierta(s))`;
                } else {
                    this.mensajeBalanceo = '✗ ' + (data.error || 'No se pudo asignar');
                }
            } catch (e) {
                this.mensajeBalanceo = '✗ Error: ' + e.message;
            }
            this.cargandoBalanceo = false;
        },

        // === Fase 15: Sugerir categoría según palabras clave ===
        sugerirCategoria() {
            clearTimeout(this.timerCategoria);
            // Si ya hay categoría seleccionada, no sugerir (respetar elección del usuario)
            if (this.categoriaId) {
                this.sugerenciasCategoria = [];
                return;
            }
            const texto = (this.titulo + ' ' + this.descripcion).trim();
            if (texto.length < 3) {
                this.sugerenciasCategoria = [];
                return;
            }
            this.timerCategoria = setTimeout(async () => {
                try {
                    const params = new URLSearchParams();
                    params.append('titulo', this.titulo);
                    params.append('descripcion', this.descripcion);
                    const resp = await fetch('<?= url('api/sugerir_categoria.php') ?>?' + params.toString(), {
                        credentials: 'same-origin'
                    });
                    if (resp.ok) {
                        const data = await resp.json();
                        this.sugerenciasCategoria = data.sugerencias || [];
                    }
                } catch (e) {
                    console.error('Error sugerencias categoría:', e);
                }
            }, 500);
        },

        usarSugerenciaPlantilla(p) {
            // Reutilizamos el método aplicarPlantilla
            this.aplicarPlantilla(p);
            this.mostrarSugerencias = false;
        },

        usarSugerenciaSolucion(s) {
            const form = this.$refs.formulario;
            // Si hay campo solucion, lo llenamos
            if (form.solucion) form.solucion.value = s.solucion;
            // Toggle del bloque "marcar resuelta"
            window.dispatchEvent(new CustomEvent('abrir-bloque-solucion'));
        },

        // === Borrador automático ===
        _borrador_key: 'inc_nueva_borrador_v1',

        _guardarBorrador() {
            try {
                const data = {
                    titulo: this.titulo,
                    descripcion: this.descripcion,
                    sucursalId: this.sucursalId,
                    areaId: this.areaId,
                    categoriaId: this.categoriaId,
                    subcategoriaId: this.subcategoriaId,
                    tipoTrabajoId: this.tipoTrabajoId,
                    severidadId: this.severidadId,
                };
                localStorage.setItem(this._borrador_key, JSON.stringify(data));
            } catch(e) { /* localStorage bloqueado */ }
        },

        _cargarBorrador() {
            try {
                const raw = localStorage.getItem(this._borrador_key);
                if (!raw) return;
                const data = JSON.parse(raw);
                // Solo restaurar si hay contenido sustancial (titulo o descripcion)
                if (!data.titulo && !data.descripcion) return;

                const form = this.$refs.formulario;
                if (data.titulo) { this.titulo = data.titulo; if (form?.titulo) form.titulo.value = data.titulo; }
                if (data.descripcion) { this.descripcion = data.descripcion; if (form?.descripcion) form.descripcion.value = data.descripcion; }
                if (data.sucursalId) { this.sucursalId = data.sucursalId; this.cargarEquipos(); }
                if (data.areaId) { this.areaId = data.areaId; }
                if (data.categoriaId) { this.categoriaId = data.categoriaId; }
                if (data.subcategoriaId) { this.subcategoriaId = data.subcategoriaId; }
                if (data.tipoTrabajoId) { this.tipoTrabajoId = data.tipoTrabajoId; }
                if (data.severidadId) { this.severidadId = data.severidadId; }

                this.borradorRestaurado = true;
            } catch(e) { /* JSON roto */ }
        },

        descartarBorrador() {
            try { localStorage.removeItem(this._borrador_key); } catch(e) {}
            this.borradorRestaurado = false;
        },

        // === Modal catálogo rápido ===
        abrirModalCatalogo(tipo) {
            this.modalTipo = tipo;
            this.modalNombre = '';
            this.modalColor = '#6B7280';
            this.modalError = '';
            this.modalCargando = false;
            this.modalAbierto = true;
            this.$nextTick(() => this.$refs.modalInput?.focus());
        },

        async guardarModalCatalogo() {
            this.modalError = '';
            if (!this.modalNombre.trim()) return;
            this.modalCargando = true;
            try {
                const params = new URLSearchParams({
                    _csrf: '<?= csrf_token() ?>',
                    tabla: this.modalTipo,
                    nombre: this.modalNombre.trim(),
                    color: this.modalColor,
                    categoria_id: this.categoriaId || '',
                });
                const resp = await fetch('<?= url('api/catalogo_crear_rapido.php') ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString(),
                });
                const data = await resp.json();
                if (data.ok) {
                    this._agregarOpcionSelect(this.modalTipo, data.id, data.nombre, data.color);
                    this.modalAbierto = false;
                } else {
                    this.modalError = data.error || 'Error al guardar';
                }
            } catch(e) {
                this.modalError = 'Error de conexión: ' + e.message;
            }
            this.modalCargando = false;
        },

        _agregarOpcionSelect(tipo, id, nombre, color) {
            const mapa = {
                area: 'area_id',
                categoria: 'categoria_id',
                subcategoria: 'subcategoria_id',
                tipo_trabajo: 'tipo_trabajo_id',
                origen: 'origen_reporte_id',
            };
            const nombreCampo = mapa[tipo];
            if (!nombreCampo) return;
            const form = this.$refs.formulario;
            const select = form?.[nombreCampo];
            if (!select) return;
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = nombre;
            opt.selected = true;
            select.appendChild(opt);
            // Actualizar el modelo Alpine según el campo
            if (tipo === 'area') { this.areaId = String(id); this.buscarReincidencias(); }
            else if (tipo === 'categoria') { this.categoriaId = String(id); this.buscarReincidencias(); }
            else if (tipo === 'subcategoria') { this.subcategoriaId = String(id); }
            // Para tipo_trabajo y origen el select no tiene x-model, la opción queda seleccionada vía DOM
        },

        init() {
            // Cargar equipos al iniciar si ya hay sucursal
            if (this.sucursalId) this.cargarEquipos();
            // Buscar reincidencias si ya hay datos
            if (this.areaId) this.buscarReincidencias();

            // Exponer aplicarPlantilla globalmente para el selector externo
            window.__aplicarPlantilla = (p) => this.aplicarPlantilla(p);

            // Cargar sugerencias iniciales si ya hay título (por error de validación)
            if (this.titulo.length >= 3) {
                this.cargarSugerencias();
                this.sugerirCategoria();
            }

            // Borrador: restaurar al iniciar, guardar al cambiar campos
            this._cargarBorrador();
            this.$watch('titulo', () => this._guardarBorrador());
            this.$watch('descripcion', () => this._guardarBorrador());
            this.$watch('sucursalId', () => this._guardarBorrador());
            this.$watch('areaId', () => this._guardarBorrador());
            this.$watch('categoriaId', () => this._guardarBorrador());
            this.$watch('subcategoriaId', () => this._guardarBorrador());
            this.$watch('tipoTrabajoId', () => this._guardarBorrador());
            this.$watch('severidadId', () => this._guardarBorrador());

            // Limpiar borrador al enviar el formulario
            const form = this.$refs.formulario;
            if (form) {
                form.addEventListener('submit', () => {
                    try { localStorage.removeItem(this._borrador_key); } catch(e) {}
                });
            }

            // Enfocar input del modal al abrirlo
            this.$watch('modalAbierto', val => {
                if (val) this.$nextTick(() => this.$refs.modalInput?.focus());
            });
        },

        aplicarPlantilla(p) {
            // Pre-llenar campos del formulario
            const form = this.$refs.formulario;
            if (p.titulo) form.titulo.value = p.titulo;
            if (p.descripcion_inc) form.descripcion.value = p.descripcion_inc;

            if (p.area_id) {
                this.areaId = String(p.area_id);
                form.area_id.value = p.area_id;
            }
            if (p.categoria_id) {
                this.categoriaId = String(p.categoria_id);
                form.categoria_id.value = p.categoria_id;
            }
            if (p.subcategoria_id) {
                this.subcategoriaId = String(p.subcategoria_id);
            }
            if (p.tipo_trabajo_id) form.tipo_trabajo_id.value = p.tipo_trabajo_id;
            if (p.severidad_id) {
                this.severidadId = String(p.severidad_id);
                form.severidad_id.value = p.severidad_id;
            }
            if (p.origen_reporte_id) form.origen_reporte_id.value = p.origen_reporte_id;

            // Si hay solución sugerida, mostrarla
            if (p.solucion_sugerida && form.solucion) {
                form.solucion.value = p.solucion_sugerida;
            }

            // Buscar reincidencias con los nuevos datos
            this.buscarReincidencias();

            // Mensaje visual
            alert('Plantilla aplicada: "' + p.nombre + '". Revisa y completa los datos restantes.');

            // Incrementar contador de uso (silenciosamente)
            fetch('<?= url('api/plantilla_usada.php') ?>?id=' + p.id, { credentials: 'same-origin' }).catch(() => {});
        }
    }
}

// Conectar el botón de plantilla externa con el formulario interno
document.addEventListener('alpine:init', () => {
    Alpine.data('selectorPlantillas', () => ({
        aplicarPlantilla(p) {
            if (window.__aplicarPlantilla) window.__aplicarPlantilla(p);
        }
    }));
});
</script>

<?php require_once __DIR__ . '/config/footer.php'; ?>