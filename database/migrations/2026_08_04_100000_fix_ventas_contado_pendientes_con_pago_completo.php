<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Migración correctiva: actualiza ventas al contado que quedaron en estado 'pendiente'
 * cuando en realidad estaban completamente pagadas (total_pagado >= total_final).
 *
 * Esto ocurría cuando se aplicaba un descuento por ítem o general y la comparación
 * de punto flotante fallaba, dejando la venta como pendiente y sin registrar fecha_venta
 * en las prendas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Identificar ventas al contado en pendiente que están pagadas ──────────
        $ventasPendientes = DB::table('ventas')
            ->where('tipo_venta', 'contado')
            ->where('estado', 'pendiente')
            ->whereRaw('ROUND(total_pagado, 2) >= ROUND(total_final, 2)')
            ->select('id', 'codigo_venta', 'total_final', 'total_pagado', 'fecha_venta')
            ->get();

        if ($ventasPendientes->isEmpty()) {
            Log::info('[MigCorrectivaVentas] No hay ventas al contado pendientes con pago completo. Nada que corregir.');
            return;
        }

        Log::info("[MigCorrectivaVentas] Corrigiendo {$ventasPendientes->count()} venta(s) al contado pendientes con pago completo.");

        foreach ($ventasPendientes as $venta) {
            DB::transaction(function () use ($venta) {
                $fechaVenta = $venta->fecha_venta ?? now();

                // ── 2. Actualizar venta a estado 'pagada' ──────────────────────────
                DB::table('ventas')
                    ->where('id', $venta->id)
                    ->update([
                        'estado'           => 'pagada',
                        'saldo_pendiente'  => 0,
                        'updated_at'       => now(),
                    ]);

                // ── 3. Actualizar prendas asociadas a la venta ─────────────────────
                // Buscar prenda_ids en los detalles de la venta
                $prendaIds = DB::table('venta_detalles')
                    ->where('venta_id', $venta->id)
                    ->whereNotNull('prenda_id')
                    ->pluck('prenda_id');

                if ($prendaIds->isNotEmpty()) {
                    DB::table('prendas')
                        ->whereIn('id', $prendaIds)
                        ->update([
                            'estado'      => 'vendida',
                            'fecha_venta' => $fechaVenta,
                            'updated_at'  => now(),
                        ]);
                }

                Log::info("[MigCorrectivaVentas] Venta {$venta->codigo_venta} corregida a 'pagada'. Prendas: " . $prendaIds->implode(', '));
            });
        }

        Log::info("[MigCorrectivaVentas] Corrección completada: {$ventasPendientes->count()} venta(s) actualizadas.");
    }

    public function down(): void
    {
        // Esta migración correctiva no tiene rollback automático
        // para no revertir datos de producción involuntariamente.
        Log::info('[MigCorrectivaVentas] down() llamado — no se realizan cambios (migración correctiva).');
    }
};
