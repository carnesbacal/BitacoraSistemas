-- ============================================================================
-- limpiar_datos_demo_v2.sql
-- ============================================================================
-- Versión corregida usando DELETE en lugar de TRUNCATE (MySQL no permite
-- TRUNCATE en tablas referenciadas por FK aunque desactives FK_CHECKS).
--
-- Adaptado a la estructura REAL de la BD (verificada contra el backup).
--
-- ⚠ HAZ UN BACKUP COMPLETO DE LA BD ANTES DE EJECUTAR ESTE SCRIPT.
-- ============================================================================

USE carnes_bacal;

-- ============================================================================
-- Deshabilitar checks de FK para borrar en orden libre
-- ============================================================================
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. REACCIONES Y COMENTARIOS (las hijas primero por orden lógico)
-- ============================================================================
DELETE FROM comentario_reacciones WHERE 1=1;
DELETE FROM incidencias_comentarios WHERE 1=1;
DELETE FROM incidencias_historial WHERE 1=1;
DELETE FROM incidencias_adjuntos WHERE 1=1;
DELETE FROM incidencias_etiquetas WHERE 1=1;

-- ============================================================================
-- 2. INCIDENCIAS
-- ============================================================================
DELETE FROM incidencias WHERE 1=1;

-- ============================================================================
-- 3. EQUIPOS Y DEPENDENCIAS
-- ============================================================================
DELETE FROM equipo_fotos WHERE 1=1;
DELETE FROM equipo_transferencias WHERE 1=1;
DELETE FROM mantenimientos WHERE 1=1;
DELETE FROM equipos WHERE 1=1;

-- ============================================================================
-- 4. MAPA Y PLANTAS
-- ============================================================================
DELETE FROM sucursal_plantas WHERE 1=1;

-- ============================================================================
-- 5. COMUNICACIÓN
-- ============================================================================
DELETE FROM anuncios_lecturas WHERE 1=1;
DELETE FROM anuncios WHERE 1=1;
DELETE FROM recordatorios WHERE 1=1;
DELETE FROM notificaciones WHERE 1=1;

-- ============================================================================
-- 6. IMPORTACIONES Y AUDITORÍA
-- ============================================================================
DELETE FROM importaciones WHERE 1=1;
DELETE FROM auditoria_sistema WHERE 1=1;
DELETE FROM backups_realizados WHERE 1=1;

-- ============================================================================
-- 7. SESIONES (cierra todas las activas)
-- ============================================================================
DELETE FROM sesiones WHERE 1=1;

-- ============================================================================
-- 8. USUARIOS DEMO
-- ============================================================================
-- Conservar: admin y abraham (ajusta si tu técnico real tiene otro login)
-- ============================================================================
DELETE FROM usuarios WHERE usuario NOT IN ('admin', 'abraham');

-- ============================================================================
-- 9. RESETEAR AUTO_INCREMENT
-- ============================================================================
ALTER TABLE incidencias AUTO_INCREMENT = 1;
ALTER TABLE incidencias_comentarios AUTO_INCREMENT = 1;
ALTER TABLE incidencias_historial AUTO_INCREMENT = 1;
ALTER TABLE incidencias_adjuntos AUTO_INCREMENT = 1;
ALTER TABLE incidencias_etiquetas AUTO_INCREMENT = 1;
ALTER TABLE comentario_reacciones AUTO_INCREMENT = 1;
ALTER TABLE equipos AUTO_INCREMENT = 1;
ALTER TABLE equipo_fotos AUTO_INCREMENT = 1;
ALTER TABLE equipo_transferencias AUTO_INCREMENT = 1;
ALTER TABLE mantenimientos AUTO_INCREMENT = 1;
ALTER TABLE sucursal_plantas AUTO_INCREMENT = 1;
ALTER TABLE anuncios AUTO_INCREMENT = 1;
ALTER TABLE anuncios_lecturas AUTO_INCREMENT = 1;
ALTER TABLE recordatorios AUTO_INCREMENT = 1;
ALTER TABLE notificaciones AUTO_INCREMENT = 1;
ALTER TABLE importaciones AUTO_INCREMENT = 1;
ALTER TABLE auditoria_sistema AUTO_INCREMENT = 1;
ALTER TABLE backups_realizados AUTO_INCREMENT = 1;
ALTER TABLE sesiones AUTO_INCREMENT = 1;

-- ============================================================================
-- Rehabilitar checks de FK
-- ============================================================================
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- VERIFICACIÓN
-- ============================================================================
SELECT 'Limpieza completada' AS estado;

SELECT '== Conteos finales ==' AS info;
SELECT 'Usuarios:' AS tabla, COUNT(*) AS total FROM usuarios
UNION ALL SELECT 'Sucursales:', COUNT(*) FROM sucursales
UNION ALL SELECT 'Áreas:', COUNT(*) FROM areas
UNION ALL SELECT 'Categorías:', COUNT(*) FROM categorias
UNION ALL SELECT 'Subcategorías:', COUNT(*) FROM subcategorias
UNION ALL SELECT 'Tipos de trabajo:', COUNT(*) FROM tipos_trabajo
UNION ALL SELECT 'Severidades:', COUNT(*) FROM severidades
UNION ALL SELECT 'Estados:', COUNT(*) FROM estados
UNION ALL SELECT 'Orígenes:', COUNT(*) FROM origenes_reporte
UNION ALL SELECT 'Proveedores:', COUNT(*) FROM proveedores
UNION ALL SELECT 'Plantillas:', COUNT(*) FROM plantillas_incidencias
UNION ALL SELECT 'Palabras clave:', COUNT(*) FROM categorias_palabras_clave
UNION ALL SELECT 'Reglas asignación:', COUNT(*) FROM reglas_asignacion
UNION ALL SELECT '-- VACIOS:', 0
UNION ALL SELECT 'Incidencias:', COUNT(*) FROM incidencias
UNION ALL SELECT 'Equipos:', COUNT(*) FROM equipos
UNION ALL SELECT 'Mantenimientos:', COUNT(*) FROM mantenimientos
UNION ALL SELECT 'Notificaciones:', COUNT(*) FROM notificaciones
UNION ALL SELECT 'Anuncios:', COUNT(*) FROM anuncios
UNION ALL SELECT 'Auditoría:', COUNT(*) FROM auditoria_sistema;

-- ============================================================================
-- DESPUÉS DE EJECUTAR:
-- ============================================================================
-- 1. La contraseña de 'admin' sigue siendo la que tenías. Si por algún motivo
--    necesitas resetearla a 'admin123':
--      UPDATE usuarios
--      SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
--      WHERE usuario = 'admin';
--
-- 2. Cierra sesión y vuelve a entrar (las sesiones se eliminaron).
--
-- 3. Borra los archivos físicos huérfanos de uploads en:
--      C:\xampp\htdocs\UtilidadesBacal\BitacoraSistemas\uploads\adjuntos\
--      C:\xampp\htdocs\UtilidadesBacal\BitacoraSistemas\uploads\equipo_fotos\
--      C:\xampp\htdocs\UtilidadesBacal\BitacoraSistemas\uploads\planos\
--    (NO borres uploads/avatares si quieres conservar los avatares de admin y abraham)
-- ============================================================================
