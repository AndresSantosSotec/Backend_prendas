<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla de planes de interés configurables por categoría de productos
     * Similar a PrendaFlex: permite múltiples configuraciones de tasas por categoría
     */
    public function up(): void
    {
        Schema::create('planes_interes_categoria', function (Blueprint $table) {
            $table->id();

            // Relación con categoría
            $table->foreignId('categoria_producto_id')
                ->constrained('categoria_productos')
                ->onDelete('cascade')
                ->comment('Categoría a la que pertenece este plan');

            // Identificación del plan
            $table->string('nombre', 100)->comment('Nombre del plan (ej: "Plan Semanal 4 semanas")');
            $table->string('codigo', 50)->nullable()->comment('Código único del plan');
            $table->text('descripcion')->nullable()->comment('Descripción del plan');

            // Configuración de periodo y plazo
            $table->enum('tipo_periodo', ['diario', 'semanal', 'quincenal', 'mensual'])
                ->default('semanal')
                ->comment('Tipo de periodo para cálculo de interés');

            $table->integer('plazo_numero')
                ->comment('Número de periodos (ej: 4, 8, 12)');

            $table->enum('plazo_unidad', ['dias', 'semanas', 'quincenas', 'meses'])
                ->default('semanas')
                ->comment('Unidad del plazo');

            $table->integer('plazo_dias_total')
                ->comment('Plazo total convertido a días (calculado)');

            // Tasas e intereses
            $table->decimal('tasa_interes', 8, 4)
                ->comment('Tasa de interés por periodo (%)');

            $table->decimal('tasa_almacenaje', 8, 4)
                ->default(0)
                ->comment('Tasa de almacenaje por periodo (%)');

            $table->decimal('tasa_moratorios', 8, 4)
                ->default(0)
                ->comment('Tasa de moratorios por día de atraso (%)');

            // Configuración de préstamo
            $table->decimal('porcentaje_prestamo', 5, 2)
                ->default(60.00)
                ->comment('Porcentaje del avalúo que se presta (%)');

            $table->decimal('monto_minimo', 12, 2)
                ->nullable()
                ->comment('Monto mínimo de crédito para este plan');

            $table->decimal('monto_maximo', 12, 2)
                ->nullable()
                ->comment('Monto máximo de crédito para este plan');

            // Días especiales
            $table->integer('dias_gracia')
                ->default(0)
                ->comment('Días de gracia antes de generar mora');

            $table->integer('dias_enajenacion')
                ->default(14)
                ->comment('Días antes de poder enajenar la prenda');

            // Cálculos adicionales
            $table->decimal('cat', 8, 2)
                ->nullable()
                ->comment('Costo Anual Total (%)');

            $table->decimal('interes_anual', 8, 2)
                ->nullable()
                ->comment('Tasa de interés anualizada (%)');

            $table->decimal('porcentaje_precio_venta', 5, 2)
                ->nullable()
                ->comment('Porcentaje sobre avalúo para precio de venta (%)');

            // Configuración de refrendos
            $table->integer('numero_refrendos_permitidos')
                ->nullable()
                ->comment('Número máximo de refrendos permitidos (null = ilimitado)');

            $table->boolean('permite_refrendos')
                ->default(true)
                ->comment('Si permite refrendos en este plan');

            // Control
            $table->boolean('activo')
                ->default(true)
                ->comment('Si el plan está activo y puede usarse');

            $table->boolean('es_default')
                ->default(false)
                ->comment('Si es el plan por defecto para la categoría');

            $table->integer('orden')
                ->default(0)
                ->comment('Orden de visualización');

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('categoria_producto_id');
            $table->index('tipo_periodo');
            $table->index(['categoria_producto_id', 'activo']);
            $table->index(['categoria_producto_id', 'es_default']);
            $table->unique(['categoria_producto_id', 'codigo'], 'unique_categoria_codigo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planes_interes_categoria');
    }
};
