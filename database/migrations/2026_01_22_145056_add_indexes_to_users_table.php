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
        Schema::table('users', function (Blueprint $table) {
            // Índices para mejorar rendimiento de búsquedas y filtros
            $table->index('activo', 'idx_users_activo');
            $table->index('rol', 'idx_users_rol');
            $table->index('email', 'idx_users_email');
            $table->index('username', 'idx_users_username');
            $table->index('name', 'idx_users_name');
            
            // Índice compuesto para filtros comunes
            $table->index(['activo', 'rol'], 'idx_users_activo_rol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Eliminar índices
            $table->dropIndex('idx_users_activo');
            $table->dropIndex('idx_users_rol');
            $table->dropIndex('idx_users_email');
            $table->dropIndex('idx_users_username');
            $table->dropIndex('idx_users_name');
            $table->dropIndex('idx_users_activo_rol');
        });
    }
};
