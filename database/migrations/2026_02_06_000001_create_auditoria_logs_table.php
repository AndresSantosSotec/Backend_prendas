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
        Schema::create('auditoria_logs', function (Blueprint $table) {
            $table->id();

            // Usuario y sucursal
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->onDelete('set null');

            // Información de la acción
            $table->string('modulo', 50)->index(); // clientes, creditos, ventas, etc.
            $table->string('accion', 50)->index(); // crear, editar, eliminar, ver, exportar, etc.
            $table->string('tabla', 100)->nullable(); // Tabla afectada
            $table->string('registro_id', 100)->nullable(); // ID del registro afectado

            // Datos del cambio
            $table->json('datos_anteriores')->nullable(); // Estado anterior
            $table->json('datos_nuevos')->nullable(); // Estado nuevo

            // Información de contexto
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('metodo_http', 10)->nullable(); // GET, POST, PUT, DELETE
            $table->text('url')->nullable();
            $table->text('descripcion')->nullable(); // Descripción legible

            $table->timestamp('created_at')->index();

            // Índices compuestos para búsquedas comunes
            $table->index(['modulo', 'accion']);
            $table->index(['user_id', 'created_at']);
            $table->index(['sucursal_id', 'created_at']);
            $table->index(['tabla', 'registro_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditoria_logs');
    }
};
