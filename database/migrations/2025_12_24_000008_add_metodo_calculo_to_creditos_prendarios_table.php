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
        Schema::table('creditos_prendarios', function (Blueprint $table) {
            $table->enum('metodo_calculo', ['francesa', 'flat'])->default('francesa')->after('tipo_interes')->comment('Método de cálculo de intereses: francesa (amortización) o flat (interés fijo)');
            $table->boolean('afecta_interes_mensual')->default(false)->after('metodo_calculo')->comment('Si afecta intereses mensualmente (para método flat)');
            $table->boolean('permite_pago_capital_diferente')->default(false)->after('afecta_interes_mensual')->comment('Si permite pago de capital en periodos diferentes (peripagcap)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('creditos_prendarios', function (Blueprint $table) {
            $table->dropColumn([
                'metodo_calculo',
                'afecta_interes_mensual',
                'permite_pago_capital_diferente'
            ]);
        });
    }
};
