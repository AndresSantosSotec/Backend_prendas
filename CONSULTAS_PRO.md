# CONSULTAS PRO - Guía de Soporte y Correcciones en Base de Datos

Este documento contiene consultas SQL útiles para resolver incidencias comunes en el sistema DigiPrenda directamente desde la base de datos (phpMyAdmin o consola SQL).

---

## 🔐 1. Gestión de Permisos

### 1.1 Restaurar Permisos por Defecto de un Vendedor
Si los permisos de un vendedor fueron sobrescritos o eliminados accidentalmente, ejecuta esta consulta para asignarle todos los permisos por defecto de su rol:

```sql
-- Asegurar que los permisos base de caja, cobros y recibos existan
INSERT IGNORE INTO permissions (modulo, accion, descripcion, created_at, updated_at) VALUES
('caja', 'abrir', 'Abrir en Caja', NOW(), NOW()),
('caja', 'cerrar', 'Cerrar en Caja', NOW(), NOW()),
('caja', 'ver_movimientos', 'Ver movimientos en Caja', NOW(), NOW()),
('cobros', 'realizar', 'Realizar en Cobros', NOW(), NOW()),
('cobros', 'ver', 'Ver en Cobros', NOW(), NOW()),
('cobros', 'imprimir_recibo', 'Imprimir recibo en Cobros', NOW(), NOW()),
('recibos', 'ver', 'Ver en Recibos', NOW(), NOW()),
('recibos', 'crear', 'Crear en Recibos', NOW(), NOW()),
('recibos', 'imprimir', 'Imprimir en Recibos', NOW(), NOW());

-- Asignar los permisos predeterminados al vendedor (reemplazar 'Angelmoreira9210' por el username correspondiente)
INSERT IGNORE INTO user_permissions (user_id, permission_id, created_at, updated_at)
SELECT u.id as user_id, p.id as permission_id, NOW(), NOW()
FROM users u
CROSS JOIN permissions p
WHERE u.username = 'Angelmoreira9210'
  AND (
    (p.modulo = 'dashboard' AND p.accion IN ('ver')) OR
    (p.modulo = 'clientes' AND p.accion IN ('ver', 'crear')) OR
    (p.modulo = 'ventas' AND p.accion IN ('ver', 'vender', 'apartar', 'crear_plan_pago', 'aplicar_descuento')) OR
    (p.modulo = 'compras' AND p.accion IN ('ver')) OR
    (p.modulo = 'prendas' AND p.accion IN ('ver')) OR
    (p.modulo = 'cotizaciones' AND p.accion IN ('ver', 'crear', 'editar')) OR
    (p.modulo = 'caja' AND p.accion IN ('abrir', 'cerrar', 'ver_movimientos')) OR
    (p.modulo = 'cobros' AND p.accion IN ('realizar', 'ver', 'imprimir_recibo')) OR
    (p.modulo = 'recibos' AND p.accion IN ('ver', 'crear', 'imprimir')) OR
    (p.modulo = 'historial' AND p.accion IN ('ver'))
  );
```

---

## 💵 2. Caja y Bóveda

### 2.1 Corregir Descuadre de Caja al Cierre (ej: Q500 faltantes)
Si la caja se cerró con diferencia (descuadre) debido a un movimiento que no se registró a tiempo, sigue estos pasos:

```sql
-- Paso A: Insertar el movimiento de incremento omitido (ej: 500.00 en caja_id 34)
INSERT INTO movimiento_cajas (caja_id, tipo, monto, concepto, detalles_movimiento, estado, user_id, created_at, updated_at)
SELECT 
    34, -- ID de la caja
    'incremento', -- 'incremento' o 'decremento'
    500.00, -- Monto
    'SALDO PARA OPERACIONES DE CAJA (Ajuste por error de permisos)', 
    '{"denominaciones":{"100":5},"total":500}', 
    'aplicado', 
    u.id, 
    NOW(), 
    NOW()
FROM users u 
WHERE u.username = 'Angelmoreira9210' 
LIMIT 1;

-- Paso B: Recalcular la diferencia y el estado de arqueo de la caja automáticamente
UPDATE caja_apertura_cierres c
SET 
    c.diferencia = c.saldo_final - (
        c.saldo_inicial + COALESCE((
            SELECT SUM(CASE WHEN tipo IN ('incremento', 'ingreso_pago') THEN monto ELSE -monto END)
            FROM movimiento_cajas
            WHERE caja_id = c.id AND estado = 'aplicado'
        ), 0)
    ),
    c.resultado_arqueo = CASE 
        WHEN ABS(c.saldo_final - (c.saldo_inicial + COALESCE((
            SELECT SUM(CASE WHEN tipo IN ('incremento', 'ingreso_pago') THEN monto ELSE -monto END)
            FROM movimiento_cajas
            WHERE caja_id = c.id AND estado = 'aplicado'
        ), 0))) < 0.01 THEN 'cuadrada'
        ELSE 'diferencia'
    END
WHERE c.id = 34; -- ID de la caja
```

### 2.2 Reversar el Ajuste de Descuadre anterior
Si necesitas deshacer la inserción anterior y restaurar la caja a su descuadre original:

```sql
-- Paso A: Borrar el movimiento de ajuste
DELETE FROM movimiento_cajas 
WHERE caja_id = 34 
  AND concepto = 'SALDO PARA OPERACIONES DE CAJA (Ajuste por error de permisos)';

-- Paso B: Volver a calcular el arqueo (se restaurará al estado original)
UPDATE caja_apertura_cierres c
SET 
    c.diferencia = c.saldo_final - (
        c.saldo_inicial + COALESCE((
            SELECT SUM(CASE WHEN tipo IN ('incremento', 'ingreso_pago') THEN monto ELSE -monto END)
            FROM movimiento_cajas
            WHERE caja_id = c.id AND estado = 'aplicado'
        ), 0)
    ),
    c.resultado_arqueo = CASE 
        WHEN ABS(c.saldo_final - (c.saldo_inicial + COALESCE((
            SELECT SUM(CASE WHEN tipo IN ('incremento', 'ingreso_pago') THEN monto ELSE -monto END)
            FROM movimiento_cajas
            WHERE caja_id = c.id AND estado = 'aplicado'
        ), 0))) < 0.01 THEN 'cuadrada'
        ELSE 'diferencia'
    END
WHERE c.id = 34;
```

### 2.3 Reapertura de Caja Cerrada por Accidente
Si un cajero cerró su caja por error y necesita seguir operando en ella el mismo día:

```sql
UPDATE caja_apertura_cierres 
SET 
    estado = 'abierta',
    saldo_final = NULL,
    fecha_cierre = NULL,
    diferencia = NULL,
    resultado_arqueo = NULL,
    detalles_arqueo = NULL
WHERE id = 34; -- ID de la caja
```

---

## 👤 3. Usuarios y Seguridad

### 3.1 Desbloquear Usuario por Intentos de Inicio de Sesión Fallidos
Si el sistema bloqueó a un usuario debido a múltiples intentos incorrectos:

```sql
UPDATE users 
SET 
    failed_login_attempts = 0,
    locked_until = NULL
WHERE username = 'Angelmoreira9210'; -- Nombre de usuario a desbloquear
```

### 3.2 Forzar Cambio de Contraseña de un Usuario en su Próximo Login
```sql
UPDATE users 
SET 
    force_password_change = 1 
WHERE username = 'Angelmoreira9210';
```

---

## 💍 4. Prendas e Inventario

### 4.1 Devolver Prenda Vendida al Estado de Venta
Si una venta se registró incorrectamente y la prenda debe regresar al inventario disponible para la venta:

```sql
UPDATE prendas 
SET 
    estado = 'en_venta' 
WHERE id = 123; -- ID de la prenda
```
