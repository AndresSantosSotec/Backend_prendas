<?php

namespace App\Http\Controllers;

use App\Models\CreditoPrendario;
use App\Models\CreditoMovimiento;
use App\Services\PagoService;
use Barryvdh\DomPDF\Facade\Pdf;
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

    /**
     * Generar recibo PDF de un movimiento/pago específico de un crédito prendario.
     * GET /api/v1/creditos-prendarios/{id}/movimientos/{movimientoId}/recibo
     */
    public function generarReciboPago(string $creditoId, string $movimientoId)
    {
        try {
            $credito = CreditoPrendario::with(['cliente', 'prendas', 'sucursal'])->findOrFail($creditoId);

            $movimiento = CreditoMovimiento::with('usuario')
                ->where('credito_prendario_id', $creditoId)
                ->findOrFail($movimientoId);

            $cliente  = $credito->cliente;
            $prendas  = $credito->prendas;
            $sucursal = $credito->sucursal;

            $pdf = Pdf::loadView('creditos.recibo_pago', compact(
                'credito', 'movimiento', 'cliente', 'prendas', 'sucursal'
            ));
            $pdf->setPaper('letter', 'portrait');

            $filename = 'Recibo_Pago_' . ($movimiento->numero_movimiento ?? $movimientoId) . '.pdf';

            return $pdf->stream($filename);
        } catch (\Exception $e) {
            Log::error('Error al generar recibo de pago: ' . $e->getMessage(), [
                'credito_id'     => $creditoId,
                'movimiento_id'  => $movimientoId,
                'trace'          => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el recibo: ' . $e->getMessage(),
            ], 500);
        }
    }
}
