<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de apartados
     * Gestiona ventas con anticipo pendientes de completar
     */
    public function up(): void
    {
        if (!Schema::hasTable('apartados')) {
            Schema::create('apartados', function (Blueprint $table) {
            $table->id();

            // Relación con venta
            $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade');

            // Cliente
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nombre', 200);
            $table->string('cliente_telefono', 20)->nullable();

            // Montos
            $table->decimal('total_apartado', 20, 2)->default(0)->comment('Total de la venta');
            $table->decimal('anticipo', 20, 2)->default(0)->comment('Anticipo pagado');
            $table->decimal('saldo_pendiente', 20, 2)->default(0)->comment('Saldo por pagar');

            // Fechas
            $table->timestamp('fecha_apartado')->useCurrent();
            $table->date('fecha_limite')->comment('Fecha límite para completar el pago');
            $table->timestamp('fecha_completado')->nullable();
            $table->timestamp('fecha_cancelado')->nullable();

            // Estado
            $table->enum('estado', [
                'activo',       // Con anticipo, esperando pago final
                'completado',   // Pagado totalmente
                'cancelado',    // Cancelado, se devuelve anticipo
                'vencido'       // Pasó la fecha límite
            ])->default('activo');

            // Observaciones
            $table->text('observaciones')->nullable();
            $table->text('motivo_cancelacion')->nullable();

            $table->timestamps();

            // Índices
            $table->index('venta_id');
            $table->index('cliente_id');
            $table->index('estado');
            $table->index('fecha_limite');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apartados');
    }
};
