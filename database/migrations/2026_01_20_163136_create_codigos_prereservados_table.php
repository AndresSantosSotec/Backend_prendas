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
        Schema::create('codigos_prereservados', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo_credito', 20)->unique()->comment('Código único del crédito CR-XXXXXX');
            $table->string('codigo_prenda', 20)->unique()->comment('Código único de la prenda PRN-XXXXXX');
            $table->string('session_token', 100)->comment('Token de sesión del usuario para identificar la reserva');
            $table->unsignedBigInteger('usuario_id')->nullable()->comment('ID del usuario que reservó');
            $table->string('cliente_id', 36)->nullable()->comment('ID del cliente asociado');
            $table->enum('estado', ['reservado', 'usado', 'expirado'])->default('reservado');
            $table->timestamp('fecha_expiracion')->comment('Los códigos reservados expiran después de cierto tiempo');
            $table->timestamps();
            
            // Índices para búsqueda rápida
            $table->index(['session_token', 'estado']);
            $table->index(['estado', 'fecha_expiracion']);
            $table->index('usuario_id');
            $table->index('cliente_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codigos_prereservados');
    }
};
