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
        Schema::create('denominaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moneda_id')->constrained('monedas')->onDelete('cascade');
            $table->decimal('valor', 10, 2); // 200, 100, 50, 0.50, 0.25, etc.
            $table->enum('tipo', ['billete', 'moneda']); // billete o moneda
            $table->string('descripcion', 100)->nullable(); // "Billete de 200 Quetzales"
            $table->integer('orden')->default(0); // Para ordenar de mayor a menor
            $table->boolean('activa')->default(true);
            $table->timestamps();

            // Índice único para evitar duplicados
            $table->unique(['moneda_id', 'valor', 'tipo']);
        });

        // Obtener el ID de la moneda GTQ
        $gtqId = DB::table('monedas')->where('codigo', 'GTQ')->value('id');

        if ($gtqId) {
            // Insertar billetes GTQ
            $billetes = [
                ['valor' => 200.00, 'descripcion' => 'Billete de Q200.00', 'orden' => 1],
                ['valor' => 100.00, 'descripcion' => 'Billete de Q100.00', 'orden' => 2],
                ['valor' => 50.00, 'descripcion' => 'Billete de Q50.00', 'orden' => 3],
                ['valor' => 20.00, 'descripcion' => 'Billete de Q20.00', 'orden' => 4],
                ['valor' => 10.00, 'descripcion' => 'Billete de Q10.00', 'orden' => 5],
                ['valor' => 5.00, 'descripcion' => 'Billete de Q5.00', 'orden' => 6],
            ];

            foreach ($billetes as $billete) {
                DB::table('denominaciones')->insert([
                    'moneda_id' => $gtqId,
                    'valor' => $billete['valor'],
                    'tipo' => 'billete',
                    'descripcion' => $billete['descripcion'],
                    'orden' => $billete['orden'],
                    'activa' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insertar monedas GTQ
            $monedas = [
                ['valor' => 1.00, 'descripcion' => 'Moneda de Q1.00', 'orden' => 7],
                ['valor' => 0.50, 'descripcion' => 'Moneda de Q0.50', 'orden' => 8],
                ['valor' => 0.25, 'descripcion' => 'Moneda de Q0.25', 'orden' => 9],
                ['valor' => 0.10, 'descripcion' => 'Moneda de Q0.10', 'orden' => 10],
                ['valor' => 0.05, 'descripcion' => 'Moneda de Q0.05', 'orden' => 11],
                ['valor' => 0.01, 'descripcion' => 'Moneda de Q0.01', 'orden' => 12],
            ];

            foreach ($monedas as $moneda) {
                DB::table('denominaciones')->insert([
                    'moneda_id' => $gtqId,
                    'valor' => $moneda['valor'],
                    'tipo' => 'moneda',
                    'descripcion' => $moneda['descripcion'],
                    'orden' => $moneda['orden'],
                    'activa' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('denominaciones');
    }
};
