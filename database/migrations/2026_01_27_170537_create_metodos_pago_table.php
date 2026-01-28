<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Catálogo de métodos de pago disponibles
     * Configurable por sucursal
     */
    public function up(): void
    {
        if (!Schema::hasTable('metodos_pago')) {
            Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();

            // Información del método
            $table->string('nombre', 100)->comment('Nombre del método de pago');
            $table->string('codigo', 50)->unique()->comment('Código único');
            $table->text('descripcion')->nullable();

            // Tipo de método
            $table->enum('tipo', [
                'efectivo',
                'tarjeta',
                'transferencia',
                'cheque',
                'digital',
                'otro'
            ])->default('efectivo');

            // Configuración
            $table->boolean('requiere_referencia')->default(false)->comment('Si requiere número de referencia');
            $table->boolean('requiere_autorizacion')->default(false)->comment('Si requiere código de autorización');
            $table->boolean('activo')->default(true);

            // Comisión si aplica
            $table->decimal('comision_porcentaje', 5, 2)->default(0)->comment('Comisión en porcentaje');
            $table->decimal('comision_fija', 20, 2)->default(0)->comment('Comisión fija');

            $table->timestamps();

            // Índices
            $table->index('codigo');
            $table->index('activo');
            });

            // Insertar métodos de pago por defecto
            DB::table('metodos_pago')->insert([
                [
                    'nombre' => 'Efectivo',
                    'codigo' => 'efectivo',
                    'tipo' => 'efectivo',
                    'requiere_referencia' => false,
                    'requiere_autorizacion' => false,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'nombre' => 'Tarjeta de Débito',
                    'codigo' => 'tarjeta_debito',
                    'tipo' => 'tarjeta',
                    'requiere_referencia' => true,
                    'requiere_autorizacion' => true,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'nombre' => 'Tarjeta de Crédito',
                    'codigo' => 'tarjeta_credito',
                    'tipo' => 'tarjeta',
                    'requiere_referencia' => true,
                    'requiere_autorizacion' => true,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'nombre' => 'Transferencia Bancaria',
                    'codigo' => 'transferencia',
                    'tipo' => 'transferencia',
                    'requiere_referencia' => true,
                    'requiere_autorizacion' => false,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                [
                    'nombre' => 'Cheque',
                    'codigo' => 'cheque',
                    'tipo' => 'cheque',
                    'requiere_referencia' => true,
                    'requiere_autorizacion' => false,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metodos_pago');
    }
};
