<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar columnas a caja_apertura_cierres
        Schema::table('caja_apertura_cierres', function (Blueprint $table) {
            // Bóveda de la cual se extrajo el saldo inicial (modo integrado)
            $table->unsignedBigInteger('boveda_origen_id')->nullable()->after('boveda_destino_id')
                ->comment('Bóveda de donde provino el saldo inicial (solo modo integrado)');

            // ID del movimiento de bóveda que registró el retiro del saldo inicial
            $table->unsignedBigInteger('boveda_movimiento_apertura_id')->nullable()->after('boveda_origen_id')
                ->comment('MovimientoBovedaID del retiro inicial al abrir caja (modo integrado)');

            $table->foreign('boveda_origen_id')->references('id')->on('bovedas')->nullOnDelete();
        });

        // Agregar columnas a movimiento_cajas
        Schema::table('movimiento_cajas', function (Blueprint $table) {
            // Estado extendido para modo integrado: pendiente_boveda, aprobado, rechazado
            $table->string('estado_boveda', 30)->nullable()->after('estado')
                ->comment('Estado del movimiento respecto a bóveda: pendiente_aprobacion, aprobado, rechazado (solo modo integrado)');

            // Enlace al movimiento de bóveda generado
            $table->unsignedBigInteger('boveda_movimiento_id')->nullable()->after('estado_boveda')
                ->comment('MovimientoBovedaID generado para este movimiento de caja (modo integrado)');

            // ID de la bóveda origen/destino relacionada
            $table->unsignedBigInteger('boveda_id')->nullable()->after('boveda_movimiento_id')
                ->comment('Bóveda involucrada en este movimiento (modo integrado)');

            $table->timestamp('fecha_aprobacion_boveda')->nullable()->after('boveda_id')
                ->comment('Fecha y hora en que el movimiento fue aprobado/rechazado en bóveda');

            $table->unsignedBigInteger('aprobado_por_id')->nullable()->after('fecha_aprobacion_boveda')
                ->comment('Usuario que aprobó/rechazó el movimiento en bóveda');

            $table->text('observaciones_boveda')->nullable()->after('aprobado_por_id')
                ->comment('Observaciones del aprobador en bóveda');

            $table->foreign('boveda_id')->references('id')->on('bovedas')->nullOnDelete();
            $table->foreign('aprobado_por_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimiento_cajas', function (Blueprint $table) {
            $table->dropForeign(['boveda_id']);
            $table->dropForeign(['aprobado_por_id']);
            $table->dropColumn([
                'estado_boveda',
                'boveda_movimiento_id',
                'boveda_id',
                'fecha_aprobacion_boveda',
                'aprobado_por_id',
                'observaciones_boveda',
            ]);
        });

        Schema::table('caja_apertura_cierres', function (Blueprint $table) {
            $table->dropForeign(['boveda_origen_id']);
            $table->dropColumn(['boveda_origen_id', 'boveda_movimiento_apertura_id']);
        });
    }
};
