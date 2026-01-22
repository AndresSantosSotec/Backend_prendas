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
        Schema::create('caja_apertura_cierres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('sucursal_id')->constrained('sucursales'); // Assuming table is 'sucursales'
            $table->date('fecha_apertura');
            $table->time('hora_apertura');
            $table->decimal('saldo_inicial', 12, 2);
            $table->decimal('saldo_final', 12, 2)->nullable();
            $table->dateTime('fecha_cierre')->nullable();
            $table->decimal('diferencia', 12, 2)->nullable(); // + Sobrante, - Faltante
            $table->string('resultado_arqueo')->nullable(); // Texto descriptivo
            $table->json('detalles_arqueo')->nullable(); // Conteo de billetes/monedas al cierre
            $table->enum('estado', ['abierta', 'cerrada'])->default('abierta');
            $table->timestamps();

            // Evitar doble apertura por usuario el mismo día
            $table->unique(['user_id', 'fecha_apertura']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caja_apertura_cierres');
    }
};
