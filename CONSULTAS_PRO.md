# CONSULTAS PRO - Guía de Soporte y Correcciones en Base de Datos

Este documento contiene consultas SQL avanzadas y estructuradas para resolver incidencias comunes y de soporte crítico en el sistema DigiPrenda directamente desde la base de datos (phpMyAdmin o consola SQL).

> [!WARNING]
> **Antes de ejecutar cualquier consulta de modificación (UPDATE, DELETE, INSERT):**
> 1. Realiza una copia de seguridad (Backup) de la base de datos.
> 2. Valida los IDs de registros (`caja_id`, `prenda_id`, `credito_id`, etc.) con una consulta `SELECT` previa.
> 3. Ejecuta las sentencias dentro de una transacción (`START TRANSACTION;` ... `COMMIT;`) si es posible.

---

## 🗂️ Tabla de Contenidos
1. [🔐 1. Gestión de Permisos y Roles](#-1-gestión-de-permisos-y-roles)
2. [💵 2. Caja, Bóveda y Transferencias](#-2-caja-bóveda-y-transferencias)
3. [👤 3. Usuarios y Seguridad](#-3-usuarios-y-seguridad)
4. [💍 4. Prendas e Inventario](#-4-prendas-e-inventario)
5. [📈 5. Créditos, Refrendos y Empeños](#-5-créditos-refrendos-y-empeños)
6. [🔍 6. Diagnósticos, Logs y Auditoría](#-6-diagnósticos-logs-y-auditoría)

---

## 🔐 1. Gestión de Permisos y Roles

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

### 1.2 Promover un Usuario a Administrador o Superadmin
Si un supervisor o gerente necesita permisos totales inmediatos debido a una contingencia:

```sql
UPDATE users 
SET 
    rol = 'superadmin', -- Opciones: 'administrador', 'supervisor', 'cajero', 'tasador', 'vendedor'
    activo = 1
WHERE username = 'Angelmoreira9210';
```

### 1.3 Listar Usuarios con Permiso Específico
Útil para auditar quién puede realizar acciones críticas (ej. aplicar descuentos o anular operaciones):

```sql
SELECT u.id, u.name, u.username, u.rol, p.modulo, p.accion
FROM users u
JOIN user_permissions up ON u.id = up.user_id
JOIN permissions p ON up.permission_id = p.id
WHERE p.modulo = 'ventas' AND p.accion = 'aplicar_descuento' AND u.activo = 1;
```

---

## 💵 2. Caja, Bóveda y Transferencias

### 2.1 Corregir Descuadre de Caja al Cierre (ej: Q500 faltantes)
Si la caja se cerró con diferencia (descuadre) debido a un movimiento de ingreso o egreso que no se registró en el sistema a tiempo:

```sql
-- Paso A: Insertar el movimiento de incremento omitido (ej: 500.00 en caja_id 34)
INSERT INTO movimiento_cajas (caja_id, tipo, monto, concepto, detalles_movimiento, estado, user_id, created_at, updated_at)
SELECT 
    34, -- ID de la caja a ajustar
    'incremento', -- 'incremento' o 'decremento'
    500.00, -- Monto del ajuste
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
WHERE id = 34; -- ID de la caja a reabrir
```

### 2.4 Forzar Cierre de Caja Abierta de un Día Anterior
Si un cajero dejó su turno abierto y el sistema bloquea operaciones en la nueva fecha de trabajo:

```sql
UPDATE caja_apertura_cierres 
SET 
    estado = 'cerrada',
    saldo_final = saldo_inicial + COALESCE((
        SELECT SUM(CASE WHEN tipo IN ('incremento', 'ingreso_pago') THEN monto ELSE -monto END)
        FROM movimiento_cajas
        WHERE caja_id = 34 AND estado = 'aplicado'
    ), 0),
    fecha_cierre = NOW(),
    diferencia = 0.00,
    resultado_arqueo = 'cuadrada',
    detalles_arqueo = '{"nota": "Cierre administrativo forzado por base de datos"}'
WHERE id = 34 AND estado = 'abierta';
```

### 2.5 Destrabar Transferencia de Bóveda en Estado Pendiente
Si una transferencia entre bóvedas de sucursales quedó atascada en estado 'pendiente':

```sql
-- Caso A: Aprobar la transferencia manualmente y registrar quién la aprobó
UPDATE boveda_movimientos 
SET 
    estado = 'aprobado',
    aprobado_por = 1, -- ID del usuario administrador/supervisor
    fecha_aprobacion = NOW()
WHERE id = 12; -- ID del movimiento de transferencia pendiente

-- Caso B: Rechazar/Anular la transferencia pendiente
UPDATE boveda_movimientos 
SET 
    estado = 'rechazado',
    motivo_rechazo = 'Anulado administrativamente por soporte técnico',
    aprobado_por = 1,
    fecha_aprobacion = NOW()
WHERE id = 12;
```

### 2.6 Recalcular y Sincronizar Saldo Real de Bóvedas
Si existe alguna discrepancia y el saldo actual de una bóveda no refleja la suma real de sus movimientos aprobados (por ejemplo, después de corregir tipos de movimiento):

```sql
-- Actualizar el saldo de todas las bóvedas basándose en la suma aritmética de sus movimientos aprobados
UPDATE bovedas b
SET b.saldo_actual = COALESCE((
    SELECT SUM(
        CASE 
            WHEN tipo_movimiento IN ('entrada', 'transferencia_entrada', 'ingreso_cierre_diario') THEN monto
            WHEN tipo_movimiento IN ('salida', 'transferencia_salida') THEN -monto
            ELSE 0
        END
    )
    FROM boveda_movimientos
    WHERE boveda_id = b.id AND estado = 'aprobado' AND deleted_at IS NULL
), 0);
```

### 2.7 Ajuste Manual Permanente del Saldo de Bóveda
**IMPORTANTE:** Si modificas el campo `saldo_actual` directamente en la tabla `bovedas`, este cambio se perderá la próxima vez que el sistema realice una operación y recalcule automáticamente. La forma correcta de ajustar el saldo es insertando un movimiento de ajuste en la tabla `boveda_movimientos`:

```sql
START TRANSACTION;

-- Paso A: Insertar el movimiento de ajuste (ej: sumar Q5,000 a la bóveda con ID 3)
INSERT INTO boveda_movimientos (
    boveda_id, 
    usuario_id, 
    sucursal_id, 
    tipo_movimiento, 
    monto, 
    concepto, 
    estado, 
    aprobado_por, 
    fecha_aprobacion, 
    created_at, 
    updated_at
)
VALUES (
    3,              -- ID de la bóveda a ajustar
    1,              -- ID del usuario administrador
    1,              -- ID de la sucursal de la bóveda
    'entrada',      -- 'entrada' para sumar dinero, 'salida' para restar dinero
    5000.00,        -- Monto de ajuste
    'AJUSTE MANUAL DE SALDO (Corrección por diferencia física de caja)', 
    'aprobado', 
    1,              -- Aprobado por el admin con ID 1
    NOW(), 
    NOW(), 
    NOW()
);

-- Paso B: Recalcular y actualizar permanentemente el saldo en la bóveda
UPDATE bovedas 
SET saldo_actual = (
    SELECT COALESCE(SUM(
        CASE 
            WHEN tipo_movimiento IN ('entrada', 'transferencia_entrada', 'ingreso_cierre_diario') THEN monto
            WHEN tipo_movimiento IN ('salida', 'transferencia_salida') THEN -monto
            ELSE 0
        END
    ), 0)
    FROM boveda_movimientos
    WHERE boveda_id = 3 AND estado = 'aprobado' AND deleted_at IS NULL
)
WHERE id = 3; -- ID de la bóveda ajustada

COMMIT;
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

### 3.2 Forzar Cambio de Contraseña en el Próximo Login
```sql
UPDATE users 
SET 
    force_password_change = 1 
WHERE username = 'Angelmoreira9210';
```

### 3.3 Desactivar de Emergencia un Usuario (Bloqueo Total)
Si se requiere revocar el acceso a un empleado de forma inmediata:

```sql
UPDATE users 
SET 
    activo = 0,
    locked_until = '2099-12-31 23:59:59',
    failed_login_attempts = 5
WHERE username = 'vendedor_retirado';
```

---

## 💍 4. Prendas e Inventario

### 4.1 Devolver Prenda Vendida al Estado de Venta
Si una venta se registró incorrectamente y la prenda debe regresar al inventario disponible:

```sql
UPDATE prendas 
SET 
    estado = 'en_venta',
    credito_prendario_id = NULL -- Desvincular de créditos si corresponde
WHERE id = 123; -- ID de la prenda
```

### 4.2 Trasladar Prenda de Sucursal Administrativamente
Si una prenda fue enviada físicamente a otra tienda y debe actualizarse en el sistema:

```sql
UPDATE prendas 
SET 
    sucursal_id = 2 -- ID de la sucursal destino
WHERE id = 123;
```

### 4.3 Reversar Completamente una Venta Errante
Para revertir una venta mal ingresada, liberando la prenda de vuelta al stock:

```sql
START TRANSACTION;

-- 1. Marcar la venta como cancelada
UPDATE ventas 
SET 
    estado = 'cancelada', 
    fecha_cancelacion = NOW(), 
    motivo_cancelacion = 'Reversión administrativa por error de digitación' 
WHERE id = 45; -- ID de la venta

-- 2. Regresar la prenda al stock disponible
UPDATE prendas p
JOIN ventas v ON p.id = v.prenda_id
SET p.estado = 'en_venta'
WHERE v.id = 45;

-- 3. Si la venta afectó caja, anular el movimiento de caja asociado
UPDATE movimiento_cajas mc
JOIN ventas v ON mc.venta_id = v.id -- Asegúrate que exista la columna venta_id o relación
SET mc.estado = 'anulado',
    mc.concepto = CONCAT(mc.concepto, ' - ANULADO POR REVERSION DE VENTA')
WHERE v.id = 45;

COMMIT;
```

---

## 📈 5. Créditos, Refrendos y Empeños

### 5.1 Reversar / Eliminar un Refrendo Erróneo
Si un cajero ingresó un pago de refrendo con valores incorrectos, esta transacción revierte el crédito a su vencimiento y estado anterior, y anula el movimiento de caja:

```sql
START TRANSACTION;

-- Paso A: Identificar los datos del refrendo (Ej: refrendo_id = 7)
-- Ejecutar este SELECT primero para anotar los valores
SELECT credito_id, fecha_vencimiento_anterior, monto_total_pagado, caja_movimiento_id 
FROM refrendos 
WHERE id = 7;

-- Paso B: Revertir la fecha de vencimiento y saldos en el crédito prendario
-- (Reemplazar con los datos obtenidos en el Paso A)
UPDATE creditos_prendarios cp
JOIN refrendos r ON cp.id = r.credito_id
SET 
    cp.fecha_vencimiento = r.fecha_vencimiento_anterior,
    cp.estado = 'vigente', -- O el estado anterior (vigente / en_mora / vencido)
    cp.capital_pendiente = cp.capital_pendiente + r.monto_capital_pagado,
    cp.capital_pagado = cp.capital_pagado - r.monto_capital_pagado,
    cp.interes_pagado = cp.interes_pagado - (r.monto_total_pagado - r.monto_capital_pagado - r.descuento_aplicado)
WHERE r.id = 7;

-- Paso C: Anular el movimiento de caja generado por el cobro
UPDATE movimiento_cajas mc
JOIN refrendos r ON mc.id = r.caja_movimiento_id
SET 
    mc.estado = 'anulado',
    mc.concepto = CONCAT(mc.concepto, ' - REVERSADO POR SOPORTE')
WHERE r.id = 7;

-- Paso D: Eliminar (o softdelete) el registro del refrendo
UPDATE refrendos 
SET deleted_at = NOW() 
WHERE id = 7;

COMMIT;
```

### 5.2 Reactivar un Crédito Pagado/Cancelado por Error
Si un crédito fue liquidado por equivocación y debe volver a estar vigente:

```sql
UPDATE creditos_prendarios 
SET 
    estado = 'vigente',
    fecha_cancelacion = NULL,
    capital_pendiente = monto_aprobado - capital_pagado
WHERE id = 89; -- ID del crédito prendario
```

### 5.3 Condonar o Ajustar Mora de un Empeño
Si se le otorga un descuento o condonación total de la mora acumulada a un cliente:

```sql
UPDATE creditos_prendarios 
SET 
    mora_generada = mora_pagada, -- Iguala la mora generada a la pagada para dejar el saldo en 0
    dias_mora = 0,
    observaciones = CONCAT(COALESCE(observaciones, ''), ' | Mora condonada administrativamente el ', NOW())
WHERE id = 89;
```

### 5.4 Forzar Paso a Remate de Prenda por Incumplimiento
Si el plazo de gracia expiró y la prenda debe pasar automáticamente al catálogo de remates:

```sql
START TRANSACTION;

-- 1. Cambiar estado del crédito a 'vendido' o 'incobrable'
UPDATE creditos_prendarios 
SET 
    estado = 'incobrable',
    observaciones = CONCAT(COALESCE(observaciones, ''), ' | Prenda enviada a remate por falta de pago.')
WHERE id = 89;

-- 2. Cambiar estado de la prenda a 'en_remate' o 'en_venta'
UPDATE prendas 
SET 
    estado = 'en_venta', -- Disponible para la tienda
    observaciones = 'Prenda adjudicada por incumplimiento de contrato.'
WHERE credito_prendario_id = 89;

COMMIT;
```

---

## 🔍 6. Diagnósticos, Logs y Auditoría

### 6.1 Ver los Últimos 20 Errores del Sistema
Permite identificar rápidamente fallos del sistema o excepciones recientes en producción:

```sql
SELECT id, user_id, exception, message, file, line, status_code, created_at
FROM system_error_logs
ORDER BY id DESC
LIMIT 20;
```

### 6.2 Auditar Movimientos de un Usuario en el Día
Verifica el historial detallado de movimientos de caja de un cajero para cuadres manuales:

```sql
SELECT mc.id, mc.created_at, mc.tipo, mc.monto, mc.concepto, mc.estado, u.username
FROM movimiento_cajas mc
JOIN users u ON mc.user_id = u.id
WHERE u.username = 'Angelmoreira9210'
  AND DATE(mc.created_at) = CURDATE()
ORDER BY mc.created_at DESC;
```

### 6.3 Buscar Transacciones de Caja sin Registro Contable
Consulta útil para verificar si la integración contable (`ctb_diario`) falló al registrar algún movimiento de caja:

```sql
SELECT mc.id AS movimiento_caja_id, mc.concepto, mc.monto, mc.created_at
FROM movimiento_cajas mc
LEFT JOIN ctb_diario cd ON mc.id = cd.movimiento_caja_id -- Ajustar según nombre real de FK
WHERE mc.estado = 'aplicado'
  AND cd.id IS NULL
  AND mc.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);
```
