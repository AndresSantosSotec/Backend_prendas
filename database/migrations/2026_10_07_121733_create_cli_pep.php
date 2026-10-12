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
        Schema::create('cli_pep', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cliente')->constrained('clientes')->onDelete('cascade')->comment('Referencia al cliente');
            $table->string('entidad');
            $table->string('puesto');
            $table->string('paisEntidad')->default('Guatemala')->comment('País de la entidad');

            $table->enum('origen', [
                'Bienes muebles e inmuebles por herencia',
                'Bienes muebles e inmuebles',
                'Negocio propio',
                'Servicios profesionales',
                'Préstamos bancarios',
                'Trabajos anteriores',
                'Trabajo actual',
                'Otros'
            ])->comment('Origen de los fondos o bienes');

            $table->timestamps();
            $table->softDeletes();

            $table->index('id_cliente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cli_pep');
    }
};
