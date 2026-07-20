<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE boveda_movimientos MODIFY COLUMN tipo_movimiento ENUM('entrada', 'salida', 'transferencia_entrada', 'transferencia_salida', 'ingreso_cierre_diario') NOT NULL COMMENT 'Tipo de movimiento'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE boveda_movimientos MODIFY COLUMN tipo_movimiento ENUM('entrada', 'salida', 'transferencia_entrada', 'transferencia_salida') NOT NULL COMMENT 'Tipo de movimiento'");
        }
    }
};
