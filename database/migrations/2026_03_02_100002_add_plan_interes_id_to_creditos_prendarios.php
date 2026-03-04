<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agregar referencia al plan de interés usado en el crédito
     */
    public function up(): void
    {
        Schema::table('creditos_prendarios', function (Blueprint $table) {
            $table->foreignId('plan_interes_id')
                ->nullable()
                ->after('tasador_id')
                ->constrained('planes_interes_categoria')
                ->nullOnDelete()
                ->comment('Plan de interés aplicado al crédito');

            $table->index('plan_interes_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('creditos_prendarios', function (Blueprint $table) {
            $table->dropForeign(['plan_interes_id']);
            $table->dropIndex(['plan_interes_id']);
            $table->dropColumn('plan_interes_id');
        });
    }
};
