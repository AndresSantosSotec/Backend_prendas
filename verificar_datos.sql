-- Verificar datos en la base de datos

-- 1. Ver sucursales activas
SELECT id, codigo, nombre, activa FROM sucursales WHERE activa = 1;

-- 2. Ver usuario SuperAdmin
SELECT id, name, email, rol, sucursal_id, activo FROM users WHERE rol = 'superadmin';

-- 3. Contar sucursales
SELECT COUNT(*) as total_sucursales FROM sucursales WHERE activa = 1;

-- 4. Ver todos los usuarios con sus sucursales
SELECT
    u.id,
    u.name,
    u.email,
    u.rol,
    u.sucursal_id,
    s.nombre as sucursal_nombre
FROM users u
LEFT JOIN sucursales s ON u.sucursal_id = s.id;
