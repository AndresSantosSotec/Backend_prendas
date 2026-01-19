-- Migración para agregar fecha_primer_pago a creditos_prendarios
-- Ejecutar este script directamente en la base de datos si la migración de Laravel falla

ALTER TABLE `creditos_prendarios` 
ADD COLUMN `fecha_primer_pago` DATE NULL 
AFTER `fecha_desembolso` 
COMMENT 'Fecha del primer pago programado';
