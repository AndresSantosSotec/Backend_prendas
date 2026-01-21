<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega campo para datos adicionales dinámicos según la categoría de la prenda.
     * Ejemplo: para joyería podría contener {quilates: "18K", peso_gramos: 5.5, material: "Oro"}
     */
    public function up(): void
    {
        Schema::table('prendas', function (Blueprint $table) {
            $table->json('datos_adicionales')->nullable()->after('observaciones')
                ->comment('Datos adicionales dinámicos según la categoría del producto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prendas', function (Blueprint $table) {
            $table->dropColumn('datos_adicionales');
        });
    }
};
