# DigiPrenda / Empenios — Esquema BD: Prendas, Categorías y relacionadas

> Esquema completo (clientes + créditos + prendas): ver **`ESQUEMA_BD_DIGIPRENDA.md`**.

Fuente: migraciones de `empenios-api` (MySQL). SoftDeletes donde se indica `deleted_at`.

## Diagrama de relaciones

```
categoria_productos (1)
   │
   ├──< planes_interes_categoria (N)     [FK legacy categoria_producto_id]
   │         │
   │         └── M:N via plan_interes_categorias
   │
   └──< prendas (N)
          │
          ├── FK credito_prendario_id → creditos_prendarios (nullable si compra_directa)
          ├── FK tasador_id → users
          ├── FK comprador_id → clientes
          ├── FK sucursal_id → sucursales
          │
          ├──< prenda_imagenes (N)
          ├──< prenda_datos_adicionales (N)   [EAV campos dinámicos]
          ├──< tasaciones (N)
          ├──< transferencias_prendas (N)
          └──< venta_detalles (N)             [cuando se vende]
```

---

## 1. `categoria_productos`

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `codigo` | VARCHAR(20) UNIQUE | NO | | Ej: `CAT-INM` |
| `nombre` | VARCHAR(255) | NO | | |
| `descripcion` | TEXT | SÍ | NULL | |
| `color` | VARCHAR(7) | SÍ | NULL | Hex `#RRGGBB` |
| `icono` | VARCHAR(50) | SÍ | NULL | Nombre icono UI |
| `orden` | INT | NO | 0 | Orden visual |
| `activa` | BOOLEAN | NO | true | |
| `tasa_interes_default` | DECIMAL(8,2) | SÍ | NULL | % |
| `tasa_mora_default` | DECIMAL(8,2) | SÍ | NULL | % |
| `tipo_mora_default` | VARCHAR(20) | SÍ | NULL | `porcentaje` \| `monto_fijo` |
| `mora_monto_fijo_default` | DECIMAL(12,2) | SÍ | NULL | Mora fija/día |
| `plazo_maximo_dias` | INT | SÍ | NULL | |
| `porcentaje_prestamo_maximo` | DECIMAL(5,2) | SÍ | NULL | % sobre avalúo |
| `metodo_calculo_default` | ENUM(`francesa`,`flat`) | NO | `francesa` | |
| `afecta_interes_mensual` | BOOLEAN | NO | false | |
| `permite_pago_capital_diferente` | BOOLEAN | NO | false | |
| `refrendos_maximos_default` | INT | SÍ | NULL | NULL = ilimitado |
| `requiere_pago_capital_refrendo` | BOOLEAN | NO | false | |
| `porcentaje_capital_minimo` | DECIMAL(5,2) | SÍ | NULL | % |
| `campos_formulario` | JSON | SÍ | NULL | Flags de campos base |
| `campos_adicionales` | JSON | SÍ | NULL | Array de campos custom |
| `campos_dinamicos` | JSON | SÍ | NULL | Extra dinámicos |
| `created_at` | TIMESTAMP | SÍ | | |
| `updated_at` | TIMESTAMP | SÍ | | |
| `deleted_at` | TIMESTAMP | SÍ | NULL | Soft delete |

**Ejemplo `campos_adicionales`:**
```json
[
  {"nombre":"quilates","label":"Quilates","tipo":"number","requerido":true},
  {"nombre":"material","label":"Material","tipo":"select","opciones":["Oro","Plata"],"requerido":true}
]
```

Tipos de campo usados: `text`, `number`, `select`, `checkbox`, `date`, `textarea`, `mapa`.

---

## 2. `planes_interes_categoria`

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `categoria_producto_id` | BIGINT UNSIGNED FK | SÍ | NULL | Legacy; hoy puede ser multi vía pivote |
| `nombre` | VARCHAR(100) | NO | | |
| `codigo` | VARCHAR(50) | SÍ | NULL | Unique con categoría |
| `descripcion` | TEXT | SÍ | NULL | |
| `tipo_periodo` | ENUM(`diario`,`semanal`,`quincenal`,`mensual`) | NO | `semanal` | |
| `plazo_numero` | INT | NO | | Nº de periodos |
| `plazo_unidad` | ENUM(`dias`,`semanas`,`quincenas`,`meses`) | NO | `semanas` | |
| `plazo_dias_total` | INT | NO | | Días totales |
| `tasa_interes` | DECIMAL(8,4) | NO | | % por periodo |
| `tasa_almacenaje` | DECIMAL(8,4) | NO | 0 | % |
| `tasa_moratorios` | DECIMAL(8,4) | NO | 0 | % / día |
| `tipo_mora` | VARCHAR(20) | NO | `porcentaje` | `porcentaje` \| `monto_fijo` |
| `mora_monto_fijo` | DECIMAL(12,2) | SÍ | NULL | Mora fija/día |
| `porcentaje_prestamo` | DECIMAL(5,2) | NO | 60.00 | % |
| `monto_minimo` | DECIMAL(12,2) | SÍ | NULL | |
| `monto_maximo` | DECIMAL(12,2) | SÍ | NULL | |
| `dias_gracia` | INT | NO | 0 | |
| `dias_enajenacion` | INT | NO | 14 | |
| `cat` | DECIMAL(8,2) | SÍ | NULL | Costo anual total |
| `interes_anual` | DECIMAL(8,2) | SÍ | NULL | |
| `porcentaje_precio_venta` | DECIMAL(5,2) | SÍ | NULL | |
| `numero_refrendos_permitidos` | INT | SÍ | NULL | |
| `permite_refrendos` | BOOLEAN | NO | true | |
| `activo` | BOOLEAN | NO | true | |
| `es_default` | BOOLEAN | NO | false | |
| `orden` | INT | NO | 0 | |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | | SoftDeletes |

**FK:** `categoria_producto_id` → `categoria_productos.id` (ON DELETE CASCADE)

---

## 3. `plan_interes_categorias` (pivote M:N)

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `plan_id` | BIGINT UNSIGNED FK | NO | | → `planes_interes_categoria` |
| `categoria_id` | BIGINT UNSIGNED FK | NO | | → `categoria_productos` |
| `es_default` | BOOLEAN | NO | false | |
| `orden` | INT | NO | 0 | |
| `created_at` / `updated_at` | TIMESTAMP | | | |
| UNIQUE | (`plan_id`, `categoria_id`) | | | |

---

## 4. `prendas`

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `credito_prendario_id` | BIGINT UNSIGNED FK | SÍ | NULL | NULL si `compra_directa` |
| `tipo_ingreso` | ENUM(`empeño`,`compra_directa`) | NO | `empeño` | |
| `sucursal_id` | BIGINT UNSIGNED FK | SÍ | NULL | → `sucursales` |
| `codigo_cliente_propietario` | VARCHAR(30) | SÍ | NULL | Desnormalizado |
| `categoria_producto_id` | BIGINT UNSIGNED FK | NO | | → `categoria_productos` |
| `tasador_id` | BIGINT UNSIGNED FK | SÍ | NULL | → `users` |
| `codigo_prenda` | VARCHAR(50) UNIQUE | NO | | |
| `descripcion` | VARCHAR(500) | NO | | |
| `marca` | VARCHAR(100) | SÍ | NULL | |
| `modelo` | VARCHAR(100) | SÍ | NULL | |
| `serie` | VARCHAR(100) | SÍ | NULL | Nº serie |
| `color` | VARCHAR(50) | SÍ | NULL | |
| `caracteristicas` | TEXT (JSON en app) | SÍ | NULL | |
| `valor_estimado_cliente` | DECIMAL(20,2) | NO | 0 | |
| `valor_tasacion` | DECIMAL(20,2) | NO | 0 | |
| `valor_prestamo` | DECIMAL(20,2) | NO | 0 | |
| `porcentaje_prestamo` | DECIMAL(5,2) | NO | 70 | % |
| `valor_venta` | DECIMAL(20,2) | SÍ | NULL | Precio publicado |
| `estado` | ENUM | NO | `en_custodia` | ver abajo |
| `condicion` | ENUM | NO | `buena` | ver abajo |
| `ubicacion_fisica` | VARCHAR(100) | SÍ | NULL | |
| `seccion` | VARCHAR(50) | SÍ | NULL | |
| `estante` | VARCHAR(50) | SÍ | NULL | |
| `fotos` | JSON | SÍ | NULL | Legacy URLs |
| `foto_principal` | VARCHAR(255) | SÍ | NULL | |
| `fecha_ingreso` | DATE | NO | | |
| `fecha_tasacion` | DATE | SÍ | NULL | |
| `fecha_recuperacion` | DATE | SÍ | NULL | |
| `fecha_venta` | DATE | SÍ | NULL | |
| `fecha_publicacion_venta` | TIMESTAMP | SÍ | NULL | |
| `comprador_id` | BIGINT UNSIGNED FK | SÍ | NULL | → `clientes` |
| `precio_venta` | DECIMAL(20,2) | SÍ | NULL | |
| `factura_venta` | VARCHAR(50) | SÍ | NULL | |
| `observaciones` | TEXT | SÍ | NULL | |
| `datos_adicionales` | JSON | SÍ | NULL | Snapshot campos categoría |
| `requiere_mantenimiento` | BOOLEAN | NO | false | |
| `notas_mantenimiento` | TEXT | SÍ | NULL | |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | | SoftDeletes |

**`estado`:** `en_custodia` \| `recuperada` \| `en_venta` \| `vendida` \| `perdida` \| `deteriorada` \| `devuelta`

**`condicion`:** `excelente` \| `muy_buena` \| `buena` \| `regular` \| `mala`

---

## 5. `prenda_imagenes`

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `prenda_id` | BIGINT UNSIGNED FK | NO | | CASCADE |
| `nombre_archivo` | VARCHAR(255) | NO | | |
| `ruta_almacenamiento` | VARCHAR(500) | NO | | |
| `url_publica` | VARCHAR(500) | SÍ | NULL | |
| `mime_type` | VARCHAR(100) | NO | `image/jpeg` | |
| `tamano_bytes` | INT UNSIGNED | SÍ | NULL | |
| `ancho` | SMALLINT UNSIGNED | SÍ | NULL | px |
| `alto` | SMALLINT UNSIGNED | SÍ | NULL | px |
| `tipo_imagen` | VARCHAR(50) | NO | `general` | principal, frontal, detalle… |
| `etiqueta` | VARCHAR(100) | SÍ | NULL | |
| `descripcion` | TEXT | SÍ | NULL | |
| `es_principal` | BOOLEAN | NO | false | |
| `orden` | INT | NO | 0 | |
| `hash_contenido` | VARCHAR(64) | SÍ | NULL | SHA256 |
| `ruta_thumbnail` | VARCHAR(500) | SÍ | NULL | |
| `subida_por` | BIGINT UNSIGNED FK | SÍ | NULL | → `users` |
| `fecha_captura` | TIMESTAMP | SÍ | NULL | |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | | SoftDeletes |

---

## 6. `prenda_datos_adicionales` (EAV)

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `prenda_id` | BIGINT UNSIGNED FK | NO | | CASCADE |
| `campo_nombre` | VARCHAR(100) | NO | | Ej: `quilates` |
| `campo_valor` | VARCHAR(500) | SÍ | NULL | |
| `campo_tipo` | VARCHAR(50) | NO | `text` | text/number/select/… |
| `campo_label` | VARCHAR(150) | SÍ | NULL | |
| `orden` | INT | NO | 0 | |
| `created_at` / `updated_at` | TIMESTAMP | | | |
| UNIQUE | (`prenda_id`, `campo_nombre`) | | | |

---

## 7. `tasaciones`

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `prenda_id` | BIGINT UNSIGNED FK | NO | | CASCADE |
| `tasador_id` | BIGINT UNSIGNED FK | NO | | → `users` |
| `credito_prendario_id` | BIGINT UNSIGNED FK | NO | | → `creditos_prendarios` |
| `numero_tasacion` | VARCHAR(50) UNIQUE | NO | | |
| `fecha_tasacion` | DATE | NO | | |
| `valor_mercado` | DECIMAL(20,2) | NO | 0 | |
| `valor_comercial` | DECIMAL(20,2) | NO | 0 | |
| `valor_liquidacion` | DECIMAL(20,2) | NO | 0 | |
| `valor_final_asignado` | DECIMAL(20,2) | NO | 0 | |
| `porcentaje_depreciacion` | DECIMAL(5,2) | NO | 0 | |
| `condicion_fisica` | ENUM(`excelente`,`muy_buena`,`buena`,`regular`,`mala`) | NO | | |
| `antiguedad_estimada` | INT | SÍ | NULL | años |
| `tiene_accesorios` | BOOLEAN | NO | false | |
| `tiene_documentos` | BOOLEAN | NO | false | |
| `tiene_caja_original` | BOOLEAN | NO | false | |
| `funciona_correctamente` | BOOLEAN | NO | true | |
| `descripcion_detallada` | TEXT | SÍ | NULL | |
| `defectos_encontrados` | TEXT | SÍ | NULL | |
| `caracteristicas_positivas` | TEXT | SÍ | NULL | |
| `observaciones` | TEXT | SÍ | NULL | |
| `metodo_tasacion` | ENUM(`comparativo`,`costo`,`ingreso`,`mixto`) | NO | `comparativo` | |
| `referencias_mercado` | JSON | SÍ | NULL | |
| `estado` | ENUM(`borrador`,`pendiente_revision`,`aprobada`,`rechazada`,`modificada`) | NO | `borrador` | |
| `aprobado_por` | BIGINT UNSIGNED FK | SÍ | NULL | → `users` |
| `fecha_aprobacion` | DATE | SÍ | NULL | |
| `motivo_rechazo` | TEXT | SÍ | NULL | |
| `es_retasacion` | BOOLEAN | NO | false | |
| `tasacion_anterior_id` | BIGINT UNSIGNED FK | SÍ | NULL | → `tasaciones` |
| `motivo_retasacion` | TEXT | SÍ | NULL | |
| `documentos_soporte` | JSON | SÍ | NULL | |
| `fotos_tasacion` | JSON | SÍ | NULL | |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | | SoftDeletes |

---

## 8. `transferencias_prendas`

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `codigo_transferencia` | VARCHAR(30) UNIQUE | NO | | `TRF-YYYYMMDD-XXXXXX` |
| `prenda_id` | BIGINT UNSIGNED FK | NO | | RESTRICT |
| `credito_id` | BIGINT UNSIGNED FK | SÍ | NULL | → `creditos_prendarios` |
| `sucursal_origen_id` | BIGINT UNSIGNED FK | NO | | → `sucursales` |
| `sucursal_destino_id` | BIGINT UNSIGNED FK | NO | | → `sucursales` |
| `usuario_solicita_id` | BIGINT UNSIGNED FK | NO | | → `users` |
| `usuario_autoriza_id` | BIGINT UNSIGNED FK | SÍ | NULL | |
| `usuario_recibe_id` | BIGINT UNSIGNED FK | SÍ | NULL | |
| `estado` | ENUM(`solicitada`,`autorizada`,`en_transito`,`recibida`,`rechazada`,`cancelada`) | NO | `solicitada` | |
| `motivo` | TEXT | NO | | |
| `observaciones_autorizacion` | TEXT | SÍ | NULL | |
| `observaciones_recepcion` | TEXT | SÍ | NULL | |
| `motivo_rechazo` | TEXT | SÍ | NULL | |
| `fecha_solicitud` | TIMESTAMP | NO | CURRENT | |
| `fecha_autorizacion` | TIMESTAMP | SÍ | NULL | |
| `fecha_envio` | TIMESTAMP | SÍ | NULL | |
| `fecha_recepcion` | TIMESTAMP | SÍ | NULL | |
| `created_at` / `updated_at` / `deleted_at` | TIMESTAMP | | | SoftDeletes |

---

## 9. `venta_detalles` (ítem de venta de prenda)

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED PK AI | NO | | |
| `venta_id` | BIGINT UNSIGNED FK | NO | | → `ventas` CASCADE |
| `prenda_id` | BIGINT UNSIGNED FK | SÍ | NULL | → `prendas` NULL ON DELETE |
| `producto_id` | BIGINT UNSIGNED | SÍ | NULL | Futuro inventario |
| `codigo` | VARCHAR(100) | NO | | |
| `descripcion` | VARCHAR(500) | NO | | |
| `cantidad` | INT | NO | 1 | |
| `precio_unitario` | DECIMAL(20,2) | NO | 0 | |
| `descuento` | DECIMAL(20,2) | NO | 0 | |
| `descuento_porcentaje` | DECIMAL(5,2) | NO | 0 | |
| `subtotal` | DECIMAL(20,2) | NO | 0 | |
| `total` | DECIMAL(20,2) | NO | 0 | |
| `tipo_precio` | ENUM(`normal`,`mayoreo`,`promocion`,`especial`) | NO | `normal` | |
| `observaciones` | TEXT | SÍ | NULL | |
| `created_at` / `updated_at` | TIMESTAMP | | | |

---

## Consulta rápida para exportar estructura real

```sql
SHOW CREATE TABLE categoria_productos;
SHOW CREATE TABLE planes_interes_categoria;
SHOW CREATE TABLE plan_interes_categorias;
SHOW CREATE TABLE prendas;
SHOW CREATE TABLE prenda_imagenes;
SHOW CREATE TABLE prenda_datos_adicionales;
SHOW CREATE TABLE tasaciones;
SHOW CREATE TABLE transferencias_prendas;
SHOW CREATE TABLE venta_detalles;
```

O:

```sql
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'categoria_productos','planes_interes_categoria','plan_interes_categorias',
    'prendas','prenda_imagenes','prenda_datos_adicionales',
    'tasaciones','transferencias_prendas','venta_detalles'
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```
