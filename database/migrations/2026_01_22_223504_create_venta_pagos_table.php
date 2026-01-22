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
        Schema::create('venta_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago');
            $table->decimal('monto', 20, 2);
            $table->string('referencia', 100)->nullable();
            $table->string('banco', 100)->nullable();
            $table->string('numero_autorizacion', 50)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['venta_id', 'metodo_pago_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_pagos');
    }
};
