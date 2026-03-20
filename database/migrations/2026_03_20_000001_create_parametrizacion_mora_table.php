<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametrizacion_mora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();

            // Días laborales de la casa de empeño (true = laboral, false = no laboral)
            $table->boolean('lunes')->default(true);
            $table->boolean('martes')->default(true);
            $table->boolean('miercoles')->default(true);
            $table->boolean('jueves')->default(true);
            $table->boolean('viernes')->default(true);
            $table->boolean('sabado')->default(true);
            $table->boolean('domingo')->default(false);

            // Límite máximo de días laborales de mora a cobrar (0 = sin límite)
            $table->integer('max_dias_mora')->default(0);

            // Reglas custom: después de X días laborales, dejar de contar mora
            $table->boolean('aplicar_tope_mora')->default(false);
            $table->integer('dias_tope_mora')->default(0)->comment('Después de estos días laborales se deja de contar mora');

            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->unique('sucursal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametrizacion_mora');
    }
};
