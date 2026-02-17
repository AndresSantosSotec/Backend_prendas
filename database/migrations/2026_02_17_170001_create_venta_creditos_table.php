<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla principal de créditos de ventas
     * Paralela a creditos_prendarios pero para ventas a crédito
     * Esta tabla es INDEPENDIENTE de los empeños
     */
    public function up(): void
    {
        Schema::create('venta_creditos', function (Blueprint $table) {
            $table->id();

            // Información del crédito de venta
            $table->string('numero_credito', 30)->unique()->comment('Número único del crédito de venta');

            // Relación con la venta que origina el crédito
            $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade')->comment('Venta que genera el crédito');

            // Relaciones
            $table->foreignId('cliente_id')->constrained('clientes')->comment('Cliente que compra a crédito');
            $table->foreignId('sucursal_id')->constrained('sucursales')->comment('Sucursal donde se realiza la venta');
            $table->foreignId('vendedor_id')->constrained('users')->comment('Vendedor que realiza la venta');
            $table->foreignId('aprobado_por_id')->nullable()->constrained('users')->comment('Usuario que aprueba el crédito');

            // Estados del crédito
            $table->enum('estado', [
                'pendiente',        // Crédito registrado pero pendiente de aprobación
                'vigente',          // Crédito activo (aprobado y en curso)
                'pagado',           // Totalmente liquidado
                'vencido',          // No pagado en tiempo
                'en_mora',          // Con días de atraso
                'incobrable',       // Pasado a pérdida
                'cancelado',        // Cancelado antes de completar
                'devuelto'          // Producto devuelto - crédito anulado
            ])->default('vigente')->comment('Estado actual del crédito');

            // Fechas importantes
            $table->date('fecha_credito')->comment('Fecha de inicio del crédito');
            $table->date('fecha_aprobacion')->nullable()->comment('Fecha de aprobación');
            $table->date('fecha_primer_pago')->nullable()->comment('Fecha del primer pago programado');
            $table->date('fecha_vencimiento')->nullable()->comment('Fecha límite del último pago');
            $table->date('fecha_liquidacion')->nullable()->comment('Fecha de cancelación/liquidación total');
            $table->date('fecha_ultimo_pago')->nullable()->comment('Fecha del último pago realizado');

            // Montos base de la venta
            $table->decimal('monto_venta', 20, 2)->default(0)->comment('Monto total de la venta');
            $table->decimal('enganche', 20, 2)->default(0)->comment('Enganche pagado al momento');
            $table->decimal('saldo_financiar', 20, 2)->default(0)->comment('Saldo a financiar (venta - enganche)');

            // Capital e intereses
            $table->decimal('interes_total', 20, 2)->default(0)->comment('Interés total calculado (flat)');
            $table->decimal('total_credito', 20, 2)->default(0)->comment('Total a pagar (saldo + interés)');
            $table->decimal('capital_pendiente', 20, 2)->default(0)->comment('Capital que falta por pagar');
            $table->decimal('capital_pagado', 20, 2)->default(0)->comment('Capital ya pagado');
            $table->decimal('interes_pendiente', 20, 2)->default(0)->comment('Interés que falta por pagar');
            $table->decimal('interes_pagado', 20, 2)->default(0)->comment('Interés ya pagado');
            $table->decimal('mora_generada', 20, 2)->default(0)->comment('Mora total generada');
            $table->decimal('mora_pagada', 20, 2)->default(0)->comment('Mora ya pagada');
            $table->decimal('saldo_actual', 20, 2)->default(0)->comment('Saldo total pendiente');

            // Configuración del crédito
            $table->decimal('tasa_interes', 8, 2)->default(0)->comment('Tasa de interés mensual (%)');
            $table->decimal('tasa_mora', 8, 2)->default(0)->comment('Tasa de mora diaria (%)');
            $table->enum('tipo_interes', ['flat', 'sobre_saldo'])->default('flat')->comment('Tipo de cálculo de interés');
            $table->enum('frecuencia_pago', ['semanal', 'quincenal', 'mensual'])->default('mensual')->comment('Frecuencia de pagos');
            $table->integer('numero_cuotas')->default(1)->comment('Número de cuotas');
            $table->decimal('monto_cuota', 20, 2)->default(0)->comment('Monto de cada cuota');
            $table->integer('dias_gracia')->default(0)->comment('Días de gracia sin generar mora');

            // Control de mora
            $table->integer('dias_mora')->default(0)->comment('Días de mora acumulados');
            $table->integer('cuotas_vencidas')->default(0)->comment('Número de cuotas vencidas');
            $table->integer('cuotas_pagadas')->default(0)->comment('Número de cuotas pagadas');

            // Información adicional
            $table->text('observaciones')->nullable()->comment('Observaciones generales');
            $table->string('numero_contrato', 50)->nullable()->comment('Número de contrato si aplica');

            // Auditoría
            $table->timestamps();
            $table->softDeletes();

            // Índices para optimizar consultas
            $table->index('estado');
            $table->index('fecha_vencimiento');
            $table->index('cliente_id');
            $table->index('sucursal_id');
            $table->index('venta_id');
            $table->index(['estado', 'fecha_vencimiento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_creditos');
    }
};
