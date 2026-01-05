<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla principal de créditos prendarios (empeños)
     * Adaptada del sistema base cremcre_meta
     */
    public function up(): void
    {
        Schema::create('creditos_prendarios', function (Blueprint $table) {
            $table->id();

            // Información del crédito
            $table->string('numero_credito', 30)->unique()->comment('Número único del crédito/empeño');

            // Relaciones
            $table->foreignId('cliente_id')->constrained('clientes')->comment('Cliente que solicita el empeño');
            $table->foreignId('sucursal_id')->constrained('sucursales')->comment('Sucursal donde se origina');
            $table->foreignId('analista_id')->nullable()->constrained('users')->comment('Usuario que analiza/aprueba');
            $table->foreignId('cajero_id')->nullable()->constrained('users')->comment('Usuario cajero que desembolsa');
            $table->foreignId('tasador_id')->nullable()->constrained('users')->comment('Usuario tasador que evalúa prendas');

            // Estados del crédito
            $table->enum('estado', [
                'solicitado',      // Cliente solicita el empeño
                'en_analisis',     // En proceso de evaluación
                'aprobado',        // Aprobado pero no desembolsado
                'vigente',         // Desembolsado y activo
                'pagado',          // Totalmente liquidado
                'vencido',         // No pagado en tiempo
                'en_mora',         // Con días de atraso
                'incobrable',      // Pasado a pérdida
                'recuperado',      // Cliente recuperó prenda
                'vendido',         // Prenda vendida por falta de pago
                'rechazado',       // No aprobado
                'cancelado'        // Cancelado antes de desembolso
            ])->default('solicitado')->comment('Estado actual del crédito');

            // Fechas importantes
            $table->date('fecha_solicitud')->comment('Fecha de solicitud del empeño');
            $table->date('fecha_analisis')->nullable()->comment('Fecha de análisis/evaluación');
            $table->date('fecha_aprobacion')->nullable()->comment('Fecha de aprobación');
            $table->date('fecha_desembolso')->nullable()->comment('Fecha de entrega del dinero');
            $table->date('fecha_vencimiento')->nullable()->comment('Fecha límite de pago');
            $table->date('fecha_cancelacion')->nullable()->comment('Fecha de cancelación/liquidación total');
            $table->date('fecha_ultimo_pago')->nullable()->comment('Fecha del último pago realizado');

            // Montos
            $table->decimal('monto_solicitado', 20, 2)->default(0)->comment('Monto que solicita el cliente');
            $table->decimal('monto_aprobado', 20, 2)->default(0)->comment('Monto aprobado por el analista');
            $table->decimal('monto_desembolsado', 20, 2)->default(0)->comment('Monto efectivamente entregado');
            $table->decimal('valor_tasacion', 20, 2)->default(0)->comment('Valor total de tasación de prendas');

            // Capital e intereses
            $table->decimal('capital_pendiente', 20, 2)->default(0)->comment('Capital que falta por pagar');
            $table->decimal('capital_pagado', 20, 2)->default(0)->comment('Capital ya pagado');
            $table->decimal('interes_generado', 20, 2)->default(0)->comment('Interés total generado');
            $table->decimal('interes_pagado', 20, 2)->default(0)->comment('Interés ya pagado');
            $table->decimal('mora_generada', 20, 2)->default(0)->comment('Mora total generada');
            $table->decimal('mora_pagada', 20, 2)->default(0)->comment('Mora ya pagada');

            // Configuración del crédito
            $table->decimal('tasa_interes', 8, 2)->default(0)->comment('Tasa de interés (%)');
            $table->decimal('tasa_mora', 8, 2)->default(0)->comment('Tasa de mora (%)');
            $table->enum('tipo_interes', ['mensual', 'quincenal', 'semanal', 'diario'])->default('mensual')->comment('Periodo de cálculo de interés');
            $table->integer('plazo_dias')->default(30)->comment('Plazo en días del crédito');
            $table->integer('dias_gracia')->default(0)->comment('Días de gracia sin generar mora');
            $table->integer('numero_cuotas')->default(1)->comment('Número de cuotas');
            $table->decimal('monto_cuota', 20, 2)->default(0)->comment('Monto de cada cuota');

            // Control de mora
            $table->integer('dias_mora')->default(0)->comment('Días de mora acumulados');
            $table->enum('calificacion', ['A', 'B', 'C', 'D', 'E'])->default('A')->comment('Calificación de cartera');

            // Información del desembolso
            $table->enum('forma_desembolso', ['efectivo', 'transferencia', 'cheque'])->nullable()->comment('Forma de entrega del dinero');
            $table->string('referencia_desembolso', 100)->nullable()->comment('Referencia del desembolso');

            // Información adicional
            $table->text('observaciones')->nullable()->comment('Observaciones generales');
            $table->text('motivo_rechazo')->nullable()->comment('Motivo de rechazo si aplica');
            $table->string('numero_pagare', 50)->nullable()->comment('Número de pagaré firmado');
            $table->string('numero_contrato', 50)->nullable()->comment('Número de contrato');

            // Control
            $table->boolean('requiere_renovacion')->default(false)->comment('Si requiere renovación');
            $table->foreignId('credito_renovado_id')->nullable()->constrained('creditos_prendarios')->comment('ID del crédito que se renovó');

            $table->timestamps();
            $table->softDeletes();

            // Índices para optimizar consultas
            $table->index('estado');
            $table->index('fecha_vencimiento');
            $table->index('cliente_id');
            $table->index('sucursal_id');
            $table->index(['estado', 'fecha_vencimiento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creditos_prendarios');
    }
};
