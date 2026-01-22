<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('monedas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique(); // GTQ, USD, EUR
            $table->string('nombre', 100); // Quetzal guatemalteco
            $table->string('simbolo', 10); // Q, $, €
            $table->decimal('tipo_cambio', 10, 4)->default(1.0000); // Tipo de cambio respecto a moneda base
            $table->boolean('es_moneda_base')->default(false); // GTQ será la moneda base
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // Insertar moneda base (Quetzal)
        DB::table('monedas')->insert([
            'codigo' => 'GTQ',
            'nombre' => 'Quetzal guatemalteco',
            'simbolo' => 'Q',
            'tipo_cambio' => 1.0000,
            'es_moneda_base' => true,
            'activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monedas');
    }
};
