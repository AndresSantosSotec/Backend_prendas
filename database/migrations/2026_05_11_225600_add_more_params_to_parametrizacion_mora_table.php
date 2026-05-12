<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametrizacion_mora', function (Blueprint $table) {
            if (!Schema::hasColumn('parametrizacion_mora', 'frecuencia_defecto')) {
                $table->enum('frecuencia_defecto', ['semanal', 'quincenal', 'mensual'])->default('mensual')->after('dias_gracia');
            }
            if (!Schema::hasColumn('parametrizacion_mora', 'tasa_interes_defecto')) {
                $table->decimal('tasa_interes_defecto', 8, 2)->default(5.00)->after('frecuencia_defecto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parametrizacion_mora', function (Blueprint $table) {
            $table->dropColumn(['frecuencia_defecto', 'tasa_interes_defecto']);
        });
    }
};
