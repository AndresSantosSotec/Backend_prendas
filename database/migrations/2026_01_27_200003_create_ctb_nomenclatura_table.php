<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Plan de cuentas contables (Nomenclatura)
     * Define la estructura contable completa
     */
    public function up(): void
    {
        if (!Schema::hasTable('ctb_nomenclatura')) {
            Schema::create('ctb_nomenclatura', function (Blueprint $table) {
                $table->id();

                // Identificación de la cuenta
                $table->string('codigo_cuenta', 20)->unique()->comment('Código de la cuenta (ej: 1101.01.001)');
                $table->string('nombre_cuenta', 255)->comment('Nombre descriptivo de la cuenta');

                // Clasificación
                $table->enum('tipo', [
                    'activo',
                    'pasivo',
                    'patrimonio',
                    'ingreso',
                    'gasto',
                    'costos',
                    'cuentas_orden'
                ])->comment('Tipo de cuenta contable');

                $table->enum('naturaleza', ['deudora', 'acreedora'])->comment('Naturaleza de la cuenta');
                $table->integer('nivel')->comment('Nivel en la jerarquía (1=mayor, 2=submáyor, 3=detalle)');

                // Jerarquía
                $table->unsignedBigInteger('cuenta_padre_id')->nullable()->comment('Cuenta padre en la jerarquía');

                // Configuración
                $table->boolean('acepta_movimientos')->default(true)->comment('Si puede tener movimientos contables');
                $table->boolean('requiere_auxiliar')->default(false)->comment('Si requiere auxiliar (cliente, proveedor)');

                // Para flujo de efectivo
                $table->enum('categoria_flujo', [
                    'operacion',
                    'inversion',
                    'financiamiento',
                    'ninguno'
                ])->default('ninguno')->comment('Categoría para flujo de efectivo');

                // Estado
                $table->boolean('estado')->default(true)->comment('Si la cuenta está activa');

                $table->timestamps();
                $table->softDeletes();

                // Foreign keys
                $table->foreign('cuenta_padre_id')
                    ->references('id')
                    ->on('ctb_nomenclatura')
                    ->onDelete('restrict');

                // Índices
                $table->index('codigo_cuenta');
                $table->index(['tipo', 'estado']);
                $table->index('cuenta_padre_id');
                $table->index('nivel');
                $table->index('acepta_movimientos');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ctb_nomenclatura');
    }
};
