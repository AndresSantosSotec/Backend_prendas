<?php

namespace App\Http\Controllers;

use App\Models\CreditoPrendario;
use App\Services\PagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PagoController extends Controller
{
    protected $pagoService;

    public function __construct(PagoService $pagoService)
    {
        $this->pagoService = $pagoService;
    }

    /**
     * Obtener cálculo proyectado de pago al día de hoy.
     */
    public function calcularPago(Request $request, string $id)
    {
        try {
            $credito = CreditoPrendario::findOrFail($id);

            // Recalcular mora en cuotas vencidas antes de calcular deuda
            app(\App\Services\MoraService::class)->recalcularMoraCredito($credito);
            $credito->refresh();

            $calculo = $this->pagoService->calcularDeudaAlDia($credito);

            return response()->json([
                'success' => true,
                'data' => $calculo
            ]);

        } catch (\Exception $e) {
            Log::error('Error al calcular pago: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular montos de pago',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Ejecutar un pago (Cuota, Renovación, Adelanto, Abono/Parcial, Liquidación).
     */
    public function ejecutarPago(Request $request, string $id)
    {
        $request->validate([
            'tipo' => 'required|in:CUOTA,RENOVACION,ADELANTO,PARCIAL,LIQUIDACION',
            'monto' => 'required|numeric|min:0.01',
            'metodo_pago' => 'nullable|string',
            'referencia' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'idempotency_key' => 'required|string|uuid',
            'periodos' => 'nullable|integer|min:1|max:12', // Para RENOVACION
            'cuotas' => 'nullable|integer|min:1|max:10', // Para ADELANTO
        ]);

        try {
            $data = $request->all();
            $data['credito_id'] = $id;

            $movimiento = $this->pagoService->ejecutarPago($data);

            return response()->json([
                'success' => true,
                'message' => 'Pago procesado exitosamente',
                'data' => $movimiento
            ]);

        } catch (\Exception $e) {
            Log::error('Error al ejecutar pago: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(), // Mensaje seguro para el usuario (validaciones de negocio)
            ], 400);
        }
    }
}
