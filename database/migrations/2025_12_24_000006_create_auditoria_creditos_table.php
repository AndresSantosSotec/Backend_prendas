<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla de auditoría de créditos
     * Registra todos los cambios y acciones realizadas sobre los créditos
     */
    public function up(): void
    {
        Schema::create('auditoria_creditos', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('credito_prendario_id')->constrained('creditos_prendarios')->onDelete('cascade')->comment('Crédito relacionado');
            $table->foreignId('usuario_id')->constrained('users')->comment('Usuario que realizó la acción');

            // Información de la acción
            $table->enum('accion', [
                'creado',
                'modificado',
                'aprobado',
                'rechazado',
                'desembolsado',
                'pagado',
                'renovado',
                'rescate',
                'anulado',
                'movimiento_anulado',
                'estado_cambiado',
            ])->comment('Tipo de acción realizada');

            // Detalles del cambio
            $table->string('campo_modificado', 100)->nullable()->comment('Campo que fue modificado');
            $table->text('valor_anterior')->nullable()->comment('Valor anterior del campo');
            $table->text('valor_nuevo')->nullable()->comment('Valor nuevo del campo');

            // Información adicional
            $table->text('observaciones')->nullable()->comment('Observaciones adicionales');
            $table->string('ip_address', 45)->nullable()->comment('Dirección IP desde donde se realizó la acción');
            $table->text('user_agent')->nullable()->comment('User Agent del navegador');

            // Timestamps
            $table->timestamp('created_at')->useCurrent();

            // Índices
            $table->index('credito_prendario_id');
            $table->index('usuario_id');
            $table->index('accion');
            $table->index('created_at');
            $table->index(['credito_prendario_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditoria_creditos');
    }
};
