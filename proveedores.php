<?php
/**
 * ============================================================================
 * proveedores.php - Directorio de proveedores
 * ============================================================================
 * Cualquier usuario logueado puede ver el directorio y los detalles.
 * Crear/editar: admin + ingenieros (puede_resolver)
 * Desactivar: solo admin
 * ============================================================================
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requerir_login();

$u_actual = usuario_actual();

// Permisos según la matriz definida
$puede_crear_editar = tiene_permiso('administrar') || tiene_permiso('resolver');
$puede_desactivar   = tiene_permiso('administrar');

$accion = (string) input('accion', 'listar');
$id     = (int) input('id', 0);

// Bloquear acciones según permisos
if ($accion === 'nuevo' && !$puede_crear_editar) {
    flash_set('error', 'No tienes permiso para crear proveedores.');
    header('Location: ' . url('proveedores.php'));
    exit;
}
if ($accion === 'editar' && !$puede_crear_editar) {
    flash_set('error', 'No tienes permiso para editar proveedores.');
    header('Location: ' . url('proveedores.php'));
    exit;
}
if ($accion === 'toggle' && !$puede_desactivar) {
    flash_set('error', 'Solo el administrador puede activar/desactivar proveedores.');
    header('Location: ' . url('proveedores.php'));
    exit;
}

$proveedor_edit = null;
if (in_array($accion, ['editar', 'toggle'], true) && $id > 0) {
    $proveedor_edit = db_one("SELECT * FROM proveedores WHERE id = :id", ['id' => $id]);
    if (!$proveedor_edit) {
        flash_set('error', 'Proveedor no encontrado.');
        header('Location: ' . url('proveedores.php'));
        exit;
    }
}

$errores = [];

// ----------------------------------------------------------------------------
// Procesar POST
// ----------------------------------------------------------------------------
if (es_post()) {
    if (!csrf_valido(input('_csrf'))) {
        $errores[] = 'Token de seguridad inválido.';
    } else {
        $op = (string) input('op', '');

        try {
            if ($op === 'crear' || $op === 'editar') {
                $datos = [
                    'nombre'           => trim((string) input('nombre', '')),
                    'razon_social'     => trim((string) input('razon_social', '')) ?: null,
                    'rfc'              => strtoupper(trim((string) input('rfc', ''))) ?: null,
                    'servicio'         => trim((string) input('servicio', '')) ?: null,
                    'direccion'        => trim((string) input('direccion', '')) ?: null,
                    'telefono'         => trim((string) input('telefono', '')) ?: null,
                    'email'            => trim((string) input('email', '')) ?: null,
                    'sitio_web'        => trim((string) input('sitio_web', '')) ?: null,
                    'horario_atencion' => trim((string) input('horario_atencion', '')) ?: null,
                    'calificacion'     => (int) input('calificacion', 0) ?: null,
                    'notas'            => trim((string) input('notas', '')) ?: null,
                ];

                if ($datos['nombre'] === '') $errores[] = 'El nombre comercial es obligatorio.';

                // Validar email si viene
                if ($datos['email'] && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
                    $errores[] = 'El correo principal no parece válido.';
                }

                // Validar URL si viene
                if ($datos['sitio_web'] && !filter_var($datos['sitio_web'], FILTER_VALIDATE_URL)) {
                    // Intentar agregar https:// si el usuario olvidó el protocolo
                    $url_intento = 'https://' . $datos['sitio_web'];
                    if (filter_var($url_intento, FILTER_VALIDATE_URL)) {
                        $datos['sitio_web'] = $url_intento;
                    } else {
                        $errores[] = 'El sitio web no parece una URL válida.';
                    }
                }

                // Verificar duplicado por nombre
                $check_id = $op === 'editar' ? (int) $proveedor_edit['id'] : 0;
                $dup = db_one("SELECT id FROM proveedores WHERE nombre = :n AND id <> :id",
                    ['n' => $datos['nombre'], 'id' => $check_id]);
                if ($dup) $errores[] = 'Ya existe otro proveedor con ese nombre.';

                if (empty($errores)) {
                    db()->beginTransaction();

                    if ($op === 'crear') {
                        $datos['creado_por_id'] = $u_actual['id'];
                        $datos['activo'] = 1;
                        $cols = implode(', ', array_keys($datos));
                        $params = ':' . implode(', :', array_keys($datos));
                        db_exec("INSERT INTO proveedores ($cols) VALUES ($params)", $datos);
                        $proveedor_id = (int) db_last_id();
                        registrar_auditoria('crear_proveedor', 'proveedores', $proveedor_id, "Proveedor {$datos['nombre']}");
                        $mensaje_exito = "Proveedor \"{$datos['nombre']}\" creado.";
                    } else {
                        $proveedor_id = (int) $proveedor_edit['id'];
                        $sets = [];
                        foreach (array_keys($datos) as $k) $sets[] = "$k = :$k";
                        $datos['id'] = $proveedor_id;
                        db_exec("UPDATE proveedores SET " . implode(', ', $sets) . " WHERE id = :id", $datos);
                        registrar_auditoria('editar_proveedor', 'proveedores', $proveedor_id, "Proveedor {$datos['nombre']}");
                        $mensaje_exito = "Proveedor actualizado.";
                    }

                    // ----------------------------------------------
                    // Procesar contactos múltiples
                    // ----------------------------------------------
                    // Eliminar los anteriores
                    db_exec("DELETE FROM proveedor_contactos WHERE proveedor_id = :pid", ['pid' => $proveedor_id]);

                    $contactos_nombres   = (array) input('contacto_nombre', []);
                    $contactos_puestos   = (array) input('contacto_puesto', []);
                    $contactos_telefonos = (array) input('contacto_telefono', []);
                    $contactos_emails    = (array) input('contacto_email', []);
                    $contactos_notas     = (array) input('contacto_notas', []);
                    $contactos_principal = (int) input('contacto_principal', 0);

                    foreach ($contactos_nombres as $idx => $nom) {
                        $nom = trim((string) $nom);
                        if ($nom === '') continue; // saltar filas vacías

                        db_exec(
                            "INSERT INTO proveedor_contactos
                             (proveedor_id, nombre, puesto, telefono, email, notas, es_principal, orden)
                             VALUES (:pid, :nom, :pue, :tel, :em, :nt, :pri, :ord)",
                            [
                                'pid' => $proveedor_id,
                                'nom' => $nom,
                                'pue' => trim((string) ($contactos_puestos[$idx] ?? '')) ?: null,
                                'tel' => trim((string) ($contactos_telefonos[$idx] ?? '')) ?: null,
                                'em'  => trim((string) ($contactos_emails[$idx] ?? '')) ?: null,
                                'nt'  => trim((string) ($contactos_notas[$idx] ?? '')) ?: null,
                                'pri' => ($idx === $contactos_principal) ? 1 : 0,
                                'ord' => $idx + 1,
                            ]
                        );
                    }

                    // ----------------------------------------------
                    // Procesar marcas (string CSV separado por comas)
                    // ----------------------------------------------
                    db_exec("DELETE FROM proveedor_marcas WHERE proveedor_id = :pid", ['pid' => $proveedor_id]);
                    $marcas_input = trim((string) input('marcas', ''));
                    if ($marcas_input !== '') {
                        $marcas_arr = array_unique(array_filter(array_map('trim', explode(',', $marcas_input))));
                        foreach ($marcas_arr as $marca) {
                            if ($marca === '') continue;
                            db_exec("INSERT IGNORE INTO proveedor_marcas (proveedor_id, marca) VALUES (:pid, :m)",
                                ['pid' => $proveedor_id, 'm' => mb_substr($marca, 0, 100)]);
                        }
                    }

                    // ----------------------------------------------
                    // Procesar tipos de equipo (string CSV)
                    // ----------------------------------------------
                    db_exec("DELETE FROM proveedor_tipos_equipo WHERE proveedor_id = :pid", ['pid' => $proveedor_id]);
                    $tipos_input = trim((string) input('tipos_equipo', ''));
                    if ($tipos_input !== '') {
                        $tipos_arr = array_unique(array_filter(array_map('trim', explode(',', $tipos_input))));
                        foreach ($tipos_arr as $tipo) {
                            if ($tipo === '') continue;
                            db_exec("INSERT IGNORE INTO proveedor_tipos_equipo (proveedor_id, tipo) VALUES (:pid, :t)",
                                ['pid' => $proveedor_id, 't' => mb_substr($tipo, 0, 100)]);
                        }
                    }

                    // ----------------------------------------------
                    // Procesar sucursales (modo: todas = sin filas; específicas = casillas)
                    // ----------------------------------------------
                    db_exec("DELETE FROM proveedor_sucursales WHERE proveedor_id = :pid", ['pid' => $proveedor_id]);
                    $modo_suc = (string) input('modo_suc', 'todas');
                    $sucursales_sel = ($modo_suc === 'especificas') ? (array) input('sucursales', []) : [];
                    foreach ($sucursales_sel as $sid) {
                        $sid = (int) $sid;
                        if ($sid <= 0) continue;
                        db_exec("INSERT IGNORE INTO proveedor_sucursales (proveedor_id, sucursal_id) VALUES (:pid, :sid)",
                            ['pid' => $proveedor_id, 'sid' => $sid]);
                    }

                    db()->commit();
                    flash_set('success', $mensaje_exito);
                    header('Location: ' . url('proveedor_ver.php?id=' . $proveedor_id));
                    exit;
                }
            } elseif ($op === 'toggle' && $proveedor_edit) {
                // Toggle inline (no usamos admin_helpers porque este archivo no requiere ser admin)
                $nuevo = (int) $proveedor_edit['activo'] === 1 ? 0 : 1;
                db_exec("UPDATE proveedores SET activo = :a WHERE id = :id",
                    ['a' => $nuevo, 'id' => $proveedor_edit['id']]);
                registrar_auditoria(
                    $nuevo ? 'activar' : 'desactivar',
                    'proveedores', (int) $proveedor_edit['id'],
                    ($nuevo ? 'Activación' : 'Desactivación') . " de proveedor {$proveedor_edit['nombre']}"
                );
                flash_set('success', "Proveedor {$proveedor_edit['nombre']} " . ($nuevo ? 'activado' : 'desactivado') . '.');
                header('Location: ' . url('proveedores.php'));
                exit;
            }
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $errores[] = 'Error: ' . $e->getMessage();
        }
    }
}

// ----------------------------------------------------------------------------
// Cargar datos del proveedor a editar (contactos, marcas, tipos)
// ----------------------------------------------------------------------------
$contactos_edit = [];
$marcas_edit = '';
$tipos_edit = '';
$sucursales_edit = [];   // ids de sucursales asignadas al proveedor en edición
if ($accion === 'editar' && $proveedor_edit) {
    $contactos_edit = db_all(
        "SELECT * FROM proveedor_contactos WHERE proveedor_id = :pid ORDER BY orden ASC",
        ['pid' => $proveedor_edit['id']]
    );
    $marcas_rows = db_all("SELECT marca FROM proveedor_marcas WHERE proveedor_id = :pid ORDER BY marca",
        ['pid' => $proveedor_edit['id']]);
    $marcas_edit = implode(', ', array_column($marcas_rows, 'marca'));
    $tipos_rows = db_all("SELECT tipo FROM proveedor_tipos_equipo WHERE proveedor_id = :pid ORDER BY tipo",
        ['pid' => $proveedor_edit['id']]);
    $tipos_edit = implode(', ', array_column($tipos_rows, 'tipo'));
    $sucursales_edit = array_map('intval', array_column(
        db_all("SELECT sucursal_id FROM proveedor_sucursales WHERE proveedor_id = :pid", ['pid' => $proveedor_edit['id']]),
        'sucursal_id'
    ));
}

// Catálogo de sucursales activas para las casillas del formulario
$cat_sucursales = db_all("SELECT id, nombre, codigo FROM sucursales WHERE activo = 1 ORDER BY nombre");

$titulo_pagina = 'Proveedores';
$pagina_activa = 'proveedores';
require_once __DIR__ . '/config/header.php';

// ============================================================================
// VISTA: FORMULARIO (crear o editar)
// ============================================================================
if ($accion === 'nuevo' || ($accion === 'editar' && $proveedor_edit)):
    $es_edicion = ($accion === 'editar');
    $p = $proveedor_edit;
?>
<div class="max-w-4xl mx-auto animate-fade-in"
     x-data="formProveedor(<?= htmlspecialchars(json_encode($contactos_edit), ENT_QUOTES) ?>)">

    <div class="flex items-center gap-3 mb-6">
        <a href="<?= url('proveedores.php') ?>" class="p-2 rounded-lg hover:bg-zinc-100 text-zinc-500">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">
                <?= $es_edicion ? 'Editar proveedor' : 'Nuevo proveedor' ?>
            </h2>
            <p class="text-xs text-zinc-500"><?= $es_edicion ? e($p['nombre']) : 'Registra un nuevo proveedor en el directorio' ?></p>
        </div>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="mb-5 px-4 py-3 rounded-lg bg-bacal-50 border border-bacal-200 text-bacal-800 text-sm">
        <ul class="list-disc list-inside text-xs">
            <?php foreach ($errores as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">
        <?= csrf_input() ?>
        <input type="hidden" name="op" value="<?= $es_edicion ? 'editar' : 'crear' ?>">

        <!-- Datos generales -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="building" class="w-4 h-4 text-bacal-700"></i> Datos generales
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Nombre comercial *</label>
                    <input type="text" name="nombre" required maxlength="150"
                           value="<?= e($es_edicion ? $p['nombre'] : (string) input('nombre', '')) ?>"
                           placeholder="ej. Abasteo, enetSystem, Sipcons"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Razón social</label>
                    <input type="text" name="razon_social" maxlength="200"
                           value="<?= e($es_edicion ? (string) $p['razon_social'] : (string) input('razon_social', '')) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">RFC</label>
                    <input type="text" name="rfc" maxlength="20"
                           value="<?= e($es_edicion ? (string) $p['rfc'] : (string) input('rfc', '')) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm font-mono uppercase focus:outline-none focus:border-bacal-700"
                           style="text-transform: uppercase">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Servicio que ofrece</label>
                    <input type="text" name="servicio" maxlength="255"
                           value="<?= e($es_edicion ? (string) $p['servicio'] : (string) input('servicio', '')) ?>"
                           placeholder="ej. Soporte técnico, Proveedor de tecnología, Líneas troncales"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>
        </div>

        <!-- Contacto general y datos web -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="phone" class="w-4 h-4 text-bacal-700"></i> Contacto general
            </h3>
            <p class="text-xs text-zinc-500 mb-4">Estos datos son del proveedor en general. Más abajo puedes agregar contactos individuales.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Teléfono principal</label>
                    <input type="text" name="telefono" maxlength="50"
                           value="<?= e($es_edicion ? (string) $p['telefono'] : (string) input('telefono', '')) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Email principal</label>
                    <input type="email" name="email" maxlength="150"
                           value="<?= e($es_edicion ? (string) $p['email'] : (string) input('email', '')) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Sitio web</label>
                    <input type="text" name="sitio_web" maxlength="200"
                           value="<?= e($es_edicion ? (string) $p['sitio_web'] : (string) input('sitio_web', '')) ?>"
                           placeholder="ej. https://abasteo.mx"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Horario de atención</label>
                    <input type="text" name="horario_atencion" maxlength="255"
                           value="<?= e($es_edicion ? (string) $p['horario_atencion'] : (string) input('horario_atencion', '')) ?>"
                           placeholder="ej. Lun-Vie 9:00-18:00"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Dirección</label>
                    <input type="text" name="direccion" maxlength="255"
                           value="<?= e($es_edicion ? (string) $p['direccion'] : (string) input('direccion', '')) ?>"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                </div>
            </div>
        </div>

        <!-- Contactos múltiples -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-display text-base font-bold text-zinc-900 flex items-center gap-2">
                    <i data-lucide="users" class="w-4 h-4 text-bacal-700"></i> Contactos individuales
                </h3>
                <button type="button" @click="agregarContacto()"
                        class="text-xs font-semibold text-bacal-700 hover:text-bacal-800 flex items-center gap-1">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Agregar contacto
                </button>
            </div>
            <p class="text-xs text-zinc-500 mb-4">Puedes registrar varios contactos por proveedor (útil cuando hay áreas/líneas de producto distintas).</p>

            <div class="space-y-3">
                <template x-for="(c, idx) in contactos" :key="idx">
                    <div class="border border-zinc-200 rounded-lg p-3 bg-zinc-50">
                        <div class="flex items-center justify-between mb-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="contacto_principal" :value="idx"
                                       :checked="c.es_principal == 1"
                                       class="text-bacal-700 focus:ring-bacal-500">
                                <span class="text-xs font-semibold text-zinc-700">Contacto principal</span>
                            </label>
                            <button type="button" @click="eliminarContacto(idx)"
                                    class="text-xs text-zinc-400 hover:text-bacal-700">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <input type="text" :name="'contacto_nombre[]'" x-model="c.nombre"
                                   placeholder="Nombre completo *" required
                                   class="px-3 py-1.5 rounded-md border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                            <input type="text" :name="'contacto_puesto[]'" x-model="c.puesto"
                                   placeholder="Puesto o área (ej. Soporte, Ventas)"
                                   class="px-3 py-1.5 rounded-md border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                            <input type="text" :name="'contacto_telefono[]'" x-model="c.telefono"
                                   placeholder="Teléfono"
                                   class="px-3 py-1.5 rounded-md border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                            <input type="email" :name="'contacto_email[]'" x-model="c.email"
                                   placeholder="Email"
                                   class="px-3 py-1.5 rounded-md border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                            <input type="text" :name="'contacto_notas[]'" x-model="c.notas"
                                   placeholder="Notas (ej. Solo turno matutino, Línea básculas, etc.)"
                                   class="md:col-span-2 px-3 py-1.5 rounded-md border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                        </div>
                    </div>
                </template>
                <div x-show="contactos.length === 0" class="text-center py-4 text-xs text-zinc-400 italic">
                    Sin contactos individuales. Haz clic en "Agregar contacto" para añadir uno.
                </div>
            </div>
        </div>

        <!-- Marcas y tipos -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="package" class="w-4 h-4 text-bacal-700"></i> Marcas y tipos de equipo que maneja
            </h3>
            <p class="text-xs text-zinc-500 mb-4">Informativo. Útil para identificar a qué proveedor llamar según el tipo de equipo afectado.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Marcas</label>
                    <input type="text" name="marcas" maxlength="500"
                           value="<?= e($marcas_edit) ?>"
                           placeholder="ej. HP, Dell, Lenovo, Epson"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                    <p class="text-[10px] text-zinc-500 mt-1">Separa con comas.</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Tipos de equipo</label>
                    <input type="text" name="tipos_equipo" maxlength="500"
                           value="<?= e($tipos_edit) ?>"
                           placeholder="ej. PC, Impresora, Báscula, POS"
                           class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700">
                    <p class="text-[10px] text-zinc-500 mt-1">Separa con comas.</p>
                </div>
            </div>
        </div>

        <!-- Sucursales que atiende -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6"
             x-data="{ modo: '<?= !empty($sucursales_edit) ? 'especificas' : 'todas' ?>' }">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-1 flex items-center gap-2">
                <i data-lucide="store" class="w-4 h-4 text-bacal-700"></i> Sucursales que atiende
            </h3>
            <p class="text-xs text-zinc-500 mb-4">Define en qué sucursales está disponible este proveedor.</p>

            <!-- Toggle Todas / Específicas -->
            <div class="inline-flex bg-zinc-100 rounded-lg p-0.5 border border-zinc-200 mb-4">
                <label class="cursor-pointer">
                    <input type="radio" name="modo_suc" value="todas" x-model="modo" class="sr-only">
                    <span class="block px-4 py-1.5 rounded-md text-xs font-semibold transition-colors"
                          :class="modo === 'todas' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500'">
                        Todas las sucursales
                    </span>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="modo_suc" value="especificas" x-model="modo" class="sr-only">
                    <span class="block px-4 py-1.5 rounded-md text-xs font-semibold transition-colors"
                          :class="modo === 'especificas' ? 'bg-white text-zinc-900 shadow-sm' : 'text-zinc-500'">
                        Solo algunas
                    </span>
                </label>
            </div>

            <!-- Mensaje modo "todas" -->
            <p x-show="modo === 'todas'" class="text-xs text-zinc-600 bg-zinc-50 border border-zinc-200 rounded-lg px-3 py-2">
                Este proveedor estará disponible para <strong>todas</strong> las sucursales (actuales y futuras).
            </p>

            <!-- Casillas (solo si específicas) -->
            <div x-show="modo === 'especificas'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                <?php foreach ($cat_sucursales as $s): ?>
                <label class="flex items-center gap-2.5 px-3 py-2 rounded-lg border border-zinc-200 hover:bg-zinc-50 cursor-pointer">
                    <input type="checkbox" name="sucursales[]" value="<?= $s['id'] ?>"
                           <?= in_array((int) $s['id'], $sucursales_edit, true) ? 'checked' : '' ?>
                           class="rounded border-zinc-300 text-bacal-700 focus:ring-bacal-700">
                    <span class="text-sm text-zinc-700">
                        <?= e($s['nombre']) ?>
                        <?php if (!empty($s['codigo'])): ?><span class="text-[10px] text-zinc-400 font-mono">(<?= e($s['codigo']) ?>)</span><?php endif; ?>
                    </span>
                </label>
                <?php endforeach; ?>
                <?php if (empty($cat_sucursales)): ?>
                <p class="text-xs text-zinc-500 col-span-full">Aún no hay sucursales dadas de alta.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Calificación y notas -->
        <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-6">
            <h3 class="font-display text-base font-bold text-zinc-900 mb-4 flex items-center gap-2">
                <i data-lucide="star" class="w-4 h-4 text-bacal-700"></i> Evaluación y notas
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-2 uppercase tracking-wide">Calificación</label>
                    <div class="flex items-center gap-1" x-data="{ cal: <?= (int) ($es_edicion ? $p['calificacion'] : 0) ?> }">
                        <input type="hidden" name="calificacion" :value="cal">
                        <template x-for="n in 5" :key="n">
                            <button type="button" @click="cal = (cal === n ? 0 : n)"
                                    class="p-1 transition-transform hover:scale-110">
                                <i data-lucide="star" class="w-7 h-7 transition-colors"
                                   :class="n <= cal ? 'fill-amber-400 text-amber-400' : 'text-zinc-300'"
                                   :style="n <= cal ? 'fill: #FBBF24' : ''"></i>
                            </button>
                        </template>
                        <button type="button" @click="cal = 0" class="ml-2 text-[10px] text-zinc-400 hover:text-zinc-700">limpiar</button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-zinc-700 mb-1 uppercase tracking-wide">Notas internas</label>
                    <textarea name="notas" rows="3"
                              class="w-full px-3 py-2 rounded-lg border border-zinc-300 text-sm focus:outline-none focus:border-bacal-700"
                              placeholder="ej. Buen tiempo de respuesta, garantiza piezas, etc."><?= e($es_edicion ? (string) $p['notas'] : (string) input('notas', '')) ?></textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="<?= url('proveedores.php') ?>" class="px-4 py-2 rounded-lg border border-zinc-300 text-zinc-700 text-sm">Cancelar</a>
            <button type="submit" class="px-5 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
                <?= $es_edicion ? 'Guardar cambios' : 'Crear proveedor' ?>
            </button>
        </div>
    </form>
</div>

<script>
function formProveedor(contactosIniciales) {
    return {
        contactos: contactosIniciales.length > 0 ? contactosIniciales.map(c => ({
            nombre: c.nombre || '',
            puesto: c.puesto || '',
            telefono: c.telefono || '',
            email: c.email || '',
            notas: c.notas || '',
            es_principal: c.es_principal || 0,
        })) : [],
        agregarContacto() {
            this.contactos.push({
                nombre: '', puesto: '', telefono: '', email: '', notas: '',
                es_principal: this.contactos.length === 0 ? 1 : 0
            });
        },
        eliminarContacto(idx) {
            if (confirm('¿Quitar este contacto?')) this.contactos.splice(idx, 1);
        }
    }
}
</script>

<?php
// ============================================================================
// VISTA: LISTADO
// ============================================================================
else:
    $q = trim((string) input('q', ''));
    $where = ['1=1'];
    $params = [];
    if ($q !== '') {
        $where[] = "(p.nombre LIKE :q1 OR p.servicio LIKE :q2 OR p.email LIKE :q3 OR p.razon_social LIKE :q4)";
        $params['q1'] = "%$q%"; $params['q2'] = "%$q%"; $params['q3'] = "%$q%"; $params['q4'] = "%$q%";
    }

    // Filtro por sucursal (botón). Incluye los proveedores "globales" (sin asignación = todas).
    $ver_todas = tiene_permiso('ver_todas_sucursales');
    $f_sucursal = (int) input('sucursal', 0);
    if (!$ver_todas && !empty($u_actual['sucursal_id'])) {
        $f_sucursal = (int) $u_actual['sucursal_id']; // usuario de una sola sucursal: forzar la suya
    }
    if ($f_sucursal > 0) {
        $where[] = "(EXISTS (SELECT 1 FROM proveedor_sucursales psf WHERE psf.proveedor_id = p.id AND psf.sucursal_id = :fsuc)
                     OR NOT EXISTS (SELECT 1 FROM proveedor_sucursales psn WHERE psn.proveedor_id = p.id))";
        $params['fsuc'] = $f_sucursal;
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where);

    $proveedores = db_all(
        "SELECT p.*,
                (SELECT COUNT(*) FROM equipos WHERE proveedor_id = p.id AND activo = 1) AS equipos_count,
                (SELECT COUNT(*) FROM incidencias WHERE proveedor_escalado_id = p.id) AS incidencias_count,
                (SELECT GROUP_CONCAT(DISTINCT tipo SEPARATOR ', ')
                 FROM proveedor_tipos_equipo WHERE proveedor_id = p.id LIMIT 5) AS tipos_resumen,
                (SELECT GROUP_CONCAT(s.codigo ORDER BY s.codigo SEPARATOR ', ')
                 FROM proveedor_sucursales ps JOIN sucursales s ON ps.sucursal_id = s.id
                 WHERE ps.proveedor_id = p.id) AS sucursales_resumen
         FROM proveedores p
         $where_sql
         ORDER BY p.activo DESC, p.nombre ASC",
        $params
    );
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <div>
        <div class="flex items-center gap-3 flex-wrap">
            <h2 class="font-display text-2xl font-extrabold text-zinc-900">Proveedores</h2>
            <?php if (tiene_permiso('ver_todas_sucursales') && count($cat_sucursales) > 1 && usuario_prefiere_radio_sucursal()): ?>
            <form method="GET" class="flex items-center gap-2 flex-wrap">
                <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
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
        </div>
        <p class="text-xs text-zinc-500 mt-0.5">Directorio de proveedores de servicios. <?= count($proveedores) ?> registro(s).</p>
    </div>
    <?php if ($puede_crear_editar): ?>
    <a href="<?= url('proveedores.php?accion=nuevo') ?>"
       class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold shadow-sm transition-colors">
        <i data-lucide="plus" class="w-4 h-4"></i> Nuevo proveedor
    </a>
    <?php endif; ?>
</div>

<!-- Buscador + toggle de vista -->
<div x-data="{
    vista: localStorage.getItem('proveedores_vista') || 'tarjetas',
    cambiar(v) { this.vista = v; localStorage.setItem('proveedores_vista', v); }
}">

<div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
    <form method="GET" class="flex-1 max-w-md">
        <?php if ($ver_todas && $f_sucursal > 0): ?>
        <input type="hidden" name="sucursal" value="<?= (int) $f_sucursal ?>">
        <?php endif; ?>
        <div class="relative">
            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400"></i>
            <input type="text" name="q" value="<?= e($q) ?>"
                   placeholder="Buscar por nombre, servicio, razón social o email..."
                   class="w-full pl-9 pr-3 py-2 rounded-lg border border-zinc-300 bg-white text-sm focus:outline-none focus:border-bacal-700">
        </div>
    </form>

    <!-- Sucursal + vista (agrupados a la derecha) -->
    <div class="flex items-center gap-2 flex-wrap">

    <!-- Selector rápido de sucursal (como en bitácora) -->
    <?php if ($ver_todas && !usuario_prefiere_radio_sucursal()): ?>
    <form method="GET" class="relative">
        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
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

    <!-- Toggle de vista -->
    <div class="inline-flex rounded-lg border border-zinc-300 bg-white p-0.5 shadow-sm">
        <button type="button" @click="cambiar('tarjetas')"
                :class="vista === 'tarjetas' ? 'bg-bacal-700 text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-50'"
                class="px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors">
            <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
            Tarjetas
        </button>
        <button type="button" @click="cambiar('lista')"
                :class="vista === 'lista' ? 'bg-bacal-700 text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-50'"
                class="px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-colors">
            <i data-lucide="list" class="w-3.5 h-3.5"></i>
            Lista
        </button>
    </div>
    </div>
</div>

<!-- Tarjetas de proveedores -->
<!-- VISTA TARJETAS -->
<div x-show="vista === 'tarjetas'" x-cloak class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($proveedores as $p): ?>
    <div class="bg-white rounded-xl border border-zinc-200 shadow-sm p-5 hover:shadow-md transition-shadow group <?= !$p['activo'] ? 'opacity-50' : '' ?>">

        <!-- Header con ícono y nombre -->
        <div class="flex items-start gap-3 mb-3">
            <div class="w-11 h-11 rounded-lg bg-bacal-700 text-white flex items-center justify-center font-display font-bold flex-shrink-0">
                <?= e(strtoupper(substr($p['nombre'], 0, 2))) ?>
            </div>
            <div class="flex-1 min-w-0">
                <a href="<?= url('proveedor_ver.php?id=' . $p['id']) ?>" class="font-display font-bold text-base text-zinc-900 hover:text-bacal-700 truncate block">
                    <?= e($p['nombre']) ?>
                </a>
                <?php if ($p['servicio']): ?>
                <div class="text-xs text-zinc-500 truncate"><?= e($p['servicio']) ?></div>
                <?php endif; ?>

                <?php if ($p['calificacion']): ?>
                <div class="flex items-center gap-0.5 mt-1">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i data-lucide="star" class="w-3 h-3 <?= $i <= (int) $p['calificacion'] ? 'fill-amber-400 text-amber-400' : 'text-zinc-300' ?>"
                       style="<?= $i <= (int) $p['calificacion'] ? 'fill: #FBBF24' : '' ?>"></i>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
                <div class="text-[10px] text-zinc-400 mt-1 flex items-center gap-1">
                    <i data-lucide="store" class="w-3 h-3"></i>
                    <?= $p['sucursales_resumen'] ? e($p['sucursales_resumen']) : 'Todas las sucursales' ?>
                </div>
            </div>

            <!-- Acciones -->
            <?php if ($puede_crear_editar || $puede_desactivar): ?>
            <div class="flex flex-col gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <?php if ($puede_crear_editar): ?>
                <a href="<?= url('proveedores.php?accion=editar&id=' . $p['id']) ?>"
                   class="p-1.5 rounded text-zinc-500 hover:bg-zinc-100" title="Editar">
                    <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                </a>
                <?php endif; ?>
                <?php if ($puede_desactivar): ?>
                <form method="POST" action="<?= url('proveedores.php?accion=toggle&id=' . $p['id']) ?>"
                      onsubmit="return confirm('¿<?= $p['activo'] ? 'Desactivar' : 'Activar' ?> este proveedor?');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="op" value="toggle">
                    <button type="submit" class="p-1.5 rounded text-zinc-500 hover:bg-zinc-100"
                            title="<?= $p['activo'] ? 'Desactivar' : 'Activar' ?>">
                        <i data-lucide="<?= $p['activo'] ? 'power' : 'power-off' ?>" class="w-3.5 h-3.5"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Datos de contacto -->
        <div class="space-y-1.5 mb-3">
            <?php if ($p['email']): ?>
            <a href="mailto:<?= e($p['email']) ?>" class="flex items-center gap-2 text-xs text-zinc-600 hover:text-bacal-700 truncate">
                <i data-lucide="mail" class="w-3.5 h-3.5 flex-shrink-0 text-zinc-400"></i>
                <span class="truncate"><?= e($p['email']) ?></span>
            </a>
            <?php endif; ?>
            <?php if ($p['telefono']): ?>
            <a href="tel:<?= e($p['telefono']) ?>" class="flex items-center gap-2 text-xs text-zinc-600 hover:text-bacal-700">
                <i data-lucide="phone" class="w-3.5 h-3.5 flex-shrink-0 text-zinc-400"></i>
                <span><?= e($p['telefono']) ?></span>
            </a>
            <?php endif; ?>
            <?php if ($p['sitio_web']): ?>
            <a href="<?= e($p['sitio_web']) ?>" target="_blank" rel="noopener" class="flex items-center gap-2 text-xs text-zinc-600 hover:text-bacal-700 truncate">
                <i data-lucide="globe" class="w-3.5 h-3.5 flex-shrink-0 text-zinc-400"></i>
                <span class="truncate"><?= e(preg_replace('#^https?://(www\.)?#', '', $p['sitio_web'])) ?></span>
            </a>
            <?php endif; ?>
        </div>

        <?php if ($p['tipos_resumen']): ?>
        <div class="text-[10px] text-zinc-500 mb-3 line-clamp-1">
            <i data-lucide="package" class="w-3 h-3 inline -mt-0.5"></i>
            <?= e($p['tipos_resumen']) ?>
        </div>
        <?php endif; ?>

        <!-- KPIs -->
        <div class="grid grid-cols-2 gap-2 pt-3 border-t border-zinc-100">
            <div class="text-center">
                <div class="font-display text-lg font-bold text-zinc-900"><?= $p['equipos_count'] ?></div>
                <div class="text-[10px] text-zinc-500 uppercase tracking-wide">Equipos</div>
            </div>
            <div class="text-center">
                <div class="font-display text-lg font-bold text-zinc-900"><?= $p['incidencias_count'] ?></div>
                <div class="text-[10px] text-zinc-500 uppercase tracking-wide">Escalados</div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($proveedores)): ?>
    <div class="col-span-full text-center py-12 bg-white rounded-xl border border-zinc-200">
        <div class="w-16 h-16 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-3">
            <i data-lucide="search-x" class="w-8 h-8 text-zinc-400"></i>
        </div>
        <p class="text-sm font-medium text-zinc-700"><?= $q !== '' ? 'Sin resultados' : 'Sin proveedores registrados' ?></p>
        <?php if ($puede_crear_editar && $q === ''): ?>
        <a href="<?= url('proveedores.php?accion=nuevo') ?>"
           class="mt-4 inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
            <i data-lucide="plus" class="w-4 h-4"></i> Agregar primero
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- VISTA LISTA -->
<div x-show="vista === 'lista'" x-cloak class="bg-white rounded-xl border border-zinc-200 shadow-sm overflow-hidden">
    <?php if (empty($proveedores)): ?>
    <div class="text-center py-12">
        <div class="w-16 h-16 mx-auto rounded-full bg-zinc-100 flex items-center justify-center mb-3">
            <i data-lucide="search-x" class="w-8 h-8 text-zinc-400"></i>
        </div>
        <p class="text-sm font-medium text-zinc-700"><?= $q !== '' ? 'Sin resultados' : 'Sin proveedores registrados' ?></p>
        <?php if ($puede_crear_editar && $q === ''): ?>
        <a href="<?= url('proveedores.php?accion=nuevo') ?>"
           class="mt-4 inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-bacal-700 hover:bg-bacal-800 text-white text-sm font-semibold">
            <i data-lucide="plus" class="w-4 h-4"></i> Agregar primero
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 border-b border-zinc-200">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Proveedor</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Servicio</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Contacto</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Calificación</th>
                    <th class="px-4 py-2.5 text-center text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Equipos</th>
                    <th class="px-4 py-2.5 text-center text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Escalados</th>
                    <th class="px-4 py-2.5 text-left text-[10px] font-bold text-zinc-500 uppercase tracking-wider">Estado</th>
                    <?php if ($puede_crear_editar || $puede_desactivar): ?>
                    <th class="px-4 py-2.5"></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                <?php foreach ($proveedores as $p): ?>
                <tr class="hover:bg-zinc-50 <?= !$p['activo'] ? 'opacity-50' : '' ?>">
                    <!-- Proveedor -->
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-md bg-bacal-700 text-white flex items-center justify-center font-display font-bold text-xs flex-shrink-0">
                                <?= e(strtoupper(substr($p['nombre'], 0, 2))) ?>
                            </div>
                            <div class="min-w-0">
                                <a href="<?= url('proveedor_ver.php?id=' . $p['id']) ?>"
                                   class="font-semibold text-sm text-zinc-900 hover:text-bacal-700">
                                    <?= e($p['nombre']) ?>
                                </a>
                                <div class="text-[10px] text-zinc-400 flex items-center gap-1">
                                    <i data-lucide="store" class="w-3 h-3"></i>
                                    <?= $p['sucursales_resumen'] ? e($p['sucursales_resumen']) : 'Todas' ?>
                                </div>
                            </div>
                        </div>
                    </td>

                    <!-- Servicio -->
                    <td class="px-4 py-2.5 text-xs text-zinc-700">
                        <?php if ($p['servicio']): ?>
                            <?= e($p['servicio']) ?>
                        <?php else: ?>
                            <span class="text-zinc-400">—</span>
                        <?php endif; ?>
                        <?php if ($p['tipos_resumen']): ?>
                        <div class="text-[10px] text-zinc-500 truncate max-w-[200px]" title="<?= e($p['tipos_resumen']) ?>">
                            <i data-lucide="package" class="w-2.5 h-2.5 inline -mt-0.5"></i>
                            <?= e($p['tipos_resumen']) ?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <!-- Contacto -->
                    <td class="px-4 py-2.5 text-xs">
                        <?php if ($p['email']): ?>
                        <a href="mailto:<?= e($p['email']) ?>" class="flex items-center gap-1 text-zinc-600 hover:text-bacal-700">
                            <i data-lucide="mail" class="w-3 h-3 text-zinc-400"></i>
                            <span class="truncate max-w-[160px]"><?= e($p['email']) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php if ($p['telefono']): ?>
                        <a href="tel:<?= e($p['telefono']) ?>" class="flex items-center gap-1 text-zinc-600 hover:text-bacal-700 mt-0.5">
                            <i data-lucide="phone" class="w-3 h-3 text-zinc-400"></i>
                            <span><?= e($p['telefono']) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php if (!$p['email'] && !$p['telefono']): ?>
                        <span class="text-zinc-400">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Calificación -->
                    <td class="px-4 py-2.5">
                        <?php if ($p['calificacion']): ?>
                        <div class="flex items-center gap-0.5">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i data-lucide="star" class="w-3 h-3 <?= $i <= (int) $p['calificacion'] ? 'fill-amber-400 text-amber-400' : 'text-zinc-300' ?>"
                               style="<?= $i <= (int) $p['calificacion'] ? 'fill: #FBBF24' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <?php else: ?>
                        <span class="text-zinc-400 text-xs">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Equipos -->
                    <td class="px-4 py-2.5 text-center">
                        <span class="font-display font-bold text-sm text-zinc-900"><?= $p['equipos_count'] ?></span>
                    </td>

                    <!-- Escalados -->
                    <td class="px-4 py-2.5 text-center">
                        <span class="font-display font-bold text-sm text-zinc-900"><?= $p['incidencias_count'] ?></span>
                    </td>

                    <!-- Estado -->
                    <td class="px-4 py-2.5">
                        <?php if ($p['activo']): ?>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded uppercase text-emerald-700 bg-emerald-50">
                            <i data-lucide="check" class="w-3 h-3"></i> Activo
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded uppercase text-zinc-600 bg-zinc-100">
                            <i data-lucide="power-off" class="w-3 h-3"></i> Inactivo
                        </span>
                        <?php endif; ?>
                    </td>

                    <!-- Acciones -->
                    <?php if ($puede_crear_editar || $puede_desactivar): ?>
                    <td class="px-4 py-2.5 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <?php if ($puede_crear_editar): ?>
                            <a href="<?= url('proveedores.php?accion=editar&id=' . $p['id']) ?>"
                               class="p-1.5 rounded text-zinc-500 hover:bg-zinc-100 hover:text-bacal-700" title="Editar">
                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($puede_desactivar): ?>
                            <form method="POST" action="<?= url('proveedores.php?accion=toggle&id=' . $p['id']) ?>"
                                  onsubmit="return confirm('¿<?= $p['activo'] ? 'Desactivar' : 'Activar' ?> este proveedor?');"
                                  class="inline">
                                <?= csrf_input() ?>
                                <input type="hidden" name="op" value="toggle">
                                <button type="submit" class="p-1.5 rounded text-zinc-500 hover:bg-zinc-100 hover:text-bacal-700"
                                        title="<?= $p['activo'] ? 'Desactivar' : 'Activar' ?>">
                                    <i data-lucide="<?= $p['activo'] ? 'power' : 'power-off' ?>" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</div><!-- /Alpine vista -->

<?php endif; ?>

<?php require_once __DIR__ . '/config/footer.php'; ?>
