<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración del Módulo Contable
    |--------------------------------------------------------------------------
    |
    | Configuración general para el módulo de contabilidad del sistema.
    | Incluye opciones para asientos automáticos, validaciones y más.
    |
    */

    /**
     * Habilitar generación automática de asientos contables
     *
     * true = Los asientos se generan automáticamente desde las operaciones
     * false = Los asientos deben crearse manualmente
     */
    'auto_asientos' => env('CONTABILIDAD_AUTO_ASIENTOS', false),

    /**
     * Configuración de asientos automáticos por tipo de operación
     */
    'auto_asientos_por_operacion' => [
        'desembolso_credito' => env('CONTABILIDAD_AUTO_DESEMBOLSO', false),
        'pago_credito' => env('CONTABILIDAD_AUTO_PAGO', false),
        'venta_prenda' => env('CONTABILIDAD_AUTO_VENTA', false),
        'compra_directa' => env('CONTABILIDAD_AUTO_COMPRA', false),
    ],

    /**
     * Requiere aprobación de asientos antes de registrarlos
     */
    'requiere_aprobacion' => env('CONTABILIDAD_REQUIERE_APROBACION', true),

    /**
     * Código de moneda por defecto
     */
    'moneda_defecto' => env('CONTABILIDAD_MONEDA', 'GTQ'),

    /**
     * Cuentas contables por defecto para operaciones automáticas
     * IMPORTANTE: Estos códigos deben existir en ctb_nomenclatura
     */
    'cuentas' => [
        // ACTIVOS
        'caja' => '1101.01.001',
        'caja_general' => '1101.01.001',
        'bancos' => '1101.01.003',
        'creditos_por_cobrar' => '1101.02.001',
        'intereses_por_cobrar' => '1101.02.002',
        'mora_por_cobrar' => '1101.02.003',
        'inventario_prendas_custodia' => '1101.03.001',
        'inventario_prendas_venta' => '1101.03.002',

        // INGRESOS
        'ventas' => '4101.04',
        'ingresos_intereses' => '4101.01',
        'ingresos_mora' => '4101.02',
        'ingresos_comisiones' => '4101.03',
        'ingresos_venta_prendas' => '4101.04',

        // COSTOS
        'costo_prendas_vendidas' => '6101',
    ],

    /**
     * Configuración de tipos de póliza por defecto
     */
    'tipos_poliza' => [
        'ingreso' => 'PI',
        'egreso' => 'PE',
        'diario' => 'PD',
        'cheque' => 'PC',
        'transferencia' => 'PT',
    ],

    /**
     * Validaciones
     */
    'validaciones' => [
        // Validar que el asiento cuadre (debe = haber)
        'validar_cuadre' => true,

        // No permitir asientos en periodos cerrados
        'validar_periodo_abierto' => false,

        // Validar que las cuentas acepten movimientos
        'validar_cuentas_movimiento' => true,
    ],

    /**
     * Logging
     */
    'log_asientos' => env('CONTABILIDAD_LOG', true),

    /**
     * Configuración de numeración de comprobantes
     */
    'numeracion' => [
        // Prefijo por tipo de póliza
        'prefijos' => [
            'PI' => 'PI',
            'PE' => 'PE',
            'PD' => 'PD',
            'PC' => 'PC',
            'PT' => 'PT',
        ],

        // Formato: PREFIJO-AÑO-NÚMERO
        'formato' => '{prefijo}-{anio}-{numero}',

        // Longitud de número (con ceros a la izquierda)
        'longitud_numero' => 6,

        // Reiniciar numeración cada año
        'reiniciar_anual' => true,
    ],
];
