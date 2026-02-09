<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla pivot para relacionar créditos con gastos.
     * Permite asociar múltiples gastos a un crédito.
     */
    public function up(): void
    {
        Schema::create('credito_gasto', function (Blueprint $table) {
            $table->id();

            // FK al crédito prendario
            $table->unsignedBigInteger('credito_id');
            $table->foreign('credito_id')
                ->references('id')
                ->on('creditos_prendarios')
                ->onDelete('cascade');

            // FK al gasto
            $table->unsignedBigInteger('gasto_id');
            $table->foreign('gasto_id')
                ->references('id_gasto')
                ->on('gastos')
                ->onDelete('cascade');

            // Valor calculado al momento de asociar (para histórico)
            $table->decimal('valor_calculado', 12, 2)->nullable()->comment('Valor calculado al momento de asociar');

            $table->timestamps();

            // Constraint único para evitar duplicados
            $table->unique(['credito_id', 'gasto_id'], 'credito_gasto_unique');

            // Índices
            $table->index('credito_id');
            $table->index('gasto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credito_gasto');
    }
};
