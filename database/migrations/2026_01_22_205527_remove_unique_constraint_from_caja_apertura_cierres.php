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
        if (!Schema::hasTable('caja_apertura_cierres')) {
            return;
        }

        // 1. En MySQL/InnoDB, una clave foránea (user_id -> users.id) exige un índice que comience con user_id.
        // Si el índice único (user_id, fecha_apertura) era el único que cubría a user_id,
        // MySQL arroja el error 1553 al intentar borrarlo.
        // Por ello, creamos PRIMERO el índice individual para user_id para respaldar la FK.
        try {
            Schema::table('caja_apertura_cierres', function (Blueprint $table) {
                $table->index('user_id', 'caja_apertura_cierres_user_id_index');
            });
        } catch (\Throwable $e) {}

        // También creamos el índice compuesto no único para optimizar consultas por usuario y fecha
        try {
            Schema::table('caja_apertura_cierres', function (Blueprint $table) {
                $table->index(['user_id', 'fecha_apertura'], 'caja_user_fecha_idx');
            });
        } catch (\Throwable $e) {}

        // 2. Ahora que user_id ya cuenta con índices de respaldo, procedemos a eliminar la restricción UNIQUE
        try {
            Schema::table('caja_apertura_cierres', function (Blueprint $table) {
                $table->dropUnique('caja_apertura_cierres_user_id_fecha_apertura_unique');
            });
        } catch (\Throwable $e) {
            if (DB::getDriverName() === 'mysql') {
                try {
                    DB::statement('ALTER TABLE caja_apertura_cierres DROP INDEX caja_apertura_cierres_user_id_fecha_apertura_unique');
                } catch (\Throwable $ex) {}
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('caja_apertura_cierres')) {
            return;
        }

        try {
            Schema::table('caja_apertura_cierres', function (Blueprint $table) {
                $table->unique(['user_id', 'fecha_apertura'], 'caja_apertura_cierres_user_id_fecha_apertura_unique');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('caja_apertura_cierres', function (Blueprint $table) {
                $table->dropIndex('caja_user_fecha_idx');
                $table->dropIndex('caja_apertura_cierres_user_id_index');
            });
        } catch (\Throwable $e) {}
    }
};
