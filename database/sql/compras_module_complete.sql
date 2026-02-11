-- =====================================================
-- MÓDULO DE COMPRAS DIRECTAS
-- Script SQL Completo para MySQL/MariaDB
-- Sistema de Empeños v2.0
-- =====================================================

-- =====================================================
-- TABLA PRINCIPAL: compras
-- =====================================================

CREATE TABLE IF NOT EXISTS `compras` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Relaciones
  `cliente_id` BIGINT UNSIGNED NOT NULL,
  `prenda_id` BIGINT UNSIGNED NULL,
  `categoria_producto_id` BIGINT UNSIGNED NOT NULL,
  `sucursal_id` BIGINT UNSIGNED NOT NULL,
  `usuario_id` BIGINT UNSIGNED NOT NULL,
  `movimiento_caja_id` BIGINT UNSIGNED NULL,

  -- Código único de compra
  `codigo_compra` VARCHAR(50) NOT NULL,

  -- Snapshot del cliente (datos al momento de la compra)
  `cliente_nombre` VARCHAR(200) NOT NULL,
  `cliente_documento` VARCHAR(50) NULL,
  `cliente_telefono` VARCHAR(20) NULL,
  `cliente_codigo` VARCHAR(50) NULL,

  -- Información de la prenda comprada
  `categoria_nombre` VARCHAR(200) NOT NULL COMMENT 'Snapshot del nombre de categoría',
  `descripcion` TEXT NOT NULL,
  `marca` VARCHAR(100) NULL,
  `modelo` VARCHAR(100) NULL,
  `serie` VARCHAR(100) NULL,
  `color` VARCHAR(50) NULL,
  `condicion` ENUM('excelente', 'muy_buena', 'buena', 'regular', 'mala') NOT NULL DEFAULT 'buena',

  -- Valores económicos
  `valor_tasacion` DECIMAL(15,2) NOT NULL COMMENT 'Valor estimado del artículo',
  `monto_pagado` DECIMAL(15,2) NOT NULL COMMENT 'Cantidad pagada al cliente',
  `precio_venta_sugerido` DECIMAL(15,2) NOT NULL COMMENT 'Precio propuesto para venta posterior',

  -- Financiero
  `metodo_pago` ENUM('efectivo', 'transferencia', 'cheque', 'mixto') NOT NULL DEFAULT 'efectivo',
  `genera_egreso_caja` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Si afecta o no la caja',

  -- Tracking y auditoría
  `estado` ENUM('activa', 'cancelada', 'vendida') NOT NULL DEFAULT 'activa',
  `observaciones` TEXT NULL,
  `fecha_compra` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `codigo_prenda_generado` VARCHAR(50) NULL COMMENT 'Referencia al código de prenda creada',

  -- Datos adicionales (JSON para flexibilidad)
  `datos_adicionales` JSON NULL COMMENT 'Campos personalizados según categoría',

  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,

  PRIMARY KEY (`id`),

  -- Índices para optimización de queries
  UNIQUE KEY `unique_codigo_compra` (`codigo_compra`),
  INDEX `idx_fecha_compra` (`fecha_compra`),
  INDEX `idx_estado` (`estado`),
  INDEX `idx_sucursal_fecha` (`sucursal_id`, `fecha_compra`),
  INDEX `idx_cliente_fecha` (`cliente_id`, `fecha_compra`),
  INDEX `idx_categoria` (`categoria_producto_id`),

  -- Claves foráneas
  CONSTRAINT `fk_compras_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_compras_prenda` FOREIGN KEY (`prenda_id`) REFERENCES `prendas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_compras_categoria` FOREIGN KEY (`categoria_producto_id`) REFERENCES `categoria_productos` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_compras_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_compras_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_compras_movimiento` FOREIGN KEY (`movimiento_caja_id`) REFERENCES `movimientos_caja` (`id`) ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de compras directas de artículos';

-- =====================================================
-- TABLA DE CAMPOS DINÁMICOS: compra_campos_dinamicos
-- =====================================================
-- Nota: Los campos dinámicos están definidos como JSON en categoria_productos.campos_dinamicos
-- Esta tabla almacena los valores para cada compra específica

CREATE TABLE IF NOT EXISTS `compra_campos_dinamicos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  `compra_id` BIGINT UNSIGNED NOT NULL,

  -- ID del campo dinámico (no es FK, solo referencia al JSON de categoria_productos)
  `campo_dinamico_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,

  -- Valor del campo (se usa según el tipo de campo)
  `valor` TEXT NULL,

  -- Snapshot del campo para auditoría
  `campo_nombre` VARCHAR(100) NOT NULL,
  `campo_tipo` VARCHAR(50) NOT NULL COMMENT 'texto, numero, fecha, booleano, seleccion, texto_largo',

  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  -- Evitar duplicados por nombre de campo
  UNIQUE KEY `unique_compra_campo` (`compra_id`, `campo_nombre`),
  INDEX `idx_compra_id` (`compra_id`),

  -- Clave foránea solo a compras
  CONSTRAINT `fk_compra_campos_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE
-- =====================================================
-- VISTAS ÚTILES
-- =====================================================

-- Vista: Compras con información completa
CREATE OR REPLACE VIEW `vista_compras_detalle` AS
SELECT
    c.id,
    c.codigo_compra,
    c.fecha_compra,
    c.estado,
    c.cliente_nombre,
    c.descripcion,
    c.categoria_nombre,
    c.monto_pagado,
    c.precio_venta_sugerido,
    ROUND(((c.precio_venta_sugerido - c.monto_pagado) / c.monto_pagado) * 100, 2) AS margen_esperado,
    s.nombre AS sucursal_nombre,
    u.name AS usuario_nombre,
    CASE
        WHEN p.estado = 'vendida' THEN 'vendida'
        WHEN c.estado = 'cancelada' THEN 'cancelada'
        ELSE 'activa'
    END AS estado_actual,
    c.created_at AS fecha_registro
FROM compras c
LEFT JOIN sucursales s ON c.sucursal_id = s.id
LEFT JOIN usuarios u ON c.usuario_id = u.id
LEFT JOIN prendas p ON c.prenda_id = p.id
WHERE c.deleted_at IS NULL;

-- Vista: Estadísticas por sucursal
CREATE OR REPLACE VIEW `vista_compras_stats_sucursal` AS
SELECT
    s.id AS sucursal_id,
    s.nombre AS sucursal_nombre,
    COUNT(c.id) AS total_compras,
    SUM(c.monto_pagado) AS total_invertido,
    SUM(CASE WHEN c.estado = 'activa' THEN c.precio_venta_sugerido ELSE 0 END) AS valor_inventario_actual,
    SUM(CASE WHEN c.estado = 'activa' THEN 1 ELSE 0 END) AS compras_activas,
    SUM(CASE WHEN c.estado = 'vendida' THEN 1 ELSE 0 END) AS compras_vendidas,
    SUM(CASE WHEN c.estado = 'cancelada' THEN 1 ELSE 0 END) AS compras_canceladas,
    ROUND(AVG((c.precio_venta_sugerido - c.monto_pagado) / c.monto_pagado * 100), 2) AS margen_promedio
FROM sucursales s
LEFT JOIN compras c ON s.id = c.sucursal_id AND c.deleted_at IS NULL
GROUP BY s.id, s.nombre;

-- =====================================================
-- PROCEDIMIENTOS ALMACENADOS
-- =====================================================

DELIMITER $$

-- Procedimiento: Obtener estadísticas de compras por periodo
CREATE PROCEDURE IF NOT EXISTS `sp_compras_stats_periodo`(
    IN p_fecha_inicio DATE,
    IN p_fecha_fin DATE,
    IN p_sucursal_id BIGINT
)
BEGIN
    SELECT
        COUNT(*) AS total_compras,
        SUM(monto_pagado) AS total_invertido,
        SUM(CASE WHEN estado = 'activa' THEN precio_venta_sugerido ELSE 0 END) AS valor_inventario,
        SUM(CASE WHEN estado = 'vendida' THEN (precio_venta_sugerido - monto_pagado) ELSE 0 END) AS utilidad_real,
        ROUND(AVG((precio_venta_sugerido - monto_pagado) / monto_pagado * 100), 2) AS margen_promedio
    FROM compras
    WHERE DATE(fecha_compra) BETWEEN p_fecha_inicio AND p_fecha_fin
        AND (p_sucursal_id IS NULL OR sucursal_id = p_sucursal_id)
        AND deleted_at IS NULL;
END$$

DELIMITER ;

-- =====================================================
-- TRIGGERS
-- =====================================================

DELIMITER $$

-- Trigger: Actualizar estado de prenda cuando se cancela la compra
CREATE TRIGGER IF NOT EXISTS `tr_compras_after_cancel`
AFTER UPDATE ON `compras`
FOR EACH ROW
BEGIN
    IF NEW.estado = 'cancelada' AND OLD.estado != 'cancelada' THEN
        UPDATE prendas
        SET observaciones = CONCAT(IFNULL(observaciones, ''), '\n[COMPRA CANCELADA] ', NOW())
        WHERE id = NEW.prenda_id;
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- DATOS DE EJEMPLO (OPCIONAL)
-- =====================================================

-- Ejemplo de compra directa
-- INSERT INTO compras (
--     cliente_id, categoria_producto_id, sucursal_id, usuario_id,
--     codigo_compra, cliente_nombre, categoria_nombre,
--     descripcion, marca, modelo, condicion,
--     valor_tasacion, monto_pagado, precio_venta_sugerido,
--     metodo_pago
-- ) VALUES (
--     1, 3, 1, 1,
--     'CMP-001-000001', 'Juan Pérez Gómez', 'Electrónica',
--     'Laptop ASUS ROG Gaming', 'ASUS', 'ROG Strix G15', 'muy_buena',
--     15000.00, 12000.00, 18000.00,
--     'efectivo'
-- );

-- =====================================================
-- VERIFICACIÓN
-- =====================================================

-- Verificar estructura de tablas
SHOW CREATE TABLE compras;
SHOW CREATE TABLE compra_campos_dinamicos;

-- Verificar índices
SHOW INDEX FROM compras;

-- Verificar vistas
SHOW CREATE VIEW vista_compras_detalle;

-- =====================================================
-- SCRIPT COMPLETADO
-- =====================================================

SELECT 'Módulo de Compras Directas instalado correctamente' AS mensaje;
