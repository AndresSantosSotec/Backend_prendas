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
     * Agrega campo codigo_cliente (codcli) auto-generado
     * Formato: CLI-YYYYMMDD-XXXXXX (ej: CLI-20260102-000001)
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Código único de cliente auto-generado
            if (!Schema::hasColumn('clientes', 'codigo_cliente')) {
                $table->string('codigo_cliente', 30)->unique()->after('id')->comment('Código único auto-generado del cliente');

                // Índice para búsquedas rápidas
                $table->index('codigo_cliente');
            }
        });

        // Generar códigos para clientes existentes
        $this->generarCodigosExistentes();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['codigo_cliente']);
            $table->dropColumn('codigo_cliente');
        });
    }

    /**
     * Generar códigos para clientes que ya existen
     */
    private function generarCodigosExistentes(): void
    {
        $clientes = DB::table('clientes')->whereNull('codigo_cliente')->get();

        foreach ($clientes as $cliente) {
            $codigo = $this->generarCodigoCliente($cliente->id);
            DB::table('clientes')
                ->where('id', $cliente->id)
                ->update(['codigo_cliente' => $codigo]);
        }
    }

    /**
     * Generar código único de cliente
     * Formato: CLI-YYYYMMDD-XXXXXX
     *
     * @param int $clienteId
     * @return string
     */
    private function generarCodigoCliente(int $clienteId): string
    {
        $fecha = date('Ymd'); // 20260102
        $numero = str_pad($clienteId, 6, '0', STR_PAD_LEFT); // 000001

        return "CLI-{$fecha}-{$numero}";
    }
};
