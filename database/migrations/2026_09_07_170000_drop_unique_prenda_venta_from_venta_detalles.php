<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Eliminar restricción de prenda única en venta_detalles para permitir
     * que prendas de ventas canceladas puedan ser revendidas.
     */
    public function up(): void
    {
        // 1. Limpiar registros de ventas canceladas
        try {
            DB::table('venta_detalles as vd')
                ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
                ->where('v.estado', 'cancelada')
                ->update(['vd.prenda_id' => null]);
        } catch (\Exception $e) {}

        // 2. Eliminar el índice único que bloquea la reventa de prendas
        try {
            Schema::table('venta_detalles', function (Blueprint $table) {
                $table->dropUnique('unique_prenda_venta');
            });
        } catch (\Exception $e) {
            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement('ALTER TABLE venta_detalles DROP INDEX unique_prenda_venta');
                } catch (\Exception $ex) {}
            }
        }

        // 3. Crear índice regular (no único) para optimizar consultas por prenda_id
        try {
            Schema::table('venta_detalles', function (Blueprint $table) {
                $table->index('prenda_id', 'idx_venta_detalles_prenda_id');
            });
        } catch (\Exception $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('venta_detalles', function (Blueprint $table) {
                $table->dropIndex('idx_venta_detalles_prenda_id');
                $table->unique('prenda_id', 'unique_prenda_venta');
            });
        } catch (\Exception $e) {}
    }
};
