<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar soft deletes a venta_pagos
     * Permite auditoría de pagos eliminados/revertidos
     */
    public function up(): void
    {
        if (!Schema::hasColumn('venta_pagos', 'deleted_at')) {
            Schema::table('venta_pagos', function (Blueprint $table) {
                $table->softDeletes()->after('observaciones');

                // Índice para performance
                $table->index(['venta_id', 'deleted_at']);
            });

            echo "\n✅ Soft deletes agregado a venta_pagos\n";
            echo "   - Los pagos eliminados quedan registrados para auditoría\n";
            echo "   - Útil para rastrear reversiones de pago\n\n";
        } else {
            echo "\n⚠️  Columna deleted_at ya existe en venta_pagos\n\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venta_pagos', function (Blueprint $table) {
            $table->dropIndex(['venta_id', 'deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
