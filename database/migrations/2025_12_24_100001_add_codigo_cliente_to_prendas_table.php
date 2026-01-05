<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega campo codigo_cliente_propietario a prendas
     * para facilitar búsquedas directas por código de cliente
     */
    public function up(): void
    {
        Schema::table('prendas', function (Blueprint $table) {
            // Código del cliente propietario (desnormalizado para consultas rápidas)
            if (!Schema::hasColumn('prendas', 'codigo_cliente_propietario')) {
                $table->string('codigo_cliente_propietario', 30)->nullable()->after('credito_prendario_id')->comment('Código del cliente propietario (desnormalizado)');

                // Índice para búsquedas por código de cliente
                $table->index('codigo_cliente_propietario');
            }
        });

        // Actualizar códigos para prendas existentes
        $this->actualizarCodigosExistentes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prendas', function (Blueprint $table) {
            $table->dropIndex(['codigo_cliente_propietario']);
            $table->dropColumn('codigo_cliente_propietario');
        });
    }

    /**
     * Actualizar códigos de cliente para prendas existentes
     */
    private function actualizarCodigosExistentes(): void
    {
        // Actualizar prendas que tienen crédito
        DB::statement("
            UPDATE prendas p
            INNER JOIN creditos_prendarios cp ON p.credito_prendario_id = cp.id
            INNER JOIN clientes c ON cp.cliente_id = c.id
            SET p.codigo_cliente_propietario = c.codigo_cliente
            WHERE p.codigo_cliente_propietario IS NULL
        ");
    }
};
