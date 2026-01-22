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
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recibo', 50)->unique()->comment('Número único de recibo');
            $table->enum('tipo', ['ingreso', 'egreso'])->comment('Tipo de recibo');
            $table->date('fecha')->comment('Fecha del recibo');
            $table->string('serie', 10)->nullable()->comment('Serie del recibo');

            // Relaciones opcionales
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->comment('Cliente relacionado');
            $table->foreignId('credito_id')->nullable()->constrained('creditos_prendarios')->comment('Crédito relacionado');
            $table->foreignId('caja_id')->nullable()->constrained('caja_apertura_cierres')->comment('Caja donde se registró');

            // Montos
            $table->decimal('monto', 12, 2)->comment('Monto total del recibo');
            $table->json('desglose_denominaciones')->nullable()->comment('Desglose de billetes/monedas');

            // Detalles
            $table->string('concepto', 500)->comment('Concepto o descripción');
            $table->text('observaciones')->nullable()->comment('Observaciones adicionales');

            // Usuario y sucursal
            $table->foreignId('user_id')->constrained('users')->comment('Usuario que registra');
            $table->foreignId('sucursal_id')->constrained('sucursales')->comment('Sucursal');

            // Control
            $table->enum('estado', ['emitido', 'anulado', 'reimpreso'])->default('emitido');
            $table->timestamp('fecha_anulacion')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users');

            // Auditoría
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['tipo', 'fecha']);
            $table->index(['cliente_id', 'fecha']);
            $table->index(['sucursal_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recibos');
    }
};
