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

            $esReEmpeno = ($validacion['es_re_empeno'] ?? false) === true;
            $tipoRefrendo = $esReEmpeno ? 're_empeno' : $request->input('tipo_refrendo', 'parcial');
            $abonoCapital = (float) $request->input('abono_capital', 0);
            $montoPrestamoNuevo = $request->has('monto_prestamo_nuevo') ? (float) $request->input('monto_prestamo_nuevo') : null;
            $plazoDiasNuevo = $request->has('plazo_dias_nuevo') ? (int) $request->input('plazo_dias_nuevo') : null;
            $tasaInteresNuevo = $request->filled('tasa_interes_nuevo') ? (float) $request->input('tasa_interes_nuevo') : null;
            $tipoInteresNuevo = $request->filled('tipo_interes_nuevo') ? (string) $request->input('tipo_interes_nuevo') : null;

            // Calcular montos (o datos de re-empeño)
            $calculo = $this->calcularMontosRefrendo($credito, $tipoRefrendo, $abonoCapital, $montoPrestamoNuevo, $plazoDiasNuevo, $tasaInteresNuevo, $tipoInteresNuevo);

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
        $credito = CreditoPrendario::with(['prendas', 'prendas.categoria', 'cliente', 'sucursal'])
            ->findOrFail($creditoId);

        $esReEmpeno = $credito->estado === 'pagado';

        $rules = [
            'tipo_refrendo' => 'required|in:parcial,total,con_capital' . ($esReEmpeno ? ',re_empeno' : ''),
            'monto_pagado' => ($esReEmpeno ? 'nullable' : 'required') . '|numeric|min:0',
            'abono_capital' => 'nullable|numeric|min:0',
            'metodo_pago' => 'required|string',
            'observaciones' => 'nullable|string|max:1000',
        ];
        if ($esReEmpeno) {
            $rules['monto_prestamo_nuevo'] = 'required|numeric|min:0.01';
            $rules['plazo_dias_nuevo'] = 'required|integer|min:1';
            $rules['tasa_interes_nuevo'] = 'nullable|numeric|min:0';
            $rules['tipo_interes_nuevo'] = 'nullable|string|in:diario,semanal,catorcenal,quincenal,cada_28_dias,mensual';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de refrendo inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $usuario = $request->user();
            $sucursalId = $usuario->sucursal_id ?? $credito->sucursal_id;

            if (!$esReEmpeno) {
                $cajaAbierta = CajaAperturaCierre::where('sucursal_id', $sucursalId)
                    ->where('cajero_id', $usuario->id)
                    ->where('estado', 'abierta')
                    ->first();
                if (!$cajaAbierta) {
                    throw new Exception('No hay caja abierta. Debe abrir la caja antes de procesar refrendos.');
                }
            }

            $validacion = $this->validarRefrendo($credito);
            if (!$validacion['valido']) {
                throw new Exception($validacion['mensaje']);
            }

            $tipoRefrendo = $request->input('tipo_refrendo');
            $montoPagado = (float) $request->input('monto_pagado', 0);
            $abonoCapital = (float) $request->input('abono_capital', 0);
            $metodoPago = $request->input('metodo_pago');
            $observaciones = $request->input('observaciones');
            $montoPrestamoNuevo = $esReEmpeno ? (float) $request->input('monto_prestamo_nuevo') : null;
            $plazoDiasNuevo = $esReEmpeno ? (int) $request->input('plazo_dias_nuevo') : null;
            $tasaInteresNuevo = $esReEmpeno && $request->filled('tasa_interes_nuevo') ? (float) $request->input('tasa_interes_nuevo') : null;
            $tipoInteresNuevo = $esReEmpeno && $request->filled('tipo_interes_nuevo') ? (string) $request->input('tipo_interes_nuevo') : null;

            $calculo = $this->calcularMontosRefrendo($credito, $tipoRefrendo, $abonoCapital, $montoPrestamoNuevo, $plazoDiasNuevo, $tasaInteresNuevo, $tipoInteresNuevo);

            $movimientoCaja = null;
            $fechaVencimientoAnterior = $credito->fecha_vencimiento;
            $fechaVencimientoNueva = null;

            if ($esReEmpeno) {
                // ---------- Re-empeño: reactivar crédito pagado con nuevo monto y plazo ----------
                $montoNuevo = $calculo['monto_prestamo_nuevo'];
                $plazoNuevo = $calculo['plazo_dias_nuevo'];
                $fechaVencimientoNueva = Carbon::now()->addDays($plazoNuevo);

                $tasaNuevo = $request->filled('tasa_interes_nuevo') ? (float) $request->input('tasa_interes_nuevo') : null;
                $tipoIntNuevo = $request->filled('tipo_interes_nuevo') ? (string) $request->input('tipo_interes_nuevo') : null;

                CreditoPrendario::withoutEvents(function () use (
                    $credito, $montoNuevo, $plazoNuevo, $fechaVencimientoNueva, $tasaNuevo, $tipoIntNuevo
                ) {
                    $credito->estado = 'vigente';
                    $credito->capital_pendiente = $montoNuevo;
                    $credito->capital_pagado = 0;
                    $credito->interes_generado = 0;
                    $credito->interes_pagado = 0;
                    $credito->mora_generada = 0;
                    $credito->mora_pagada = 0;
                    $credito->dias_mora = 0;
                    $credito->fecha_vencimiento = $fechaVencimientoNueva;
                    $credito->fecha_ultimo_pago = null;
                    $credito->plazo_dias = $plazoNuevo;
                    $credito->monto_aprobado = $montoNuevo;
                    $credito->monto_desembolsado = $montoNuevo;
                    $credito->fecha_desembolso = now();
                    if ($tasaNuevo !== null) {
                        $credito->tasa_interes = $tasaNuevo;
                    }
                    if ($tipoIntNuevo !== null) {
                        $credito->tipo_interes = $tipoIntNuevo;
                    }
                    $credito->refrendos_realizados = ($credito->refrendos_realizados ?? 0) + 1;
                    $credito->fecha_ultimo_refrendo = now();
                    $credito->save();
                });

                foreach ($credito->prendas as $prenda) {
                    $prenda->update(['estado' => 'en_custodia']);
                }

                $refrendo = Refrendo::create([
                    'credito_id' => $credito->id,
                    'tipo_refrendo' => 're_empeno',
                    'monto_interes_adeudado' => 0,
                    'monto_mora_adeudado' => 0,
                    'monto_capital_pagado' => 0,
                    'monto_total_pagado' => $montoPagado,
                    'fecha_refrendo' => now(),
                    'fecha_vencimiento_anterior' => $fechaVencimientoAnterior ?? now(),
                    'fecha_vencimiento_nueva' => $fechaVencimientoNueva,
                    'dias_extendidos' => $plazoNuevo,
                    'tasa_interes_aplicada' => $credito->tasa_interes,
                    'plazo_dias_nuevo' => $plazoNuevo,
                    'promocion_aplicada' => null,
                    'descuento_aplicado' => 0,
                    'usuario_id' => $usuario->id,
                    'sucursal_id' => $sucursalId,
                    'observaciones' => $observaciones ?: 'Re-empeño (reactivación de crédito pagado)',
                ]);

                if ($montoPagado > 0) {
                    $cajaAbierta = CajaAperturaCierre::where('sucursal_id', $sucursalId)
                        ->where('cajero_id', $usuario->id)
                        ->where('estado', 'abierta')
                        ->first();
                    if ($cajaAbierta) {
                        $movimientoCaja = MovimientoCaja::create([
                            'caja_apertura_cierre_id' => $cajaAbierta->id,
                            'tipo_movimiento' => 'ingreso',
                            'concepto' => 'refrendo',
                            'descripcion' => "Re-empeño #{$refrendo->numero_refrendo} - Crédito {$credito->numero_credito} (cargo por reactivación)",
                            'monto' => $montoPagado,
                            'saldo_anterior' => $cajaAbierta->saldo_actual,
                            'saldo_nuevo' => $cajaAbierta->saldo_actual + $montoPagado,
                            'metodo_pago' => $metodoPago,
                            'referencia' => $credito->numero_credito,
                            'credito_prendario_id' => $credito->id,
                            'usuario_id' => $usuario->id,
                            'sucursal_id' => $sucursalId,
                        ]);
                        $cajaAbierta->saldo_actual += $montoPagado;
                        $cajaAbierta->total_ingresos += $montoPagado;
                        $cajaAbierta->save();
                        $refrendo->caja_movimiento_id = $movimientoCaja->id;
                        $refrendo->save();
                    }
                }
            } else {
                // ---------- Refrendo normal ----------
                if ($montoPagado < $calculo['monto_minimo']) {
                    throw new Exception(
                        "Monto insuficiente. Mínimo requerido: Q" . number_format($calculo['monto_minimo'], 2)
                    );
                }

                $cajaAbierta = CajaAperturaCierre::where('sucursal_id', $sucursalId)
                    ->where('cajero_id', $usuario->id)
                    ->where('estado', 'abierta')
                    ->first();

                $fechaVencimientoNueva = Carbon::parse($credito->fecha_vencimiento)->addDays($credito->plazo_dias);

                $credito->interes_pagado += $calculo['interes_a_pagar'];
                $credito->mora_pagada += $calculo['mora_a_pagar'];
                if ($abonoCapital > 0) {
                    $credito->capital_pagado += $abonoCapital;
                    $credito->capital_pendiente -= $abonoCapital;
                }
                $credito->interes_generado = 0;
                $credito->mora_generada = 0;
                $credito->dias_mora = 0;
                $credito->fecha_vencimiento = $fechaVencimientoNueva;
                $credito->fecha_ultimo_pago = now();
                $credito->refrendos_realizados += 1;
                $credito->fecha_ultimo_refrendo = now();
                $credito->save();

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
                $cajaAbierta->saldo_actual += $montoPagado;
                $cajaAbierta->total_ingresos += $montoPagado;
                $cajaAbierta->save();
                $refrendo->caja_movimiento_id = $movimientoCaja->id;
                $refrendo->save();
            }

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
     * Validar si un crédito puede ser refrendado (vigente/vencido) o re-empeñado (pagado).
     */
    private function validarRefrendo(CreditoPrendario $credito): array
    {
        $esReEmpeno = $credito->estado === 'pagado';

        // R1: Vigente, vencido, en_mora = refrendo normal; pagado = re-empeño (reactivar crédito)
        if (!in_array($credito->estado, ['vigente', 'vencido', 'en_mora', 'pagado'])) {
            return [
                'valido' => false,
                'mensaje' => "Solo créditos vigentes, vencidos o pagados pueden ser refrendados/re-empeñados. Estado actual: {$credito->estado}",
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

        // R3: Verificar límite de refrendos (no aplica estricto para re-empeño: es un nuevo ciclo)
        $limiteRefrendos = $credito->refrendos_maximos;
        if ($limiteRefrendos === null) {
            $categoria = $credito->prendas->first()?->categoria;
            if ($categoria) {
                $limiteRefrendos = $categoria->refrendos_maximos_default;
            }
        }
        if (!$esReEmpeno && $limiteRefrendos !== null && $credito->refrendos_realizados >= $limiteRefrendos) {
            return [
                'valido' => false,
                'mensaje' => "Límite de refrendos alcanzado ({$limiteRefrendos} máximo). El cliente debe liquidar el crédito.",
                'codigo' => 'LIMITE_ALCANZADO',
                'refrendos_realizados' => $credito->refrendos_realizados,
                'refrendos_maximos' => $limiteRefrendos
            ];
        }

        // R4: Para refrendo normal, debe haber capital pendiente. Para re-empeño (pagado) no aplica.
        if (!$esReEmpeno && (float) $credito->capital_pendiente <= 0) {
            return [
                'valido' => false,
                'mensaje' => 'El crédito no tiene capital pendiente para refrendar',
                'codigo' => 'SIN_CAPITAL_PENDIENTE'
            ];
        }

        $refrendosDisponibles = $limiteRefrendos === null ? 'ilimitado' : ($limiteRefrendos - $credito->refrendos_realizados);
        $out = [
            'valido' => true,
            'mensaje' => $esReEmpeno ? 'Crédito pagado puede ser re-empeñado (reactivado)' : 'Crédito válido para refrendo',
            'refrendos_disponibles' => $refrendosDisponibles,
            'es_re_empeno' => $esReEmpeno,
        ];
        if ($esReEmpeno) {
            $out['config_anterior'] = [
                'monto_aprobado' => (float) $credito->monto_aprobado,
                'plazo_dias' => (int) $credito->plazo_dias,
                'tasa_interes' => (float) $credito->tasa_interes,
                'tipo_interes' => $credito->tipo_interes ?? 'mensual',
            ];
        }
        return $out;
    }

    /**
     * Calcular montos necesarios para el refrendo, o datos del re-empeño si crédito está pagado.
     */
    private function calcularMontosRefrendo(
        CreditoPrendario $credito,
        string $tipoRefrendo,
        float $abonoCapital = 0,
        ?float $montoPrestamoNuevo = null,
        ?int $plazoDiasNuevo = null,
        ?float $tasaInteresNuevo = null,
        ?string $tipoInteresNuevo = null
    ): array {
        $esReEmpeno = $tipoRefrendo === 're_empeno' || $credito->estado === 'pagado';

        if ($esReEmpeno) {
            // Re-empeño: nuevo préstamo sobre el mismo crédito/prendas. Sin intereses/mora a pagar.
            $monto = $montoPrestamoNuevo ?? (float) $credito->monto_aprobado;
            if ($monto <= 0 && $credito->prendas->isNotEmpty()) {
                $monto = (float) $credito->prendas->sum('valor_prestamo');
            }
            if ($monto <= 0) {
                $monto = (float) $credito->monto_aprobado;
            }
            $plazo = $plazoDiasNuevo ?? (int) $credito->plazo_dias;
            if ($plazo <= 0) {
                $plazo = 30;
            }
            $tasa = $tasaInteresNuevo !== null ? $tasaInteresNuevo : (float) $credito->tasa_interes;
            $tipoInt = $tipoInteresNuevo !== null ? $tipoInteresNuevo : ($credito->tipo_interes ?? 'mensual');
            $nuevaVenc = Carbon::now()->addDays($plazo)->format('Y-m-d');
            return [
                'es_re_empeno' => true,
                'interes_adeudado' => 0,
                'mora_adeudada' => 0,
                'interes_a_pagar' => 0,
                'mora_a_pagar' => 0,
                'capital_minimo_requerido' => 0,
                'requiere_capital' => false,
                'abono_capital' => 0,
                'monto_minimo' => 0,
                'tipo_refrendo' => 're_empeno',
                'promocion_aplicada' => null,
                'descuento_aplicado' => 0,
                'nueva_fecha_vencimiento' => $nuevaVenc,
                'plazo_dias' => $plazo,
                'tasa_interes' => $tasa,
                'tipo_interes_nuevo' => $tipoInt,
                'monto_prestamo_nuevo' => $monto,
                'plazo_dias_nuevo' => $plazo,
            ];
        }

        // Refrendo normal
        $interesAdeudado = (float) $credito->interes_generado - (float) $credito->interes_pagado;
        $moraAdeudada = (float) $credito->mora_generada - (float) $credito->mora_pagada;
        $capitalMinimo = 0;
        $requiereCapital = false;
        $promocionAplicada = null;
        $descuentoAplicado = 0;

        $categoria = $credito->prendas->first()?->categoria;
        if ($categoria && $categoria->requiere_pago_capital_refrendo) {
            $requiereCapital = true;
            $porcentaje = (float) $categoria->porcentaje_capital_minimo;
            $capitalMinimo = ($credito->capital_pendiente * $porcentaje) / 100;
        }

        if ($tipoRefrendo === 'con_capital' && $requiereCapital && $abonoCapital < $capitalMinimo) {
            throw new Exception(
                "Esta categoría requiere un pago mínimo de " .
                number_format($categoria->porcentaje_capital_minimo, 2) .
                "% del capital (Q" . number_format($capitalMinimo, 2) . ")"
            );
        }

        $montoMinimo = $interesAdeudado + $moraAdeudada - $descuentoAplicado;
        if ($tipoRefrendo === 'total' || $tipoRefrendo === 'con_capital') {
            $montoMinimo += $abonoCapital;
        }

        return [
            'es_re_empeno' => false,
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
