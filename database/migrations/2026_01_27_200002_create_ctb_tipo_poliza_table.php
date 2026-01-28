<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tipos de pólizas contables
     * Clasifica los asientos por tipo de operación
     */
    public function up(): void
    {
        if (!Schema::hasTable('ctb_tipo_poliza')) {
            Schema::create('ctb_tipo_poliza', function (Blueprint $table) {
                $table->id();

                $table->string('codigo', 5)->unique()->comment('Código de la póliza (PI, PE, PD, PC, PT)');
                $table->string('nombre', 100)->comment('Nombre de la póliza');
                $table->text('descripcion')->nullable()->comment('Descripción detallada');

                $table->boolean('requiere_aprobacion')->default(false)->comment('Si requiere aprobación');
                $table->string('usuario_aprobador_rol', 50)->nullable()->comment('Rol requerido para aprobar');

                $table->boolean('activo')->default(true)->comment('Si está activa');

                $table->timestamps();

                $table->index('activo');
                $table->index('codigo');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ctb_tipo_poliza');
    }
};
