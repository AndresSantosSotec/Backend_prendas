<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Crea la tabla refrendos para llevar un historial detallado de todos los
     * refrendos aplicados a los créditos prendarios.
     */
    public function up(): void
    {
        Schema::create('refrendos', function (Blueprint $table) {
            $table->id();

            // Relación con crédito
            $table->foreignId('credito_id')->constrained('creditos_prendarios')
                ->onDelete('cascade')
                ->comment('ID del crédito prendario al que se aplica el refrendo');

            $table->integer('numero_refrendo')->comment('Número secuencial del refrendo (1, 2, 3...)');

            // Tipo de refrendo
            $table->enum('tipo_refrendo', ['parcial', 'total', 'con_capital'])
                ->default('parcial')
                ->comment('parcial = solo intereses, total = intereses + capital, con_capital = con % mínimo obligatorio');

            // Montos involucrados
            $table->decimal('monto_interes_adeudado', 12, 2)->default(0)
                ->comment('Monto de intereses adeudados al momento del refrendo');

            $table->decimal('monto_mora_adeudado', 12, 2)->default(0)
                ->comment('Monto de mora adeudada al momento del refrendo');

            $table->decimal('monto_capital_pagado', 12, 2)->default(0)
                ->comment('Monto de capital pagado en este refrendo (si aplica)');

            $table->decimal('monto_total_pagado', 12, 2)->default(0)
                ->comment('Monto total pagado en este refrendo');

            // Fechas y plazos
            $table->datetime('fecha_refrendo')->default(DB::raw('CURRENT_TIMESTAMP'))
                ->comment('Fecha y hora en que se realizó el refrendo');

            $table->date('fecha_vencimiento_anterior')
                ->comment('Fecha de vencimiento antes del refrendo');

            $table->date('fecha_vencimiento_nueva')
                ->comment('Nueva fecha de vencimiento después del refrendo');

            $table->integer('dias_extendidos')->default(0)
                ->comment('Cantidad de días que se extendió el plazo');

            // Configuración aplicada en el refrendo
            $table->decimal('tasa_interes_aplicada', 8, 2)->default(0)
                ->comment('Tasa de interés que se aplicará en el nuevo período');

            $table->integer('plazo_dias_nuevo')->default(0)
                ->comment('Nuevo plazo en días del crédito');

            // Promociones y descuentos
            $table->string('promocion_aplicada', 100)->nullable()
                ->comment('Nombre de la promoción aplicada (si existe)');

            $table->decimal('descuento_aplicado', 12, 2)->default(0)
                ->comment('Monto de descuento aplicado en el refrendo');

            // Auditoría y referencias
            $table->foreignId('usuario_id')->constrained('users')
                ->comment('Usuario que procesó el refrendo');

            $table->foreignId('sucursal_id')->constrained('sucursales')
                ->comment('Sucursal donde se realizó el refrendo');

            $table->unsignedBigInteger('caja_movimiento_id')->nullable()
                ->comment('ID del movimiento de caja asociado');

            $table->string('recibo_pdf_url', 255)->nullable()
                ->comment('URL del PDF del recibo de refrendo');

            $table->text('observaciones')->nullable()
                ->comment('Observaciones adicionales del refrendo');

            $table->timestamps();
            $table->softDeletes();

            // Índices para optimizar consultas
            $table->index('credito_id');
            $table->index('tipo_refrendo');
            $table->index('fecha_refrendo');
            $table->index(['credito_id', 'numero_refrendo']);
            $table->index('usuario_id');
            $table->index('sucursal_id');

            // Constraint: un número de refrendo por crédito debe ser único
            $table->unique(['credito_id', 'numero_refrendo'], 'unique_numero_refrendo_por_credito');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refrendos');
    }
};
