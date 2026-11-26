# DigiPrenda — Esquema BD completo (Clientes, Créditos, Prendas)

Fuente de verdad: migraciones Laravel de `empenios-api` (MySQL).  
Misma BD para DigiPrenda / PrendaMás / frontend `sistema-de-gestin-de`.

Detalle ampliado de prendas/categorías: ver también `ESQUEMA_PRENDAS_CATEGORIAS.md`.

---

## Diagrama general

```
sucursales
users
   │
clientes ──< cliente_referencias
   │     └──< cliente_borradores (por user_id)
   │
   └──< creditos_prendarios
            │  FK: cliente_id, sucursal_id, analista/cajero/tasador → users
            │  FK: plan_interes_id → planes_interes_categoria
            │  FK: credito_renovado_id → self
            │
            ├──< prendas ──→ categoria_productos
            │       ├──< prenda_imagenes
            │       ├──< prenda_datos_adicionales
            │       ├──< tasaciones
            │       └──< transferencias_prendas
            │
            ├──< credito_plan_pagos (cuotas)
            │       └── ultimo_movimiento_id → credito_movimientos
            │
            ├──< credito_movimientos (kardex / pagos)
            │       └── cuota_id → credito_plan_pagos
            │
            ├──< refrendos
            ├──< credito_gasto ──→ gastos
            └──< auditoria_creditos

categoria_productos ──< planes_interes_categoria
                              └── M:N plan_interes_categorias
```

---

## 1. `clientes`

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `codigo_cliente` | VARCHAR(30) UNIQUE | NO | | `CLI-YYYYMMDD-XXXXXX` |
| `nombres` | VARCHAR(255) | NO | | |
| `apellidos` | VARCHAR(255) | NO | | |
| `dpi` | VARCHAR(20) | SÍ | NULL | Unique compuesto c/ eliminado |
| `nit` | VARCHAR(20) | SÍ | NULL | |
| `fecha_nacimiento` | DATE | NO | | |
| `genero` | ENUM(`masculino`,`femenino`,`otro`) | NO | | |
| `estado_civil` | VARCHAR(100) | SÍ | NULL | |
| `profesion` | VARCHAR(150) | SÍ | NULL | |
| `telefono` | VARCHAR(20) | SÍ | NULL | |
| `telefono_secundario` | VARCHAR(20) | SÍ | NULL | |
| `email` | VARCHAR(255) | SÍ | NULL | |
| `direccion` | TEXT | NO | | |
| `municipio` | VARCHAR(100) | SÍ | NULL | |
| `departamento_geoname_id` | INT | SÍ | NULL | |
| `municipio_geoname_id` | INT | SÍ | NULL | |
| `fotografia` | TEXT | SÍ | NULL | ruta / base64 / URL |
| `estado` | ENUM(`activo`,`inactivo`) | NO | `activo` | |
| `sucursal` | VARCHAR(255) | SÍ | NULL | texto, no FK |
| `tipo_cliente` | ENUM(`regular`,`vip`) | NO | `regular` | |
| `notas` | TEXT | SÍ | NULL | |
| `eliminado` | BOOLEAN | NO | false | soft lógico extra |
| `eliminado_en` | TIMESTAMP | SÍ | NULL | |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | | SoftDeletes |

---

## 2. `cliente_referencias`

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `cliente_id` | BIGINT UNSIGNED FK | NO | | → `clientes` CASCADE |
| `nombre` | VARCHAR(255) | NO | | |
| `telefono` | VARCHAR(20) | NO | | |
| `relacion` | VARCHAR(255) | SÍ | NULL | |
| `created_at` / `updated_at` | TIMESTAMP | | | |

---

## 3. `cliente_borradores`

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `user_id` | BIGINT UNSIGNED FK | NO | | → `users` CASCADE |
| `titulo` | VARCHAR(100) | NO | `Borrador` | |
| `datos` | JSON | NO | | Formulario cliente |
| `created_at` / `updated_at` | TIMESTAMP | | | |

---

## 4. `sucursales`

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `codigo` | VARCHAR(20) UNIQUE | NO | | |
| `nombre` | VARCHAR(255) | NO | | |
| `direccion` | TEXT | SÍ | NULL | |
| `telefono` | VARCHAR(20) | SÍ | NULL | |
| `email` | VARCHAR(255) | SÍ | NULL | |
| `ciudad` | VARCHAR(100) | SÍ | NULL | |
| `departamento` | VARCHAR(100) | SÍ | NULL | |
| `municipio` | VARCHAR(100) | SÍ | NULL | |
| `departamento_geoname_id` | INT | SÍ | NULL | |
| `municipio_geoname_id` | INT | SÍ | NULL | |
| `descripcion` | TEXT | SÍ | NULL | |
| `activa` | BOOLEAN | NO | true | |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | | SoftDeletes |

---

## 5. `creditos_prendarios`

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `numero_credito` | VARCHAR(30) UNIQUE | NO | | |
| `cliente_id` | BIGINT UNSIGNED FK | NO | | → `clientes` |
| `sucursal_id` | BIGINT UNSIGNED FK | NO | | → `sucursales` |
| `analista_id` | BIGINT UNSIGNED FK | SÍ | NULL | → `users` |
| `cajero_id` | BIGINT UNSIGNED FK | SÍ | NULL | → `users` |
| `tasador_id` | BIGINT UNSIGNED FK | SÍ | NULL | → `users` |
| `plan_interes_id` | BIGINT UNSIGNED FK | SÍ | NULL | → `planes_interes_categoria` |
| `estado` | ENUM | NO | `solicitado` | ver lista abajo |
| `fecha_solicitud` | DATE | NO | | |
| `fecha_analisis` | DATE | SÍ | NULL | |
| `fecha_aprobacion` | DATE | SÍ | NULL | |
| `fecha_desembolso` | DATE | SÍ | NULL | |
| `fecha_primer_pago` | DATE | SÍ | NULL | |
| `fecha_vencimiento` | DATE | SÍ | NULL | |
| `fecha_cancelacion` | DATE | SÍ | NULL | |
| `fecha_ultimo_pago` | DATE | SÍ | NULL | |
| `fecha_incobrable` | DATE | SÍ | NULL | |
| `monto_solicitado` | DECIMAL(20,2) | NO | 0 | |
| `monto_aprobado` | DECIMAL(20,2) | NO | 0 | |
| `monto_desembolsado` | DECIMAL(20,2) | NO | 0 | |
| `valor_tasacion` | DECIMAL(20,2) | NO | 0 | |
| `capital_pendiente` | DECIMAL(20,2) | NO | 0 | |
| `capital_pagado` | DECIMAL(20,2) | NO | 0 | |
| `interes_generado` | DECIMAL(20,2) | NO | 0 | |
| `interes_pagado` | DECIMAL(20,2) | NO | 0 | |
| `mora_generada` | DECIMAL(20,2) | NO | 0 | |
| `mora_pagada` | DECIMAL(20,2) | NO | 0 | |
| `tasa_interes` | DECIMAL(8,2) | NO | 0 | % |
| `porcentaje_descuento_interes` | DECIMAL(5,2) | NO | 0 | |
| `descuento_interes_motivo` | VARCHAR(255) | SÍ | NULL | |
| `tasa_mora` | DECIMAL(8,2) | NO | 0 | % |
| `tipo_mora` | VARCHAR(20) | NO | `porcentaje` | `porcentaje` \| `monto_fijo` |
| `mora_monto_fijo` | DECIMAL(12,2) | SÍ | NULL | Mora fija/día |
| `tipo_interes` | ENUM(`mensual`,`quincenal`,`semanal`,`diario`) | NO | `mensual` | |
| `metodo_calculo` | ENUM(`francesa`,`flat`) | NO | `francesa` | |
| `afecta_interes_mensual` | BOOLEAN | NO | false | |
| `permite_pago_capital_diferente` | BOOLEAN | NO | false | |
| `plazo_dias` | INT | NO | 30 | |
| `dias_gracia` | INT | NO | 0 | |
| `numero_cuotas` | INT | NO | 1 | |
| `monto_cuota` | DECIMAL(20,2) | NO | 0 | |
| `dias_mora` | INT | NO | 0 | |
| `calificacion` | ENUM(`A`,`B`,`C`,`D`,`E`) | NO | `A` | |
| `forma_desembolso` | ENUM(`efectivo`,`transferencia`,`cheque`) | SÍ | NULL | |
| `referencia_desembolso` | VARCHAR(100) | SÍ | NULL | |
| `observaciones` | TEXT | SÍ | NULL | |
| `motivo_rechazo` | TEXT | SÍ | NULL | |
| `motivo_incobrable` | TEXT | SÍ | NULL | |
| `numero_pagare` | VARCHAR(50) | SÍ | NULL | |
| `numero_contrato` | VARCHAR(50) | SÍ | NULL | |
| `requiere_renovacion` | BOOLEAN | NO | false | |
| `credito_renovado_id` | BIGINT UNSIGNED FK | SÍ | NULL | → self |
| `refrendos_realizados` | INT | NO | 0 | |
| `refrendos_maximos` | INT | SÍ | NULL | NULL = ilimitado |
| `permite_refrendo` | BOOLEAN | NO | true | |
| `fecha_ultimo_refrendo` | DATETIME | SÍ | NULL | |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | | SoftDeletes |

**`estado`:**  
`solicitado` \| `en_analisis` \| `aprobado` \| `vigente` \| `pagado` \| `vencido` \| `en_mora` \| `incobrable` \| `recuperado` \| `vendido` \| `rechazado` \| `cancelado` \| `rematado` \| `rescatado` \| `en_inventario` \| `anulado` \| `renovado`

---

## 6. `credito_plan_pagos` (cuotas)

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `credito_prendario_id` | BIGINT UNSIGNED FK | NO | | CASCADE |
| `numero_cuota` | INT | NO | | UNIQUE c/ crédito |
| `fecha_vencimiento` | DATE | NO | | |
| `fecha_pago` | DATE | SÍ | NULL | |
| `estado` | ENUM | NO | `pendiente` | ver abajo |
| `capital_proyectado` | DECIMAL(20,2) | NO | 0 | |
| `interes_proyectado` | DECIMAL(20,2) | NO | 0 | |
| `mora_proyectada` | DECIMAL(20,2) | NO | 0 | |
| `otros_cargos_proyectados` | DECIMAL(20,2) | NO | 0 | |
| `monto_cuota_proyectado` | DECIMAL(20,2) | NO | 0 | |
| `capital_pagado` | DECIMAL(20,2) | NO | 0 | |
| `interes_pagado` | DECIMAL(20,2) | NO | 0 | |
| `mora_pagada` | DECIMAL(20,2) | NO | 0 | |
| `otros_cargos_pagados` | DECIMAL(20,2) | NO | 0 | |
| `monto_total_pagado` | DECIMAL(20,2) | NO | 0 | |
| `capital_pendiente` | DECIMAL(20,2) | NO | 0 | |
| `interes_pendiente` | DECIMAL(20,2) | NO | 0 | |
| `mora_pendiente` | DECIMAL(20,2) | NO | 0 | |
| `otros_cargos_pendientes` | DECIMAL(20,2) | NO | 0 | |
| `monto_pendiente` | DECIMAL(20,2) | NO | 0 | |
| `saldo_capital_credito` | DECIMAL(20,2) | NO | 0 | |
| `dias_mora` | INT | NO | 0 | |
| `fecha_inicio_mora` | DATE | SÍ | NULL | |
| `tasa_mora_aplicada` | DECIMAL(8,2) | NO | 0 | |
| `mora_monto_fijo_aplicado` | DECIMAL(12,2) | SÍ | NULL | |
| `ultimo_movimiento_id` | BIGINT UNSIGNED FK | SÍ | NULL | → `credito_movimientos` |
| `usuario_pago_id` | BIGINT UNSIGNED FK | SÍ | NULL | → `users` |
| `observaciones` | TEXT | SÍ | NULL | |
| `permite_pago_parcial` | BOOLEAN | NO | true | |
| `es_cuota_gracia` | BOOLEAN | NO | false | |
| `tipo_modificacion` | ENUM(`original`,`refinanciamiento`,`reestructuracion`,`ajuste`,`condonacion`) | NO | `original` | |
| `motivo_modificacion` | TEXT | SÍ | NULL | |
| `modificado_por` | BIGINT UNSIGNED FK | SÍ | NULL | → `users` |
| `fecha_modificacion` | DATETIME | SÍ | NULL | |
| `tasa_interes_aplicada` | DECIMAL(8,2) | NO | 0 | |
| `dias_cuota` | INT | NO | 30 | |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | | SoftDeletes |

**`estado`:** `pendiente` \| `pagada` \| `pagada_parcial` \| `vencida` \| `en_mora` \| `cancelada` \| `condonada` \| `renovada`

---

## 7. `credito_movimientos` (kardex / pagos)

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `credito_prendario_id` | BIGINT UNSIGNED FK | NO | | CASCADE |
| `usuario_id` | BIGINT UNSIGNED FK | NO | | → `users` |
| `sucursal_id` | BIGINT UNSIGNED FK | NO | | → `sucursales` |
| `cuota_id` | BIGINT UNSIGNED FK | SÍ | NULL | → `credito_plan_pagos` |
| `numero_movimiento` | VARCHAR(50) UNIQUE | NO | | |
| `numero_recibo` | VARCHAR(50) | SÍ | NULL | |
| `numero_factura` | VARCHAR(50) | SÍ | NULL | |
| `tipo_movimiento` | ENUM | NO | | ver abajo |
| `numero_cuota` | INT | NO | 0 | |
| `fecha_movimiento` | DATE | NO | | |
| `fecha_registro` | DATETIME | NO | | |
| `fecha_boleta` | DATE | SÍ | NULL | |
| `monto_total` | DECIMAL(20,2) | NO | 0 | |
| `capital` | DECIMAL(20,2) | NO | 0 | |
| `interes` | DECIMAL(20,2) | NO | 0 | |
| `mora` | DECIMAL(20,2) | NO | 0 | |
| `otros_cargos` | DECIMAL(20,2) | NO | 0 | |
| `saldo_capital` | DECIMAL(20,2) | NO | 0 | post-mov |
| `saldo_interes` | DECIMAL(20,2) | NO | 0 | |
| `saldo_mora` | DECIMAL(20,2) | NO | 0 | |
| `forma_pago` | ENUM | SÍ | NULL | efectivo, transferencia, cheque, tarjeta_debito, tarjeta_credito, deposito_bancario, mixto |
| `banco` | VARCHAR(100) | SÍ | NULL | |
| `numero_cuenta` | VARCHAR(50) | SÍ | NULL | |
| `numero_cheque` | VARCHAR(50) | SÍ | NULL | |
| `numero_autorizacion` | VARCHAR(50) | SÍ | NULL | |
| `referencia_bancaria` | VARCHAR(100) | SÍ | NULL | |
| `concepto` | TEXT | SÍ | NULL | |
| `observaciones` | TEXT | SÍ | NULL | |
| `estado` | ENUM(`activo`,`reversado`,`anulado`,`pendiente`) | NO | `activo` | |
| `reversado_por` | BIGINT UNSIGNED FK | SÍ | NULL | → `users` |
| `fecha_reversion` | DATETIME | SÍ | NULL | |
| `motivo_reversion` | TEXT | SÍ | NULL | |
| `movimiento_reversa_id` | BIGINT UNSIGNED FK | SÍ | NULL | → self |
| `moneda` | VARCHAR(10) | NO | `GTQ` | |
| `tipo_cambio` | DECIMAL(10,4) | NO | 1 | |
| `terminal` | VARCHAR(20) | SÍ | NULL | |
| `turno` | VARCHAR(20) | SÍ | NULL | |
| `ip_origen` | VARCHAR(45) | SÍ | NULL | |
| `datos_adicionales` | JSON | SÍ | NULL | |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | | SoftDeletes |

**`tipo_movimiento`:**  
`desembolso` \| `pago` \| `pago_parcial` \| `pago_total` \| `pago_adelantado` \| `pago_interes` \| `renovacion` \| `cargo_mora` \| `cargo_administracion` \| `ajuste` \| `reversion` \| `condonacion`

---

## 8. `refrendos`

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `credito_id` | BIGINT UNSIGNED FK | NO | | → `creditos_prendarios` CASCADE |
| `numero_refrendo` | INT | NO | | UNIQUE c/ crédito |
| `tipo_refrendo` | ENUM(`parcial`,`total`,`con_capital`,`re_empeno`) | NO | `parcial` | |
| `monto_interes_adeudado` | DECIMAL(12,2) | NO | 0 | |
| `monto_mora_adeudado` | DECIMAL(12,2) | NO | 0 | |
| `monto_capital_pagado` | DECIMAL(12,2) | NO | 0 | |
| `monto_total_pagado` | DECIMAL(12,2) | NO | 0 | |
| `fecha_refrendo` | DATETIME | NO | CURRENT | |
| `fecha_vencimiento_anterior` | DATE | NO | | |
| `fecha_vencimiento_nueva` | DATE | NO | | |
| `dias_extendidos` | INT | NO | 0 | |
| `tasa_interes_aplicada` | DECIMAL(8,2) | NO | 0 | |
| `plazo_dias_nuevo` | INT | NO | 0 | |
| `promocion_aplicada` | VARCHAR(100) | SÍ | NULL | |
| `descuento_aplicado` | DECIMAL(12,2) | NO | 0 | |
| `usuario_id` | BIGINT UNSIGNED FK | NO | | → `users` |
| `sucursal_id` | BIGINT UNSIGNED FK | NO | | → `sucursales` |
| `caja_movimiento_id` | BIGINT UNSIGNED | SÍ | NULL | |
| `recibo_pdf_url` | VARCHAR(255) | SÍ | NULL | |
| `observaciones` | TEXT | SÍ | NULL | |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | | SoftDeletes |

---

## 9. `gastos` (catálogo)

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id_gasto` | BIGINT UNSIGNED PK AI | NO | | PK nombre especial |
| `nombre` | VARCHAR(100) | NO | | |
| `tipo` | ENUM(`FIJO`,`VARIABLE`) | NO | | |
| `porcentaje` | DECIMAL(5,2) | SÍ | NULL | si VARIABLE |
| `monto` | DECIMAL(12,2) | SÍ | NULL | si FIJO |
| `descripcion` | TEXT | SÍ | NULL | |
| `activo` | BOOLEAN | NO | true | |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | | SoftDeletes |

## 10. `credito_gasto` (pivote)

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `credito_id` | BIGINT UNSIGNED FK | NO | | → `creditos_prendarios` |
| `gasto_id` | BIGINT UNSIGNED FK | NO | | → `gastos.id_gasto` |
| `valor_calculado` | DECIMAL(12,2) | SÍ | NULL | |
| `created_at` / `updated_at` | TIMESTAMP | | | |
| UNIQUE | (`credito_id`, `gasto_id`) | | | |

---

## 11. `auditoria_creditos`

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `credito_prendario_id` | BIGINT UNSIGNED FK | NO | | CASCADE |
| `usuario_id` | BIGINT UNSIGNED FK | NO | | → `users` |
| `accion` | ENUM | NO | | creado, modificado, aprobado, rechazado, desembolsado, pagado, renovado, rescate, anulado, movimiento_anulado, estado_cambiado, estado_actualizado, reactivado |
| `campo_modificado` | VARCHAR(100) | SÍ | NULL | |
| `valor_anterior` | TEXT | SÍ | NULL | |
| `valor_nuevo` | TEXT | SÍ | NULL | |
| `observaciones` | TEXT | SÍ | NULL | |
| `ip_address` | VARCHAR(45) | SÍ | NULL | |
| `user_agent` | TEXT | SÍ | NULL | |
| `created_at` | TIMESTAMP | NO | CURRENT | sin updated_at |

---

## 12–20. Prendas y categorías (resumen)

Tablas (detalle completo en `ESQUEMA_PRENDAS_CATEGORIAS.md`):

| Tabla | Rol |
|-------|-----|
| `categoria_productos` | Categorías + tasas default + campos dinámicos JSON |
| `planes_interes_categoria` | Planes de tasa/plazo por categoría |
| `plan_interes_categorias` | Pivote M:N plan ↔ categoría |
| `prendas` | Garantía / artículo; FK `credito_prendario_id`, `categoria_producto_id` |
| `prenda_imagenes` | Fotos |
| `prenda_datos_adicionales` | EAV campos dinámicos |
| `tasaciones` | Historial de avalúos |
| `transferencias_prendas` | Traslado entre sucursales |
| `venta_detalles` | Ítem de venta de prenda |

**`prendas.estado`:** `en_custodia` \| `recuperada` \| `en_venta` \| `vendida` \| `perdida` \| `deteriorada` \| `devuelta`  
**`prendas.tipo_ingreso`:** `empeño` \| `compra_directa`  
**`prendas.condicion`:** `excelente` \| `muy_buena` \| `buena` \| `regular` \| `mala`

---

## Exportar estructura real desde MySQL

```sql
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'clientes','cliente_referencias','cliente_borradores','sucursales',
    'creditos_prendarios','credito_plan_pagos','credito_movimientos',
    'refrendos','gastos','credito_gasto','auditoria_creditos',
    'categoria_productos','planes_interes_categoria','plan_interes_categorias',
    'prendas','prenda_imagenes','prenda_datos_adicionales',
    'tasaciones','transferencias_prendas','venta_detalles'
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```
