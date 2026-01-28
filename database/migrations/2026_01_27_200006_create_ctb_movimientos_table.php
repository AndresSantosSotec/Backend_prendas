<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Movimientos contables (Debe/Haber)
     * Cada asiento tiene uno o más movimientos que deben cuadrar
     */
    public function up(): void
    {
        if (!Schema::hasTable('ctb_movimientos')) {
            Schema::create('ctb_movimientos', function (Blueprint $table) {
                $table->id();

                // Relaciones principales
                $table->foreignId('diario_id')->constrained('ctb_diario')->onDelete('cascade')->comment('Asiento al que pertenece');
                $table->foreignId('cuenta_contable_id')->constrained('ctb_nomenclatura')->comment('Cuenta contable afectada');

                // Montos (debe y haber)
                $table->decimal('debe', 20, 2)->default(0)->comment('Monto en el debe');
                $table->decimal('haber', 20, 2)->default(0)->comment('Monto en el haber');

                // Información adicional
                $table->string('numero_comprobante', 12)->comment('Número del comprobante (denormalizado)');
                $table->text('detalle')->nullable()->comment('Descripción específica del movimiento');

                // Auxiliares opcionales
                $table->foreignId('cliente_id')->nullable()->constrained('clientes')->comment('Cliente auxiliar si aplica');
                $table->unsignedBigInteger('proveedor_id')->nullable()->comment('Proveedor auxiliar si aplica (FK manual)');
                $table->foreignId('banco_ctb_id')->nullable()->constrained('ctb_bancos')->comment('Banco si es movimiento bancario');

                // Para futura implementación de centros de costo
                $table->integer('centro_costo_id')->nullable()->comment('Centro de costo (futuro)');

                $table->timestamp('created_at')->nullable();

                // Índices para optimización
                $table->index('diario_id');
                $table->index(['cuenta_contable_id', 'debe', 'haber']);
                $table->index('numero_comprobante');
                $table->index('created_at');

                // Constraint para asegurar que solo uno tenga valor (debe O haber, no ambos)
                // Se validará a nivel de aplicación
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ctb_movimientos');
    }
};
