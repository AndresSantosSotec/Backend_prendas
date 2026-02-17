<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla pivot para relacionar créditos de ventas con gastos.
     * Paralela a credito_gasto pero para ventas a crédito
     * Permite asociar múltiples gastos a un crédito de venta.
     */
    public function up(): void
    {
        Schema::create('venta_credito_gastos', function (Blueprint $table) {
            $table->id();

            // FK al crédito de venta
            $table->foreignId('venta_credito_id')->constrained('venta_creditos')->onDelete('cascade');

            // FK al gasto (de la tabla de gastos del sistema)
            $table->unsignedBigInteger('gasto_id');
            $table->foreign('gasto_id')
                ->references('id_gasto')
                ->on('gastos')
                ->onDelete('cascade');

            // Valor calculado al momento de asociar (para histórico)
            $table->decimal('valor_calculado', 12, 2)->nullable()->comment('Valor calculado al momento de asociar');

            // Indicar si está incluido en las cuotas o es adicional
            $table->boolean('incluido_en_cuotas')->default(true)->comment('Si el gasto está prorrateado en las cuotas');

            // Estado del gasto en el crédito
            $table->enum('estado', [
                'pendiente',
                'pagado',
                'parcial',
                'condonado'
            ])->default('pendiente')->comment('Estado del gasto');

            $table->timestamps();

            // Constraint único para evitar duplicados
            $table->unique(['venta_credito_id', 'gasto_id'], 'venta_credito_gasto_unique');

            // Índices
            $table->index('venta_credito_id');
            $table->index('gasto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_credito_gastos');
    }
};
