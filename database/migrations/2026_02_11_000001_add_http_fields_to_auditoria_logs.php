<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auditoria_logs', function (Blueprint $table) {
            // Agregar campos para auditoría de peticiones HTTP
            if (!Schema::hasColumn('auditoria_logs', 'codigo_respuesta')) {
                $table->integer('codigo_respuesta')->nullable()->after('url');
            }
            if (!Schema::hasColumn('auditoria_logs', 'tiempo_respuesta_ms')) {
                $table->float('tiempo_respuesta_ms')->nullable()->after('codigo_respuesta');
            }

            // Índices para mejorar consultas
            $table->index(['modulo', 'accion'], 'idx_modulo_accion');
            $table->index(['created_at'], 'idx_created_at');
            $table->index(['user_id', 'created_at'], 'idx_user_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auditoria_logs', function (Blueprint $table) {
            $table->dropColumn(['codigo_respuesta', 'tiempo_respuesta_ms']);
            $table->dropIndex('idx_modulo_accion');
            $table->dropIndex('idx_created_at');
            $table->dropIndex('idx_user_fecha');
        });
    }
};
