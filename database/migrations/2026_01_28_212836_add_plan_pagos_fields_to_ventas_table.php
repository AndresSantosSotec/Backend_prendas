<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar campos para ventas a crédito y plan de pagos
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Campos para plan de pagos
            if (!Schema::hasColumn('ventas', 'plazo_dias')) {
                $table->integer('plazo_dias')->nullable()->after('tipo_venta')
                    ->comment('Días de plazo para pago completo');
            }

            if (!Schema::hasColumn('ventas', 'fecha_vencimiento')) {
                $table->timestamp('fecha_vencimiento')->nullable()->after('plazo_dias')
                    ->comment('Fecha límite de pago completo');
            }

            if (!Schema::hasColumn('ventas', 'enganche')) {
                $table->decimal('enganche', 20, 2)->default(0)->after('fecha_vencimiento')
                    ->comment('Monto de enganche/anticipo');
            }

            if (!Schema::hasColumn('ventas', 'saldo_pendiente')) {
                $table->decimal('saldo_pendiente', 20, 2)->default(0)->after('total_pagado')
                    ->comment('Saldo pendiente de pagar');
            }

            if (!Schema::hasColumn('ventas', 'numero_cuotas')) {
                $table->integer('numero_cuotas')->nullable()->after('saldo_pendiente')
                    ->comment('Número total de cuotas si es plan de pagos');
            }

            if (!Schema::hasColumn('ventas', 'monto_cuota')) {
                $table->decimal('monto_cuota', 20, 2)->nullable()->after('numero_cuotas')
                    ->comment('Monto de cada cuota');
            }

            if (!Schema::hasColumn('ventas', 'frecuencia_pago')) {
                $table->enum('frecuencia_pago', ['semanal', 'quincenal', 'mensual'])->nullable()->after('monto_cuota')
                    ->comment('Frecuencia de pago de cuotas');
            }

            if (!Schema::hasColumn('ventas', 'fecha_proximo_pago')) {
                $table->timestamp('fecha_proximo_pago')->nullable()->after('frecuencia_pago')
                    ->comment('Fecha del próximo pago esperado');
            }

            if (!Schema::hasColumn('ventas', 'cuotas_pagadas')) {
                $table->integer('cuotas_pagadas')->default(0)->after('fecha_proximo_pago')
                    ->comment('Número de cuotas ya pagadas');
            }

            if (!Schema::hasColumn('ventas', 'intereses')) {
                $table->decimal('intereses', 20, 2)->default(0)->after('cuotas_pagadas')
                    ->comment('Intereses aplicados (si aplica)');
            }

            if (!Schema::hasColumn('ventas', 'tasa_interes')) {
                $table->decimal('tasa_interes', 5, 2)->default(0)->after('intereses')
                    ->comment('Tasa de interés mensual (%)');
            }

            if (!Schema::hasColumn('ventas', 'fecha_liquidacion')) {
                $table->timestamp('fecha_liquidacion')->nullable()->after('tasa_interes')
                    ->comment('Fecha en que se liquidó totalmente la venta');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $columns = [
                'plazo_dias', 'fecha_vencimiento', 'enganche',
                'saldo_pendiente', 'numero_cuotas', 'monto_cuota',
                'frecuencia_pago', 'fecha_proximo_pago', 'cuotas_pagadas',
                'intereses', 'tasa_interes', 'fecha_liquidacion'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('ventas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
