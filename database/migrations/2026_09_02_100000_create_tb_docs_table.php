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
        Schema::create('tb_docs', function (Blueprint $table) {
            $table->id();
            $table->string('organizacion_code', 50)->default('01')->index();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->onDelete('cascade');
            $table->string('tipo_documento', 50)->index(); // recibo_pago, contrato_credito, plan_pagos, recibo_compra, recibo_venta, balance_general, estado_resultados, etc.
            $table->string('nombre_documento', 150);
            $table->string('plantilla_variante', 100)->default('estandar'); // estandar, cemadec, ticket_80mm, carta_compacto, etc.
            $table->string('vista_pdf', 150)->nullable(); // Nombre de la vista blade, ej: pdf.recibo
            $table->string('logo_url', 500)->nullable(); // Logo personalizado para este documento
            $table->string('titulo_personalizado', 255)->nullable();
            $table->string('subtitulo_personalizado', 255)->nullable();
            $table->text('encabezado_texto')->nullable();
            $table->text('pie_pagina_texto')->nullable();
            $table->string('firmante_1_nombre', 150)->nullable();
            $table->string('firmante_1_titulo', 150)->nullable();
            $table->string('firmante_2_nombre', 150)->nullable();
            $table->string('firmante_2_titulo', 150)->nullable();
            $table->string('perito_contador_nombre', 150)->nullable();
            $table->string('perito_contador_registro', 100)->nullable();
            $table->boolean('mostrar_logo')->default(true);
            $table->boolean('mostrar_firmas')->default(true);
            $table->json('configuracion_extra')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organizacion_code', 'tipo_documento', 'sucursal_id'], 'unique_org_tipo_sucursal_doc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_docs');
    }
};
