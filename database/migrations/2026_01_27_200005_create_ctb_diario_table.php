<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Libro Diario - Registro de asientos contables
     * Cada asiento documenta una transacción u operación
     */
    public function up(): void
    {
        if (!Schema::hasTable('ctb_diario')) {
            Schema::create('ctb_diario', function (Blueprint $table) {
                $table->id();

                // Identificación del asiento
                $table->string('numero_comprobante', 12)->unique()->comment('Número único del comprobante');
                $table->foreignId('tipo_poliza_id')->constrained('ctb_tipo_poliza')->comment('Tipo de póliza');
                $table->foreignId('moneda_id')->constrained('monedas')->comment('Moneda del asiento');

                // Origen del asiento (vinculación con módulos)
                $table->enum('tipo_origen', [
                    'credito_prendario',
                    'venta_prenda',
                    'caja',
                    'compra',
                    'gasto',
                    'ajuste',
                    'cierre',
                    'apertura',
                    'otro'
                ])->nullable()->comment('Tipo de operación que genera el asiento');

                $table->foreignId('credito_prendario_id')->nullable()->constrained('creditos_prendarios')->comment('Crédito relacionado');
                $table->foreignId('movimiento_credito_id')->nullable()->constrained('credito_movimientos')->comment('Movimiento de crédito relacionado');
                $table->foreignId('venta_id')->nullable()->constrained('ventas')->comment('Venta relacionada');
                $table->foreignId('caja_id')->nullable()->constrained('caja_apertura_cierres')->comment('Caja relacionada');

                // Información del documento
                $table->string('numero_documento', 50)->comment('Número de documento origen (recibo, factura, etc)');
                $table->text('glosa')->nullable()->comment('Descripción/explicación del asiento');

                // Fechas
                $table->date('fecha_documento')->comment('Fecha del documento origen');
                $table->date('fecha_contabilizacion')->comment('Fecha de registro contable');

                // Auditoría operativa
                $table->foreignId('sucursal_id')->constrained('sucursales')->comment('Sucursal donde se registra');
                $table->foreignId('usuario_id')->constrained('users')->comment('Usuario que registra');

                // Control de estado
                $table->enum('estado', [
                    'borrador',
                    'registrado',
                    'aprobado',
                    'anulado'
                ])->default('registrado')->comment('Estado del asiento');

                $table->boolean('editable')->default(false)->comment('Si se puede editar');

                // Aprobación
                $table->foreignId('aprobado_por')->nullable()->constrained('users')->comment('Usuario que aprobó');
                $table->datetime('fecha_aprobacion')->nullable()->comment('Fecha de aprobación');

                // Anulación
                $table->foreignId('anulado_por')->nullable()->constrained('users')->comment('Usuario que anuló');
                $table->datetime('fecha_anulacion')->nullable()->comment('Fecha de anulación');
                $table->text('motivo_anulacion')->nullable()->comment('Motivo de anulación');

                $table->timestamps();
                $table->softDeletes();

                // Índices para optimizar consultas
                $table->index('numero_comprobante');
                $table->index(['fecha_contabilizacion', 'estado']);
                $table->index(['tipo_origen', 'credito_prendario_id']);
                $table->index(['tipo_poliza_id', 'estado']);
                $table->index(['sucursal_id', 'fecha_contabilizacion']);
                $table->index('estado');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ctb_diario');
    }
};
