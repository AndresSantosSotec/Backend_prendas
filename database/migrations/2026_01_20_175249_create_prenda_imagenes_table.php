<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla normalizada para almacenar múltiples imágenes por prenda.
     * Permite reutilización, segmentación y consultas eficientes.
     */
    public function up(): void
    {
        Schema::create('prenda_imagenes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prenda_id')->comment('FK a la prenda');

            // Datos de la imagen
            $table->string('nombre_archivo', 255)->comment('Nombre original del archivo');
            $table->string('ruta_almacenamiento', 500)->comment('Ruta completa en storage');
            $table->string('url_publica', 500)->nullable()->comment('URL pública accesible');
            $table->string('mime_type', 100)->default('image/jpeg')->comment('Tipo MIME');
            $table->unsignedInteger('tamano_bytes')->nullable()->comment('Tamaño en bytes');
            $table->unsignedSmallInteger('ancho')->nullable()->comment('Ancho en píxeles');
            $table->unsignedSmallInteger('alto')->nullable()->comment('Alto en píxeles');

            // Clasificación y metadatos
            $table->string('tipo_imagen', 50)->default('general')->comment('Tipo: principal, frontal, trasera, detalle, defecto, general');
            $table->string('etiqueta', 100)->nullable()->comment('Etiqueta descriptiva');
            $table->text('descripcion')->nullable()->comment('Descripción de la imagen');
            $table->boolean('es_principal')->default(false)->comment('Si es la imagen principal');
            $table->integer('orden')->default(0)->comment('Orden de visualización');

            // Hash para detectar duplicados y reutilización
            $table->string('hash_contenido', 64)->nullable()->comment('SHA256 del contenido para detectar duplicados');

            // Thumbnail/miniatura
            $table->string('ruta_thumbnail', 500)->nullable()->comment('Ruta de la miniatura');

            // Auditoría
            $table->unsignedBigInteger('subida_por')->nullable()->comment('Usuario que subió la imagen');
            $table->timestamp('fecha_captura')->nullable()->comment('Fecha de captura de la imagen');
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('prenda_id')
                ->references('id')
                ->on('prendas')
                ->onDelete('cascade');

            $table->foreign('subida_por')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Índices
            $table->index('prenda_id');
            $table->index('tipo_imagen');
            $table->index('es_principal');
            $table->index('hash_contenido'); // Para detectar duplicados rápidamente
            $table->index(['prenda_id', 'es_principal'], 'idx_prenda_principal');
            $table->index(['prenda_id', 'orden'], 'idx_prenda_orden');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prenda_imagenes');
    }
};
