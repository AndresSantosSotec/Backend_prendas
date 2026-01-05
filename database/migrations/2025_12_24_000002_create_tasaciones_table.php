<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla de tasaciones/avalúos de prendas
     * Permite mantener historial de valuaciones
     */
    public function up(): void
    {
        Schema::create('tasaciones', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('prenda_id')->constrained('prendas')->onDelete('cascade')->comment('Prenda tasada');
            $table->foreignId('tasador_id')->constrained('users')->comment('Usuario tasador');
            $table->foreignId('credito_prendario_id')->constrained('creditos_prendarios')->comment('Crédito relacionado');

            // Información de la tasación
            $table->string('numero_tasacion', 50)->unique()->comment('Número de tasación');
            $table->date('fecha_tasacion')->comment('Fecha de la tasación');

            // Valores
            $table->decimal('valor_mercado', 20, 2)->default(0)->comment('Valor de mercado actual');
            $table->decimal('valor_comercial', 20, 2)->default(0)->comment('Valor comercial');
            $table->decimal('valor_liquidacion', 20, 2)->default(0)->comment('Valor de liquidación rápida');
            $table->decimal('valor_final_asignado', 20, 2)->default(0)->comment('Valor final de tasación');
            $table->decimal('porcentaje_depreciacion', 5, 2)->default(0)->comment('% de depreciación aplicado');

            // Criterios de evaluación
            $table->enum('condicion_fisica', [
                'excelente',
                'muy_buena',
                'buena',
                'regular',
                'mala'
            ])->comment('Condición física evaluada');

            $table->integer('antiguedad_estimada')->nullable()->comment('Antigüedad en años');
            $table->boolean('tiene_accesorios')->default(false)->comment('Si incluye accesorios originales');
            $table->boolean('tiene_documentos')->default(false)->comment('Si tiene documentos/garantía');
            $table->boolean('tiene_caja_original')->default(false)->comment('Si tiene empaque original');
            $table->boolean('funciona_correctamente')->default(true)->comment('Si funciona bien');

            // Detalles de la evaluación
            $table->text('descripcion_detallada')->nullable()->comment('Descripción detallada del tasador');
            $table->text('defectos_encontrados')->nullable()->comment('Defectos o daños encontrados');
            $table->text('caracteristicas_positivas')->nullable()->comment('Características que aumentan valor');
            $table->text('observaciones')->nullable()->comment('Observaciones generales');

            // Metodología
            $table->enum('metodo_tasacion', [
                'comparativo',      // Comparación con mercado
                'costo',            // Basado en costo de reposición
                'ingreso',          // Basado en ingreso que genera
                'mixto'             // Combinación de métodos
            ])->default('comparativo')->comment('Método de tasación usado');

            $table->json('referencias_mercado')->nullable()->comment('Referencias de precios de mercado (JSON)');

            // Validación y aprobación
            $table->enum('estado', [
                'borrador',
                'pendiente_revision',
                'aprobada',
                'rechazada',
                'modificada'
            ])->default('borrador')->comment('Estado de la tasación');

            $table->foreignId('aprobado_por')->nullable()->constrained('users')->comment('Usuario que aprobó');
            $table->date('fecha_aprobacion')->nullable()->comment('Fecha de aprobación');
            $table->text('motivo_rechazo')->nullable()->comment('Motivo de rechazo si aplica');

            // Control de versiones
            $table->boolean('es_retasacion')->default(false)->comment('Si es una re-tasación');
            $table->foreignId('tasacion_anterior_id')->nullable()->constrained('tasaciones')->comment('Tasación anterior si es retasación');
            $table->text('motivo_retasacion')->nullable()->comment('Motivo de la re-tasación');

            // Documentos
            $table->json('documentos_soporte')->nullable()->comment('URLs de documentos de soporte');
            $table->json('fotos_tasacion')->nullable()->comment('URLs de fotos tomadas en tasación');

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index('prenda_id');
            $table->index('tasador_id');
            $table->index('fecha_tasacion');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasaciones');
    }
};
