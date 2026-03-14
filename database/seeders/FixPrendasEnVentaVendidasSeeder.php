<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Corrige prendas que quedaron en estado 'en_venta' aunque
 * ya tienen una venta activa (no cancelada).
 * Bug: VentaMultiPrendaService no las marcaba como 'vendida'
 * para ventas de tipo credito, apartado y plan_pagos.
 */
class FixPrendasEnVentaVendidasSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('=== Fix: Prendas en_venta con ventas activas ===');

        // Verificar cuántas prendas están afectadas
        $afectadas = DB::select("
            SELECT p.id, p.codigo_prenda, v.codigo_venta, v.tipo_venta, v.estado as estado_venta
            FROM prendas p
            INNER JOIN venta_detalles vd ON vd.prenda_id = p.id
            INNER JOIN ventas v ON v.id = vd.venta_id
            WHERE p.estado = 'en_venta'
            AND v.estado NOT IN ('cancelada', 'devuelta')
        ");

        $count = count($afectadas);
        $this->command->info("Prendas afectadas encontradas: {$count}");

        if ($count === 0) {
            $this->command->info('No hay prendas que corregir.');
            return;
        }

        foreach ($afectadas as $row) {
            $this->command->line("  - {$row->codigo_prenda} | venta: {$row->codigo_venta} | tipo: {$row->tipo_venta} | estado venta: {$row->estado_venta}");
        }

        // Parche: marcar todas como vendida
        $actualizadas = DB::update("
            UPDATE prendas p
            INNER JOIN venta_detalles vd ON vd.prenda_id = p.id
            INNER JOIN ventas v ON v.id = vd.venta_id
            SET p.estado = 'vendida'
            WHERE p.estado = 'en_venta'
            AND v.estado NOT IN ('cancelada', 'devuelta')
        ");

        $this->command->info("✓ {$actualizadas} prenda(s) actualizadas a estado 'vendida'.");
        $this->command->info('=== Fix completado ===');
    }
}
