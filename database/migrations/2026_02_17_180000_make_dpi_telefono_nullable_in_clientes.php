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
        Schema::table('clientes', function (Blueprint $table) {
            // Hacer el DPI nullable
            $table->string('dpi', 20)->nullable()->change();

            // Hacer el teléfono nullable
            $table->string('telefono', 20)->nullable()->change();
        });

        // Verificar si existe el índice único y eliminarlo
        try {
            DB::statement('ALTER TABLE clientes DROP INDEX clientes_dpi_unique');
        } catch (\Exception $e) {
            // El índice no existe, continuar
        }

        // Crear un índice normal si no existe
        $indexes = DB::select("SHOW INDEX FROM clientes WHERE Key_name = 'clientes_dpi_index'");
        if (empty($indexes)) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->index('dpi');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar el índice normal
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['dpi']);
        });

        // Restaurar el índice único
        Schema::table('clientes', function (Blueprint $table) {
            $table->unique('dpi');
        });

        // Volver a hacer los campos NOT NULL
        Schema::table('clientes', function (Blueprint $table) {
            // Primero actualizar valores NULL a valores por defecto
            DB::statement("UPDATE clientes SET dpi = 'N/A' WHERE dpi IS NULL");
            DB::statement("UPDATE clientes SET telefono = '00000000' WHERE telefono IS NULL");

            $table->string('dpi', 20)->nullable(false)->change();
            $table->string('telefono', 20)->nullable(false)->change();
        });
    }
};
