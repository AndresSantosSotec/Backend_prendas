<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('prenda_id')->constrained('prendas')->comment('Prenda vendida');
            $table->foreignId('credito_prendario_id')->nullable()->constrained('creditos_prendarios')->comment('Crédito asociado si aplica');

            // Identificación de la venta
            $table->string('codigo_venta', 50)->unique()->comment('Código único de venta');

            // Información del comprador
            $table->string('cliente_nombre', 200)->comment('Nombre del comprador');
            $table->string('cliente_nit', 20)->default('C/F')->comment('NIT del comprador');
            $table->string('cliente_telefono', 20)->nullable()->comment('Teléfono del comprador');
            $table->string('cliente_email', 100)->nullable()->comment('Email del comprador');

            // Montos
            $table->decimal('precio_publicado', 20, 2)->default(0)->comment('Precio publicado original');
            $table->decimal('precio_final', 20, 2)->default(0)->comment('Precio final de venta');
            $table->decimal('descuento', 20, 2)->default(0)->comment('Descuento aplicado');

            // Pago
            $table->enum('metodo_pago', ['efectivo', 'tarjeta', 'transferencia', 'cheque', 'mixto'])->default('efectivo')->comment('Método de pago');
            $table->string('referencia_pago', 100)->nullable()->comment('Referencia de pago (número de transacción, etc)');

            // Responsables
            $table->foreignId('vendedor_id')->constrained('users')->comment('Usuario que realizó la venta');
            $table->foreignId('sucursal_id')->constrained('sucursales')->comment('Sucursal donde se realizó la venta');

            // Fechas
            $table->timestamp('fecha_venta')->comment('Fecha y hora de la venta');
            $table->timestamp('fecha_cancelacion')->nullable()->comment('Fecha de cancelación si aplica');

            // Información adicional
            $table->text('observaciones')->nullable()->comment('Observaciones de la venta');
            $table->text('motivo_cancelacion')->nullable()->comment('Motivo de cancelación si aplica');

            // Estado
            $table->enum('estado', ['completada', 'cancelada', 'pendiente_entrega'])->default('completada')->comment('Estado de la venta');

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('codigo_venta');
            $table->index('fecha_venta');
            $table->index('estado');
            $table->index('vendedor_id');
            $table->index(['fecha_venta', 'sucursal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
