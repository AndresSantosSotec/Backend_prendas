<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Esta tabla almacena los valores de campos dinámicos específicos
     * de cada compra, según la configuración de su categoría.
     *
     * Nota: Los campos dinámicos están definidos en JSON en la tabla categoria_productos,
     * por lo que no hay FK a una tabla campos_dinamicos.
     */
    public function up(): void
    {
        Schema::create('compra_campos_dinamicos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('compra_id')->constrained('compras')->onDelete('cascade');

            // ID del campo dinámico (no es FK, solo referencia al JSON de categoria_productos)
            $table->unsignedBigInteger('campo_dinamico_id')->default(0);

            // Valor del campo (se usa según el tipo de campo)
            $table->text('valor')->nullable();

            // Snapshot del campo para auditoría
            $table->string('campo_nombre', 100);
            $table->string('campo_tipo', 50); // texto, numero, fecha, booleano, seleccion, texto_largo

            $table->timestamps();

            // Evitar duplicados por nombre de campo
            $table->unique(['compra_id', 'campo_nombre']);
            $table->index('compra_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compra_campos_dinamicos');
    }
};
