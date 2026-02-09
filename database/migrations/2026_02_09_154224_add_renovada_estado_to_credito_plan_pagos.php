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
        // Agregar 'renovada' al ENUM de estado
        DB::statement("ALTER TABLE credito_plan_pagos MODIFY COLUMN estado ENUM(
            'pendiente',
            'pagada',
            'pagada_parcial',
            'vencida',
            'en_mora',
            'cancelada',
            'condonada',
            'renovada'
        ) NOT NULL DEFAULT 'pendiente' COMMENT 'Estado de la cuota'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al ENUM original
        DB::statement("ALTER TABLE credito_plan_pagos MODIFY COLUMN estado ENUM(
            'pendiente',
            'pagada',
            'pagada_parcial',
            'vencida',
            'en_mora',
            'cancelada',
            'condonada'
        ) NOT NULL DEFAULT 'pendiente' COMMENT 'Estado de la cuota'");
    }
};
