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
        Schema::create('compras', function (Blueprint $table) {
            $table->id();

            // --- Relaciones ---
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('restrict');
            $table->foreignId('prenda_id')->nullable()->constrained('prendas')->onDelete('set null');
            $table->foreignId('categoria_producto_id')->constrained('categoria_productos')->onDelete('restrict');
            $table->foreignId('sucursal_id')->constrained('sucursales')->onDelete('restrict');
            $table->foreignId('usuario_id')->constrained('users')->comment('Usuario que registró la compra');
            $table->foreignId('movimiento_caja_id')->nullable()->constrained('movimiento_cajas')->onDelete('set null');

            // --- Código único de compra ---
            $table->string('codigo_compra', 50)->unique()->comment('Ej: CMP-001-000001');

            // --- Snapshot del cliente (datos al momento de la compra) ---
            $table->string('cliente_nombre', 200);
            $table->string('cliente_documento', 50)->nullable();
            $table->string('cliente_telefono', 20)->nullable();
            $table->string('cliente_codigo', 50)->nullable();

            // --- Información de la prenda comprada ---
            $table->string('categoria_nombre', 200)->comment('Snapshot del nombre de categoría');
            $table->text('descripcion');
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('serie', 100)->nullable();
            $table->string('color', 50)->nullable();
            $table->enum('condicion', ['excelente', 'muy_buena', 'buena', 'regular', 'mala'])->default('buena');

            // --- Valores económicos ---
            $table->decimal('valor_tasacion', 15, 2)->comment('Valor estimado del artículo');
            $table->decimal('monto_pagado', 15, 2)->comment('Cantidad pagada al cliente');
            $table->decimal('precio_venta_sugerido', 15, 2)->comment('Precio propuesto para venta posterior');

            // --- Financiero ---
            $table->enum('metodo_pago', ['efectivo', 'transferencia', 'cheque', 'mixto'])->default('efectivo');
            $table->boolean('genera_egreso_caja')->default(true)->comment('Si afecta o no la caja');

            // --- Tracking y auditoría ---
            $table->enum('estado', ['activa', 'cancelada', 'vendida'])->default('activa');
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_compra')->useCurrent();
            $table->string('codigo_prenda_generado', 50)->nullable()->comment('Referencia al código de prenda creada');

            // --- Datos adicionales (JSON para flexibilidad) ---
            $table->json('datos_adicionales')->nullable()->comment('Campos personalizados según categoría');

            $table->timestamps();
            $table->softDeletes();

            // --- Índices ---
            $table->index('codigo_compra');
            $table->index('fecha_compra');
            $table->index('estado');
            $table->index(['sucursal_id', 'fecha_compra']);
            $table->index(['cliente_id', 'fecha_compra']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
