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
        Schema::create('apartados', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_apartado', 50)->unique();
            $table->foreignId('prenda_id')->constrained('prendas');

            // Datos del cliente que aparta
            $table->string('cliente_nombre', 200);
            $table->string('cliente_nit', 20)->default('C/F');
            $table->string('cliente_telefono', 20)->nullable();
            $table->string('cliente_email', 100)->nullable();
            $table->string('cliente_dpi', 20)->nullable();

            // Valores
            $table->decimal('precio_total', 20, 2);
            $table->decimal('enganche', 20, 2); // Pago inicial
            $table->decimal('saldo_pendiente', 20, 2);
            $table->decimal('total_abonado', 20, 2)->default(0);

            // Plan de pagos
            $table->integer('numero_cuotas')->default(1);
            $table->decimal('monto_cuota', 20, 2);
            $table->date('fecha_limite'); // Fecha máxima para completar
            $table->date('proxima_fecha_pago')->nullable();

            // Control
            $table->foreignId('vendedor_id')->constrained('users');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('caja_id')->nullable()->constrained('caja_apertura_cierres')->nullOnDelete();

            $table->enum('estado', ['activo', 'completado', 'cancelado', 'vencido'])->default('activo');
            $table->date('fecha_apartado');
            $table->date('fecha_completado')->nullable();
            $table->date('fecha_cancelacion')->nullable();

            $table->text('observaciones')->nullable();
            $table->text('motivo_cancelacion')->nullable();

            // Si se convierte en venta
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
            $table->index('fecha_apartado');
            $table->index('fecha_limite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apartados');
    }
};
