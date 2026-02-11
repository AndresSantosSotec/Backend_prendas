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
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('numero_cotizacion', 50)->unique();
            $table->date('fecha');

            // Cliente (opcional)
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->onDelete('set null');
            $table->string('cliente_nombre', 200)->nullable();

            // Sucursal
            $table->foreignId('sucursal_id')->constrained('sucursales')->onDelete('cascade');

            // Usuario que crea la cotización
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Productos (prendas)
            $table->json('productos'); // Array de productos con prenda_id, descripcion, precio, cantidad

            // Totales
            $table->decimal('subtotal', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            // Tipo de venta
            $table->enum('tipo_venta', ['contado', 'credito'])->default('contado');

            // Plan de pagos (solo para crédito)
            $table->json('plan_pagos')->nullable(); // numero_cuotas, tasa_interes, monto_cuota, total_con_intereses

            // Observaciones
            $table->text('observaciones')->nullable();

            // Estado
            $table->enum('estado', ['pendiente', 'convertida', 'cancelada', 'vencida'])->default('pendiente');

            // Conversión a venta
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->onDelete('set null');
            $table->timestamp('fecha_conversion')->nullable();

            // Vigencia
            $table->date('fecha_vencimiento')->nullable(); // Cotización válida hasta

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('numero_cotizacion');
            $table->index('fecha');
            $table->index('estado');
            $table->index('sucursal_id');
            $table->index('cliente_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
