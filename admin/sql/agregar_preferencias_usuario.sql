-- ============================================================================
-- admin/sql/agregar_preferencias_usuario.sql
-- Agrega columna `preferencias` a la tabla usuarios y asigna el selector
-- de tipo radio a los usuarios privilegiados.
--
-- INSTRUCCIONES:
--   1. Si la columna `preferencias` NO existe, ejecuta TODO el archivo.
--   2. Si la columna YA existe, omite el ALTER TABLE y ejecuta solo el UPDATE.
-- ============================================================================

ALTER TABLE usuarios
    ADD COLUMN preferencias JSON NULL DEFAULT NULL
    COMMENT 'Preferencias de UI del usuario en formato JSON';

UPDATE usuarios
SET preferencias = '{"sucursal_selector":"radio"}'
WHERE usuario IN ('jlcorral', 'admin');
