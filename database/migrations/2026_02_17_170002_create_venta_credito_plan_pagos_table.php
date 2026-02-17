<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de plan de pagos para créditos de ventas
     * Paralela a credito_plan_pagos pero para ventas a crédito
     * Define las cuotas del crédito de venta
     */
    public function up(): void
    {
        Schema::create('venta_credito_plan_pagos', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('venta_credito_id')->constrained('venta_creditos')->onDelete('cascade')->comment('Crédito de venta al que pertenece');

            // Información de la cuota
            $table->integer('numero_cuota')->comment('Número consecutivo de la cuota');
            $table->date('fecha_vencimiento')->comment('Fecha de vencimiento de la cuota');
            $table->date('fecha_pago')->nullable()->comment('Fecha en que se pagó');

            // Estado de la cuota
            $table->enum('estado', [
                'pendiente',        // No pagada
                'pagada',           // Pagada totalmente
                'pagada_parcial',   // Pagada parcialmente
                'vencida',          // Vencida sin pagar
                'en_mora',          // Con días de mora
                'cancelada',        // Cancelada
                'condonada'         // Condonada
            ])->default('pendiente')->comment('Estado de la cuota');

            // Montos proyectados (lo que debe pagar)
            $table->decimal('capital_proyectado', 20, 2)->default(0)->comment('Capital a pagar en esta cuota');
            $table->decimal('interes_proyectado', 20, 2)->default(0)->comment('Interés a pagar en esta cuota');
            $table->decimal('mora_proyectada', 20, 2)->default(0)->comment('Mora proyectada');
            $table->decimal('otros_cargos_proyectados', 20, 2)->default(0)->comment('Otros cargos (gastos, etc.)');
            $table->decimal('monto_cuota_proyectado', 20, 2)->default(0)->comment('Monto total de la cuota');

            // Montos pagados (lo que ha pagado)
            $table->decimal('capital_pagado', 20, 2)->default(0)->comment('Capital pagado');
            $table->decimal('interes_pagado', 20, 2)->default(0)->comment('Interés pagado');
            $table->decimal('mora_pagada', 20, 2)->default(0)->comment('Mora pagada');
            $table->decimal('otros_cargos_pagados', 20, 2)->default(0)->comment('Otros cargos pagados');
            $table->decimal('monto_total_pagado', 20, 2)->default(0)->comment('Monto total pagado');

            // Saldos pendientes
            $table->decimal('capital_pendiente', 20, 2)->default(0)->comment('Capital pendiente de pago');
            $table->decimal('interes_pendiente', 20, 2)->default(0)->comment('Interés pendiente de pago');
            $table->decimal('mora_pendiente', 20, 2)->default(0)->comment('Mora pendiente de pago');
            $table->decimal('otros_cargos_pendientes', 20, 2)->default(0)->comment('Otros cargos pendientes');
            $table->decimal('monto_pendiente', 20, 2)->default(0)->comment('Monto total pendiente');

            // Saldo de capital del crédito después de esta cuota
            $table->decimal('saldo_capital_credito', 20, 2)->default(0)->comment('Saldo del crédito después de pagar esta cuota');

            // Control de mora
            $table->integer('dias_mora')->default(0)->comment('Días de mora acumulados');
            $table->date('fecha_inicio_mora')->nullable()->comment('Fecha en que inició la mora');
            $table->decimal('tasa_mora_aplicada', 8, 2)->default(0)->comment('Tasa de mora aplicada');

            // Información de pago
            $table->unsignedBigInteger('ultimo_movimiento_id')->nullable()->comment('Último movimiento aplicado');
            $table->foreignId('usuario_pago_id')->nullable()->constrained('users')->comment('Usuario que registró el pago');

            // Datos adicionales
            $table->text('observaciones')->nullable()->comment('Observaciones de la cuota');
            $table->boolean('permite_pago_parcial')->default(true)->comment('Si permite pagos parciales');

            // Control de modificaciones
            $table->enum('tipo_modificacion', [
                'original',
                'refinanciamiento',
                'ajuste',
                'condonacion'
            ])->default('original')->comment('Tipo de modificación');
            $table->foreignId('modificado_por_id')->nullable()->constrained('users')->comment('Usuario que modificó');
            $table->datetime('fecha_modificacion')->nullable()->comment('Fecha de última modificación');

            // Auditoría
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('venta_credito_id');
            $table->index('estado');
            $table->index('fecha_vencimiento');
            $table->index(['venta_credito_id', 'numero_cuota']);
            $table->index(['venta_credito_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_credito_plan_pagos');
    }
};
