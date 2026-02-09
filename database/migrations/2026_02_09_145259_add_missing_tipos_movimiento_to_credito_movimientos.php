<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar ENUM para agregar tipos faltantes: renovacion, pago_interes
        DB::statement("ALTER TABLE credito_movimientos MODIFY COLUMN tipo_movimiento ENUM(
            'desembolso',
            'pago',
            'pago_parcial',
            'pago_total',
            'pago_adelantado',
            'pago_interes',
            'renovacion',
            'cargo_mora',
            'cargo_administracion',
            'ajuste',
            'reversion',
            'condonacion'
        ) NOT NULL COMMENT 'Tipo de movimiento'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al ENUM original
        DB::statement("ALTER TABLE credito_movimientos MODIFY COLUMN tipo_movimiento ENUM(
            'desembolso',
            'pago',
            'pago_parcial',
            'pago_total',
            'pago_adelantado',
            'cargo_mora',
            'cargo_administracion',
            'ajuste',
            'reversion',
            'condonacion'
        ) NOT NULL COMMENT 'Tipo de movimiento'");
    }
};
