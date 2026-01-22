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
        Schema::create('apartado_pagos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recibo', 50)->unique();
            $table->foreignId('apartado_id')->constrained('apartados')->cascadeOnDelete();
            $table->integer('numero_pago'); // 1, 2, 3... (enganche es 0)

            // Monto
            $table->decimal('monto', 20, 2);
            $table->decimal('saldo_anterior', 20, 2);
            $table->decimal('saldo_despues', 20, 2);

            // Método de pago
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago');
            $table->string('referencia', 100)->nullable();
            $table->string('banco', 100)->nullable();

            // Control
            $table->foreignId('cajero_id')->constrained('users');
            $table->foreignId('caja_id')->nullable()->constrained('caja_apertura_cierres')->nullOnDelete();

            $table->date('fecha_pago');
            $table->enum('tipo_pago', ['enganche', 'abono', 'liquidacion'])->default('abono');
            $table->enum('estado', ['activo', 'anulado'])->default('activo');

            $table->text('observaciones')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users');
            $table->timestamp('fecha_anulacion')->nullable();

            $table->timestamps();

            $table->index(['apartado_id', 'numero_pago']);
            $table->index('fecha_pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apartado_pagos');
    }
};
