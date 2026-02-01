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
        Schema::table('prendas', function (Blueprint $table) {
            $table->foreignId('credito_prendario_id')->nullable()->change();
            $table->enum('tipo_ingreso', ['empeño', 'compra_directa'])->default('empeño')->after('credito_prendario_id');
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->after('tipo_ingreso');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prendas', function (Blueprint $table) {
            $table->foreignId('credito_prendario_id')->nullable(false)->change();
            $table->dropColumn('tipo_ingreso');
            $table->dropForeign(['sucursal_id']);
            $table->dropColumn('sucursal_id');
        });
    }
};
