<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla EAV (Entity-Attribute-Value) para almacenar campos dinámicos de prendas.
     * Esto permite consultas SQL sobre los campos adicionales y mantiene integridad referencial.
     */
    public function up(): void
    {
        Schema::create('prenda_datos_adicionales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prenda_id')->comment('FK a la prenda');
            $table->string('campo_nombre', 100)->comment('Nombre del campo (ej: material, quilates)');
            $table->string('campo_valor', 500)->nullable()->comment('Valor del campo');
            $table->string('campo_tipo', 50)->default('text')->comment('Tipo: text, number, select, checkbox, date');
            $table->string('campo_label', 150)->nullable()->comment('Etiqueta legible del campo');
            $table->integer('orden')->default(0)->comment('Orden de visualización');
            $table->timestamps();

            // Foreign key
            $table->foreign('prenda_id')
                ->references('id')
                ->on('prendas')
                ->onDelete('cascade');

            // Índices para búsquedas rápidas
            $table->index('prenda_id');
            $table->index('campo_nombre');

            // Unique: una prenda no puede tener el mismo campo dos veces
            $table->unique(['prenda_id', 'campo_nombre'], 'unique_prenda_campo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prenda_datos_adicionales');
    }
};
