<?php
/**
 * ============================================================================
 * config/vault_helpers.php
 * ============================================================================
 * Lógica completa del módulo Bóveda:
 *   - Cifrado/descifrado AES-256 de contraseñas
 *   - Permisos granulares por entrada (todos / rol / sucursal / usuarios / admin)
 *   - Auditoría de accesos
 *   - Favoritos por usuario
 *   - Historial de cambios
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

// ============================================================================
// CONFIGURACIÓN DE CIFRADO
// ============================================================================
// La clave maestra se deriva de una constante en db.php. Si no existe, usa una
// derivada del nombre de la base de datos (mejor que nada). Se RECOMIENDA
// definir VAULT_KEY en config/db.php para mayor seguridad.

if (!defined('VAULT_KEY')) {
    // Clave por defecto derivada (no ideal, pero funcional)
    define('VAULT_KEY', hash('sha256', 'carnes_bacal_vault_2026_default_key', true));
}

const VAULT_CIPHER = 'aes-256-cbc';


// ============================================================================
// CIFRADO Y DESCIFRADO
// ============================================================================

/**
 * Cifra una cadena con AES-256-CBC. Retorna base64(iv + ciphertext).
 */
function vault_cifrar(?string $plaintext): ?string {
    if ($plaintext === null || $plaintext === '') return null;

    $ivlen = openssl_cipher_iv_length(VAULT_CIPHER);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $cipher = openssl_encrypt($plaintext, VAULT_CIPHER, VAULT_KEY, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) return null;

    return base64_encode($iv . $cipher);
}


/**
 * Descifra una cadena cifrada con vault_cifrar().
 */
function vault_descifrar(?string $cifrado): ?string {
    if ($cifrado === null || $cifrado === '') return null;

    $raw = base64_decode($cifrado, true);
    if ($raw === false) return null;

    $ivlen = openssl_cipher_iv_length(VAULT_CIPHER);
    if (strlen($raw) < $ivlen) return null;

    $iv = substr($raw, 0, $ivlen);
    $cipher = substr($raw, $ivlen);
    $plain = openssl_decrypt($cipher, VAULT_CIPHER, VAULT_KEY, OPENSSL_RAW_DATA, $iv);

    return $plain === false ? null : $plain;
}


// ============================================================================
// CATEGORÍAS
// ============================================================================

function vault_listar_categorias(): array {
    return db_all(
        "SELECT * FROM vault_categorias
         WHERE activo = 1
         ORDER BY familia_orden ASC, orden ASC"
    );
}


function vault_categorias_agrupadas(): array {
    $cats = vault_listar_categorias();
    $grupos = [];
    foreach ($cats as $c) {
        $f = $c['familia'];
        if (!isset($grupos[$f])) {
            $grupos[$f] = [
                'familia' => $f,
                'familia_orden' => (int) $c['familia_orden'],
                'categorias' => [],
            ];
        }
        $grupos[$f]['categorias'][] = $c;
    }
    return array_values($grupos);
}


function vault_obtener_categoria(int $id): ?array {
    $r = db_one("SELECT * FROM vault_categorias WHERE id = :id", ['id' => $id]);
    return $r ?: null;
}


// ============================================================================
// PERMISOS
// ============================================================================

/**
 * Determina si un usuario puede ver una entrada específica.
 */
function vault_usuario_puede_ver(array $entrada, array $usuario): bool {
    $es_admin = isset($usuario['puede_administrar']) && $usuario['puede_administrar'];
    if ($es_admin) return true; // admin siempre ve

    switch ($entrada['permisos_tipo']) {
        case 'todos':
            return true;
        case 'admin':
            return false; // ya filtramos admin arriba

        case 'rol':
            $rol_id = (int) ($usuario['rol_id'] ?? 0);
            $r = db_one(
                "SELECT 1 FROM vault_permisos
                 WHERE entrada_id = :eid AND tipo = 'rol' AND referencia_id = :rid LIMIT 1",
                ['eid' => $entrada['id'], 'rid' => $rol_id]
            );
            return $r !== null;

        case 'sucursal':
            $suc_id = (int) ($usuario['sucursal_id'] ?? 0);
            if (!$suc_id) return false;
            $r = db_one(
                "SELECT 1 FROM vault_permisos
                 WHERE entrada_id = :eid AND tipo = 'sucursal' AND referencia_id = :sid LIMIT 1",
                ['eid' => $entrada['id'], 'sid' => $suc_id]
            );
            return $r !== null;

        case 'usuarios':
            $uid = (int) ($usuario['id'] ?? 0);
            $r = db_one(
                "SELECT 1 FROM vault_permisos
                 WHERE entrada_id = :eid AND tipo = 'usuario' AND referencia_id = :uid LIMIT 1",
                ['eid' => $entrada['id'], 'uid' => $uid]
            );
            return $r !== null;
    }
    return false;
}


/**
 * Aplica filtros de permisos en SQL: devuelve cláusula WHERE adicional + params.
 */
function vault_clausula_permisos(array $usuario): array {
    $es_admin = isset($usuario['puede_administrar']) && $usuario['puede_administrar'];
    if ($es_admin) return ['sql' => '', 'params' => []];

    $rol_id = (int) ($usuario['rol_id'] ?? 0);
    $suc_id = (int) ($usuario['sucursal_id'] ?? 0);
    $uid = (int) ($usuario['id'] ?? 0);

    $sql = " AND (
        e.permisos_tipo = 'todos'
        OR (e.permisos_tipo = 'rol' AND EXISTS (
            SELECT 1 FROM vault_permisos vp
            WHERE vp.entrada_id = e.id AND vp.tipo = 'rol' AND vp.referencia_id = :p_rol
        ))
        OR (e.permisos_tipo = 'sucursal' AND EXISTS (
            SELECT 1 FROM vault_permisos vp
            WHERE vp.entrada_id = e.id AND vp.tipo = 'sucursal' AND vp.referencia_id = :p_suc
        ))
        OR (e.permisos_tipo = 'usuarios' AND EXISTS (
            SELECT 1 FROM vault_permisos vp
            WHERE vp.entrada_id = e.id AND vp.tipo = 'usuario' AND vp.referencia_id = :p_uid
        ))
    )";

    return [
        'sql' => $sql,
        'params' => ['p_rol' => $rol_id, 'p_suc' => $suc_id, 'p_uid' => $uid],
    ];
}


/**
 * Guarda los permisos granulares para una entrada (borra previos e inserta nuevos).
 */
function vault_guardar_permisos(int $entrada_id, string $tipo, array $referencias_ids): void {
    db_exec("DELETE FROM vault_permisos WHERE entrada_id = :eid", ['eid' => $entrada_id]);

    if (!in_array($tipo, ['rol', 'sucursal', 'usuario'], true)) return;
    if (empty($referencias_ids)) return;

    foreach ($referencias_ids as $rid) {
        $rid = (int) $rid;
        if ($rid <= 0) continue;
        try {
            db_exec(
                "INSERT INTO vault_permisos (entrada_id, tipo, referencia_id)
                 VALUES (:eid, :t, :rid)",
                ['eid' => $entrada_id, 't' => $tipo, 'rid' => $rid]
            );
        } catch (Throwable $e) { /* duplicado, ignorar */ }
    }
}


/**
 * Lista los IDs de referencia (roles/usuarios/sucursales) con permiso para una entrada.
 */
function vault_obtener_permisos(int $entrada_id, string $tipo): array {
    $rows = db_all(
        "SELECT referencia_id FROM vault_permisos
         WHERE entrada_id = :eid AND tipo = :t
         ORDER BY referencia_id ASC",
        ['eid' => $entrada_id, 't' => $tipo]
    );
    return array_map(fn($r) => (int) $r['referencia_id'], $rows);
}


// ============================================================================
// ENTRADAS
// ============================================================================

/**
 * Lista las entradas visibles para un usuario, con filtros opcionales.
 */
function vault_listar_entradas(array $usuario, array $filtros = []): array {
    $where = ["e.activo = 1"];
    $params = [];

    if (!empty($filtros['categoria_id'])) {
        $where[] = "e.categoria_id = :cid";
        $params['cid'] = (int) $filtros['categoria_id'];
    }

    if (!empty($filtros['familia'])) {
        $where[] = "c.familia = :fam";
        $params['fam'] = $filtros['familia'];
    }

    if (!empty($filtros['busqueda'])) {
        $like = '%' . $filtros['busqueda'] . '%';
        $where[] = "(e.nombre LIKE :q1 OR e.notas LIKE :q2 OR e.tags LIKE :q3 OR e.usuario LIKE :q4)";
        $params['q1'] = $like;
        $params['q2'] = $like;
        $params['q3'] = $like;
        $params['q4'] = $like;
    }

    if (!empty($filtros['solo_favoritos']) && !empty($usuario['id'])) {
        $where[] = "EXISTS (
            SELECT 1 FROM vault_favoritos vf
            WHERE vf.entrada_id = e.id AND vf.usuario_id = :uid_fav
        )";
        $params['uid_fav'] = (int) $usuario['id'];
    }

    if (!empty($filtros['sucursal_id'])) {
        $where[] = "(e.sucursal_id = :sid OR e.sucursal_id IS NULL)";
        $params['sid'] = (int) $filtros['sucursal_id'];
    }

    // Aplicar permisos
    $perm = vault_clausula_permisos($usuario);
    $where_sql = "WHERE " . implode(' AND ', $where);
    $params = array_merge($params, $perm['params']);

    return db_all(
        "SELECT e.id, e.nombre, e.categoria_id, e.url, e.usuario, e.notas,
                e.version_build, e.vencimiento, e.tags, e.sensibilidad,
                e.permisos_tipo, e.actualizado_en,
                CASE WHEN e.password_cifrado IS NOT NULL AND e.password_cifrado <> '' THEN 1 ELSE 0 END AS tiene_password,
                c.familia, c.nombre AS categoria_nombre, c.icono AS categoria_icono, c.color AS categoria_color,
                s.codigo AS sucursal_codigo,
                u.nombre_completo AS actualizado_por_nombre,
                CASE WHEN EXISTS (
                    SELECT 1 FROM vault_favoritos vf
                    WHERE vf.entrada_id = e.id AND vf.usuario_id = :uid_main
                ) THEN 1 ELSE 0 END AS es_favorito
         FROM vault_entradas e
         INNER JOIN vault_categorias c ON e.categoria_id = c.id
         LEFT JOIN sucursales s ON e.sucursal_id = s.id
         LEFT JOIN usuarios u ON e.actualizado_por_id = u.id
         $where_sql {$perm['sql']}
         ORDER BY c.familia_orden ASC, c.orden ASC, e.nombre ASC",
        array_merge($params, ['uid_main' => (int) ($usuario['id'] ?? 0)])
    );
}


/**
 * Obtiene una entrada completa por ID (sin descifrar el password).
 */
function vault_obtener_entrada(int $id): ?array {
    $e = db_one(
        "SELECT e.*,
                c.familia, c.nombre AS categoria_nombre, c.icono AS categoria_icono, c.color AS categoria_color,
                s.codigo AS sucursal_codigo, s.nombre AS sucursal_nombre,
                uc.nombre_completo AS creado_por_nombre,
                ua.nombre_completo AS actualizado_por_nombre,
                CASE WHEN e.password_cifrado IS NOT NULL AND e.password_cifrado <> '' THEN 1 ELSE 0 END AS tiene_password
         FROM vault_entradas e
         INNER JOIN vault_categorias c ON e.categoria_id = c.id
         LEFT JOIN sucursales s ON e.sucursal_id = s.id
         LEFT JOIN usuarios uc ON e.creado_por_id = uc.id
         LEFT JOIN usuarios ua ON e.actualizado_por_id = ua.id
         WHERE e.id = :id",
        ['id' => $id]
    );
    return $e ?: null;
}


function vault_crear_entrada(array $datos, int $usuario_id): int {
    $password_cifrado = isset($datos['password']) && $datos['password'] !== ''
        ? vault_cifrar($datos['password'])
        : null;

    db_exec(
        "INSERT INTO vault_entradas
         (categoria_id, nombre, url, usuario, password_cifrado, notas, archivos,
          version_build, vencimiento, tags, sucursal_id, sensibilidad, permisos_tipo,
          creado_por_id, actualizado_por_id)
         VALUES
         (:cid, :n, :url, :user, :pwd, :notas, :archivos,
          :ver, :venc, :tags, :sid, :sens, :perm,
          :uid, :uid)",
        [
            'cid' => (int) $datos['categoria_id'],
            'n' => mb_substr($datos['nombre'], 0, 200),
            'url' => $datos['url'] ?? null,
            'user' => $datos['usuario'] ?? null,
            'pwd' => $password_cifrado,
            'notas' => $datos['notas'] ?? null,
            'archivos' => $datos['archivos'] ?? null,
            'ver' => $datos['version_build'] ?? null,
            'venc' => $datos['vencimiento'] ?? null,
            'tags' => $datos['tags'] ?? null,
            'sid' => $datos['sucursal_id'] ?: null,
            'sens' => $datos['sensibilidad'] ?? 'normal',
            'perm' => $datos['permisos_tipo'] ?? 'admin',
            'uid' => $usuario_id,
        ]
    );
    $id = (int) db_last_id();
    vault_registrar_historial($id, $usuario_id, 'crear', "Entrada creada: {$datos['nombre']}");
    return $id;
}


function vault_actualizar_entrada(int $id, array $datos, int $usuario_id, bool $cambiar_password): void {
    $entrada_actual = vault_obtener_entrada($id);
    if (!$entrada_actual) throw new RuntimeException('Entrada no encontrada');

    $sets = [
        "categoria_id = :cid", "nombre = :n", "url = :url", "usuario = :user",
        "notas = :notas", "archivos = :archivos", "version_build = :ver",
        "vencimiento = :venc", "tags = :tags", "sucursal_id = :sid",
        "sensibilidad = :sens", "permisos_tipo = :perm",
        "actualizado_por_id = :uid",
    ];

    $params = [
        'cid' => (int) $datos['categoria_id'],
        'n' => mb_substr($datos['nombre'], 0, 200),
        'url' => $datos['url'] ?? null,
        'user' => $datos['usuario'] ?? null,
        'notas' => $datos['notas'] ?? null,
        'archivos' => $datos['archivos'] ?? null,
        'ver' => $datos['version_build'] ?? null,
        'venc' => $datos['vencimiento'] ?? null,
        'tags' => $datos['tags'] ?? null,
        'sid' => $datos['sucursal_id'] ?: null,
        'sens' => $datos['sensibilidad'] ?? 'normal',
        'perm' => $datos['permisos_tipo'] ?? 'admin',
        'uid' => $usuario_id,
        'id' => $id,
    ];

    if ($cambiar_password) {
        $sets[] = "password_cifrado = :pwd";
        $params['pwd'] = isset($datos['password']) && $datos['password'] !== ''
            ? vault_cifrar($datos['password'])
            : null;
    }

    db_exec(
        "UPDATE vault_entradas SET " . implode(', ', $sets) . " WHERE id = :id",
        $params
    );

    vault_registrar_historial($id, $usuario_id, 'editar', "Entrada actualizada");
    if ($cambiar_password) {
        vault_registrar_historial($id, $usuario_id, 'password_cambiada', 'Contraseña modificada');
    }
}


function vault_eliminar_entrada(int $id, int $usuario_id): void {
    $entrada = vault_obtener_entrada($id);
    if (!$entrada) return;

    // Borrado lógico (recomendado para conservar historial)
    db_exec("UPDATE vault_entradas SET activo = 0 WHERE id = :id", ['id' => $id]);
    vault_registrar_historial($id, $usuario_id, 'eliminar', "Entrada eliminada: {$entrada['nombre']}");
}


/**
 * Descifra el password de una entrada (solo después de validar permisos).
 */
function vault_obtener_password(int $entrada_id, array $usuario): ?string {
    $e = db_one(
        "SELECT password_cifrado FROM vault_entradas WHERE id = :id AND activo = 1",
        ['id' => $entrada_id]
    );
    if (!$e || empty($e['password_cifrado'])) return null;

    $entrada_full = vault_obtener_entrada($entrada_id);
    if (!$entrada_full || !vault_usuario_puede_ver($entrada_full, $usuario)) return null;

    return vault_descifrar($e['password_cifrado']);
}


// ============================================================================
// HISTORIAL Y AUDITORÍA
// ============================================================================

function vault_registrar_historial(int $entrada_id, int $usuario_id, string $accion, ?string $descripcion = null): void {
    db_exec(
        "INSERT INTO vault_historial (entrada_id, usuario_id, accion, descripcion)
         VALUES (:eid, :uid, :acc, :desc)",
        ['eid' => $entrada_id, 'uid' => $usuario_id, 'acc' => $accion, 'desc' => $descripcion]
    );
}


function vault_listar_historial(int $entrada_id, int $limite = 20): array {
    return db_all(
        "SELECT h.*, u.nombre_completo AS usuario_nombre
         FROM vault_historial h
         LEFT JOIN usuarios u ON h.usuario_id = u.id
         WHERE h.entrada_id = :eid
         ORDER BY h.creado_en DESC
         LIMIT $limite",
        ['eid' => $entrada_id]
    );
}


function vault_registrar_acceso(int $entrada_id, int $usuario_id, string $accion): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    db_exec(
        "INSERT INTO vault_accesos (entrada_id, usuario_id, accion, ip)
         VALUES (:eid, :uid, :acc, :ip)",
        ['eid' => $entrada_id, 'uid' => $usuario_id, 'acc' => $accion, 'ip' => $ip]
    );
}


// ============================================================================
// FAVORITOS
// ============================================================================

function vault_toggle_favorito(int $entrada_id, int $usuario_id): string {
    $existe = db_one(
        "SELECT id FROM vault_favoritos WHERE entrada_id = :eid AND usuario_id = :uid",
        ['eid' => $entrada_id, 'uid' => $usuario_id]
    );
    if ($existe) {
        db_exec("DELETE FROM vault_favoritos WHERE id = :id", ['id' => $existe['id']]);
        return 'eliminado';
    }
    db_exec(
        "INSERT INTO vault_favoritos (entrada_id, usuario_id) VALUES (:eid, :uid)",
        ['eid' => $entrada_id, 'uid' => $usuario_id]
    );
    return 'agregado';
}


// ============================================================================
// ESTADÍSTICAS
// ============================================================================

function vault_stats(array $usuario): array {
    $perm = vault_clausula_permisos($usuario);
    $params_main = array_merge($perm['params'], ['uid_main' => (int) ($usuario['id'] ?? 0)]);

    $total = db_one(
        "SELECT COUNT(*) c FROM vault_entradas e
         WHERE e.activo = 1 {$perm['sql']}",
        $perm['params']
    );

    $favoritos = db_one(
        "SELECT COUNT(*) c FROM vault_favoritos
         WHERE usuario_id = :uid",
        ['uid' => (int) ($usuario['id'] ?? 0)]
    );

    $por_categoria = db_all(
        "SELECT c.id, c.nombre, c.familia, c.familia_orden, c.icono, c.color, c.orden,
                COUNT(e.id) AS total
         FROM vault_categorias c
         LEFT JOIN vault_entradas e ON e.categoria_id = c.id AND e.activo = 1 {$perm['sql']}
         WHERE c.activo = 1
         GROUP BY c.id, c.nombre, c.familia, c.familia_orden, c.icono, c.color, c.orden
         ORDER BY c.familia_orden, c.orden",
        $perm['params']
    );

    return [
        'total' => (int) ($total['c'] ?? 0),
        'favoritos' => (int) ($favoritos['c'] ?? 0),
        'por_categoria' => $por_categoria,
    ];
}


// ============================================================================
// PERMISO PARA ADMINISTRAR EL VAULT
// ============================================================================

/**
 * Solo admins pueden crear/editar/eliminar entradas y gestionar permisos.
 */
function vault_puede_administrar(): bool {
    return tiene_permiso('administrar');
}
