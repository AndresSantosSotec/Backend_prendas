<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla de catálogo de gastos para créditos prendarios.
     * Los gastos son cargos adicionales que NO generan interés.
     */
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->id('id_gasto');
            $table->string('nombre', 100)->comment('Nombre del gasto');
            $table->enum('tipo', ['FIJO', 'VARIABLE'])->comment('FIJO=monto fijo, VARIABLE=porcentaje del monto otorgado');
            $table->decimal('porcentaje', 5, 2)->nullable()->comment('Solo para tipo VARIABLE (0-100)');
            $table->decimal('monto', 12, 2)->nullable()->comment('Solo para tipo FIJO');
            $table->text('descripcion')->nullable()->comment('Descripción opcional del gasto');
            $table->boolean('activo')->default(true)->comment('Si el gasto está disponible para usar');
            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('tipo');
            $table->index('activo');
            $table->index(['activo', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
