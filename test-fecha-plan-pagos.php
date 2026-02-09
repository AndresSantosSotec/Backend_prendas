<?php

/**
 * Script de prueba para diagnosticar problema de fechas en plan de pagos
 *
 * Problema reportado:
 * - Fecha seleccionada: 04/03/2026
 * - Fecha generada: 16/02/2026 (fecha actual del sistema)
 *
 * Uso: php test-fecha-plan-pagos.php
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Carbon;

echo "=== PRUEBA DE CÁLCULO DE FECHAS PLAN DE PAGOS ===\n\n";

// Simular escenario reportado
$fechaActual = Carbon::parse('2026-02-16'); // Fecha actual del sistema
$fechaPrimerPagoSeleccionada = Carbon::parse('2026-03-04'); // Fecha seleccionada por usuario
$numeroCuotas = 4;
$tipoInteres = 'mensual';

echo "Fecha actual del sistema: " . $fechaActual->format('d/m/Y') . "\n";
echo "Fecha primer pago seleccionada: " . $fechaPrimerPagoSeleccionada->format('d/m/Y') . "\n";
echo "Número de cuotas: $numeroCuotas\n";
echo "Tipo de interés: $tipoInteres\n\n";

echo "--- PRUEBA 1: Usando fecha_primer_pago (ESPERADO) ---\n";
for ($i = 1; $i <= $numeroCuotas; $i++) {
    if ($i === 1) {
        $fechaVencimiento = $fechaPrimerPagoSeleccionada->copy();
    } else {
        $fechaVencimiento = $fechaPrimerPagoSeleccionada->copy()->addMonths($i - 1);
    }
    echo "Cuota $i: " . $fechaVencimiento->format('d/m/Y') . "\n";
}

echo "\n--- PRUEBA 2: Usando fecha_desembolso (INCORRECTO) ---\n";
for ($i = 1; $i <= $numeroCuotas; $i++) {
    $fechaVencimiento = $fechaActual->copy()->addMonths($i - 1);
    echo "Cuota $i: " . $fechaVencimiento->format('d/m/Y') . "\n";
}

echo "\n--- PRUEBA 3: Con días de gracia (30 días) ---\n";
$diasGracia = 30;
for ($i = 1; $i <= $numeroCuotas; $i++) {
    if ($i === 1) {
        $fechaVencimiento = $fechaPrimerPagoSeleccionada->copy();
        // Solo se aplican días de gracia si NO hay fecha_primer_pago
        // En este caso NO se deberían aplicar
    } else {
        $fechaVencimiento = $fechaPrimerPagoSeleccionada->copy()->addMonths($i - 1);
    }
    echo "Cuota $i: " . $fechaVencimiento->format('d/m/Y') . "\n";
}

echo "\n--- DIAGNÓSTICO ---\n";
echo "Si la primera cuota muestra 16/02/2026, el problema puede ser:\n";
echo "1. La variable \$fechaPrimerPago es NULL (no se está recibiendo del request)\n";
echo "2. El campo fecha_primer_pago no se está guardando en la BD\n";
echo "3. El request no está enviando el campo fecha_primer_pago correctamente\n\n";

echo "SOLUCIÓN RECOMENDADA:\n";
echo "1. Revisar logs de Laravel en storage/logs/laravel.log\n";
echo "2. Buscar líneas que contengan 'generarPlanPagos - Fechas recibidas'\n";
echo "3. Verificar si fechaPrimerPagoRequest y fechaPrimerPago_final son NULL o tienen valor\n";
echo "4. Si son NULL, revisar el frontend para confirmar que está enviando 'fecha_primer_pago'\n\n";

echo "=== FIN DE LA PRUEBA ===\n";
