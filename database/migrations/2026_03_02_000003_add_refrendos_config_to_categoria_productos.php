<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega campos de configuración de refrendos a la tabla categorias.
     * Esto permite configurar límites y requisitos específicos por categoría.
     */
    public function up(): void
    {
        Schema::table('categoria_productos', function (Blueprint $table) {
            // Configuración de refrendos por categoría
            $table->integer('refrendos_maximos_default')->nullable()
                ->after('permite_pago_capital_diferente')
                ->comment('Límite predeterminado de refrendos para esta categoría (NULL = ilimitado)');

            $table->boolean('requiere_pago_capital_refrendo')->default(false)
                ->after('refrendos_maximos_default')
                ->comment('Si al refrendar se debe pagar un porcentaje mínimo de capital');

            $table->decimal('porcentaje_capital_minimo', 5, 2)->nullable()
                ->after('requiere_pago_capital_refrendo')
                ->comment('Porcentaje mínimo de capital a pagar en refrendos (ej: 10.00 = 10%)');

            // Índice para consultas
            $table->index('requiere_pago_capital_refrendo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categoria_productos', function (Blueprint $table) {
            // Eliminar índice
            $table->dropIndex(['requiere_pago_capital_refrendo']);

            // Eliminar columnas
            $table->dropColumn([
                'refrendos_maximos_default',
                'requiere_pago_capital_refrendo',
                'porcentaje_capital_minimo'
            ]);
        });
    }
};
