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
        // Tabla de permisos disponibles
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('modulo'); // clientes, creditos, ventas, etc.
            $table->string('accion'); // ver, crear, editar, eliminar, etc.
            $table->string('descripcion')->nullable();
            $table->timestamps();
            
            $table->unique(['modulo', 'accion']);
        });

        // Tabla pivote para permisos de usuario
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['user_id', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('permissions');
    }
};

