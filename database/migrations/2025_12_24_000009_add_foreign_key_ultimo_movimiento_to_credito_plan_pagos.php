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
        // Verificar si la foreign key ya existe
        $foreignKeyExists = DB::select("
            SELECT COUNT(*) as count
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE table_schema = DATABASE()
            AND table_name = 'credito_plan_pagos'
            AND column_name = 'ultimo_movimiento_id'
            AND referenced_table_name = 'credito_movimientos'
        ");

        // Solo crear la foreign key si no existe
        if ($foreignKeyExists[0]->count == 0) {
            Schema::table('credito_plan_pagos', function (Blueprint $table) {
                $table->foreign('ultimo_movimiento_id')
                      ->references('id')
                      ->on('credito_movimientos')
                      ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credito_plan_pagos', function (Blueprint $table) {
            if (Schema::hasColumn('credito_plan_pagos', 'ultimo_movimiento_id')) {
                $table->dropForeign(['ultimo_movimiento_id']);
            }
        });
    }
};
