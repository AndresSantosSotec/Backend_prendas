<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla de prendas/artículos empeñados
     * Específica para sistema de empeños
     */
    public function up(): void
    {
        Schema::create('prendas', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('credito_prendario_id')->constrained('creditos_prendarios')->onDelete('cascade')->comment('Crédito al que pertenece la prenda');
            $table->foreignId('categoria_producto_id')->constrained('categoria_productos')->comment('Categoría del artículo');
            $table->foreignId('tasador_id')->nullable()->constrained('users')->comment('Tasador que evaluó');

            // Información de la prenda
            $table->string('codigo_prenda', 50)->unique()->comment('Código único de la prenda');
            $table->string('descripcion', 500)->comment('Descripción detallada del artículo');
            $table->string('marca', 100)->nullable()->comment('Marca del artículo');
            $table->string('modelo', 100)->nullable()->comment('Modelo del artículo');
            $table->string('serie', 100)->nullable()->comment('Número de serie');
            $table->string('color', 50)->nullable()->comment('Color del artículo');
            $table->text('caracteristicas')->nullable()->comment('Características adicionales (JSON)');

            // Valores
            $table->decimal('valor_estimado_cliente', 20, 2)->default(0)->comment('Valor estimado por el cliente');
            $table->decimal('valor_tasacion', 20, 2)->default(0)->comment('Valor de tasación oficial');
            $table->decimal('valor_prestamo', 20, 2)->default(0)->comment('Monto prestado sobre esta prenda');
            $table->decimal('porcentaje_prestamo', 5, 2)->default(70)->comment('% del valor de tasación prestado');
            $table->decimal('valor_venta', 20, 2)->nullable()->comment('Valor de venta si no se recupera');

            // Estado de la prenda
            $table->enum('estado', [
                'en_custodia',      // Guardada en la casa de empeño
                'recuperada',       // Cliente la recuperó al pagar
                'en_venta',         // Puesta a la venta
                'vendida',          // Ya fue vendida
                'perdida',          // Extraviada
                'deteriorada',      // Dañada
                'devuelta'          // Devuelta al cliente
            ])->default('en_custodia')->comment('Estado actual de la prenda');

            // Condición física
            $table->enum('condicion', [
                'excelente',
                'muy_buena',
                'buena',
                'regular',
                'mala'
            ])->default('buena')->comment('Condición física del artículo');

            // Ubicación
            $table->string('ubicacion_fisica', 100)->nullable()->comment('Ubicación en bodega/almacén');
            $table->string('seccion', 50)->nullable()->comment('Sección del almacén');
            $table->string('estante', 50)->nullable()->comment('Estante específico');

            // Imágenes
            $table->json('fotos')->nullable()->comment('URLs de fotos de la prenda');
            $table->string('foto_principal')->nullable()->comment('URL de la foto principal');

            // Fechas importantes
            $table->date('fecha_ingreso')->comment('Fecha en que ingresó la prenda');
            $table->date('fecha_tasacion')->nullable()->comment('Fecha de tasación');
            $table->date('fecha_recuperacion')->nullable()->comment('Fecha en que fue recuperada');
            $table->date('fecha_venta')->nullable()->comment('Fecha de venta');

            // Información de venta
            $table->foreignId('comprador_id')->nullable()->constrained('clientes')->comment('Cliente que compró la prenda');
            $table->decimal('precio_venta', 20, 2)->nullable()->comment('Precio al que se vendió');
            $table->string('factura_venta', 50)->nullable()->comment('Número de factura de venta');

            // Control
            $table->text('observaciones')->nullable()->comment('Observaciones sobre la prenda');
            $table->boolean('requiere_mantenimiento')->default(false)->comment('Si requiere limpieza/reparación');
            $table->text('notas_mantenimiento')->nullable()->comment('Notas sobre mantenimiento');

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('codigo_prenda');
            $table->index('estado');
            $table->index('credito_prendario_id');
            $table->index('categoria_producto_id');
            $table->index(['estado', 'categoria_producto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prendas');
    }
};
