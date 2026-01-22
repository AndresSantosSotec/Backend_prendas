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
        // Primero eliminar cualquier foreign key que use este índice
        Schema::table('caja_apertura_cierres', function (Blueprint $table) {
            // Desactivar verificación de claves foráneas temporalmente
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        });

        // Intentar eliminar el índice único
        try {
            Schema::table('caja_apertura_cierres', function (Blueprint $table) {
                $table->dropUnique('caja_apertura_cierres_user_id_fecha_apertura_unique');
            });
        } catch (\Exception $e) {
            // Si falla, intentar con SQL directo
            DB::statement('ALTER TABLE caja_apertura_cierres DROP INDEX caja_apertura_cierres_user_id_fecha_apertura_unique');
        }

        // Reactivar verificación de claves foráneas
        Schema::table('caja_apertura_cierres', function (Blueprint $table) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
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
