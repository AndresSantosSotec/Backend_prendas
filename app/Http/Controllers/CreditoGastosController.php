<?php

namespace App\Http\Controllers;

use App\Models\CreditoPrendario;
use App\Services\GastosService;
use App\Http\Requests\SyncGastosCreditoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar gastos asociados a un crédito específico
 */
class CreditoGastosController extends Controller
{
    protected GastosService $gastosService;

    public function __construct(GastosService $gastosService)
    {
        $this->gastosService = $gastosService;
    }

    /**
     * Obtener gastos de un crédito con valores calculados
     *
     * GET /api/creditos/{cred_id}/gastos
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "gastos": [...],
     *     "total_gastos": 150.00,
     *     "monto_otorgado": 5000.00
     *   }
     * }
     */
    public function index(int $credId): JsonResponse
    {
        $credito = CreditoPrendario::with('gastos')->findOrFail($credId);
        $resultado = $this->gastosService->obtenerGastosCredito($credito);

        return response()->json([
            'success' => true,
            'data' => [
                'gastos' => $resultado['gastos'],
                'total_gastos' => $resultado['total_gastos'],
                'monto_otorgado' => (float) ($credito->monto_aprobado ?? $credito->monto_solicitado ?? 0),
            ],
        ]);
    }

    /**
     * Sincronizar gastos de un crédito
     *
     * POST /api/creditos/{cred_id}/gastos
     * Body: { "gas_ids": [1, 2, 3] }
     *
     * Comportamiento: SYNC
     * - El array enviado representa el estado final
     * - Los gastos que no estén en la lista se eliminan
     * - Los nuevos se agregan
     * - Los existentes se mantienen
     */
    public function sync(SyncGastosCreditoRequest $request, int $credId): JsonResponse
    {
        $credito = CreditoPrendario::findOrFail($credId);
        $gastoIds = $request->validated()['gas_ids'] ?? [];

        $resultado = $this->gastosService->sincronizarGastos($credito, $gastoIds);

        return response()->json([
            'success' => true,
            'message' => 'Gastos sincronizados exitosamente',
            'data' => [
                'gastos' => $resultado['gastos'],
                'total_gastos' => $resultado['total_gastos'],
                'monto_otorgado' => (float) ($credito->monto_aprobado ?? $credito->monto_solicitado ?? 0),
            ],
        ]);
    }

    /**
     * Eliminar un gasto específico de un crédito
     *
     * DELETE /api/creditos/{cred_id}/gastos/{gas_id}
     */
    public function destroy(int $credId, int $gasId): JsonResponse
    {
        $credito = CreditoPrendario::findOrFail($credId);
        $resultado = $this->gastosService->removerGasto($credito, $gasId);

        return response()->json([
            'success' => true,
            'message' => 'Gasto removido del crédito exitosamente',
            'data' => [
                'gastos' => $resultado['gastos'],
                'total_gastos' => $resultado['total_gastos'],
                'monto_otorgado' => (float) ($credito->monto_aprobado ?? $credito->monto_solicitado ?? 0),
            ],
        ]);
    }

    /**
     * Vista previa de gastos para un monto dado (sin guardar)
     *
     * POST /api/creditos/preview-gastos
     * Body: {
     *   "gas_ids": [1, 2, 3],
     *   "monto_otorgado": 5000
     * }
     *
     * Útil para mostrar cálculos antes de crear el crédito
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'gas_ids' => 'required|array',
            'gas_ids.*' => 'integer|exists:gastos,id_gasto',
            'monto_otorgado' => 'required|numeric|min:0',
        ]);

        $gastos = \App\Models\Gasto::whereIn('id_gasto', $request->gas_ids)
            ->activos()
            ->get();

        $resultado = $this->gastosService->calcularValoresGastos(
            $gastos,
            (float) $request->monto_otorgado
        );

        return response()->json([
            'success' => true,
            'data' => [
                'gastos' => $resultado['gastos'],
                'total_gastos' => $resultado['total_gastos'],
                'monto_otorgado' => (float) $request->monto_otorgado,
            ],
        ]);
    }

    /**
     * Recalcular valores de gastos de un crédito
     *
     * POST /api/creditos/{cred_id}/gastos/recalcular
     *
     * Útil cuando cambia el monto del crédito
     */
    public function recalcular(int $credId): JsonResponse
    {
        $credito = CreditoPrendario::findOrFail($credId);
        $resultado = $this->gastosService->recalcularValoresGastos($credito);

        return response()->json([
            'success' => true,
            'message' => 'Valores de gastos recalculados',
            'data' => [
                'gastos' => $resultado['gastos'],
                'total_gastos' => $resultado['total_gastos'],
                'monto_otorgado' => (float) ($credito->monto_aprobado ?? $credito->monto_solicitado ?? 0),
            ],
        ]);
    }
}
