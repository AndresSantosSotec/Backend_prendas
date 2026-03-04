<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para el sistema de transferencias de prendas entre sucursales.
 *
 * Permite registrar y rastrear el movimiento de prendas entre sucursales,
 * con flujo de aprobación (solicitada → en_transito → recibida/rechazada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencias_prendas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_transferencia', 30)->unique()->comment('Auto: TRF-YYYYMMDD-XXXXXX');

            $table->unsignedBigInteger('prenda_id');
            $table->unsignedBigInteger('credito_id')->nullable()->comment('Crédito asociado si la prenda está empeñada');

            $table->unsignedBigInteger('sucursal_origen_id');
            $table->unsignedBigInteger('sucursal_destino_id');

            $table->unsignedBigInteger('usuario_solicita_id')->comment('Quien solicita la transferencia');
            $table->unsignedBigInteger('usuario_autoriza_id')->nullable()->comment('Quien autoriza/rechaza');
            $table->unsignedBigInteger('usuario_recibe_id')->nullable()->comment('Quien recibe en sucursal destino');

            $table->enum('estado', [
                'solicitada',
                'autorizada',
                'en_transito',
                'recibida',
                'rechazada',
                'cancelada'
            ])->default('solicitada');

            $table->text('motivo')->comment('Razón de la transferencia');
            $table->text('observaciones_autorizacion')->nullable();
            $table->text('observaciones_recepcion')->nullable();
            $table->text('motivo_rechazo')->nullable();

            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->timestamp('fecha_autorizacion')->nullable();
            $table->timestamp('fecha_envio')->nullable();
            $table->timestamp('fecha_recepcion')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('prenda_id')->references('id')->on('prendas')->onDelete('restrict');
            $table->foreign('credito_id')->references('id')->on('creditos_prendarios')->onDelete('set null');
            $table->foreign('sucursal_origen_id')->references('id')->on('sucursales')->onDelete('restrict');
            $table->foreign('sucursal_destino_id')->references('id')->on('sucursales')->onDelete('restrict');
            $table->foreign('usuario_solicita_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('usuario_autoriza_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('usuario_recibe_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['estado']);
            $table->index(['sucursal_origen_id', 'estado']);
            $table->index(['sucursal_destino_id', 'estado']);
            $table->index('prenda_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias_prendas');
    }
};
