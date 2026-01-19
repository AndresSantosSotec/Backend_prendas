<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla de idempotency keys para evitar operaciones duplicadas
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();

            // Hash único del idempotency_key (SHA256)
            $table->string('key_hash', 64)->unique()->comment('Hash SHA256 del idempotency_key');
            
            // Tipo de operación
            $table->enum('operacion', [
                'pago',
                'desembolso',
                'renovacion',
                'rescate',
                'anulacion',
                'reversion'
            ])->comment('Tipo de operación');

            // Relaciones
            $table->foreignId('credito_prendario_id')->nullable()->constrained('creditos_prendarios')->comment('Crédito relacionado');
            $table->foreignId('movimiento_id')->nullable()->constrained('credito_movimientos')->comment('Movimiento generado');

            // Resultado de la operación (JSON)
            $table->json('resultado')->nullable()->comment('Resultado de la operación serializado');

            // Timestamps
            $table->timestamp('created_at')->useCurrent();
            
            // Índices
            $table->index('key_hash');
            $table->index('credito_prendario_id');
            $table->index('operacion');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
