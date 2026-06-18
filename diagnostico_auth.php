<?php
/**
 * diagnostico_auth.php - Diagnóstico de autenticación y sesiones
 * BORRAR después de usar.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/helpers.php';

requerir_login();
requerir_permiso('administrar');

$u_sesion = usuario_actual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Diagnóstico Auth</title>
<style>
body { font-family: monospace; padding: 20px; background: #f5f5f5; }
h2 { color: #333; border-bottom: 2px solid #333; padding-bottom: 5px; }
table { border-collapse: collapse; width: 100%; margin-bottom: 30px; background: white; }
th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left; }
th { background: #333; color: white; }
tr:nth-child(even) { background: #f9f9f9; }
.ok { color: green; font-weight: bold; }
.warn { color: orange; font-weight: bold; }
.error { color: red; font-weight: bold; }
.box { background: white; border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
</style>
</head>
<body>

<h2>1. Datos de sesión actual</h2>
<div class="box">
<table>
<tr><th>Campo</th><th>Valor en $_SESSION</th></tr>
<tr><td>id</td><td><?= (int)$u_sesion['id'] ?></td></tr>
<tr><td>usuario</td><td><?= htmlspecialchars($u_sesion['usuario']) ?></td></tr>
<tr><td>nombre</td><td><?= htmlspecialchars($u_sesion['nombre']) ?></td></tr>
<tr><td>rol_nombre</td><td><?= htmlspecialchars($u_sesion['rol_nombre']) ?></td></tr>
<tr><td>PHPSESSID</td><td><?= session_id() ?></td></tr>
</table>
</div>

<?php
// Verificar que el ID de sesión corresponde al usuario correcto en BD
$db_user = db_one("SELECT id, usuario, nombre_completo FROM usuarios WHERE id = :id", ['id' => $u_sesion['id']]);
$db_user_by_name = db_one("SELECT id, usuario, nombre_completo FROM usuarios WHERE usuario = :u", ['u' => $u_sesion['usuario']]);
?>

<h2>2. Validación: sesión vs BD</h2>
<div class="box">
<?php if ($db_user): ?>
    <p>Registro en BD para ID <strong><?= (int)$u_sesion['id'] ?></strong>:
       <strong><?= htmlspecialchars($db_user['usuario']) ?></strong> — <?= htmlspecialchars($db_user['nombre_completo']) ?>
    </p>
    <?php if ($db_user['usuario'] === $u_sesion['usuario']): ?>
        <p class="ok">✔ El ID de sesión coincide con el usuario correcto en BD.</p>
    <?php else: ?>
        <p class="error">✘ PROBLEMA: El ID <?= (int)$u_sesion['id'] ?> en BD pertenece a "<?= htmlspecialchars($db_user['usuario']) ?>"
           pero la sesión dice usuario "<?= htmlspecialchars($u_sesion['usuario']) ?>".
           <br><strong>Esto fue la causa del bug: la sesión tenía el ID equivocado.</strong></p>
    <?php endif; ?>
<?php else: ?>
    <p class="error">✘ No existe ningún registro en BD con id = <?= (int)$u_sesion['id'] ?></p>
<?php endif; ?>

<?php if ($db_user_by_name): ?>
    <p>ID real en BD para usuario "<?= htmlspecialchars($u_sesion['usuario']) ?>": <strong><?= (int)$db_user_by_name['id'] ?></strong></p>
    <?php if ((int)$db_user_by_name['id'] === (int)$u_sesion['id']): ?>
        <p class="ok">✔ Sesión actual correcta (post-logout).</p>
    <?php else: ?>
        <p class="error">✘ Aún hay mismatch. ID sesión: <?= (int)$u_sesion['id'] ?>, ID en BD: <?= (int)$db_user_by_name['id'] ?></p>
    <?php endif; ?>
<?php endif; ?>
</div>

<h2>3. Todos los usuarios en BD</h2>
<div class="box">
<table>
<tr><th>id</th><th>usuario</th><th>nombre_completo</th><th>rol_id</th><th>activo</th></tr>
<?php
$users = db_all("SELECT id, usuario, nombre_completo, rol_id, activo FROM usuarios ORDER BY id");
foreach ($users as $row):
?>
<tr>
    <td><?= (int)$row['id'] ?></td>
    <td><?= htmlspecialchars($row['usuario']) ?></td>
    <td><?= htmlspecialchars($row['nombre_completo']) ?></td>
    <td><?= (int)$row['rol_id'] ?></td>
    <td><?= $row['activo'] ? '<span class="ok">Sí</span>' : '<span class="error">No</span>' ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<h2>4. Incidencias recientes con posible ID incorrecto</h2>
<div class="box">
<?php
// Buscar incidencias creadas por jose Alberto (jamartinez) en las últimas 24 horas
$ja = db_one("SELECT id FROM usuarios WHERE usuario = 'jamartinez'");
$lf = db_one("SELECT id FROM usuarios WHERE usuario = 'lfrodriguez'");
?>
<p>ID de jamartinez en BD: <strong><?= $ja ? (int)$ja['id'] : 'no encontrado' ?></strong></p>
<p>ID de lfrodriguez en BD: <strong><?= $lf ? (int)$lf['id'] : 'no encontrado' ?></strong></p>

<?php if ($ja): ?>
<p>Incidencias registradas como creadas por jamartinez en las últimas 48h:</p>
<table>
<tr><th>ID Incidencia</th><th>Folio</th><th>reportado_por_id</th><th>creado_en</th></tr>
<?php
$incs = db_all(
    "SELECT id, folio, reportado_por_id, creado_en FROM incidencias
     WHERE reportado_por_id = :id AND creado_en >= NOW() - INTERVAL 48 HOUR
     ORDER BY creado_en DESC",
    ['id' => $ja['id']]
);
foreach ($incs as $inc):
?>
<tr>
    <td><?= (int)$inc['id'] ?></td>
    <td><?= htmlspecialchars($inc['folio']) ?></td>
    <td><?= (int)$inc['reportado_por_id'] ?></td>
    <td><?= htmlspecialchars($inc['creado_en']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (empty($incs)): ?><tr><td colspan="4">Ninguna en 48h</td></tr><?php endif; ?>
</table>
<?php endif; ?>
</div>

<h2>5. Sesiones activas en BD</h2>
<div class="box">
<table>
<tr><th>id</th><th>usuario_id</th><th>usuario</th><th>session_id</th><th>activa</th><th>creado_en</th><th>ultima_actividad</th></tr>
<?php
$sesiones = db_all(
    "SELECT s.id, s.usuario_id, u.usuario, s.session_id, s.activa, s.creado_en, s.ultima_actividad
     FROM sesiones s
     JOIN usuarios u ON s.usuario_id = u.id
     ORDER BY s.creado_en DESC LIMIT 20"
);
foreach ($sesiones as $s):
?>
<tr>
    <td><?= (int)$s['id'] ?></td>
    <td><?= (int)$s['usuario_id'] ?></td>
    <td><?= htmlspecialchars($s['usuario']) ?></td>
    <td><?= substr($s['session_id'], 0, 16) ?>…</td>
    <td><?= $s['activa'] ? '<span class="ok">Sí</span>' : 'No' ?></td>
    <td><?= $s['creado_en'] ?></td>
    <td><?= $s['ultima_actividad'] ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<h2>6. Auditoría reciente (últimas 48h)</h2>
<div class="box">
<table>
<tr><th>usuario_id</th><th>usuario</th><th>accion</th><th>descripcion</th><th>creado_en</th></tr>
<?php
$audit = db_all(
    "SELECT a.usuario_id, u.usuario, a.accion, a.descripcion, a.creado_en
     FROM auditoria_sistema a
     LEFT JOIN usuarios u ON a.usuario_id = u.id
     WHERE a.creado_en >= NOW() - INTERVAL 48 HOUR
     ORDER BY a.creado_en DESC LIMIT 50"
);
foreach ($audit as $a):
?>
<tr>
    <td><?= (int)$a['usuario_id'] ?></td>
    <td><?= htmlspecialchars($a['usuario'] ?? '?') ?></td>
    <td><?= htmlspecialchars($a['accion']) ?></td>
    <td><?= htmlspecialchars($a['descripcion'] ?? '') ?></td>
    <td><?= $a['creado_en'] ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<h2>7. Limpieza manual de sesiones huérfanas</h2>
<div class="box">
<?php
$afectadas = db_one(
    "SELECT COUNT(*) c FROM sesiones WHERE activa = 1 AND ultima_actividad < NOW() - INTERVAL 8 HOUR"
);
$n = (int)($afectadas['c'] ?? 0);
if ($n > 0):
    db_exec(
        "UPDATE sesiones SET activa = 0, motivo_cierre = 'limpieza manual', cerrada_en = NOW()
         WHERE activa = 1 AND ultima_actividad < NOW() - INTERVAL 8 HOUR"
    );
?>
    <p class="ok">✔ Se cerraron <?= $n ?> sesiones huérfanas (inactivas hace más de 8h).</p>
<?php else: ?>
    <p class="ok">✔ No había sesiones huérfanas pendientes de limpiar.</p>
<?php endif; ?>
</div>

<p style="color:red; font-weight:bold; margin-top:30px;">⚠ BORRAR este archivo cuando termines: diagnostico_auth.php</p>
</body>
</html>
