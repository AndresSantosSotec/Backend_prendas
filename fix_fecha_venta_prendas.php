<?php
/**
 * Script para rellenar fecha_venta en prendas que están vendidas
 * pero no tienen fecha_venta registrada.
 * 
 * Lo obtiene de la tabla ventas relacionada por prenda_id.
 * 
 * Uso: php artisan tinker --execute="require 'fix_fecha_venta_prendas.php';"
 * O directamente: php fix_fecha_venta_prendas.php
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== Corrección de fecha_venta en Prendas ===\n\n";

// 1. Buscar prendas vendidas SIN fecha_venta
$prendasSinFecha = DB::table('prendas')
    ->whereIn('estado', ['vendida', 'vendido'])
    ->whereNull('fecha_venta')
    ->whereNull('deleted_at')
    ->get(['id', 'codigo_prenda', 'estado']);

echo "Prendas vendidas sin fecha_venta: " . $prendasSinFecha->count() . "\n\n";

if ($prendasSinFecha->isEmpty()) {
    echo "✅ No hay prendas que corregir. Todo está bien.\n";
    exit(0);
}

$corregidas = 0;
$sinVenta   = 0;

foreach ($prendasSinFecha as $prenda) {
    // Buscar la venta más reciente de esta prenda
    $venta = DB::table('ventas')
        ->where('prenda_id', $prenda->id)
        ->whereNull('deleted_at')
        ->orderBy('created_at', 'desc')
        ->first(['created_at', 'fecha_venta', 'id']);

    if ($venta) {
        // Usar fecha_venta de la venta si existe, si no usar created_at de la venta
        $fechaVenta = $venta->fecha_venta ?? $venta->created_at;

        DB::table('prendas')
            ->where('id', $prenda->id)
            ->update(['fecha_venta' => $fechaVenta]);

        echo "✅ [{$prenda->codigo_prenda}] fecha_venta = {$fechaVenta} (desde venta ID: {$venta->id})\n";
        $corregidas++;
    } else {
        echo "⚠️  [{$prenda->codigo_prenda}] No se encontró venta asociada — se queda sin fecha\n";
        $sinVenta++;
    }
}

echo "\n=== Resumen ===\n";
echo "Corregidas:         {$corregidas}\n";
echo "Sin venta asociada: {$sinVenta}\n";
echo "Total revisadas:    " . $prendasSinFecha->count() . "\n";
