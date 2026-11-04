<?php

namespace App\Services;

use App\Models\Boveda;
use App\Models\BovedaMovimiento;
use App\Models\CajaAperturaCierre;
use App\Models\CreditoMovimiento;
use App\Models\CreditoPrendario;
use App\Models\MovimientoCaja;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CajaService
{
    /**
     * Obtener la caja abierta del usuario actual o de la sucursal indicada
     */
    public static function getCajaAbierta(?int $userId = null, ?int $sucursalId = null): ?CajaAperturaCierre
    {
        $userId = $userId ?? Auth::id();

        // 1. Buscar caja abierta del usuario específico (ordenada por la más reciente)
        if ($userId) {
            $caja = CajaAperturaCierre::where('user_id', $userId)
                ->where('estado', 'abierta')
                ->whereNull('fecha_cierre')
                ->orderBy('fecha_apertura', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($caja) {
                return $caja;
            }

            // Fallback usuario sin verificar fecha_cierre por posibles inconsistencias
            $caja = CajaAperturaCierre::where('user_id', $userId)
                ->where('estado', 'abierta')
                ->orderBy('fecha_apertura', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($caja) {
                return $caja;
            }
        }

        // 2. Si no hay caja del usuario, buscar cualquier caja abierta en la sucursal
        $sucursalId = $sucursalId ?? Auth::user()?->sucursal_id ?? request()->get('_sucursal_scope');
        if ($sucursalId) {
            $cajaSucursal = CajaAperturaCierre::where('sucursal_id', $sucursalId)
                ->where('estado', 'abierta')
                ->whereNull('fecha_cierre')
                ->orderBy('fecha_apertura', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($cajaSucursal) {
                return $cajaSucursal;
            }

            $cajaSucursal = CajaAperturaCierre::where('sucursal_id', $sucursalId)
                ->where('estado', 'abierta')
                ->orderBy('fecha_apertura', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($cajaSucursal) {
                return $cajaSucursal;
            }
        }

        // 3. Fallback general: la última caja abierta activa en el sistema
        return CajaAperturaCierre::where('estado', 'abierta')
            ->whereNull('fecha_cierre')
            ->orderBy('fecha_apertura', 'desc')
            ->orderBy('id', 'desc')
            ->first()
            ?? CajaAperturaCierre::where('estado', 'abierta')
                ->orderBy('fecha_apertura', 'desc')
                ->orderBy('id', 'desc')
                ->first();
    }

    /**
     * Verificar si el usuario o la sucursal tiene caja abierta
     */
    public static function tieneCajaAbierta(?int $userId = null, ?int $sucursalId = null): bool
    {
        return self::getCajaAbierta($userId, $sucursalId) !== null;
    }

    /**
     * Caja operativa de la sucursal para compras/ventas.
     *
     * Un vendedor puede registrar la compra sin tener caja propia.
     * El efectivo debe rebajar la caja abierta HOY en esa sucursal
     * (la que el cajero está cuadrando), no una caja vieja del usuario.
     */
    public static function getCajaAbiertaVigente(?int $userId = null, ?int $sucursalId = null): ?CajaAperturaCierre
    {
        $userId = $userId ?? Auth::id();
        $sucursalId = $sucursalId ?? Auth::user()?->sucursal_id ?? request()->get('_sucursal_scope');

        if (!$sucursalId) {
            return null;
        }

        $hoy = now()->toDateString();
        $base = CajaAperturaCierre::query()
            ->where('sucursal_id', $sucursalId)
            ->where('estado', 'abierta')
            ->whereNull('fecha_cierre');

        if ($userId) {
            $cajaUsuarioHoy = (clone $base)
                ->where('user_id', $userId)
                ->whereDate('fecha_apertura', $hoy)
                ->orderBy('fecha_apertura', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($cajaUsuarioHoy) {
                return $cajaUsuarioHoy;
            }
        }

        $cajaSucursalHoy = (clone $base)
            ->whereDate('fecha_apertura', $hoy)
            ->orderBy('fecha_apertura', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($cajaSucursalHoy) {
            return $cajaSucursalHoy;
        }

        return (clone $base)
            ->orderBy('fecha_apertura', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    public static function tieneCajaAbiertaVigente(?int $userId = null, ?int $sucursalId = null): bool
    {
        return self::getCajaAbiertaVigente($userId, $sucursalId) !== null;
    }

    /**
     * Registrar un ingreso a caja (pago de crédito, venta, etc.)
     */
    public static function registrarIngreso(
        float $monto,
        string $concepto,
        ?array $detalles = null,
        ?int $userId = null,
        ?int $sucursalId = null
    ): ?MovimientoCaja {
        $caja = self::getCajaAbierta($userId, $sucursalId);

        if (!$caja) {
            Log::warning("Intento de registrar ingreso sin caja abierta. Usuario: " . ($userId ?? Auth::id()) . " Sucursal: {$sucursalId}");
            return null;
        }

        return MovimientoCaja::create([
            'caja_id' => $caja->id,
            'tipo' => 'ingreso_pago',
            'monto' => abs($monto),
            'concepto' => mb_substr($concepto, 0, 250),
            'detalles_movimiento' => $detalles,
            'estado' => 'aplicado',
            'user_id' => $userId ?? Auth::id() ?? $caja->user_id,
        ]);
    }

    /**
     * Registrar un egreso de caja (desembolso, devolución, compra, etc.)
     */
    public static function registrarEgreso(
        float $monto,
        string $concepto,
        ?array $detalles = null,
        ?int $userId = null,
        ?int $sucursalId = null
    ): ?MovimientoCaja {
        $caja = self::getCajaAbierta($userId, $sucursalId);

        if (!$caja) {
            Log::warning("Intento de registrar egreso sin caja abierta. Usuario: " . ($userId ?? Auth::id()) . " Sucursal: {$sucursalId}");
            return null;
        }

        return MovimientoCaja::create([
            'caja_id' => $caja->id,
            'tipo' => 'egreso_desembolso',
            'monto' => abs($monto),
            'concepto' => mb_substr($concepto, 0, 250),
            'detalles_movimiento' => $detalles,
            'estado' => 'aplicado',
            'user_id' => $userId ?? Auth::id() ?? $caja->user_id,
        ]);
    }

    /**
     * Registrar ingreso por venta de prenda
     */
    public static function registrarVenta(
        float $monto,
        string $codigoVenta,
        string $codigoPrenda,
        string $metodoPago,
        ?string $clienteNombre = null
    ): ?MovimientoCaja {
        $concepto = "Venta #{$codigoVenta} - Prenda: {$codigoPrenda}";
        if ($clienteNombre) {
            $concepto .= " - Cliente: {$clienteNombre}";
        }

        return self::registrarIngreso($monto, $concepto, [
            'tipo_operacion' => 'venta_prenda',
            'codigo_venta' => $codigoVenta,
            'codigo_prenda' => $codigoPrenda,
            'metodo_pago' => $metodoPago,
            'cliente' => $clienteNombre,
            'fecha' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Registrar ingreso por pago de crédito
     */
    public static function registrarPagoCredito(
        float $monto,
        string $numeroCredito,
        string $tipoPago,
        ?string $numeroMovimiento = null,
        ?string $clienteNombre = null
    ): ?MovimientoCaja {
        $tipoLabel = match($tipoPago) {
            'RENOVACION' => 'Renovación',
            'PARCIAL' => 'Pago Parcial',
            'LIQUIDACION' => 'Liquidación',
            'INTERES_ADELANTADO' => 'Interés Adelantado',
            default => $tipoPago
        };

        $concepto = "Pago Crédito #{$numeroCredito} - {$tipoLabel}";
        if ($clienteNombre) {
            $concepto .= " - {$clienteNombre}";
        }

        return self::registrarIngreso($monto, $concepto, [
            'tipo_operacion' => 'pago_credito',
            'numero_credito' => $numeroCredito,
            'tipo_pago' => $tipoPago,
            'numero_movimiento' => $numeroMovimiento,
            'cliente' => $clienteNombre,
            'fecha' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Registrar egreso por desembolso de crédito
     */
    public static function registrarDesembolso(
        float $monto,
        string $numeroCredito,
        ?string $clienteNombre = null,
        ?string $formaDesembolso = 'efectivo'
    ): ?MovimientoCaja {
        // Solo registrar si es efectivo (transferencias no salen de caja física)
        if ($formaDesembolso !== 'efectivo') {
            return null;
        }

        $concepto = "Desembolso Crédito #{$numeroCredito}";
        if ($clienteNombre) {
            $concepto .= " - {$clienteNombre}";
        }

        return self::registrarEgreso($monto, $concepto, [
            'tipo_operacion' => 'desembolso_credito',
            'numero_credito' => $numeroCredito,
            'cliente' => $clienteNombre,
            'forma_desembolso' => $formaDesembolso,
            'fecha' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Obtener resumen de caja actual
     */
    public static function getResumenCaja(?int $userId = null): array
    {
        $caja = self::getCajaAbierta($userId);

        if (!$caja) {
            return [
                'tiene_caja' => false,
                'mensaje' => 'No hay caja abierta'
            ];
        }

        $movimientos = MovimientoCaja::where('caja_id', $caja->id)
            ->where('estado', 'aplicado')
            ->get();

        $ingresos = $movimientos->whereIn('tipo', ['incremento', 'ingreso_pago'])->sum('monto');
        $egresos = $movimientos->whereIn('tipo', ['decremento', 'egreso_desembolso'])->sum('monto');

        $saldoActual = $caja->saldo_inicial + $ingresos - $egresos;

        return [
            'tiene_caja' => true,
            'caja_id' => $caja->id,
            'fecha_apertura' => $caja->fecha_apertura,
            'saldo_inicial' => (float) $caja->saldo_inicial,
            'total_ingresos' => $ingresos,
            'total_egresos' => $egresos,
            'saldo_actual' => $saldoActual,
            'cantidad_movimientos' => $movimientos->count(),
        ];
    }

    /**
     * Revierte en caja el desembolso de un crédito anulado.
     *
     * Un desembolso sale de caja (egreso_desembolso). Anularlo debe regresar ese dinero:
     * - Si la caja original sigue abierta: se anula el movimiento de egreso (deja de restar del saldo).
     * - Si la caja original ya cerró:
     *     * Si hay caja abierta hoy: registra un incremento compensatorio en la caja abierta de hoy.
     *     * Si no hay caja abierta: reingresa el efectivo a la Bóveda activa de la sucursal.
     */
    public static function revertirDesembolsoCredito(
        CreditoPrendario $credito,
        string $motivo = 'Crédito anulado'
    ): array {
        $montoDesembolso = (float) ($credito->monto_desembolsado ?? $credito->monto_aprobado ?? $credito->monto_solicitado ?? 0);
        $sucursalId = (int) ($credito->sucursal_id ?? 1);

        // 1. Buscar el movimiento de desembolso en movimiento_cajas
        $movDesembolso = MovimientoCaja::query()
            ->where('tipo', 'egreso_desembolso')
            ->where('estado', 'aplicado')
            ->where(function ($q) use ($credito) {
                $q->where('detalles_movimiento->numero_credito', $credito->numero_credito)
                  ->orWhere('concepto', 'like', "%{$credito->numero_credito}%");
            })
            ->first();

        if ($movDesembolso) {
            $montoDesembolso = abs((float) $movDesembolso->monto);
            $cajaOriginal = $movDesembolso->caja;
            $cajaAbierta = $cajaOriginal
                && $cajaOriginal->estado === 'abierta'
                && empty($cajaOriginal->fecha_cierre);

            // Caso 1: La caja original sigue abierta -> simplemente anular el movimiento de egreso
            if ($cajaAbierta) {
                $movDesembolso->marcarAnulado([
                    'observaciones_boveda' => "Anulado por anulación de crédito #{$credito->numero_credito}: {$motivo}",
                ]);

                Log::info("Desembolso de crédito anulado en caja original abierta #{$cajaOriginal->id}", [
                    'credito' => $credito->numero_credito,
                    'movimiento_caja_id' => $movDesembolso->id,
                    'monto' => $montoDesembolso,
                ]);

                return [
                    'aplicado' => true,
                    'accion' => 'anulado_original',
                    'movimiento_caja_id' => $movDesembolso->id,
                    'mensaje' => 'Se anuló el egreso por desembolso en la caja abierta. El efectivo volvió a estar disponible.',
                ];
            }

            // Caso 2: La caja original ya estaba cerrada (anulación extemporánea)
            $cajaHoy = self::getCajaAbiertaVigente(Auth::id(), $sucursalId) ?? self::getCajaAbierta(null, $sucursalId);

            if ($cajaHoy) {
                $ingreso = MovimientoCaja::create([
                    'caja_id' => $cajaHoy->id,
                    'tipo' => 'incremento',
                    'monto' => $montoDesembolso,
                    'concepto' => mb_substr("Reintegro por anulación de crédito #{$credito->numero_credito}", 0, 250),
                    'detalles_movimiento' => [
                        'tipo_operacion' => 'anulacion_credito',
                        'numero_credito' => $credito->numero_credito,
                        'movimiento_caja_original_id' => $movDesembolso->id,
                        'caja_original_id' => $cajaOriginal?->id,
                        'motivo' => $motivo,
                    ],
                    'estado' => 'aplicado',
                    'user_id' => Auth::id() ?? $cajaHoy->user_id,
                ]);

                $movDesembolso->marcarAnulado([
                    'observaciones_boveda' => "Anulado extemporáneamente. Compensado con ingreso #{$ingreso->id} en caja #{$cajaHoy->id}",
                ]);

                Log::info("Reintegro registrado en caja abierta de hoy #{$cajaHoy->id} por anulación extemporánea de crédito #{$credito->numero_credito}", [
                    'monto' => $montoDesembolso,
                    'ingreso_id' => $ingreso->id,
                ]);

                return [
                    'aplicado' => true,
                    'accion' => 'ingreso_compensatorio',
                    'movimiento_caja_id' => $ingreso->id,
                    'mensaje' => 'La caja original ya estaba cerrada. Se registró un reintegro de efectivo en la caja abierta de hoy.',
                ];
            }

            // Caso 3: No hay caja abierta hoy -> Reingresar a Bóveda
            $boveda = Boveda::where('sucursal_id', $sucursalId)
                ->where('activa', true)
                ->orderByRaw("FIELD(tipo, 'principal', 'general') DESC")
                ->first();

            if ($boveda) {
                $montoReingreso = abs((float) $movDesembolso->monto);
                $boveda->increment('saldo_actual', $montoReingreso);

                $movBoveda = BovedaMovimiento::create([
                    'boveda_id' => $boveda->id,
                    'usuario_id' => Auth::id() ?? $boveda->responsable_id ?? 1,
                    'sucursal_id' => $sucursalId,
                    'tipo_movimiento' => 'entrada',
                    'monto' => $montoReingreso,
                    'concepto' => mb_substr("Reintegro por anulación de crédito extemporáneo #{$credito->numero_credito}", 0, 250),
                    'referencia' => $credito->numero_credito,
                    'estado' => 'aprobado',
                    'fecha_aprobacion' => now(),
                ]);

                $movDesembolso->marcarAnulado([
                    'observaciones_boveda' => "Anulado extemporáneamente sin caja abierta. Reingresado a bóveda #{$boveda->id} (mov #{$movBoveda->id})",
                ]);

                Log::info("Reintegro a bóveda #{$boveda->id} por crédito extemporáneo anulado sin caja #{$credito->numero_credito}", [
                    'monto' => $montoDesembolso,
                    'boveda_movimiento_id' => $movBoveda->id,
                ]);

                return [
                    'aplicado' => true,
                    'accion' => 'reingreso_boveda',
                    'boveda_movimiento_id' => $movBoveda->id,
                    'mensaje' => 'La caja original ya estaba cerrada y no hay caja abierta hoy. El efectivo se reingresó a la bóveda.',
                ];
            }
        }

        // Si no se encontró el movimiento previo de desembolso pero el crédito era en efectivo y con monto desembolsado
        if ($montoDesembolso > 0 && ($credito->forma_desembolso ?? 'efectivo') === 'efectivo') {
            $cajaHoy = self::getCajaAbiertaVigente(Auth::id(), $sucursalId) ?? self::getCajaAbierta(null, $sucursalId);
            if ($cajaHoy) {
                $ingreso = MovimientoCaja::create([
                    'caja_id' => $cajaHoy->id,
                    'tipo' => 'incremento',
                    'monto' => $montoDesembolso,
                    'concepto' => mb_substr("Reintegro por anulación de crédito #{$credito->numero_credito}", 0, 250),
                    'detalles_movimiento' => [
                        'tipo_operacion' => 'anulacion_credito',
                        'numero_credito' => $credito->numero_credito,
                        'motivo' => $motivo,
                    ],
                    'estado' => 'aplicado',
                    'user_id' => Auth::id() ?? $cajaHoy->user_id,
                ]);

                return [
                    'aplicado' => true,
                    'accion' => 'ingreso_compensatorio',
                    'movimiento_caja_id' => $ingreso->id,
                    'mensaje' => 'Se registró un reintegro de efectivo en la caja abierta de hoy.',
                ];
            }
        }

        return [
            'aplicado' => false,
            'accion' => 'sin_movimiento',
            'movimiento_caja_id' => null,
            'mensaje' => 'No se requirió movimiento de caja para este crédito.',
        ];
    }

    /**
     * Revierte en caja el efectivo de un pago de crédito anulado.
     *
     * Un pago entra a caja (ingreso_pago). Anularlo no "mete" más dinero:
     * quita ese ingreso (o registra un egreso de devolución si la caja original ya cerró).
     * Nunca se busca por monto + número de crédito: eso puede anular el desembolso.
     */
    public static function revertirPagoCredito(
        CreditoMovimiento $movimiento,
        CreditoPrendario $credito,
        string $motivo
    ): array {
        $formaPago = strtolower((string) ($movimiento->forma_pago ?? 'efectivo'));
        $esEfectivo = in_array($formaPago, ['efectivo', 'mixto', ''], true);

        if (!$esEfectivo) {
            return [
                'aplicado' => false,
                'accion' => 'omitido_no_efectivo',
                'movimiento_caja_id' => null,
                'mensaje' => "El pago fue {$formaPago}: no hay efectivo que revertir en caja.",
            ];
        }

        $movCaja = self::encontrarIngresoPagoCredito($movimiento, $credito);

        if (!$movCaja) {
            Log::warning('Anulación de pago sin ingreso de caja asociado', [
                'credito' => $credito->numero_credito,
                'numero_movimiento' => $movimiento->numero_movimiento,
                'monto' => $movimiento->monto_total,
            ]);

            return [
                'aplicado' => false,
                'accion' => 'sin_movimiento',
                'movimiento_caja_id' => null,
                'mensaje' => 'No se encontró el ingreso de caja de este pago. El crédito se revirtió; revise caja manualmente.',
            ];
        }

        $cajaOriginal = $movCaja->caja;
        $cajaAbierta = $cajaOriginal
            && $cajaOriginal->estado === 'abierta'
            && empty($cajaOriginal->fecha_cierre);

        if ($cajaAbierta) {
            $movCaja->marcarAnulado([
                'observaciones_boveda' => 'Anulado por reversión de pago crédito #'
                    . $credito->numero_credito . ': ' . $motivo,
            ]);

            return [
                'aplicado' => true,
                'accion' => 'anulado_original',
                'movimiento_caja_id' => $movCaja->id,
                'mensaje' => 'Se revirtió el ingreso en la caja abierta. El efectivo del pago ya no cuenta en el saldo.',
            ];
        }

        $cajaHoy = self::getCajaAbiertaVigente(Auth::id(), $credito->sucursal_id);
        if (!$cajaHoy) {
            throw new \RuntimeException(
                'No se puede anular este pago en efectivo: la caja original ya cerró y no hay caja abierta hoy para registrar la devolución. Abra caja e intente de nuevo.'
            );
        }

        $egreso = MovimientoCaja::create([
            'caja_id' => $cajaHoy->id,
            'tipo' => 'decremento',
            'monto' => abs((float) $movimiento->monto_total),
            'concepto' => mb_substr(
                "Devolución anulación pago crédito #{$credito->numero_credito} ({$movimiento->numero_movimiento})",
                0,
                250
            ),
            'detalles_movimiento' => [
                'tipo_operacion' => 'anulacion_pago_credito',
                'numero_credito' => $credito->numero_credito,
                'numero_movimiento_original' => $movimiento->numero_movimiento,
                'movimiento_caja_original_id' => $movCaja->id,
                'motivo' => $motivo,
            ],
            'estado' => 'aplicado',
            'user_id' => Auth::id() ?? $cajaHoy->user_id,
        ]);

        $movCaja->observaciones_boveda = trim(
            ($movCaja->observaciones_boveda ? $movCaja->observaciones_boveda . ' | ' : '')
            . 'Compensado con egreso #' . $egreso->id . ' por anulación de pago: ' . $motivo
        );
        $movCaja->save();

        return [
            'aplicado' => true,
            'accion' => 'egreso_compensatorio',
            'movimiento_caja_id' => $egreso->id,
            'mensaje' => 'La caja del pago ya estaba cerrada. Se registró un egreso de devolución en la caja de hoy.',
        ];
    }

    private static function encontrarIngresoPagoCredito(
        CreditoMovimiento $movimiento,
        CreditoPrendario $credito
    ): ?MovimientoCaja {
        $base = MovimientoCaja::query()
            ->where('tipo', 'ingreso_pago')
            ->where('estado', 'aplicado');

        if ($movimiento->numero_movimiento) {
            $porNumero = (clone $base)
                ->where('detalles_movimiento->numero_movimiento', $movimiento->numero_movimiento)
                ->first();

            if ($porNumero) {
                return $porNumero->load('caja');
            }
        }

        $candidatos = (clone $base)
            ->where('detalles_movimiento->tipo_operacion', 'pago_credito')
            ->where('detalles_movimiento->numero_credito', $credito->numero_credito)
            ->where('monto', abs((float) $movimiento->monto_total))
            ->get();

        if ($candidatos->isEmpty()) {
            return null;
        }

        $referencia = $movimiento->fecha_registro ?? $movimiento->created_at ?? now();

        $elegido = $candidatos->sortBy(function (MovimientoCaja $cajaMov) use ($referencia) {
            return abs($cajaMov->created_at->diffInSeconds($referencia));
        })->first();

        return $elegido?->load('caja');
    }
}
