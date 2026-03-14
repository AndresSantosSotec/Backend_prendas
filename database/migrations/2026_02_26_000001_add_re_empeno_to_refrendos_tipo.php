<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega el tipo 're_empeno' al enum tipo_refrendo para créditos pagados que se reactivan.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE refrendos MODIFY COLUMN tipo_refrendo ENUM('parcial', 'total', 'con_capital', 're_empeno') DEFAULT 'parcial' COMMENT 'parcial/total/con_capital = refrendo; re_empeno = reactivación de crédito pagado'");
    }

    public function down(): void
    {
        // No revertir si ya hay registros re_empeno
        $hayReEmpeno = DB::table('refrendos')->where('tipo_refrendo', 're_empeno')->exists();
        if (!$hayReEmpeno) {
            DB::statement("ALTER TABLE refrendos MODIFY COLUMN tipo_refrendo ENUM('parcial', 'total', 'con_capital') DEFAULT 'parcial'");
        }
    }
};
