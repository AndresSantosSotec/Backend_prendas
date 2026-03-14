<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otro_gasto_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->enum('tipo', ['ingreso', 'egreso'])->default('egreso');
            $table->string('grupo', 100)->nullable();
            $table->string('nomenclatura', 100)->nullable()->comment('Código contable o identificador');
            $table->enum('tipo_linea', ['bien', 'servicio'])->default('servicio');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otro_gasto_tipos');
    }
};
