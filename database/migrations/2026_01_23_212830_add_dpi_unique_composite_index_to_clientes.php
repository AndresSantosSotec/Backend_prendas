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
     * Agrega un índice único compuesto para DPI que excluye registros eliminados.
     * Esto mejora el rendimiento y garantiza que no se puedan registrar DPIs duplicados
     * en clientes activos (no eliminados).
     */
    public function up(): void
    {
        // Verificar si ya existe un índice único en DPI
        $indexExists = DB::select("
            SELECT COUNT(*) as count
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE table_schema = DATABASE()
            AND table_name = 'clientes'
            AND index_name = 'clientes_dpi_unique'
        ");

        // Si existe el índice único simple, lo eliminamos para reemplazarlo con uno compuesto
        if ($indexExists[0]->count > 0) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropUnique('clientes_dpi_unique');
            });
        }

        // Crear índice único que considera solo registros no eliminados
        // Esto permite que clientes eliminados puedan tener DPIs que se reutilicen
        DB::statement('CREATE UNIQUE INDEX clientes_dpi_eliminado_unique ON clientes(dpi, eliminado)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar el índice compuesto
        DB::statement('DROP INDEX IF EXISTS clientes_dpi_eliminado_unique ON clientes');

        // Restaurar el índice único simple si es necesario
        Schema::table('clientes', function (Blueprint $table) {
            $table->unique('dpi', 'clientes_dpi_unique');
        });
    }
};
