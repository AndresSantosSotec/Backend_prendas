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
        Schema::create('cli_complementos_pep', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cliente')->constrained('clientes')->onDelete('cascade')->comment('Referencia al cliente');
            $table->string('tipo')->comment('Tipo de parentesco con la PEP, si aplica');

            $table->string('parentesco')->comment('Parentesco con la PEP, si aplica');
            $table->string('motivoAsociacion')->comment('Motivo de la asociación con la PEP, si aplica');
            $table->string('detalleOtro')->comment('Detalle de otro parentesco o motivo, si aplica');

            $table->string('primerNombre');
            $table->string('segundoNombre')->nullable();
            $table->string('otrosNombres')->nullable();
            $table->string('primerApellido');
            $table->string('segundoApellido')->nullable();
            $table->string('apellidoCasada')->nullable();            
            $table->enum('genero', ['masculino', 'femenino']);
            $table->enum('condicion', ['nacional', 'extranjero']);
            $table->string('entidad'); 
            $table->string('puesto');
            $table->string('pais')->default('Guatemala')->comment('País de la entidad');
            $table->enum('estado', ['activo', 'inactivo'])->default('activo')->nullable();

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
        Schema::dropIfExists('cli_complementos_pep');
    }
};
