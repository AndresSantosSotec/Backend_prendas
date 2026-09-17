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
        // La FK de user_id necesita un índice independiente antes de quitar el índice compuesto.
        Schema::table('caja_apertura_cierres', function (Blueprint $table) {
            $table->index('user_id', 'caja_apertura_cierres_user_id_index');
        });

        Schema::table('caja_apertura_cierres', function (Blueprint $table) {
            $table->dropUnique('caja_apertura_cierres_user_id_fecha_apertura_unique');
        });

        // Crear un índice normal (no único) para mantener el rendimiento
        Schema::table('caja_apertura_cierres', function (Blueprint $table) {
            $table->index(['user_id', 'fecha_apertura'], 'caja_user_fecha_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('caja_apertura_cierres', function (Blueprint $table) {
            $table->dropIndex('caja_user_fecha_idx');
            $table->unique(['user_id', 'fecha_apertura']);
        });
    }
};
