<?php

namespace App\Http\Controllers;

use App\Models\Refrendo;
use App\Models\CreditoPrendario;
use App\Models\MovimientoCaja;
use App\Models\CajaAperturaCierre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Exception;

class RefrendoController extends Controller
{
    /**
     * Listar refrendos de un crédito específico
     *
     * GET /api/v1/creditos-prendarios/{credito_id}/refrendos
     */
    public function index(Request $request, int $creditoId)
    {
        try {
            $credito = CreditoPrendario::findOrFail($creditoId);

            $refrendos = Refrendo::delCredito($creditoId)
                ->with(['usuario', 'sucursal', 'movimientoCaja'])
                ->orderBy('numero_refrendo', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'credito' => [
                        'id' => $credito->id,
                        'numero_credito' => $credito->numero_credito,
                        'refrendos_realizados' => $credito->refrendos_realizados,
                        'refrendos_maximos' => $credito->refrendos_maximos,
                        'permite_refrendo' => $credito->permite_refrendo,
                    ],
                    'refrendos' => $refrendos,
                    'total' => $refrendos->count()
                ],
                'message' => 'Historial de refrendos obtenido correctamente'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial de refrendos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar si un crédito puede ser refrendado
     *
     * POST /api/v1/creditos-prendarios/{credito_id}/refrendos/validar
     */
    public function validar(Request $request, int $creditoId)
    {
        try {
            $credito = CreditoPrendario::with(['prendas', 'prendas.categoria'])
                ->findOrFail($creditoId);

            // Realizar validación completa
            $resultado = $this->validarRefrendo($credito);

            return response()->json([
                'success' => $resultado['valido'],
                'data' => $resultado,
                'message' => $resultado['valido'] ? 'Crédito puede ser refrendado' : $resultado['mensaje']
            ], $resultado['valido'] ? 200 : 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al validar refrendo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular montos necesarios para refrendo
     *
     * POST /api/v1/creditos-prendarios/{credito_id}/refrendos/calcular
     */
    public function calcular(Request $request, int $creditoId)
    {
        try {
            $credito = CreditoPrendario::with(['prendas', 'prendas.categoria'])
                ->findOrFail($creditoId);

            // Validar primero
            $validacion = $this->validarRefrendo($credito);
            if (!$validacion['valido']) {
                return response()->json([
                    'success' => false,
                    'message' => $validacion['mensaje'],
                    'data' => $validacion
                ], 422);
            }

            // Obtener tipo de refrendo solicitado
            $tipoRefrendo = $request->input('tipo_refrendo', 'parcial');
            $abonoCapital = (float) $request->input('abono_capital', 0);

            // Calcular montos
            $calculo = $this->calcularMontosRefrendo($credito, $tipoRefrendo, $abonoCapital);

            return response()->json([
                'success' => true,
                'data' => $calculo,
                'message' => 'Cálculo realizado correctamente'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular refrendo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Procesar un refrendo
     *
     * POST /api/v1/creditos-prendarios/{credito_id}/refrendos
     */
    public function store(Request $request, int $creditoId)
    {
        // Validar request
        $validator = Validator::make($request->all(), [
            'tipo_refrendo' => 'required|in:parcial,total,con_capital',
            'monto_pagado' => 'required|numeric|min:0',
            'abono_capital' => 'nullable|numeric|min:0',
            'metodo_pago' => 'required|string',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de refrendo inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Obtener crédito con relaciones
            $credito = CreditoPrendario::with(['prendas', 'prendas.categoria', 'cliente', 'sucursal'])
                ->findOrFail($creditoId);

            $usuario = $request->user();
            $sucursalId = $usuario->sucursal_id ?? $credito->sucursal_id;

            // Validar que exista caja abierta
            $cajaAbierta = CajaAperturaCierre::where('sucursal_id', $sucursalId)
                ->where('cajero_id', $usuario->id)
                ->where('estado', 'abierta')
                ->first();

            if (!$cajaAbierta) {
                throw new Exception('No hay caja abierta. Debe abrir la caja antes de procesar refrendos.');
            }

            // Validar refrendo
            $validacion = $this->validarRefrendo($credito);
            if (!$validacion['valido']) {
                throw new Exception($validacion['mensaje']);
            }

            // Obtener datos del request
            $tipoRefrendo = $request->input('tipo_refrendo');
            $montoPagado = (float) $request->input('monto_pagado');
            $abonoCapital = (float) $request->input('abono_capital', 0);
            $metodoPago = $request->input('metodo_pago');
            $observaciones = $request->input('observaciones');

            // Calcular montos necesarios
            $calculo = $this->calcularMontosRefrendo($credito, $tipoRefrendo, $abonoCapital);

            // Validar monto pagado
            if ($montoPagado < $calculo['monto_minimo']) {
                throw new Exception(
                    "Monto insuficiente. Mínimo requerido: Q" . number_format($calculo['monto_minimo'], 2)
                );
            }

            // Procesar pago: actualizar crédito
            $fechaVencimientoAnterior = $credito->fecha_vencimiento;
            $fechaVencimientoNueva = Carbon::parse($credito->fecha_vencimiento)
                ->addDays($credito->plazo_dias);

            // Actualizar intereses y mora pagados
            $credito->interes_pagado += $calculo['interes_a_pagar'];
            $credito->mora_pagada += $calculo['mora_a_pagar'];

            // Si hay abono a capital
            if ($abonoCapital > 0) {
                $credito->capital_pagado += $abonoCapital;
                $credito->capital_pendiente -= $abonoCapital;
            }

            // Resetear intereses y mora generados (nuevo período)
$credito->interes_generado = 0;
            $credito->mora_generada = 0;
            $credito->dias_mora = 0;

            // Actualizar fecha de vencimiento
            $credito->fecha_vencimiento = $fechaVencimientoNueva;
            $credito->fecha_ultimo_pago = now();

            // Incrementar contador de refrendos
            $credito->refrendos_realizados += 1;
            $credito->fecha_ultimo_refrendo = now();

            $credito->save();

            // Crear registro de refrendo
            $refrendo = Refrendo::create([
                'credito_id' => $credito->id,
                'tipo_refrendo' => $tipoRefrendo,
                'monto_interes_adeudado' => $calculo['interes_adeudado'],
                'monto_mora_adeudado' => $calculo['mora_adeudada'],
                'monto_capital_pagado' => $abonoCapital,
                'monto_total_pagado' => $montoPagado,
                'fecha_refrendo' => now(),
                'fecha_vencimiento_anterior' => $fechaVencimientoAnterior,
                'fecha_vencimiento_nueva' => $fechaVencimientoNueva,
                'dias_extendidos' => $credito->plazo_dias,
                'tasa_interes_aplicada' => $credito->tasa_interes,
                'plazo_dias_nuevo' => $credito->plazo_dias,
                'promocion_aplicada' => $calculo['promocion_aplicada'] ?? null,
                'descuento_aplicado' => $calculo['descuento_aplicado'] ?? 0,
                'usuario_id' => $usuario->id,
                'sucursal_id' => $sucursalId,
                'observaciones' => $observaciones,
            ]);

            // Registrar movimiento en caja
            $movimientoCaja = MovimientoCaja::create([
                'caja_apertura_cierre_id' => $cajaAbierta->id,
                'tipo_movimiento' => 'ingreso',
                'concepto' => 'refrendo',
                'descripcion' => "Refrendo #{$refrendo->numero_refrendo} del crédito {$credito->numero_credito}",
                'monto' => $montoPagado,
                'saldo_anterior' => $cajaAbierta->saldo_actual,
                'saldo_nuevo' => $cajaAbierta->saldo_actual + $montoPagado,
                'metodo_pago' => $metodoPago,
                'referencia' => $credito->numero_credito,
                'credito_prendario_id' => $credito->id,
                'usuario_id' => $usuario->id,
                'sucursal_id' => $sucursalId,
            ]);

            // Actualizar saldo de caja
            $cajaAbierta->saldo_actual += $montoPagado;
            $cajaAbierta->total_ingresos += $montoPagado;
            $cajaAbierta->save();

            // Asociar movimiento de caja al refrendo
            $refrendo->caja_movimiento_id = $movimientoCaja->id;
            $refrendo->save();

            DB::commit();

            // Cargar relaciones para respuesta
            $refrendo->load(['usuario', 'sucursal', 'credito.cliente']);

            return response()->json([
                'success' => true,
                'data' => [
                    'refrendo' => $refrendo,
                    'credito' => $credito,
                    'movimiento_caja' => $movimientoCaja
                ],
                'message' => 'Refrendo procesado correctamente'
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar refrendo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * MÉTODOS PRIVADOS DE LÓGICA DE NEGOCIO
     */

    /**
     * Validar si un crédito puede ser refrendado
     */
    private function validarRefrendo(CreditoPrendario $credito): array
    {
        // R1: Solo créditos vigentes o vencidos pueden refrendar
        if (!in_array($credito->estado, ['vigente', 'vencido', 'en_mora'])) {
            return [
                'valido' => false,
                'mensaje' => "Solo créditos vigentes o vencidos pueden ser refrendados. Estado actual: {$credito->estado}",
                'codigo' => 'ESTADO_INVALIDO'
            ];
        }

        // R2: Verificar que el crédito permita refrendos
        if (!$credito->permite_refrendo) {
            return [
                'valido' => false,
                'mensaje' => 'Este crédito no permite refrendos',
                'codigo' => 'REFRENDOS_DESHABILITADOS'
            ];
        }

        // R3: Verificar límite de refrendos
        $limiteRefrendos = $credito->refrendos_maximos;

        // Si no hay límite en el crédito, buscar en categoría
        if ($limiteRefrendos === null) {
            $categoria = $credito->prendas->first()?->categoria;
            if ($categoria) {
                $limiteRefrendos = $categoria->refrendos_maximos_default;
            }
        }

        // Si hay límite, validar
        if ($limiteRefrendos !== null && $credito->refrendos_realizados >= $limiteRefrendos) {
            return [
                'valido' => false,
                'mensaje' => "Límite de refrendos alcanzado ({$limiteRefrendos} máximo). El cliente debe liquidar el crédito.",
                'codigo' => 'LIMITE_ALCANZADO',
                'refrendos_realizados' => $credito->refrendos_realizados,
                'refrendos_maximos' => $limiteRefrendos
            ];
        }

        // R4: Validar que el capital pendiente sea mayor a 0
        if ($credito->capital_pendiente <= 0) {
            return [
                'valido' => false,
                'mensaje' => 'El crédito no tiene capital pendiente para refrendar',
                'codigo' => 'SIN_CAPITAL_PENDIENTE'
            ];
        }

        // Todo OK
        return [
            'valido' => true,
            'mensaje' => 'Crédito válido para refrendo',
            'refrendos_disponibles' => $limiteRefrendos === null ? 'ilimitado' : ($limiteRefrendos - $credito->refrendos_realizados)
        ];
    }

    /**
     * Calcular montos necesarios para el refrendo
     */
    private function calcularMontosRefrendo(CreditoPrendario $credito, string $tipoRefrendo, float $abonoCapital = 0): array
    {
        // Obtener montos adeudados
        $interesAdeudado = (float) $credito->interes_generado - (float) $credito->interes_pagado;
        $moraAdeudada = (float) $credito->mora_generada - (float) $credito->mora_pagada;

        // Inicializar variables
        $capitalMinimo = 0;
        $requiereCapital = false;
        $promocionAplicada = null;
        $descuentoAplicado = 0;

        // Verificar si la categoría requiere capital obligatorio
        $categoria = $credito->prendas->first()?->categoria;
        if ($categoria && $categoria->requiere_pago_capital_refrendo) {
            $requiereCapital = true;
            $porcentaje = (float) $categoria->porcentaje_capital_minimo;
            $capitalMinimo = ($credito->capital_pendiente * $porcentaje) / 100;
        }

        // Si el tipo es 'con_capital' y hay capital mínimo, validar
        if ($tipoRefrendo === 'con_capital' && $requiereCapital) {
            if ($abonoCapital < $capitalMinimo) {
                throw new Exception(
                    "Esta categoría requiere un pago mínimo de " .
                    number_format($categoria->porcentaje_capital_minimo, 2) .
                    "% del capital (Q" . number_format($capitalMinimo, 2) . ")"
                );
            }
        }

        // TODO: Aplicar promociones automáticas (Fase 1)
        // Por ahora, sin promociones

        // Calcular monto mínimo según tipo de refrendo
        $montoMinimo = $interesAdeudado + $moraAdeudada - $descuentoAplicado;

        if ($tipoRefrendo === 'total' || $tipoRefrendo === 'con_capital') {
            $montoMinimo += $abonoCapital;
        }

        return [
            'interes_adeudado' => $interesAdeudado,
            'mora_adeudada' => $moraAdeudada,
            'interes_a_pagar' => $interesAdeudado,
            'mora_a_pagar' => $moraAdeudada,
            'capital_minimo_requerido' => $capitalMinimo,
            'requiere_capital' => $requiereCapital,
            'abono_capital' => $abonoCapital,
            'monto_minimo' => max(0, $montoMinimo),
            'tipo_refrendo' => $tipoRefrendo,
            'promocion_aplicada' => $promocionAplicada,
            'descuento_aplicado' => $descuentoAplicado,
            'nueva_fecha_vencimiento' => Carbon::parse($credito->fecha_vencimiento)
                ->addDays($credito->plazo_dias)
                ->format('Y-m-d'),
            'plazo_dias' => $credito->plazo_dias,
            'tasa_interes' => $credito->tasa_interes,
        ];
    }
}
