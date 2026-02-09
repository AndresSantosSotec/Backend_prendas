<?php

namespace App\Services;

use App\Models\Gasto;
use App\Models\CreditoPrendario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para gestión de gastos de créditos prendarios
 *
 * Maneja el cálculo de valores de gastos y la asociación con créditos.
 * Los gastos son cargos adicionales que NO generan interés.
 */
class GastosService
{
    /**
     * Calcular el valor de un gasto para un monto dado
     *
     * @param Gasto $gasto
     * @param float $montoOtorgado
     * @return float
     */
    public function calcularValorGasto(Gasto $gasto, float $montoOtorgado): float
    {
        return $gasto->calcularValor($montoOtorgado);
    }

    /**
     * Calcular valores de múltiples gastos
     *
     * @param Collection|array $gastos
     * @param float $montoOtorgado
     * @return array [
     *   'gastos' => [...],
     *   'total_gastos' => float
     * ]
     */
    public function calcularValoresGastos($gastos, float $montoOtorgado): array
    {
        $gastosCalculados = [];
        $total = 0;

        foreach ($gastos as $gasto) {
            $valor = $this->calcularValorGasto($gasto, $montoOtorgado);
            $gastosCalculados[] = [
                'id_gasto' => $gasto->id_gasto,
                'nombre' => $gasto->nombre,
                'tipo' => $gasto->tipo,
                'porcentaje' => $gasto->porcentaje,
                'monto' => $gasto->monto,
                'valor_calculado' => $valor,
                'descripcion' => $gasto->descripcion,
            ];
            $total += $valor;
        }

        return [
            'gastos' => $gastosCalculados,
            'total_gastos' => round($total, 2),
        ];
    }

    /**
     * Sincronizar gastos de un crédito
     *
     * @param CreditoPrendario $credito
     * @param array $gastoIds Lista de IDs de gastos a asociar
     * @return array Resultado de la sincronización
     */
    public function sincronizarGastos(CreditoPrendario $credito, array $gastoIds): array
    {
        return DB::transaction(function () use ($credito, $gastoIds) {
            $montoOtorgado = (float) ($credito->monto_aprobado ?? $credito->monto_solicitado ?? 0);

            // Preparar datos para sync con valor calculado
            $syncData = [];
            $gastos = Gasto::whereIn('id_gasto', $gastoIds)->activos()->get();

            foreach ($gastos as $gasto) {
                $syncData[$gasto->id_gasto] = [
                    'valor_calculado' => $gasto->calcularValor($montoOtorgado),
                ];
            }

            // Sincronizar (reemplaza todos los gastos existentes)
            $credito->gastos()->sync($syncData);

            // Recargar gastos
            $credito->load('gastos');

            Log::info('Gastos sincronizados para crédito', [
                'credito_id' => $credito->id,
                'gastos_ids' => $gastoIds,
                'cantidad' => count($syncData),
            ]);

            return $this->calcularValoresGastos($credito->gastos, $montoOtorgado);
        });
    }

    /**
     * Agregar un gasto a un crédito (sin duplicar)
     *
     * @param CreditoPrendario $credito
     * @param int $gastoId
     * @return array
     */
    public function agregarGasto(CreditoPrendario $credito, int $gastoId): array
    {
        return DB::transaction(function () use ($credito, $gastoId) {
            $gasto = Gasto::findOrFail($gastoId);
            $montoOtorgado = (float) ($credito->monto_aprobado ?? $credito->monto_solicitado ?? 0);

            // Verificar si ya está asociado
            if (!$credito->gastos()->where('gasto_id', $gastoId)->exists()) {
                $credito->gastos()->attach($gastoId, [
                    'valor_calculado' => $gasto->calcularValor($montoOtorgado),
                ]);
            }

            $credito->load('gastos');
            return $this->calcularValoresGastos($credito->gastos, $montoOtorgado);
        });
    }

    /**
     * Remover un gasto de un crédito
     *
     * @param CreditoPrendario $credito
     * @param int $gastoId
     * @return array
     */
    public function removerGasto(CreditoPrendario $credito, int $gastoId): array
    {
        return DB::transaction(function () use ($credito, $gastoId) {
            $credito->gastos()->detach($gastoId);
            $credito->load('gastos');

            $montoOtorgado = (float) ($credito->monto_aprobado ?? $credito->monto_solicitado ?? 0);
            return $this->calcularValoresGastos($credito->gastos, $montoOtorgado);
        });
    }

    /**
     * Obtener gastos de un crédito con valores calculados
     *
     * @param CreditoPrendario $credito
     * @return array
     */
    public function obtenerGastosCredito(CreditoPrendario $credito): array
    {
        $montoOtorgado = (float) ($credito->monto_aprobado ?? $credito->monto_solicitado ?? 0);
        return $this->calcularValoresGastos($credito->gastos, $montoOtorgado);
    }

    /**
     * Calcular distribución de gastos por cuota (prorrateo)
     *
     * @param float $totalGastos
     * @param int $numeroCuotas
     * @return array Array de gastos por cuota con ajuste en la última
     */
    public function calcularProrrateoPorCuota(float $totalGastos, int $numeroCuotas): array
    {
        if ($numeroCuotas <= 0) {
            return [];
        }

        // Calcular gasto por cuota (redondeado)
        $gastoPorCuota = round($totalGastos / $numeroCuotas, 2);

        // Crear array de gastos por cuota
        $gastosPorCuota = array_fill(0, $numeroCuotas, $gastoPorCuota);

        // Calcular diferencia por redondeo
        $totalDistribuido = $gastoPorCuota * $numeroCuotas;
        $diferencia = round($totalGastos - $totalDistribuido, 2);

        // Ajustar última cuota
        if ($diferencia != 0) {
            $gastosPorCuota[$numeroCuotas - 1] = round($gastoPorCuota + $diferencia, 2);
        }

        return $gastosPorCuota;
    }

    /**
     * Recalcular valores de gastos cuando cambia el monto del crédito
     *
     * @param CreditoPrendario $credito
     * @return array
     */
    public function recalcularValoresGastos(CreditoPrendario $credito): array
    {
        return DB::transaction(function () use ($credito) {
            $montoOtorgado = (float) ($credito->monto_aprobado ?? $credito->monto_solicitado ?? 0);

            foreach ($credito->gastos as $gasto) {
                $nuevoValor = $gasto->calcularValor($montoOtorgado);
                $credito->gastos()->updateExistingPivot($gasto->id_gasto, [
                    'valor_calculado' => $nuevoValor,
                ]);
            }

            $credito->load('gastos');
            return $this->calcularValoresGastos($credito->gastos, $montoOtorgado);
        });
    }

    /**
     * Eliminar un gasto del catálogo
     *
     * Estrategia: Si tiene créditos asociados, hace soft delete.
     * Si no tiene créditos, hace hard delete.
     *
     * @param Gasto $gasto
     * @return array ['deleted' => bool, 'soft_delete' => bool, 'message' => string]
     */
    public function eliminarGasto(Gasto $gasto): array
    {
        return DB::transaction(function () use ($gasto) {
            $tieneCreditos = $gasto->tieneCreditos();

            if ($tieneCreditos) {
                // Soft delete - mantener para histórico
                $gasto->delete();

                Log::info('Gasto eliminado (soft delete) por tener créditos asociados', [
                    'gasto_id' => $gasto->id_gasto,
                    'nombre' => $gasto->nombre,
                ]);

                return [
                    'deleted' => true,
                    'soft_delete' => true,
                    'message' => 'Gasto desactivado (tiene créditos asociados)',
                ];
            }

            // Hard delete - no tiene créditos
            $gasto->forceDelete();

            Log::info('Gasto eliminado permanentemente', [
                'gasto_id' => $gasto->id_gasto,
                'nombre' => $gasto->nombre,
            ]);

            return [
                'deleted' => true,
                'soft_delete' => false,
                'message' => 'Gasto eliminado permanentemente',
            ];
        });
    }
}
