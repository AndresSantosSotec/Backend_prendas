<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de detalles/items de venta
     * Permite vender múltiples productos/prendas en una sola venta
     */
    public function up(): void
    {
        if (!Schema::hasTable('venta_detalles')) {
            Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();

            // Relación con venta
            $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade');

            // Producto vendido (puede ser prenda o producto regular)
            $table->foreignId('prenda_id')->nullable()->constrained('prendas')->nullOnDelete()->comment('Si es prenda empeñada');
            $table->foreignId('producto_id')->nullable()->comment('Si es producto regular del inventario (futuro)');

            // Descripción y código
            $table->string('codigo', 100)->comment('Código de barras o ID del producto');
            $table->string('descripcion', 500)->comment('Descripción del producto');

            // Cantidades y precios
            $table->integer('cantidad')->default(1)->comment('Cantidad vendida');
            $table->decimal('precio_unitario', 20, 2)->default(0)->comment('Precio por unidad');
            $table->decimal('descuento', 20, 2)->default(0)->comment('Descuento en monto');
            $table->decimal('descuento_porcentaje', 5, 2)->default(0)->comment('Descuento en porcentaje');
            $table->decimal('subtotal', 20, 2)->default(0)->comment('Subtotal antes de impuestos');
            $table->decimal('total', 20, 2)->default(0)->comment('Total del item');

            // Tipo de precio aplicado
            $table->enum('tipo_precio', ['normal', 'mayoreo', 'promocion', 'especial'])->default('normal');

            // Observaciones
            $table->text('observaciones')->nullable();

            $table->timestamps();

            // Índices
            $table->index('venta_id');
            $table->index('prenda_id');
            $table->index('codigo');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
    }
};
