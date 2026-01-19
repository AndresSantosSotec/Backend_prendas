<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla de movimientos de créditos (Kardex)
     * Adaptada de CREDKAR - Registra todos los pagos y desembolsos
     */
    public function up(): void
    {
        Schema::create('credito_movimientos', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('credito_prendario_id')->constrained('creditos_prendarios')->onDelete('cascade')->comment('Crédito al que pertenece');
            $table->foreignId('usuario_id')->constrained('users')->comment('Usuario que registra el movimiento');
            $table->foreignId('sucursal_id')->constrained('sucursales')->comment('Sucursal donde se realiza');
            $table->foreignId('cuota_id')->nullable()->constrained('credito_plan_pagos', 'id')->comment('Cuota pagada si aplica');

            // Información del movimiento
            $table->string('numero_movimiento', 50)->unique()->comment('Número único del movimiento');
            $table->string('numero_recibo', 50)->nullable()->comment('Número de recibo/boleta');
            $table->string('numero_factura', 50)->nullable()->comment('Número de factura si aplica');

            // Tipo de movimiento
            $table->enum('tipo_movimiento', [
                'desembolso',           // Entrega de dinero al cliente
                'pago',                 // Pago del cliente
                'pago_parcial',         // Pago parcial de cuota
                'pago_total',           // Pago total del crédito
                'pago_adelantado',      // Pago adelantado
                'cargo_mora',           // Cargo por mora
                'cargo_administracion', // Cargo administrativo
                'ajuste',               // Ajuste manual
                'reversion',            // Reversión de pago
                'condonacion'           // Condonación de deuda
            ])->comment('Tipo de movimiento');

            $table->integer('numero_cuota')->default(0)->comment('Número de cuota pagada (0 para desembolsos)');

            // Fechas
            $table->date('fecha_movimiento')->comment('Fecha del movimiento');
            $table->datetime('fecha_registro')->comment('Fecha y hora de registro en sistema');
            $table->date('fecha_boleta')->nullable()->comment('Fecha de boleta bancaria si aplica');

            // Montos
            $table->decimal('monto_total', 20, 2)->default(0)->comment('Monto total del movimiento');
            $table->decimal('capital', 20, 2)->default(0)->comment('Monto aplicado a capital');
            $table->decimal('interes', 20, 2)->default(0)->comment('Monto aplicado a interés');
            $table->decimal('mora', 20, 2)->default(0)->comment('Monto aplicado a mora');
            $table->decimal('otros_cargos', 20, 2)->default(0)->comment('Otros cargos');

            // Saldos después del movimiento
            $table->decimal('saldo_capital', 20, 2)->default(0)->comment('Saldo de capital después del movimiento');
            $table->decimal('saldo_interes', 20, 2)->default(0)->comment('Saldo de interés después del movimiento');
            $table->decimal('saldo_mora', 20, 2)->default(0)->comment('Saldo de mora después del movimiento');

            // Forma de pago
            $table->enum('forma_pago', [
                'efectivo',
                'transferencia',
                'cheque',
                'tarjeta_debito',
                'tarjeta_credito',
                'deposito_bancario',
                'mixto'
            ])->nullable()->comment('Forma de pago utilizada');

            // Información bancaria
            $table->string('banco', 100)->nullable()->comment('Nombre del banco');
            $table->string('numero_cuenta', 50)->nullable()->comment('Número de cuenta');
            $table->string('numero_cheque', 50)->nullable()->comment('Número de cheque');
            $table->string('numero_autorizacion', 50)->nullable()->comment('Número de autorización');
            $table->string('referencia_bancaria', 100)->nullable()->comment('Referencia bancaria');

            // Detalles adicionales
            $table->text('concepto')->nullable()->comment('Concepto/descripción del movimiento');
            $table->text('observaciones')->nullable()->comment('Observaciones adicionales');

            // Control de estado
            $table->enum('estado', [
                'activo',
                'reversado',
                'anulado',
                'pendiente'
            ])->default('activo')->comment('Estado del movimiento');

            $table->foreignId('reversado_por')->nullable()->constrained('users')->comment('Usuario que reversó');
            $table->datetime('fecha_reversion')->nullable()->comment('Fecha de reversión');
            $table->text('motivo_reversion')->nullable()->comment('Motivo de la reversión');
            $table->foreignId('movimiento_reversa_id')->nullable()->constrained('credito_movimientos')->comment('Movimiento de reversa asociado');

            // Moneda y tipo de cambio
            $table->string('moneda', 10)->default('GTQ')->comment('Moneda del movimiento');
            $table->decimal('tipo_cambio', 10, 4)->default(1)->comment('Tipo de cambio aplicado');

            // Información del cajero/terminal
            $table->string('terminal', 20)->nullable()->comment('Terminal donde se registró');
            $table->string('turno', 20)->nullable()->comment('Turno del cajero');

            // Auditoría adicional
            $table->string('ip_origen', 45)->nullable()->comment('IP desde donde se registró');
            $table->json('datos_adicionales')->nullable()->comment('Datos adicionales en JSON');

            $table->timestamps();
            $table->softDeletes();

            // Índices para optimizar consultas
            $table->index('credito_prendario_id');
            $table->index('tipo_movimiento');
            $table->index('fecha_movimiento');
            $table->index('estado');
            $table->index('numero_recibo');
            $table->index(['credito_prendario_id', 'fecha_movimiento']);
            $table->index(['tipo_movimiento', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credito_movimientos');
    }
};
