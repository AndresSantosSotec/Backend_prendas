<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Prevenir que una prenda se venda dos veces
     * Constraint crítico para evitar doble venta en concurrencia
     */
    public function up(): void
    {
        // Validar que no existan duplicados antes de crear el índice
        $duplicados = DB::table('venta_detalles')
            ->select('prenda_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('prenda_id')
            ->groupBy('prenda_id')
            ->having('total', '>', 1)
            ->get();

        if ($duplicados->count() > 0) {
            echo "\n⚠️  ADVERTENCIA: Se encontraron prendas vendidas múltiples veces:\n";
            foreach ($duplicados as $dup) {
                echo "   - Prenda ID {$dup->prenda_id}: {$dup->total} ventas\n";

                // Obtener detalles de las ventas duplicadas
                $detalles = DB::table('venta_detalles as vd')
                    ->join('ventas as v', 'vd.venta_id', '=', 'v.id')
                    ->where('vd.prenda_id', $dup->prenda_id)
                    ->select('vd.id', 'v.numero_documento', 'v.estado', 'v.created_at')
                    ->get();

                foreach ($detalles as $det) {
                    echo "      Venta: {$det->numero_documento} (Estado: {$det->estado}, {$det->created_at})\n";
                }
            }

            echo "\n🔧 Para continuar, debe resolver estos duplicados manualmente:\n";
            echo "   1. Revisar qué venta es válida\n";
            echo "   2. Marcar las otras como canceladas o eliminarlas\n";
            echo "   3. Ejecutar: DELETE FROM venta_detalles WHERE id IN (ids_a_eliminar)\n\n";

            throw new \Exception("Existen prendas vendidas múltiples veces. Debe limpiar los datos antes de ejecutar esta migración.");
        }

        // Agregar índice único para prenda_id
        Schema::table('venta_detalles', function (Blueprint $table) {
            // Crear índice único que permite múltiples NULL pero solo un valor no-NULL
            // En MySQL, los valores NULL no se cuentan como duplicados en índices únicos
            $table->unique('prenda_id', 'unique_prenda_venta');
        });

        echo "\n✅ Constraint de prenda única creado exitosamente\n";
        echo "   - Una prenda solo puede estar en UNA venta activa\n";
        echo "   - Los valores NULL son permitidos (para productos que no son prendas)\n\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropUnique('unique_prenda_venta');
        });
    }
};
