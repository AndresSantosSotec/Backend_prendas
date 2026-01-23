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
        Schema::table('categoria_productos', function (Blueprint $table) {
            // Parámetros de crédito por categoría
            $table->decimal('tasa_interes_default', 8, 2)->nullable()->after('activa')->comment('Tasa de interés por defecto para esta categoría (%)');
            $table->decimal('tasa_mora_default', 8, 2)->nullable()->after('tasa_interes_default')->comment('Tasa de mora por defecto para esta categoría (%)');
            $table->integer('plazo_maximo_dias')->nullable()->after('tasa_mora_default')->comment('Plazo máximo en días para esta categoría');
            $table->decimal('porcentaje_prestamo_maximo', 5, 2)->nullable()->after('plazo_maximo_dias')->comment('Porcentaje máximo de préstamo sobre avalúo (ej: 60.00 = 60%)');
            $table->enum('metodo_calculo_default', ['francesa', 'flat'])->default('francesa')->after('porcentaje_prestamo_maximo')->comment('Método de cálculo de intereses por defecto');
            $table->boolean('afecta_interes_mensual')->default(false)->after('metodo_calculo_default')->comment('Si afecta intereses mensualmente (para método flat)');
            $table->boolean('permite_pago_capital_diferente')->default(false)->after('afecta_interes_mensual')->comment('Si permite pago de capital en periodos diferentes (peripagcap)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categoria_productos', function (Blueprint $table) {
            $table->dropColumn([
                'tasa_interes_default',
                'tasa_mora_default',
                'plazo_maximo_dias',
                'porcentaje_prestamo_maximo',
                'metodo_calculo_default',
                'afecta_interes_mensual',
                'permite_pago_capital_diferente'
            ]);
        });
    }
};
