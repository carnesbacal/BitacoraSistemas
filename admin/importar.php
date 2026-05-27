<?php
/**
 * ============================================================================
 * admin/importar.php - Importación masiva CSV
 * ============================================================================
 * Wizard de 2 pasos:
 *   1. Selecciona tipo (usuarios/equipos/incidencias), sube archivo, ve preview
 *   2. Confirma e importa
 * ============================================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/admin_helpers.php';
require_once __DIR__ . '/../config/importacion_helpers.php';

$u = usuario_actual();
$errores = [];
$resultado = null;

// ----------------------------------------------------------------------------
// Procesar acciones
// ----------------------------------------------------------------------------
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token inválido.';
    } else {
        $op = (string) input('op', '');
        $tipo = (string) input('tipo', '');

        if ($op === 'previsualizar') {
            // Validar tipo solo cuando se previsualiza (al confirmar viene de sesión)
            if (!isset(IMPORTAR_COLUMNAS[$tipo])) {
                $errores[] = 'Tipo inválido.';
            } elseif (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                $errores[] = 'No se recibió el archivo correctamente.';
            } elseif ($_FILES['archivo']['size'] > 10 * 1024 * 1024) {
                $errores[] = 'Archivo demasiado grande (máximo 10 MB).';
            } else {
                $tmp_path = $_FILES['archivo']['tmp_name'];
                $nombre_orig = $_FILES['archivo']['name'];

                // Verificar extensión
                $ext = strtolower(pathinfo($nombre_orig, PATHINFO_EXTENSION));
                if (!in_array($ext, ['csv', 'txt'], true)) {
                    $errores[] = 'Solo se aceptan archivos .csv o .txt';
                } else {
                    try {
                        $parsed = parsear_csv($tmp_path);
                        $headers = $parsed['headers'];
                        $filas = $parsed['filas'];

                        // Validar columnas
                        $err_cols = validar_columnas_csv($headers, $tipo);
                        // Filtrar errores fatales (los que empiezan con "Falta")
                        $fatales = array_filter($err_cols, fn($e) => str_starts_with($e, 'Falta'));

                        if (!empty($fatales)) {
                            $errores = array_merge($errores, $fatales);
                        } elseif (empty($filas)) {
                            $errores[] = 'El archivo no tiene datos (solo headers o está vacío).';
                        } else {
                            // Guardar en sesión para confirmar después
                            $_SESSION['importar_preview'] = [
                                'tipo' => $tipo,
                                'nombre_archivo' => $nombre_orig,
                                'headers' => $headers,
                                'filas' => $filas,
                                'separador' => $parsed['separador'],
                                'avisos' => array_filter($err_cols, fn($e) => !str_starts_with($e, 'Falta')),
                            ];
                            // Mostrar preview
                        }
                    } catch (Throwable $e) {
                        $errores[] = 'Error al parsear CSV: ' . $e->getMessage();
                    }
                }
            }
        } elseif ($op === 'confirmar') {
            // Ejecutar la importación
            $preview = $_SESSION['importar_preview'] ?? null;
            if (!$preview) {
                $errores[] = 'Sesión expirada. Vuelve a subir el archivo.';
            } else {
                $tipo = $preview['tipo'];
                $headers = $preview['headers'];
                $filas = $preview['filas'];
                $nombre = $preview['nombre_archivo'];

                $procesado = null;
                switch ($tipo) {
                    case 'usuarios':    $procesado = importar_usuarios($headers, $filas, (int) $u['id']); break;
                    case 'equipos':     $procesado = importar_equipos($headers, $filas, (int) $u['id']); break;
                    case 'incidencias': $procesado = importar_incidencias($headers, $filas, (int) $u['id']); break;
                }

                if ($procesado) {
                    $import_id = registrar_importacion(
                        $tipo, $nombre, count($filas),
                        $procesado['exitosos'], $procesado['fallidos'],
                        $procesado['errores'], (int) $u['id']
                    );
                    registrar_auditoria('importar_masivo', 'importaciones', $import_id,
                        "Importación $tipo: {$procesado['exitosos']}/" . count($filas) . " exitosos");

                    $resultado = $procesado + ['total' => count($filas), 'tipo' => $tipo];
                    unset($_SESSION['importar_preview']);
                }
            }
        } elseif ($op === 'cancelar') {
            unset($_SESSION['importar_preview']);
            header('Location: ' . url('admin/importar.php'));
            exit;
        }
    }
}

$preview = $_SESSION['importar_preview'] ?? null;
$tipo_actual = (string) input('tipo', $preview['tipo'] ?? '');

$titulo_pagina = 'Importar datos';
$pagina_activa = 'admin_importar';
require_once __DIR__ . '/../config/header.php';
?>

<div class="max-w-5xl mx-auto animate-fade-in space-y-5">

    <!-- Header -->
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">Importación masiva</h2>
            <p class="text-xs text-zinc-500 mt-0.5">Carga usuarios, equipos o incidencias históricas desde archivos CSV.</p>
        </div>
        <a href="<?= url('admin/importar_historial.php') ?>" class="text-xs font-semibold text-bacal-700 hover:text-bacal-800 flex items-center gap-1">
            <i data-lucide="history" class="w-3.5 h-3.5"></i> Ver historial
        </a>
    </div>

    <!-- Errores -->
    <?php if (!empty($errores)): ?>
    <div class="px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <strong class="block mb-1">Revisa lo siguiente:</strong>
        <ul class="list-disc list-inside text-xs">
            <?php foreach ($errores as $e): ?><li><?= $e /* puede contener HTML */ ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Resultado de importación -->
    <?php if ($resultado): ?>
    <div class="bg-white rounded-xl border-2 <?= $resultado['fallidos'] === 0 ? 'border-emerald-300' : 'border-amber-300' ?> shadow-sm overflow-hidden">
        <div class="px-6 py-4 <?= $resultado['fallidos'] === 0 ? 'bg-emerald-50' : 'bg-amber-50' ?> border-b">
            <h3 class="font-display text-lg font-bold flex items-center gap-2 <?= $resultado['fallidos'] === 0 ? 'text-emerald-900' : 'text-amber-900' ?>">
                <i data-lucide="<?= $resultado['fallidos'] === 0 ? 'check-circle-2' : 'alert-circle' ?>" class="w-5 h-5"></i>
                Importación completada
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-3 gap-3 mb-5">
                <div class="bg-zinc-50 rounded-lg p-3 text-center">
                    <div class="text-[10px] text-zinc-500 uppercase font-bold">Total filas</div>
                    <div class="font-display text-2xl font-extrabold text-zinc-900"><?= (int) $resultado['total'] ?></div>
                </div>
                <div class="bg-emerald-50 rounded-lg p-3 text-center">
                    <div class="text-[10px] text-emerald-700 uppercase font-bold">Exitosos</div>
                    <div class="font-display text-2xl font-extrabold text-emerald-700"><?= (int) $resultado['exitosos'] ?></div>
                </div>
                <div class="bg-bacal-50 rounded-lg p-3 text-center">
                    <div class="text-[10px] text-bacal-700 uppercase font-bold">Fallidos</div>
                    <div class="font-display text-2xl font-extrabold text-bacal-700"><?= (int) $resultado['fallidos'] ?></div>
                </div>
            </div>

            <?php if (!empty($resultado['errores'])): ?>
            <details class="bg-bacal-50 border border-bacal-200 rounded-lg">
                <summary class="cursor-pointer px-4 py-2 text-sm font-semibold text-bacal-900">
                    Ver detalle de errores (<?= count($resultado['errores']) ?>)
                </summary>
                <ul class="p-4 pt-2 text-xs text-bacal-800 space-y-1 max-h-64 overflow-y-auto">
                    <?php foreach ($resultado['errores'] as $err): ?>
                    <li class="font-mono">• <?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>

            <?php if ($resultado['tipo'] === 'usuarios' && $resultado['exitosos'] > 0): ?>
            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-900 flex items-start gap-2">
                <i data-lucide="info" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
                <div>Los usuarios importados tienen contraseña <code class="font-mono bg-blue-100 px-1 rounded">demo1234</code> y deberán cambiarla en su primer login.</div>
            </div>
            <?php endif; ?>

            <div class="mt-5 flex gap-2">
                <a href="<?= url('admin/importar.php') ?>" class="px-4 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
                    <i data-lucide="upload" class="w-4 h-4"></i> Nueva importación
                </a>
                <a href="<?= url('admin/importar_historial.php') ?>" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm font-medium hover:bg-zinc-50 flex items-center gap-1.5">
                    <i data-lucide="history" class="w-4 h-4"></i> Ver historial
                </a>
            </div>
        </div>
    </div>

    <?php elseif ($preview): ?>
    <!-- =========================================================
         PASO 2: PREVIEW DEL ARCHIVO
         ========================================================= -->
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-zinc-100">
            <h3 class="font-display text-lg font-bold text-zinc-900 flex items-center gap-2">
                <i data-lucide="eye" class="w-5 h-5 text-bacal-700"></i> Vista previa
            </h3>
            <p class="text-xs text-zinc-500 mt-1">
                Archivo: <strong><?= e($preview['nombre_archivo']) ?></strong> ·
                Tipo: <strong><?= e($preview['tipo']) ?></strong> ·
                Separador: <code class="font-mono"><?= $preview['separador'] === ';' ? '; (punto y coma)' : ', (coma)' ?></code> ·
                Filas: <strong><?= count($preview['filas']) ?></strong>
            </p>
        </div>

        <?php if (!empty($preview['avisos'])): ?>
        <div class="px-6 py-3 bg-amber-50 border-b border-amber-200">
            <ul class="text-xs text-amber-900 list-disc list-inside">
                <?php foreach ($preview['avisos'] as $av): ?>
                <li><?= $av /* HTML permitido */ ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Tabla con primeras 10 filas -->
        <div class="overflow-x-auto max-h-96">
            <table class="w-full text-xs">
                <thead class="bg-zinc-50 border-b border-zinc-200 sticky top-0">
                    <tr>
                        <th class="px-3 py-2 text-left text-[10px] font-bold text-zinc-500 uppercase">#</th>
                        <?php foreach ($preview['headers'] as $h): ?>
                        <th class="px-3 py-2 text-left text-[10px] font-bold text-zinc-700 uppercase whitespace-nowrap"><?= e($h) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php foreach (array_slice($preview['filas'], 0, 10) as $idx => $fila): ?>
                    <tr class="hover:bg-zinc-50">
                        <td class="px-3 py-2 font-mono text-zinc-400"><?= $idx + 2 ?></td>
                        <?php foreach ($fila as $valor): ?>
                        <td class="px-3 py-2 text-zinc-700 max-w-[200px] truncate" title="<?= e((string) $valor) ?>"><?= e((string) $valor) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($preview['filas']) > 10): ?>
        <div class="px-6 py-2 text-[11px] text-zinc-500 bg-zinc-50 border-t">
            Mostrando 10 de <?= count($preview['filas']) ?> filas. Las demás se procesarán normalmente.
        </div>
        <?php endif; ?>

        <!-- Acciones -->
        <div class="p-6 bg-zinc-50 border-t border-zinc-200 flex justify-between items-center">
            <div class="text-xs text-zinc-700">
                ¿Todo se ve bien? Confirma para procesar las <strong><?= count($preview['filas']) ?></strong> filas.
            </div>
            <div class="flex gap-2">
                <form method="POST" class="inline">
                    <?= csrf_input() ?>
                    <input type="hidden" name="op" value="cancelar">
                    <button type="submit" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm font-medium hover:bg-white">Cancelar</button>
                </form>
                <form method="POST" class="inline" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerHTML = 'Procesando…';">
                    <?= csrf_input() ?>
                    <input type="hidden" name="op" value="confirmar">
                    <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4"></i> Confirmar e importar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- =========================================================
         PASO 1: SELECCIÓN DE TIPO + SUBIDA
         ========================================================= -->

    <!-- Cómo funciona -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3">
        <i data-lucide="info" class="w-5 h-5 text-blue-700 flex-shrink-0 mt-0.5"></i>
        <div class="text-xs text-blue-900 flex-1 leading-relaxed">
            <strong>Pasos:</strong>
            <ol class="list-decimal list-inside mt-1 space-y-0.5">
                <li>Descarga el CSV de ejemplo del tipo que vas a importar.</li>
                <li>Llena el archivo con tus datos (puedes usar Excel y guardar como CSV).</li>
                <li>Súbelo aquí y revisa la vista previa.</li>
                <li>Confirma y el sistema procesará todas las filas, mostrando cuántas fueron exitosas.</li>
            </ol>
        </div>
    </div>

    <!-- Selección de tipo y subida -->
    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="previsualizar">

        <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
            <i data-lucide="upload" class="w-4 h-4 text-bacal-700"></i> 1. Selecciona el tipo de datos
        </h3>

        <!-- Tarjetas de tipo -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6"
             x-data="{ tipo: '<?= e($tipo_actual) ?>' }">

            <label class="cursor-pointer">
                <input type="radio" name="tipo" value="usuarios" class="sr-only peer" x-model="tipo"
                       <?= $tipo_actual === 'usuarios' ? 'checked' : '' ?>>
                <div class="p-4 rounded-lg border-2 transition-all"
                     :class="tipo === 'usuarios' ? 'border-bacal-700 bg-bacal-50' : 'border-zinc-200 hover:border-zinc-300'">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                            <i data-lucide="users" class="w-5 h-5 text-blue-700"></i>
                        </div>
                        <div>
                            <div class="font-display font-bold text-zinc-900">Usuarios</div>
                            <div class="text-[10px] text-zinc-500"><?= count(IMPORTAR_COLUMNAS['usuarios']) ?> columnas</div>
                        </div>
                    </div>
                    <div class="text-[11px] text-zinc-600">Login, nombre, rol, sucursal</div>
                </div>
            </label>

            <label class="cursor-pointer">
                <input type="radio" name="tipo" value="equipos" class="sr-only peer" x-model="tipo"
                       <?= $tipo_actual === 'equipos' ? 'checked' : '' ?>>
                <div class="p-4 rounded-lg border-2 transition-all"
                     :class="tipo === 'equipos' ? 'border-bacal-700 bg-bacal-50' : 'border-zinc-200 hover:border-zinc-300'">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                            <i data-lucide="monitor" class="w-5 h-5 text-purple-700"></i>
                        </div>
                        <div>
                            <div class="font-display font-bold text-zinc-900">Equipos</div>
                            <div class="text-[10px] text-zinc-500"><?= count(IMPORTAR_COLUMNAS['equipos']) ?> columnas</div>
                        </div>
                    </div>
                    <div class="text-[11px] text-zinc-600">Código, marca, modelo, sucursal, área</div>
                </div>
            </label>

            <label class="cursor-pointer">
                <input type="radio" name="tipo" value="incidencias" class="sr-only peer" x-model="tipo"
                       <?= $tipo_actual === 'incidencias' ? 'checked' : '' ?>>
                <div class="p-4 rounded-lg border-2 transition-all"
                     :class="tipo === 'incidencias' ? 'border-bacal-700 bg-bacal-50' : 'border-zinc-200 hover:border-zinc-300'">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                            <i data-lucide="alert-circle" class="w-5 h-5 text-amber-700"></i>
                        </div>
                        <div>
                            <div class="font-display font-bold text-zinc-900">Incidencias</div>
                            <div class="text-[10px] text-zinc-500"><?= count(IMPORTAR_COLUMNAS['incidencias']) ?> columnas</div>
                        </div>
                    </div>
                    <div class="text-[11px] text-zinc-600">Histórico para migrar desde otros sistemas</div>
                </div>
            </label>
        </div>

        <!-- Descarga ejemplos + columnas requeridas -->
        <div class="flex flex-wrap gap-2 mb-6">
            <a href="<?= url('admin/importar_descargar_ejemplo.php?tipo=usuarios') ?>" class="text-xs px-3 py-1.5 rounded-lg border border-zinc-300 hover:bg-zinc-50 text-zinc-700 flex items-center gap-1.5">
                <i data-lucide="download" class="w-3 h-3"></i> Ejemplo usuarios.csv
            </a>
            <a href="<?= url('admin/importar_descargar_ejemplo.php?tipo=equipos') ?>" class="text-xs px-3 py-1.5 rounded-lg border border-zinc-300 hover:bg-zinc-50 text-zinc-700 flex items-center gap-1.5">
                <i data-lucide="download" class="w-3 h-3"></i> Ejemplo equipos.csv
            </a>
            <a href="<?= url('admin/importar_descargar_ejemplo.php?tipo=incidencias') ?>" class="text-xs px-3 py-1.5 rounded-lg border border-zinc-300 hover:bg-zinc-50 text-zinc-700 flex items-center gap-1.5">
                <i data-lucide="download" class="w-3 h-3"></i> Ejemplo incidencias.csv
            </a>
        </div>

        <!-- Documentación de columnas (collapsible) -->
        <details class="mb-6 text-xs">
            <summary class="cursor-pointer font-semibold text-bacal-700 hover:underline">📋 Ver columnas esperadas por tipo</summary>
            <div class="mt-3 space-y-4">
                <?php foreach (IMPORTAR_COLUMNAS as $tip => $cols): ?>
                <div>
                    <div class="font-bold text-zinc-900 capitalize mb-1.5"><?= e($tip) ?></div>
                    <table class="w-full text-[11px] border border-zinc-200">
                        <thead class="bg-zinc-50">
                            <tr>
                                <th class="px-2 py-1 text-left">Columna</th>
                                <th class="px-2 py-1 text-left">Requerida</th>
                                <th class="px-2 py-1 text-left">Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cols as $col => $cfg): ?>
                            <tr class="border-t border-zinc-100">
                                <td class="px-2 py-1 font-mono text-zinc-800"><?= e($col) ?></td>
                                <td class="px-2 py-1"><?= $cfg['requerido'] ? '<span class="text-bacal-700 font-bold">Sí</span>' : '<span class="text-zinc-400">No</span>' ?></td>
                                <td class="px-2 py-1 text-zinc-600"><?= e($cfg['desc']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            </div>
        </details>

        <!-- Sección 2: Archivo -->
        <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2 pt-4 border-t border-zinc-100">
            <i data-lucide="file" class="w-4 h-4 text-bacal-700"></i> 2. Sube el archivo CSV
        </h3>

        <div class="border-2 border-dashed border-zinc-300 rounded-lg p-6 text-center hover:border-bacal-700 transition-colors"
             x-data="{ archivo: null }">
            <input type="file" name="archivo" accept=".csv,.txt" required
                   x-on:change="archivo = $event.target.files[0]"
                   class="block w-full text-sm text-zinc-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-bacal-50 file:text-bacal-700 hover:file:bg-bacal-100">

            <div x-show="archivo" x-cloak class="mt-3 text-xs text-zinc-600">
                <span class="font-mono" x-text="archivo?.name"></span>
                (<span x-text="archivo ? Math.round(archivo.size / 1024) + ' KB' : ''"></span>)
            </div>
            <p class="text-[10px] text-zinc-400 mt-2">Acepta .csv o .txt · Máximo 10 MB · UTF-8 o Windows-1252 · Separador: , o ;</p>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold flex items-center gap-1.5">
                <i data-lucide="eye" class="w-4 h-4"></i> Previsualizar
            </button>
        </div>
    </form>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../config/footer.php'; ?>
