<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración para el sistema de remates de contratos vencidos.
 *
 * 1. Crea tabla `remates` para registrar cada operación de remate
 * 2. Agrega 'rematado' al enum de estados de creditos_prendarios
 * 3. Agrega 'remate' al enum de tipo_movimiento de credito_movimientos
 * 4. Agrega campos de remate a prendas (precio_remate, fecha_remate)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Crear tabla remates
        Schema::create('remates', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_remate', 30)->unique()->comment('Código auto: REM-YYYYMMDD-XXXXXX');

            $table->unsignedBigInteger('credito_id');
            $table->unsignedBigInteger('prenda_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('usuario_id')->comment('Usuario que ejecutó el remate');

            $table->enum('tipo', ['manual', 'automatico'])->default('manual');
            $table->enum('estado', ['pendiente', 'ejecutado', 'cancelado', 'vendido'])->default('pendiente');

            // Montos del crédito al momento del remate
            $table->decimal('capital_pendiente', 20, 2)->default(0);
            $table->decimal('intereses_pendientes', 20, 2)->default(0);
            $table->decimal('mora_pendiente', 20, 2)->default(0);
            $table->decimal('deuda_total', 20, 2)->default(0);

            // Valuación de la prenda
            $table->decimal('valor_avaluo', 20, 2)->default(0)->comment('Valor de avalúo original de la prenda');
            $table->decimal('precio_remate', 20, 2)->nullable()->comment('Precio de venta en remate (si se vendió)');

            // Fechas
            $table->date('fecha_vencimiento_credito')->nullable();
            $table->integer('dias_vencido')->default(0);
            $table->timestamp('fecha_remate')->useCurrent();
            $table->timestamp('fecha_venta_remate')->nullable();

            $table->text('motivo')->nullable();
            $table->text('observaciones')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('credito_id')->references('id')->on('creditos_prendarios')->onDelete('restrict');
            $table->foreign('prenda_id')->references('id')->on('prendas')->onDelete('restrict');
            $table->foreign('sucursal_id')->references('id')->on('sucursales')->onDelete('restrict');
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('restrict');

            $table->index(['estado', 'tipo']);
            $table->index('credito_id');
            $table->index('prenda_id');
            $table->index('sucursal_id');
        });

        // 2. Agregar 'rematado' al enum de creditos_prendarios.estado
        DB::statement("ALTER TABLE creditos_prendarios MODIFY COLUMN estado ENUM('solicitado','en_analisis','aprobado','vigente','pagado','vencido','en_mora','incobrable','recuperado','vendido','rechazado','cancelado','rematado') DEFAULT 'solicitado'");

        // 3. Agregar 'remate' al enum de credito_movimientos.tipo_movimiento
        DB::statement("ALTER TABLE credito_movimientos MODIFY COLUMN tipo_movimiento ENUM('desembolso','pago','pago_parcial','pago_total','pago_adelantado','pago_interes','renovacion','cargo_mora','cargo_administracion','ajuste','reversion','condonacion','remate') NOT NULL");

        // 4. Agregar campos de remate a prendas
        if (!Schema::hasColumn('prendas', 'precio_remate')) {
            Schema::table('prendas', function (Blueprint $table) {
                $table->decimal('precio_remate', 20, 2)->nullable()->after('precio_venta')->comment('Precio de venta en remate');
                $table->timestamp('fecha_remate')->nullable()->after('precio_remate');
            });
        }
    }

    public function down(): void
    {
        // Revertir campos de prendas
        Schema::table('prendas', function (Blueprint $table) {
            if (Schema::hasColumn('prendas', 'precio_remate')) {
                $table->dropColumn(['precio_remate', 'fecha_remate']);
            }
        });

        // Revertir enum de credito_movimientos
        DB::statement("ALTER TABLE credito_movimientos MODIFY COLUMN tipo_movimiento ENUM('desembolso','pago','pago_parcial','pago_total','pago_adelantado','pago_interes','renovacion','cargo_mora','cargo_administracion','ajuste','reversion','condonacion') NOT NULL");

        // Revertir enum de creditos_prendarios
        DB::statement("ALTER TABLE creditos_prendarios MODIFY COLUMN estado ENUM('solicitado','en_analisis','aprobado','vigente','pagado','vencido','en_mora','incobrable','recuperado','vendido','rechazado','cancelado') DEFAULT 'solicitado'");

        Schema::dropIfExists('remates');
    }
};
