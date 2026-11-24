-- =============================================================================
-- Agregar cuentas contables:
--   132      AMORTIZACIONES ACUMULADAS
--   13201    CARGOS DIFERIDOS
--   13201001 Gtos Organiz e Instal
--
-- Uso (MySQL):
--   mysql -u USUARIO -p NOMBRE_BD < database/sql/agregar_cuentas_132_cargos_diferidos.sql
--
-- Idempotente: si el código ya existe, solo actualiza nombre/clasificación.
-- =============================================================================

START TRANSACTION;

-- Padre opcional: si existe cuenta "13" o "1", se enlaza 132 debajo.
SET @padre_132 := (
    SELECT id FROM ctb_nomenclatura
    WHERE codigo_cuenta IN ('13', '1')
      AND deleted_at IS NULL
    ORDER BY LENGTH(codigo_cuenta) DESC
    LIMIT 1
);

-- 132 — AMORTIZACIONES ACUMULADAS (grupo, no acepta movimientos)
INSERT INTO ctb_nomenclatura (
    codigo_cuenta, nombre_cuenta, tipo, naturaleza, nivel,
    cuenta_padre_id, acepta_movimientos, requiere_auxiliar,
    categoria_flujo, estado, created_at, updated_at
) VALUES (
    '132', 'AMORTIZACIONES ACUMULADAS', 'activo', 'acreedora', 2,
    @padre_132, 0, 0, 'ninguno', 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
    nombre_cuenta      = VALUES(nombre_cuenta),
    tipo               = VALUES(tipo),
    naturaleza         = VALUES(naturaleza),
    nivel              = VALUES(nivel),
    acepta_movimientos = VALUES(acepta_movimientos),
    estado             = 1,
    updated_at         = NOW(),
    deleted_at         = NULL;

SET @id_132 := (SELECT id FROM ctb_nomenclatura WHERE codigo_cuenta = '132' AND deleted_at IS NULL LIMIT 1);

-- 13201 — CARGOS DIFERIDOS (subgrupo)
INSERT INTO ctb_nomenclatura (
    codigo_cuenta, nombre_cuenta, tipo, naturaleza, nivel,
    cuenta_padre_id, acepta_movimientos, requiere_auxiliar,
    categoria_flujo, estado, created_at, updated_at
) VALUES (
    '13201', 'CARGOS DIFERIDOS', 'activo', 'deudora', 3,
    @id_132, 0, 0, 'ninguno', 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
    nombre_cuenta      = VALUES(nombre_cuenta),
    tipo               = VALUES(tipo),
    naturaleza         = VALUES(naturaleza),
    nivel              = VALUES(nivel),
    cuenta_padre_id    = @id_132,
    acepta_movimientos = VALUES(acepta_movimientos),
    estado             = 1,
    updated_at         = NOW(),
    deleted_at         = NULL;

SET @id_13201 := (SELECT id FROM ctb_nomenclatura WHERE codigo_cuenta = '13201' AND deleted_at IS NULL LIMIT 1);

-- 13201001 — Gtos Organiz e Instal (detalle, sí acepta movimientos)
INSERT INTO ctb_nomenclatura (
    codigo_cuenta, nombre_cuenta, tipo, naturaleza, nivel,
    cuenta_padre_id, acepta_movimientos, requiere_auxiliar,
    categoria_flujo, estado, created_at, updated_at
) VALUES (
    '13201001', 'Gtos Organiz e Instal', 'activo', 'deudora', 4,
    @id_13201, 1, 0, 'operacion', 1, NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
    nombre_cuenta      = VALUES(nombre_cuenta),
    tipo               = VALUES(tipo),
    naturaleza         = VALUES(naturaleza),
    nivel              = VALUES(nivel),
    cuenta_padre_id    = @id_13201,
    acepta_movimientos = VALUES(acepta_movimientos),
    estado             = 1,
    updated_at         = NOW(),
    deleted_at         = NULL;

COMMIT;

-- Verificación
SELECT id, codigo_cuenta, nombre_cuenta, tipo, naturaleza, nivel,
       cuenta_padre_id, acepta_movimientos, estado
FROM ctb_nomenclatura
WHERE codigo_cuenta IN ('132', '13201', '13201001')
ORDER BY codigo_cuenta;
