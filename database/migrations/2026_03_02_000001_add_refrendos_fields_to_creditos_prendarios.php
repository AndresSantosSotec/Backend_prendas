<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega campos para el sistema de refrendos mejorado:
     * - refrendos_realizados: contador de refrendos aplicados
     * - refrendos_maximos: límite de refrendos (NULL = ilimitado)
     * - permite_refrendo: flag para habilitar/deshabilitar refrendos
     * - fecha_ultimo_refrendo: timestamp del último refrendo aplicado
     */
    public function up(): void
    {
        Schema::table('creditos_prendarios', function (Blueprint $table) {
            // Campos de control de refrendos
            $table->integer('refrendos_realizados')->default(0)->after('requiere_renovacion')
                ->comment('Cantidad de refrendos aplicados al crédito');

            $table->integer('refrendos_maximos')->nullable()->after('refrendos_realizados')
                ->comment('Límite máximo de refrendos permitidos (NULL = ilimitado)');

            $table->boolean('permite_refrendo')->default(true)->after('refrendos_maximos')
                ->comment('Si el crédito acepta refrendos (flag de control)');

            $table->datetime('fecha_ultimo_refrendo')->nullable()->after('permite_refrendo')
                ->comment('Fecha y hora del último refrendo aplicado');

            // Índices para mejorar consultas
            $table->index('refrendos_realizados');
            $table->index(['permite_refrendo', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('creditos_prendarios', function (Blueprint $table) {
            // Eliminar índices primero
            $table->dropIndex(['refrendos_realizados']);
            $table->dropIndex(['permite_refrendo', 'estado']);

            // Eliminar columnas
            $table->dropColumn([
                'refrendos_realizados',
                'refrendos_maximos',
                'permite_refrendo',
                'fecha_ultimo_refrendo'
            ]);
        });
    }
};
