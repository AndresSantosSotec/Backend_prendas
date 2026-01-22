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
        Schema::create('boveda_movimientos', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('boveda_id')->constrained('bovedas')->comment('Bóveda origen/destino');
            $table->foreignId('usuario_id')->constrained('users')->comment('Usuario que realiza el movimiento');
            $table->foreignId('sucursal_id')->constrained('sucursales')->comment('Sucursal donde se realiza');

            // Información del movimiento
            $table->enum('tipo_movimiento', ['entrada', 'salida', 'transferencia_entrada', 'transferencia_salida'])->comment('Tipo de movimiento');
            $table->decimal('monto', 15, 2)->comment('Monto del movimiento');
            $table->string('concepto', 500)->comment('Concepto o motivo del movimiento');
            $table->json('desglose_denominaciones')->nullable()->comment('Desglose de billetes y monedas en JSON');

            // Campos para transferencias
            $table->foreignId('boveda_destino_id')->nullable()->constrained('bovedas')->comment('Bóveda destino en transferencias');
            $table->string('referencia', 100)->nullable()->comment('Número de referencia o comprobante');

            // Campos de aprobación
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado'])->default('aprobado')->comment('Estado del movimiento');
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->comment('Usuario que aprobó el movimiento');
            $table->timestamp('fecha_aprobacion')->nullable()->comment('Fecha de aprobación');
            $table->text('motivo_rechazo')->nullable()->comment('Motivo de rechazo si aplica');

            // Auditoría
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['boveda_id', 'tipo_movimiento']);
            $table->index(['sucursal_id', 'estado']);
            $table->index(['usuario_id', 'created_at']);
            $table->index(['fecha_aprobacion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boveda_movimientos');
    }
};
