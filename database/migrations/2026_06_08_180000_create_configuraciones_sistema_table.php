<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('configuraciones_sistema', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 100)->unique()->comment('Clave única del parámetro del sistema');
            $table->text('valor')->nullable()->comment('Valor del parámetro (JSON o string)');
            $table->string('tipo', 30)->default('string')->comment('Tipo de dato: boolean, integer, string, json');
            $table->string('grupo', 60)->default('general')->comment('Grupo/módulo al que pertenece: caja, boveda, contabilidad, etc.');
            $table->string('descripcion', 500)->nullable()->comment('Descripción legible del parámetro');
            $table->boolean('editable_por_usuario')->default(true)->comment('Si el usuario puede modificar este parámetro');
            $table->timestamps();
        });

        // Sembrar parámetros iniciales
        $now = now();

        DB::table('configuraciones_sistema')->insert([
            // --- Integración Caja-Bóveda ---
            [
                'clave'                  => 'cash_vault_integration_enabled',
                'valor'                  => 'false',
                'tipo'                   => 'boolean',
                'grupo'                  => 'caja',
                'descripcion'            => 'Activa el modo integrado entre Caja y Bóveda. Cuando está activo, las aperturas retiran saldo de bóveda y los incrementos/decrementos crean movimientos en bóveda pendientes de aprobación.',
                'editable_por_usuario'   => true,
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuraciones_sistema');
    }
};
