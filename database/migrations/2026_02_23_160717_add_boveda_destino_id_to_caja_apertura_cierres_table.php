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
        Schema::table('caja_apertura_cierres', function (Blueprint $table) {
            $table->unsignedBigInteger('boveda_destino_id')->nullable()->after('estado')->comment('Bóveda a la que se transfirió el saldo al cerrar');
            $table->foreign('boveda_destino_id')->references('id')->on('bovedas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('caja_apertura_cierres', function (Blueprint $table) {
            $table->dropForeign(['boveda_destino_id']);
            $table->dropColumn('boveda_destino_id');
        });
    }
};
