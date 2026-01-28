<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de pagos de venta
     * Permite registrar múltiples métodos de pago en una venta
     */
    public function up(): void
    {
        if (!Schema::hasTable('venta_pagos')) {
            Schema::create('venta_pagos', function (Blueprint $table) {
                $table->id();

                // Relación con venta
                $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade');

                // Método de pago
                $table->enum('metodo', [
                    'efectivo',
                    'tarjeta_debito',
                    'tarjeta_credito',
                    'transferencia',
                    'cheque',
                    'deposito',
                    'qr',
                    'otro'
                ])->comment('Método de pago utilizado');

                // Monto y cambio
                $table->decimal('monto', 20, 2)->default(0)->comment('Monto pagado con este método');
                $table->decimal('cambio', 20, 2)->default(0)->comment('Cambio devuelto (si aplica)');

                // Referencia de pago
                $table->string('referencia', 100)->nullable()->comment('Número de transacción, cheque, etc');
                $table->string('banco', 100)->nullable()->comment('Banco si aplica');
                $table->string('autorizacion', 100)->nullable()->comment('Código de autorización');

                // Fecha del pago
                $table->timestamp('fecha_pago')->useCurrent();

                // Observaciones
                $table->text('observaciones')->nullable();

                $table->timestamps();

                // Índices
                $table->index('venta_id');
                $table->index('metodo');
                $table->index('fecha_pago');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_pagos');
    }
};
