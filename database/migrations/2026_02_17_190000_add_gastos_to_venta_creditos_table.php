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
        Schema::table('venta_creditos', function (Blueprint $table) {
            // Gastos adicionales del crédito
            $table->decimal('gasto_seguro', 10, 2)->default(0)->after('tasa_mora')
                ->comment('Monto del seguro del crédito');
            $table->decimal('gasto_estudio', 10, 2)->default(0)->after('gasto_seguro')
                ->comment('Gastos de estudio/análisis del crédito');
            $table->decimal('gasto_apertura', 10, 2)->default(0)->after('gasto_estudio')
                ->comment('Gastos de apertura del crédito');
            $table->decimal('gasto_otros', 10, 2)->default(0)->after('gasto_apertura')
                ->comment('Otros gastos del crédito');
            $table->decimal('total_gastos', 10, 2)->default(0)->after('gasto_otros')
                ->comment('Total de gastos adicionales (suma de todos)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venta_creditos', function (Blueprint $table) {
            $table->dropColumn([
                'gasto_seguro',
                'gasto_estudio',
                'gasto_apertura',
                'gasto_otros',
                'total_gastos'
            ]);
        });
    }
};
