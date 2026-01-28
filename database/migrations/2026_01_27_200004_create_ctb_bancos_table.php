<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cuentas bancarias de la empresa
     * Vincula cuentas bancarias reales con cuentas contables
     */
    public function up(): void
    {
        if (!Schema::hasTable('ctb_bancos')) {
            Schema::create('ctb_bancos', function (Blueprint $table) {
                $table->id();

                // Relaciones
                $table->foreignId('banco_id')->constrained('tb_bancos')->comment('Banco donde está la cuenta');
                $table->foreignId('cuenta_contable_id')->constrained('ctb_nomenclatura')->comment('Cuenta contable asociada');
                $table->foreignId('sucursal_id')->constrained('sucursales')->comment('Sucursal a la que pertenece');
                $table->foreignId('moneda_id')->constrained('monedas')->comment('Moneda de la cuenta');

                // Información de la cuenta
                $table->string('numero_cuenta', 50)->comment('Número de cuenta bancaria');
                $table->enum('tipo_cuenta', [
                    'corriente',
                    'ahorro',
                    'monetario'
                ])->default('corriente')->comment('Tipo de cuenta bancaria');

                // Control de saldos
                $table->decimal('saldo_inicial', 20, 2)->default(0)->comment('Saldo al abrir la cuenta');
                $table->decimal('saldo_actual', 20, 2)->default(0)->comment('Saldo actual de la cuenta');

                // Configuración
                $table->date('fecha_apertura')->nullable()->comment('Fecha de apertura de la cuenta');
                $table->enum('estado', [
                    'activa',
                    'inactiva',
                    'cerrada'
                ])->default('activa')->comment('Estado de la cuenta');

                $table->boolean('permite_sobregiros')->default(false)->comment('Si permite sobregiros');
                $table->decimal('limite_sobregiro', 20, 2)->default(0)->comment('Límite de sobregiro permitido');

                $table->text('observaciones')->nullable()->comment('Notas adicionales');

                $table->timestamps();
                $table->softDeletes();

                // Índices
                $table->index(['banco_id', 'estado']);
                $table->index('cuenta_contable_id');
                $table->index('sucursal_id');
                $table->index('numero_cuenta');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ctb_bancos');
    }
};
