<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar soft deletes a venta_detalles
     * Permite auditoría de ítems eliminados de ventas
     */
    public function up(): void
    {
        if (!Schema::hasColumn('venta_detalles', 'deleted_at')) {
            Schema::table('venta_detalles', function (Blueprint $table) {
                $table->softDeletes()->after('observaciones');

                // Índice para performance en consultas con soft deletes
                $table->index(['venta_id', 'deleted_at']);
            });

            echo "\n✅ Soft deletes agregado a venta_detalles\n";
            echo "   - Los ítems eliminados quedan registrados para auditoría\n";
            echo "   - Use \$detalle->delete() en lugar de \$detalle->forceDelete()\n\n";
        } else {
            echo "\n⚠️  Columna deleted_at ya existe en venta_detalles\n\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropIndex(['venta_id', 'deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
