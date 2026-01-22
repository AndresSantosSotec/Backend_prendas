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
        Schema::create('movimiento_cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('caja_apertura_cierres');
            $table->enum('tipo', ['incremento', 'decremento', 'ingreso_pago', 'egreso_desembolso']); 
            $table->decimal('monto', 12, 2);
            $table->string('concepto');
            $table->json('detalles_movimiento')->nullable(); // Desglose de billetes/monedas
            $table->enum('estado', ['pendiente', 'aplicado', 'rechazado'])->default('aplicado'); // Por defecto aplicado si no requiere aprobación
            $table->foreignId('user_id')->constrained('users'); // Quien registra
            $table->foreignId('autorizado_por')->nullable()->constrained('users'); // Quien autoriza
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimiento_cajas');
    }
};
