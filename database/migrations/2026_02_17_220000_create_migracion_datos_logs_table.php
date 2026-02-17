<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migracion_datos_logs', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_lote', 50)->unique()->comment('Código único del lote de migración');
            $table->foreignId('usuario_id')->constrained('users');
            $table->string('tabla_destino', 100)->comment('Tabla objetivo de la migración');
            $table->string('archivo_original', 255)->comment('Nombre del archivo subido');
            $table->string('archivo_ruta', 500)->nullable()->comment('Ruta del archivo almacenado');
            $table->enum('estado', ['pendiente', 'validando', 'validado', 'importando', 'completado', 'completado_parcial', 'error', 'revertido'])->default('pendiente');
            $table->integer('total_filas')->default(0);
            $table->integer('filas_insertadas')->default(0);
            $table->integer('filas_actualizadas')->default(0);
            $table->integer('filas_con_error')->default(0);
            $table->integer('filas_con_advertencia')->default(0);
            $table->json('errores')->nullable()->comment('Detalle de errores por fila');
            $table->json('advertencias')->nullable()->comment('Advertencias no bloqueantes');
            $table->json('resumen')->nullable()->comment('Resumen final de resultados');
            $table->json('mapeo_columnas')->nullable()->comment('Mapeo de columnas origen-destino');
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_fin')->nullable();
            $table->integer('tiempo_ejecucion_ms')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
            $table->index('tabla_destino');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migracion_datos_logs');
    }
};
