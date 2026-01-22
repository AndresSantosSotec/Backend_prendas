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
        Schema::table('bovedas', function (Blueprint $table) {
            // Campos adicionales para gestión completa
            $table->string('codigo', 20)->unique()->after('id')->comment('Código único de bóveda');
            $table->text('descripcion')->nullable()->after('nombre')->comment('Descripción detallada');
            $table->decimal('saldo_minimo', 15, 2)->default(0)->after('saldo_actual')->comment('Saldo mínimo requerido');
            $table->decimal('saldo_maximo', 15, 2)->nullable()->after('saldo_minimo')->comment('Saldo máximo permitido');
            $table->string('tipo', 20)->default('general')->after('sucursal_id')->comment('Tipo: general, principal, auxiliar');
            $table->boolean('requiere_aprobacion')->default(false)->after('activa')->comment('Si requiere aprobación para movimientos');
            $table->foreignId('responsable_id')->nullable()->after('requiere_aprobacion')->constrained('users')->comment('Usuario responsable de la bóveda');

            // Campos de auditoría
            $table->foreignId('creado_por')->nullable()->after('updated_at')->constrained('users')->comment('Usuario que creó la bóveda');
            $table->timestamp('ultima_apertura')->nullable()->after('creado_por')->comment('Última fecha de apertura');
            $table->timestamp('ultimo_cierre')->nullable()->after('ultima_apertura')->comment('Última fecha de cierre');

            // Índices
            $table->index('codigo');
            $table->index(['sucursal_id', 'activa']);
            $table->index('tipo');
            $table->index('responsable_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bovedas', function (Blueprint $table) {
            $table->dropColumn([
                'codigo',
                'descripcion',
                'saldo_minimo',
                'saldo_maximo',
                'tipo',
                'requiere_aprobacion',
                'responsable_id',
                'creado_por',
                'ultima_apertura',
                'ultimo_cierre'
            ]);
        });
    }
};
