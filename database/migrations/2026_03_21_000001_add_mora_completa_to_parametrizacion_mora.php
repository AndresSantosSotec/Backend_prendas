<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametrizacion_mora', function (Blueprint $table) {
            $table->boolean('aplicar_mora_completa')->default(false)
                ->after('dias_tope_mora')
                ->comment('Después de X días calendario, cobrar mora todos los días incluyendo no laborales');
            $table->integer('dias_para_mora_completa')->default(27)
                ->after('aplicar_mora_completa')
                ->comment('Cantidad de días calendario desde vencimiento para activar mora completa');
        });
    }

    public function down(): void
    {
        Schema::table('parametrizacion_mora', function (Blueprint $table) {
            $table->dropColumn(['aplicar_mora_completa', 'dias_para_mora_completa']);
        });
    }
};
