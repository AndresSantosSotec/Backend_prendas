<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabla de detalles por denominación de cada movimiento de bóveda
     * Permite almacenar el desglose de billetes/monedas de forma normalizada
     */
    public function up(): void
    {
        Schema::create('boveda_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movimiento_id')
                ->constrained('boveda_movimientos')
                ->onDelete('cascade')
                ->comment('Movimiento de bóveda al que pertenece');
            $table->foreignId('denominacion_id')
                ->constrained('denominaciones')
                ->onDelete('restrict')
                ->comment('Denominación (billete/moneda) contada');
            $table->integer('cantidad')
                ->default(0)
                ->comment('Cantidad de unidades de esta denominación');
            $table->decimal('valor_denominacion', 15, 2)
                ->comment('Valor de la denominación al momento del registro');
            $table->decimal('subtotal', 15, 2)
                ->comment('Subtotal calculado (cantidad * valor_denominacion)');
            $table->timestamps();

            // Índices
            $table->unique(['movimiento_id', 'denominacion_id']);
            $table->index('denominacion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boveda_detalles');
    }
};
