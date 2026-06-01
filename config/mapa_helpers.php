<?php
/**
 * ============================================================================
 * config/mapa_helpers.php
 * ============================================================================
 * Funciones para el mapa multi-planta de sucursales.
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

// ============================================================================
// PLANTAS
// ============================================================================

function listar_plantas_de_sucursal(int $sucursal_id): array {
    return db_all(
        "SELECT * FROM sucursal_plantas
         WHERE sucursal_id = :sid AND activo = 1
         ORDER BY orden ASC, id ASC",
        ['sid' => $sucursal_id]
    );
}

function crear_planta(int $sucursal_id, string $nombre): int {
    $nombre = trim($nombre);
    if ($nombre === '') {
        throw new RuntimeException('El nombre de la planta es obligatorio.');
    }
    $r = db_one("SELECT COALESCE(MAX(orden), 0) + 1 AS prox FROM sucursal_plantas WHERE sucursal_id = :sid",
        ['sid' => $sucursal_id]);
    $orden = (int) ($r['prox'] ?? 1);

    db_exec(
        "INSERT INTO sucursal_plantas (sucursal_id, nombre, orden)
         VALUES (:sid, :nom, :ord)",
        ['sid' => $sucursal_id, 'nom' => mb_substr($nombre, 0, 80), 'ord' => $orden]
    );
    return (int) db_last_id();
}

function renombrar_planta(int $planta_id, string $nombre_nuevo): void {
    $nombre_nuevo = trim($nombre_nuevo);
    if ($nombre_nuevo === '') {
        throw new RuntimeException('El nombre no puede quedar vacío.');
    }
    db_exec("UPDATE sucursal_plantas SET nombre = :nom WHERE id = :id",
        ['nom' => mb_substr($nombre_nuevo, 0, 80), 'id' => $planta_id]);
}

function eliminar_planta(int $planta_id): void {
    $p = db_one("SELECT plano_url FROM sucursal_plantas WHERE id = :id", ['id' => $planta_id]);
    if ($p && $p['plano_url']) {
        $ruta = __DIR__ . '/../' . $p['plano_url'];
        if (file_exists($ruta)) @unlink($ruta);
    }
    db_exec(
        "UPDATE equipos SET planta_id = NULL, pos_x = NULL, pos_y = NULL
         WHERE planta_id = :pid",
        ['pid' => $planta_id]
    );
    db_exec("DELETE FROM sucursal_plantas WHERE id = :id", ['id' => $planta_id]);
}


// ============================================================================
// EQUIPOS POR PLANTA
// ============================================================================

function equipos_en_planta(int $planta_id): array {
    return db_all(
        "SELECT e.id, e.codigo_inventario, e.nombre, e.tipo, e.estado_vida,
                e.pos_x, e.pos_y, e.estacion_id,
                a.nombre AS area_nombre, a.color AS area_color,
                est.codigo AS estacion_codigo, est.nombre AS estacion_nombre,
                (SELECT COUNT(*) FROM incidencias i
                 INNER JOIN estados es ON i.estado_id = es.id
                 WHERE i.equipo_id = e.id AND es.es_final = 0) AS incidencias_abiertas
         FROM equipos e
         LEFT JOIN areas a ON e.area_id = a.id
         LEFT JOIN estaciones_trabajo est ON e.estacion_id = est.id
         WHERE e.planta_id = :pid AND e.activo = 1
         ORDER BY e.nombre ASC",
        ['pid' => $planta_id]
    );
}

function equipos_sin_planta_en_sucursal(int $sucursal_id): array {
    return db_all(
        "SELECT e.id, e.codigo_inventario, e.nombre, e.tipo, e.estado_vida,
                e.estacion_id,
                a.nombre AS area_nombre,
                est.codigo AS estacion_codigo, est.nombre AS estacion_nombre,
                (SELECT COUNT(*) FROM incidencias i
                 INNER JOIN estados es ON i.estado_id = es.id
                 WHERE i.equipo_id = e.id AND es.es_final = 0) AS incidencias_abiertas
         FROM equipos e
         LEFT JOIN areas a ON e.area_id = a.id
         LEFT JOIN estaciones_trabajo est ON e.estacion_id = est.id
         WHERE e.sucursal_id = :sid AND e.activo = 1 AND e.planta_id IS NULL
         ORDER BY e.nombre ASC",
        ['sid' => $sucursal_id]
    );
}


// ============================================================================
// ESTACIONES POR PLANTA (agrupación visual)
// ============================================================================

/**
 * Estaciones de trabajo ya posicionadas en un plano.
 * Incluye conteo de equipos e incidencias abiertas.
 */
function estaciones_en_planta(int $planta_id): array {
    return db_all(
        "SELECT est.id, est.codigo, est.nombre, est.pos_x, est.pos_y,
                a.nombre AS area_nombre, a.color AS area_color,
                (SELECT COUNT(*) FROM equipos WHERE estacion_id = est.id AND activo = 1) AS num_equipos,
                (SELECT COUNT(*) FROM incidencias i
                 INNER JOIN estados es ON i.estado_id = es.id
                 WHERE (i.estacion_id = est.id
                        OR i.equipo_id IN (SELECT id FROM equipos WHERE estacion_id = est.id))
                   AND es.es_final = 0) AS incidencias_abiertas
         FROM estaciones_trabajo est
         LEFT JOIN areas a ON est.area_id = a.id
         WHERE est.planta_id = :pid AND est.activo = 1
         ORDER BY est.nombre",
        ['pid' => $planta_id]
    );
}


/**
 * Estaciones de la sucursal sin posicionar en ninguna planta.
 */
function estaciones_sin_planta_en_sucursal(int $sucursal_id): array {
    return db_all(
        "SELECT est.id, est.codigo, est.nombre,
                a.nombre AS area_nombre, a.color AS area_color,
                (SELECT COUNT(*) FROM equipos WHERE estacion_id = est.id AND activo = 1) AS num_equipos,
                (SELECT COUNT(*) FROM incidencias i
                 INNER JOIN estados es ON i.estado_id = es.id
                 WHERE (i.estacion_id = est.id
                        OR i.equipo_id IN (SELECT id FROM equipos WHERE estacion_id = est.id))
                   AND es.es_final = 0) AS incidencias_abiertas
         FROM estaciones_trabajo est
         LEFT JOIN areas a ON est.area_id = a.id
         WHERE est.sucursal_id = :sid AND est.activo = 1 AND est.planta_id IS NULL
         ORDER BY est.nombre",
        ['sid' => $sucursal_id]
    );
}


/**
 * Actualiza la posición de una estación en el mapa.
 */
function actualizar_posicion_estacion(int $estacion_id, ?int $planta_id, ?float $pos_x, ?float $pos_y): void {
    if ($planta_id === null || $pos_x === null || $pos_y === null) {
        db_exec(
            "UPDATE estaciones_trabajo SET planta_id = NULL, pos_x = NULL, pos_y = NULL WHERE id = :id",
            ['id' => $estacion_id]
        );
        return;
    }
    $pos_x = max(0, min(100, $pos_x));
    $pos_y = max(0, min(100, $pos_y));

    db_exec(
        "UPDATE estaciones_trabajo SET planta_id = :pid, pos_x = :x, pos_y = :y WHERE id = :id",
        ['pid' => $planta_id, 'x' => $pos_x, 'y' => $pos_y, 'id' => $estacion_id]
    );
}

function actualizar_posicion_equipo(int $equipo_id, ?int $planta_id, ?float $pos_x, ?float $pos_y): void {
    if ($planta_id === null || $pos_x === null || $pos_y === null) {
        db_exec(
            "UPDATE equipos SET planta_id = NULL, pos_x = NULL, pos_y = NULL WHERE id = :id",
            ['id' => $equipo_id]
        );
        return;
    }
    $pos_x = max(0, min(100, $pos_x));
    $pos_y = max(0, min(100, $pos_y));

    db_exec(
        "UPDATE equipos SET planta_id = :pid, pos_x = :x, pos_y = :y WHERE id = :id",
        ['pid' => $planta_id, 'x' => $pos_x, 'y' => $pos_y, 'id' => $equipo_id]
    );
}


// ============================================================================
// SUBIDA DE PLANO POR PLANTA
// ============================================================================

function subir_plano_planta(int $planta_id, array $archivo): string {
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir el archivo.');
    }
    if ($archivo['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('La imagen excede 10 MB.');
    }

    $info = @getimagesize($archivo['tmp_name']);
    if (!$info) throw new RuntimeException('No es una imagen válida.');

    $tipo = $info['mime'];
    $extensiones_ok = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensiones_ok[$tipo])) {
        throw new RuntimeException('Formato no soportado. Usa JPG, PNG o WebP.');
    }

    $dir_destino = __DIR__ . '/../uploads/planos/';
    if (!is_dir($dir_destino)) mkdir($dir_destino, 0755, true);

    $anterior = db_one("SELECT plano_url FROM sucursal_plantas WHERE id = :id", ['id' => $planta_id]);
    if ($anterior && $anterior['plano_url']) {
        $ruta_anterior = __DIR__ . '/../' . $anterior['plano_url'];
        if (file_exists($ruta_anterior)) @unlink($ruta_anterior);
    }

    $ext = $extensiones_ok[$tipo];
    $nombre_archivo = 'plano_p' . $planta_id . '_' . time() . '.' . $ext;
    $ruta_completa = $dir_destino . $nombre_archivo;

    redimensionar_plano($archivo['tmp_name'], $ruta_completa, $tipo);

    $ruta_relativa = 'uploads/planos/' . $nombre_archivo;

    db_exec(
        "UPDATE sucursal_plantas SET plano_url = :url, plano_subido_en = NOW() WHERE id = :id",
        ['url' => $ruta_relativa, 'id' => $planta_id]
    );

    return $ruta_relativa;
}

function redimensionar_plano(string $origen, string $destino, string $tipo_mime): void {
    [$ancho, $alto] = getimagesize($origen);

    $img_origen = match ($tipo_mime) {
        'image/jpeg' => imagecreatefromjpeg($origen),
        'image/png'  => imagecreatefrompng($origen),
        'image/webp' => imagecreatefromwebp($origen),
        default => null,
    };

    if (!$img_origen) {
        copy($origen, $destino);
        return;
    }

    $ancho_max = 2400;
    if ($ancho > $ancho_max) {
        $nuevo_ancho = $ancho_max;
        $nuevo_alto = (int) ($alto * ($ancho_max / $ancho));
        $img_final = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);

        if ($tipo_mime === 'image/png') {
            imagealphablending($img_final, false);
            imagesavealpha($img_final, true);
        }

        imagecopyresampled($img_final, $img_origen, 0, 0, 0, 0,
                          $nuevo_ancho, $nuevo_alto, $ancho, $alto);
    } else {
        $img_final = $img_origen;
    }

    if ($tipo_mime === 'image/png') {
        imagepng($img_final, $destino, 6);
    } elseif ($tipo_mime === 'image/webp') {
        imagewebp($img_final, $destino, 85);
    } else {
        imagejpeg($img_final, $destino, 85);
    }

    imagedestroy($img_origen);
    if (isset($img_final) && $img_final !== $img_origen) imagedestroy($img_final);
}


// ============================================================================
// PINS
// ============================================================================

function color_pin_equipo(string $estado_vida, int $incidencias_abiertas): string {
    if ($incidencias_abiertas > 0) return '#C8102E';
    return match ($estado_vida) {
        'en_uso' => '#16A34A',
        'en_mantenimiento' => '#F59E0B',
        'baja' => '#71717a',
        default => '#0EA5E9',
    };
}
