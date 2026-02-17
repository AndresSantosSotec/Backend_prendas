<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agregar soporte completo para ventas a crédito
     * Actualiza tipo_venta ENUM para incluir 'credito'
     * Agrega campos específicos para cálculo de intereses flat
     */
    public function up(): void
    {
        // 1. Actualizar ENUM tipo_venta para incluir 'credito'
        DB::statement("ALTER TABLE ventas MODIFY COLUMN tipo_venta ENUM('contado', 'credito', 'apartado', 'plan_pagos') DEFAULT 'contado'");

        Schema::table('ventas', function (Blueprint $table) {
            // Campos específicos para crédito
            if (!Schema::hasColumn('ventas', 'interes_total')) {
                $table->decimal('interes_total', 20, 2)->default(0)->after('tasa_interes')
                    ->comment('Interés total calculado (flat)');
            }

            if (!Schema::hasColumn('ventas', 'total_credito')) {
                $table->decimal('total_credito', 20, 2)->default(0)->after('interes_total')
                    ->comment('Total a pagar incluyendo intereses');
            }

            // Campos específicos para apartado
            if (!Schema::hasColumn('ventas', 'anticipo_apartado')) {
                $table->decimal('anticipo_apartado', 20, 2)->default(0)->after('total_credito')
                    ->comment('Anticipo pagado en apartado');
            }

            if (!Schema::hasColumn('ventas', 'dias_apartado')) {
                $table->integer('dias_apartado')->nullable()->after('anticipo_apartado')
                    ->comment('Días de plazo para liquidar apartado');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir ENUM
        DB::statement("ALTER TABLE ventas MODIFY COLUMN tipo_venta ENUM('contado', 'apartado', 'plan_pagos') DEFAULT 'contado'");

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['interes_total', 'total_credito', 'anticipo_apartado', 'dias_apartado']);
        });
    }
};
