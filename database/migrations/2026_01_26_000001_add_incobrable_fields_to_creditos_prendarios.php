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
        Schema::table('creditos_prendarios', function (Blueprint $table) {
            $table->date('fecha_incobrable')->nullable()->after('fecha_ultimo_pago')->comment('Fecha en que se marcó como incobrable');
            $table->text('motivo_incobrable')->nullable()->after('fecha_incobrable')->comment('Motivo por el cual se marcó como incobrable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('creditos_prendarios', function (Blueprint $table) {
            $table->dropColumn(['fecha_incobrable', 'motivo_incobrable']);
        });
    }
};
