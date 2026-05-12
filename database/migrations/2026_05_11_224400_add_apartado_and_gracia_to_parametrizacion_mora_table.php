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
        Schema::table('parametrizacion_mora', function (Blueprint $table) {
            $table->boolean('apartado_habilitado')->default(true)->after('max_dias_mora');
            $table->integer('dias_gracia')->default(0)->after('apartado_habilitado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parametrizacion_mora', function (Blueprint $table) {
            $table->dropColumn(['apartado_habilitado', 'dias_gracia']);
        });
    }
};
