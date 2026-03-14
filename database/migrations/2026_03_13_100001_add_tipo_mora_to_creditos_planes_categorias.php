<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parametrización de mora: tipo (porcentaje | monto_fijo) y monto fijo por día.
     * Permite configurar mora al crear crédito o desde producto/plan.
     */
    public function up(): void
    {
        Schema::table('creditos_prendarios', function (Blueprint $table) {
            $table->string('tipo_mora', 20)->default('porcentaje')->after('tasa_mora')
                ->comment('porcentaje = tasa_mora% aplicado; monto_fijo = mora_monto_fijo por día');
            $table->decimal('mora_monto_fijo', 12, 2)->nullable()->after('tipo_mora')
                ->comment('Monto fijo de mora por día cuando tipo_mora = monto_fijo');
        });

        Schema::table('planes_interes_categoria', function (Blueprint $table) {
            $table->string('tipo_mora', 20)->default('porcentaje')->after('tasa_moratorios')
                ->comment('porcentaje = tasa_moratorios%; monto_fijo = mora_monto_fijo por día');
            $table->decimal('mora_monto_fijo', 12, 2)->nullable()->after('tipo_mora')
                ->comment('Monto fijo de mora por día cuando tipo_mora = monto_fijo');
        });

        Schema::table('categoria_productos', function (Blueprint $table) {
            if (!Schema::hasColumn('categoria_productos', 'tipo_mora_default')) {
                $table->string('tipo_mora_default', 20)->nullable()->after('tasa_mora_default')
                    ->comment('porcentaje | monto_fijo por defecto para créditos de esta categoría');
            }
            if (!Schema::hasColumn('categoria_productos', 'mora_monto_fijo_default')) {
                $table->decimal('mora_monto_fijo_default', 12, 2)->nullable()->after('tipo_mora_default')
                    ->comment('Mora fija por día por defecto cuando tipo_mora_default = monto_fijo');
            }
        });

        Schema::table('credito_plan_pagos', function (Blueprint $table) {
            if (!Schema::hasColumn('credito_plan_pagos', 'mora_monto_fijo_aplicado')) {
                $table->decimal('mora_monto_fijo_aplicado', 12, 2)->nullable()->after('tasa_mora_aplicada')
                    ->comment('Monto fijo por día aplicado en esta cuota (copia del crédito)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('creditos_prendarios', function (Blueprint $table) {
            $table->dropColumn(['tipo_mora', 'mora_monto_fijo']);
        });
        Schema::table('planes_interes_categoria', function (Blueprint $table) {
            $table->dropColumn(['tipo_mora', 'mora_monto_fijo']);
        });
        Schema::table('categoria_productos', function (Blueprint $table) {
            $table->dropColumn(['tipo_mora_default', 'mora_monto_fijo_default']);
        });
        Schema::table('credito_plan_pagos', function (Blueprint $table) {
            $table->dropColumn('mora_monto_fijo_aplicado');
        });
    }
};
