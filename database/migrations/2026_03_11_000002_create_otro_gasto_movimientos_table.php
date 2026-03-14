<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otro_gasto_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales');
            $table->foreignId('otro_gasto_tipo_id')->constrained('otro_gasto_tipos');
            $table->foreignId('caja_id')->nullable()->constrained('caja_apertura_cierres')
                  ->comment('Caja abierta del día al momento del registro');
            $table->foreignId('movimiento_caja_id')->nullable()->constrained('movimiento_cajas')
                  ->comment('Movimiento generado en caja, si había una abierta');
            $table->date('fecha');
            $table->enum('tipo', ['ingreso', 'egreso']);
            $table->decimal('monto', 14, 2);
            $table->string('concepto', 255);
            $table->text('descripcion')->nullable();
            $table->string('numero_recibo', 80)->nullable();
            $table->enum('forma_pago', ['efectivo', 'transferencia', 'cheque', 'otro'])->default('efectivo');
            $table->enum('estado', ['aplicado', 'anulado'])->default('aplicado');
            $table->string('anulado_motivo', 255)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otro_gasto_movimientos');
    }
};
