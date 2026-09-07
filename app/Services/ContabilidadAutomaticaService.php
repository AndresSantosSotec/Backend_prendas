<?php

namespace App\Services;

use App\Models\Contabilidad\CtbDiario;
use App\Models\Contabilidad\CtbMovimiento;
use App\Models\Contabilidad\CtbNomenclatura;
use App\Models\Contabilidad\CtbParametrizacionCuenta;
use App\Models\Contabilidad\CtbTipoPoliza;
use App\Models\Moneda;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class ContabilidadAutomaticaService
{
    /**
     * Registrar asiento contable a partir de una operación
     *
     * @param string $tipoOperacion Tipo de operación (credito_desembolso, venta_contado, etc)
     * @param array $datos Datos de la operación
     * @return CtbDiario|null
     */
    public function registrarAsiento(string $tipoOperacion, array $datos): ?CtbDiario
    {
        // Verificar si la contabilidad automática está habilitada
        if (!config('contabilidad.auto_asientos', true)) {
            Log::info("Contabilidad automática deshabilitada para: {$tipoOperacion}");
            return null;
        }

        try {
            DB::beginTransaction();

            // 1. Control de idempotencia: evitar duplicar asiento para la misma operación activa
            $existente = $this->buscarAsientoExistente($tipoOperacion, $datos);
            if ($existente) {
                DB::commit();
                Log::info("Asiento contable ya existía (idempotencia activa)", [
                    'tipo_operacion' => $tipoOperacion,
                    'numero_comprobante' => $existente->numero_comprobante,
                    'id' => $existente->id
                ]);
                return $existente->load('movimientos.cuentaContable');
            }

            // 2. Crear el asiento en el diario
            $diario = $this->crearAsientoDiario($tipoOperacion, $datos);

            // 3. Obtener parametrización para esta operación
            $parametrizaciones = $this->obtenerParametrizaciones($tipoOperacion, $datos['sucursal_id'] ?? null);

            if ($parametrizaciones->isNotEmpty()) {
                // Crear los movimientos según parametrización configurada
                $this->crearMovimientosDesdeParametrizacion($diario, $parametrizaciones, $datos);
            } else {
                // Fallback automático con la nomenclatura contable estándar
                $this->crearMovimientosFallback($diario, $tipoOperacion, $datos);
            }

            // 4. Verificar que cuadre estrictamente (Debe = Haber)
            $diarioFresh = $diario->fresh(['movimientos']);
            $totalDebe = round((float) $diarioFresh->movimientos->sum('debe'), 2);
            $totalHaber = round((float) $diarioFresh->movimientos->sum('haber'), 2);
            $diferencia = abs($totalDebe - $totalHaber);

            if ($diarioFresh->movimientos->count() < 2 || $diferencia >= 0.01) {
                throw new Exception("El asiento contable no cuadra o está incompleto: Total Debe ({$totalDebe}) ≠ Total Haber ({$totalHaber}) - Líneas: {$diarioFresh->movimientos->count()}");
            }

            DB::commit();

            Log::info("Asiento contable creado exitosamente", [
                'tipo_operacion' => $tipoOperacion,
                'numero_comprobante' => $diario->numero_comprobante,
                'debe' => $totalDebe,
                'haber' => $totalHaber,
            ]);

            return $diarioFresh->load('movimientos.cuentaContable');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al registrar asiento contable", [
                'tipo_operacion' => $tipoOperacion,
                'error' => $e->getMessage(),
                'datos' => $datos,
            ]);

            return null;
        }
    }

    /**
     * Buscar si ya existe un asiento activo para evitar duplicados
     */
    private function buscarAsientoExistente(string $tipoOperacion, array $datos): ?CtbDiario
    {
        $query = CtbDiario::where('estado', '!=', 'anulado');

        if (!empty($datos['movimiento_credito_id'])) {
            return $query->where('movimiento_credito_id', $datos['movimiento_credito_id'])->first();
        }

        if (!empty($datos['venta_id']) && in_array($tipoOperacion, ['venta_contado', 'venta_enganche', 'venta_credito_enganche'])) {
            return $query->where('venta_id', $datos['venta_id'])
                         ->whereIn('tipo_origen', ['venta_prenda', 'venta_enganche'])
                         ->first();
        }

        if (!empty($datos['compra_id'])) {
            return $query->where('compra_id', $datos['compra_id'])->first();
        }

        if (!empty($datos['otro_gasto_id'])) {
            return $query->where('numero_documento', 'OG-' . $datos['otro_gasto_id'])->first();
        }

        if (!empty($datos['refrendo_id'])) {
            return $query->where('numero_documento', 'REF-' . $datos['refrendo_id'])->first();
        }

        if (!empty($datos['boveda_movimiento_id'])) {
            return $query->where('numero_documento', 'BOV-' . $datos['boveda_movimiento_id'])->first();
        }

        return null;
    }

    /**
     * Obtener parametrizaciones activas para una operación
     */
    private function obtenerParametrizaciones(string $tipoOperacion, ?int $sucursalId)
    {
        return CtbParametrizacionCuenta::where('tipo_operacion', $tipoOperacion)
            ->where('activo', true)
            ->when($sucursalId, function ($query) use ($sucursalId) {
                $query->where(function ($q) use ($sucursalId) {
                    $q->whereNull('sucursal_id')
                      ->orWhere('sucursal_id', $sucursalId);
                });
            })
            ->with(['cuentaContable', 'tipoPoliza'])
            ->orderBy('orden')
            ->get();
    }

    /**
     * Crear el encabezado del asiento en el diario
     */
    private function crearAsientoDiario(string $tipoOperacion, array $datos): CtbDiario
    {
        $tipoPolizaId = $this->determinarTipoPoliza($tipoOperacion);
        $tipoPoliza = CtbTipoPoliza::find($tipoPolizaId);
        $codigoPoliza = $tipoPoliza ? $tipoPoliza->codigo : 'PD';

        $numeroComprobante = $this->generarNumeroComprobante($codigoPoliza, $datos['sucursal_id'] ?? null);
        $monedaId = $datos['moneda_id'] ?? Moneda::where('codigo', config('contabilidad.moneda_defecto', 'GTQ'))->first()?->id ?? 1;

        $docRef = $datos['numero_documento'] 
            ?? (!empty($datos['otro_gasto_id']) ? 'OG-' . $datos['otro_gasto_id'] : null)
            ?? (!empty($datos['refrendo_id']) ? 'REF-' . $datos['refrendo_id'] : null)
            ?? (!empty($datos['boveda_movimiento_id']) ? 'BOV-' . $datos['boveda_movimiento_id'] : null)
            ?? $numeroComprobante;

        return CtbDiario::create([
            'numero_comprobante' => $numeroComprobante,
            'tipo_poliza_id' => $tipoPolizaId,
            'moneda_id' => $monedaId,
            'tipo_origen' => $this->mapearTipoOrigen($tipoOperacion),
            'credito_prendario_id' => $datos['credito_prendario_id'] ?? $datos['credito_id'] ?? null,
            'movimiento_credito_id' => $datos['movimiento_credito_id'] ?? null,
            'venta_id' => $datos['venta_id'] ?? null,
            'caja_id' => $datos['caja_id'] ?? null,
            'compra_id' => $datos['compra_id'] ?? null,
            'numero_documento' => $docRef,
            'glosa' => $datos['glosa'] ?? $this->generarGlosa($tipoOperacion, $datos),
            'fecha_documento' => $datos['fecha_documento'] ?? now(),
            'fecha_contabilizacion' => $datos['fecha_contabilizacion'] ?? now()->toDateString(),
            'sucursal_id' => $datos['sucursal_id'] ?? null,
            'usuario_id' => $datos['usuario_id'] ?? Auth::id() ?? 1,
            'estado' => 'registrado',
            'editable' => true,
        ]);
    }

    /**
     * Crear movimientos usando parametrización configurada
     */
    private function crearMovimientosDesdeParametrizacion(CtbDiario $diario, $parametrizaciones, array $datos): void
    {
        foreach ($parametrizaciones as $param) {
            $monto = $this->calcularMontoParametrizado($param, $datos);

            if ($monto <= 0) {
                continue;
            }

            CtbMovimiento::create([
                'diario_id' => $diario->id,
                'cuenta_contable_id' => $param->cuenta_contable_id,
                'debe' => $param->tipo_movimiento === 'debe' ? $monto : 0,
                'haber' => $param->tipo_movimiento === 'haber' ? $monto : 0,
                'numero_comprobante' => $diario->numero_comprobante,
                'detalle' => $param->descripcion ?? $diario->glosa,
                'cliente_id' => $datos['cliente_id'] ?? null,
            ]);
        }
    }

    /**
     * Calcular monto específico para una línea de parametrización
     */
    private function calcularMontoParametrizado(CtbParametrizacionCuenta $param, array $datos): float
    {
        $op = $param->tipo_operacion;
        $mov = $param->tipo_movimiento;
        $codigoCuenta = $param->cuentaContable?->codigo_cuenta ?? '';

        switch ($op) {
            case 'credito_desembolso':
                if ($mov === 'debe') {
                    if (str_starts_with($codigoCuenta, '1101.02.002')) {
                        return (float) ($datos['monto_intereses'] ?? 0);
                    }
                    return (float) ($datos['monto_capital'] ?? $datos['monto_desembolsado'] ?? $datos['monto'] ?? 0);
                }
                // Haber (caja o banco)
                return (float) ($datos['monto_desembolsado'] ?? $datos['monto_capital'] ?? $datos['monto'] ?? 0);

            case 'credito_pago':
            case 'credito_pago_completo':
                if ($mov === 'debe') {
                    return (float) ($datos['monto_total'] ?? $datos['monto'] ?? 0);
                }
                // Haber desglose
                if (str_starts_with($codigoCuenta, '1101.02.001')) { // Capital
                    return (float) ($datos['monto_capital'] ?? $datos['capital'] ?? 0);
                }
                if (str_starts_with($codigoCuenta, '4101.01')) { // Interés
                    return (float) ($datos['monto_interes'] ?? $datos['monto_intereses'] ?? $datos['interes'] ?? 0);
                }
                if (str_starts_with($codigoCuenta, '4101.02')) { // Mora
                    return (float) ($datos['monto_mora'] ?? $datos['mora'] ?? 0);
                }
                if (str_starts_with($codigoCuenta, '4101.03')) { // Comisiones / Otros
                    return (float) ($datos['otros_cargos'] ?? $datos['gastos_totales'] ?? 0);
                }
                return (float) ($datos['monto'] ?? 0);

            case 'credito_pago_capital':
                return (float) ($datos['monto_capital'] ?? $datos['capital'] ?? $datos['monto'] ?? 0);

            case 'credito_pago_interes':
                return (float) ($datos['monto_interes'] ?? $datos['monto_intereses'] ?? $datos['interes'] ?? $datos['monto'] ?? 0);

            case 'credito_pago_mora':
                return (float) ($datos['monto_mora'] ?? $datos['mora'] ?? $datos['monto'] ?? 0);

            case 'credito_gastos':
                return (float) ($datos['gastos_totales'] ?? $datos['otros_cargos'] ?? $datos['monto'] ?? 0);

            case 'credito_refrendo':
            case 'credito_renovacion':
                if ($mov === 'debe') {
                    return (float) ($datos['monto_total_pagado'] ?? $datos['monto_total'] ?? $datos['monto'] ?? 0);
                }
                if (str_starts_with($codigoCuenta, '4101.01')) { // Interés
                    return (float) ($datos['monto_interes_adeudado'] ?? $datos['monto_interes'] ?? 0);
                }
                if (str_starts_with($codigoCuenta, '4101.02')) { // Mora
                    return (float) ($datos['monto_mora_adeudado'] ?? $datos['monto_mora'] ?? 0);
                }
                if (str_starts_with($codigoCuenta, '1101.02.001')) { // Abono a capital en refrendo
                    return (float) ($datos['monto_capital_pagado'] ?? $datos['monto_capital'] ?? 0);
                }
                return (float) ($datos['monto'] ?? 0);

            case 'venta_contado':
                return (float) ($datos['total'] ?? $datos['monto'] ?? 0);

            case 'venta_enganche':
            case 'venta_credito_enganche':
                if ($mov === 'debe') {
                    if (str_starts_with($codigoCuenta, '1101.01')) { // Caja
                        return (float) ($datos['enganche'] ?? $datos['total_pagado'] ?? 0);
                    }
                    if (str_starts_with($codigoCuenta, '1101.02')) { // Crédito por cobrar (saldo)
                        return (float) ($datos['saldo_financiar'] ?? 0);
                    }
                }
                return (float) ($datos['total'] ?? 0);

            case 'venta_abono':
            case 'venta_credito_abono':
                return (float) ($datos['monto_abono'] ?? $datos['monto'] ?? 0);

            case 'compra_directa':
                return (float) ($datos['monto_compra'] ?? $datos['monto'] ?? 0);

            case 'otro_gasto':
            case 'otro_ingreso':
                return (float) ($datos['monto'] ?? 0);

            case 'boveda_deposito':
            case 'boveda_retiro':
                return (float) ($datos['monto'] ?? 0);

            default:
                return (float) ($datos['monto'] ?? $datos['total'] ?? 0);
        }
    }

    /**
     * Crear movimientos de fallback asegurando partida doble balanceada
     */
    private function crearMovimientosFallback(CtbDiario $diario, string $tipoOperacion, array $datos): void
    {
        $cuentaCaja = $this->obtenerCuentaPorCodigo(config('contabilidad.cuentas.caja', '1101.01.001'));
        $cuentaBanco = $this->obtenerCuentaPorCodigo(config('contabilidad.cuentas.bancos', '1101.01.003'));
        $cuentaCreditos = $this->obtenerCuentaPorCodigo(config('contabilidad.cuentas.creditos_por_cobrar', '1101.02.001'));
        $cuentaIntereses = $this->obtenerCuentaPorCodigo(config('contabilidad.cuentas.ingresos_intereses', '4101.01'));
        $cuentaMora = $this->obtenerCuentaPorCodigo(config('contabilidad.cuentas.ingresos_mora', '4101.02'));
        $cuentaComisiones = $this->obtenerCuentaPorCodigo(config('contabilidad.cuentas.ingresos_comisiones', '4101.03'));
        $cuentaVentas = $this->obtenerCuentaPorCodigo(config('contabilidad.cuentas.ventas', '4101.04'));
        $cuentaInventario = $this->obtenerCuentaPorCodigo(config('contabilidad.cuentas.inventario_prendas_venta', '1101.03.002'));
        $cuentaGastos = $this->obtenerCuentaPorCodigo('5101.01') ?? $this->obtenerCuentaPorCodigo('5101') ?? $cuentaVentas;

        $formaPago = $datos['forma_pago'] ?? 'efectivo';
        $cuentaEfectivo = ($formaPago === 'transferencia' || $formaPago === 'cheque') ? $cuentaBanco : $cuentaCaja;

        switch ($tipoOperacion) {
            case 'credito_desembolso':
                $monto = (float) ($datos['monto_desembolsado'] ?? $datos['monto_capital'] ?? $datos['monto'] ?? 0);
                if ($monto > 0) {
                    $this->crearLinea($diario, $cuentaCreditos, $monto, 0, "Cartera de créditos por cobrar");
                    $this->crearLinea($diario, $cuentaEfectivo, 0, $monto, "Desembolso en efectivo/banco");
                }
                break;

            case 'credito_pago':
            case 'credito_pago_completo':
                $montoTotal = (float) ($datos['monto_total'] ?? $datos['monto'] ?? 0);
                $capital = (float) ($datos['monto_capital'] ?? $datos['capital'] ?? 0);
                $interes = (float) ($datos['monto_interes'] ?? $datos['monto_intereses'] ?? $datos['interes'] ?? 0);
                $mora = (float) ($datos['monto_mora'] ?? $datos['mora'] ?? 0);
                $otros = (float) ($datos['otros_cargos'] ?? $datos['gastos_totales'] ?? 0);

                if ($montoTotal <= 0 && ($capital + $interes + $mora + $otros) > 0) {
                    $montoTotal = $capital + $interes + $mora + $otros;
                }

                if ($montoTotal > 0) {
                    $this->crearLinea($diario, $cuentaEfectivo, $montoTotal, 0, "Ingreso por pago de crédito");

                    if ($capital > 0) {
                        $this->crearLinea($diario, $cuentaCreditos, 0, $capital, "Abono a capital");
                    }
                    if ($interes > 0) {
                        $this->crearLinea($diario, $cuentaIntereses, 0, $interes, "Ingreso por intereses");
                    }
                    if ($mora > 0) {
                        $this->crearLinea($diario, $cuentaMora, 0, $mora, "Ingreso por mora");
                    }
                    if ($otros > 0) {
                        $this->crearLinea($diario, $cuentaComisiones, 0, $otros, "Ingreso por comisiones/gastos");
                    }
                }
                break;

            case 'credito_pago_capital':
                $monto = (float) ($datos['monto_capital'] ?? $datos['capital'] ?? $datos['monto'] ?? 0);
                if ($monto > 0) {
                    $this->crearLinea($diario, $cuentaEfectivo, $monto, 0, "Ingreso por capital de crédito");
                    $this->crearLinea($diario, $cuentaCreditos, 0, $monto, "Disminución de cartera");
                }
                break;

            case 'credito_pago_interes':
                $monto = (float) ($datos['monto_interes'] ?? $datos['monto_intereses'] ?? $datos['monto'] ?? 0);
                if ($monto > 0) {
                    $this->crearLinea($diario, $cuentaEfectivo, $monto, 0, "Ingreso por intereses de crédito");
                    $this->crearLinea($diario, $cuentaIntereses, 0, $monto, "Ganancia por intereses");
                }
                break;

            case 'credito_pago_mora':
                $monto = (float) ($datos['monto_mora'] ?? $datos['monto'] ?? 0);
                if ($monto > 0) {
                    $this->crearLinea($diario, $cuentaEfectivo, $monto, 0, "Ingreso por mora");
                    $this->crearLinea($diario, $cuentaMora, 0, $monto, "Ganancia por mora");
                }
                break;

            case 'credito_refrendo':
            case 'credito_renovacion':
                $montoTotal = (float) ($datos['monto_total_pagado'] ?? $datos['monto_total'] ?? $datos['monto'] ?? 0);
                $interes = (float) ($datos['monto_interes_adeudado'] ?? $datos['monto_interes'] ?? 0);
                $mora = (float) ($datos['monto_mora_adeudado'] ?? $datos['monto_mora'] ?? 0);
                $capital = (float) ($datos['monto_capital_pagado'] ?? $datos['monto_capital'] ?? 0);

                if ($montoTotal > 0) {
                    $this->crearLinea($diario, $cuentaEfectivo, $montoTotal, 0, "Cobro por refrendo/renovación");
                    if ($interes > 0) {
                        $this->crearLinea($diario, $cuentaIntereses, 0, $interes, "Intereses de refrendo");
                    }
                    if ($mora > 0) {
                        $this->crearLinea($diario, $cuentaMora, 0, $mora, "Mora de refrendo");
                    }
                    if ($capital > 0) {
                        $this->crearLinea($diario, $cuentaCreditos, 0, $capital, "Abono a capital en refrendo");
                    }
                }
                break;

            case 'venta_contado':
                $total = (float) ($datos['total'] ?? $datos['monto'] ?? 0);
                if ($total > 0) {
                    $this->crearLinea($diario, $cuentaEfectivo, $total, 0, "Ingreso por venta al contado");
                    $this->crearLinea($diario, $cuentaVentas, 0, $total, "Ventas de prendas");
                }
                break;

            case 'venta_enganche':
            case 'venta_credito_enganche':
                $total = (float) ($datos['total'] ?? 0);
                $enganche = (float) ($datos['enganche'] ?? $datos['total_pagado'] ?? 0);
                $saldo = max(0, $total - $enganche);

                if ($total > 0) {
                    if ($enganche > 0) {
                        $this->crearLinea($diario, $cuentaEfectivo, $enganche, 0, "Enganche de venta a crédito");
                    }
                    if ($saldo > 0) {
                        $this->crearLinea($diario, $cuentaCreditos, $saldo, 0, "Cuenta por cobrar venta crédito");
                    }
                    $this->crearLinea($diario, $cuentaVentas, 0, $total, "Venta total de prendas");
                }
                break;

            case 'venta_abono':
            case 'venta_credito_abono':
                $monto = (float) ($datos['monto_abono'] ?? $datos['monto'] ?? 0);
                if ($monto > 0) {
                    $this->crearLinea($diario, $cuentaEfectivo, $monto, 0, "Ingreso por abono a venta a crédito");
                    $this->crearLinea($diario, $cuentaCreditos, 0, $monto, "Disminución cuenta por cobrar venta");
                }
                break;

            case 'compra_directa':
                $monto = (float) ($datos['monto_compra'] ?? $datos['monto'] ?? 0);
                if ($monto > 0) {
                    $this->crearLinea($diario, $cuentaInventario, $monto, 0, "Entrada a inventario de prendas");
                    $this->crearLinea($diario, $cuentaEfectivo, 0, $monto, "Pago de compra directa");
                }
                break;

            case 'otro_gasto':
                $monto = (float) ($datos['monto'] ?? 0);
                if ($monto > 0) {
                    $this->crearLinea($diario, $cuentaGastos, $monto, 0, $diario->glosa);
                    $this->crearLinea($diario, $cuentaEfectivo, 0, $monto, "Salida de caja por gasto");
                }
                break;

            case 'boveda_deposito':
                $monto = (float) ($datos['monto'] ?? 0);
                if ($monto > 0) {
                    $this->crearLinea($diario, $cuentaBanco, $monto, 0, "Depósito en bóveda/banco");
                    $this->crearLinea($diario, $cuentaCaja, 0, $monto, "Salida de caja");
                }
                break;

            case 'boveda_retiro':
                $monto = (float) ($datos['monto'] ?? 0);
                if ($monto > 0) {
                    $this->crearLinea($diario, $cuentaCaja, $monto, 0, "Entrada a caja desde bóveda/banco");
                    $this->crearLinea($diario, $cuentaBanco, 0, $monto, "Retiro de bóveda/banco");
                }
                break;
        }
    }

    private function crearLinea(CtbDiario $diario, ?CtbNomenclatura $cuenta, float $debe, float $haber, string $detalle): void
    {
        if (!$cuenta || ($debe <= 0 && $haber <= 0)) {
            return;
        }

        CtbMovimiento::create([
            'diario_id' => $diario->id,
            'cuenta_contable_id' => $cuenta->id,
            'debe' => round($debe, 2),
            'haber' => round($haber, 2),
            'numero_comprobante' => $diario->numero_comprobante,
            'detalle' => $detalle,
        ]);
    }

    private function obtenerCuentaPorCodigo(string $codigo): ?CtbNomenclatura
    {
        return CtbNomenclatura::where('codigo_cuenta', $codigo)->first()
            ?? CtbNomenclatura::where('codigo_cuenta', 'like', $codigo . '%')->first();
    }

    /**
     * Generar número de comprobante único
     */
    public function generarNumeroComprobante(string $tipoCodigo = 'PD', ?int $sucursalId = null): string
    {
        $prefijo = $sucursalId ? str_pad((string)$sucursalId, 3, '0', STR_PAD_LEFT) : '000';
        $fecha = now()->format('Ymd');
        $base = "{$tipoCodigo}-{$prefijo}-{$fecha}";

        $ultimo = CtbDiario::where('numero_comprobante', 'like', "{$base}-%")
            ->orderBy('numero_comprobante', 'desc')
            ->first();

        if ($ultimo) {
            $partes = explode('-', $ultimo->numero_comprobante);
            $ultimoNumero = (int) end($partes);
            $nuevoNumero = $ultimoNumero + 1;
        } else {
            $nuevoNumero = 1;
        }

        return sprintf('%s-%04d', $base, $nuevoNumero);
    }

    /**
     * Determinar tipo de póliza según operación
     */
    public function determinarTipoPoliza(string $tipoOperacion): int
    {
        $mapaTiposPoliza = [
            'credito_desembolso' => 'PE',
            'credito_pago' => 'PI',
            'credito_pago_completo' => 'PI',
            'credito_pago_capital' => 'PI',
            'credito_pago_interes' => 'PI',
            'credito_pago_mora' => 'PI',
            'credito_gastos' => 'PI',
            'credito_refrendo' => 'PI',
            'credito_renovacion' => 'PI',
            'venta_contado' => 'PI',
            'venta_enganche' => 'PI',
            'venta_credito_enganche' => 'PI',
            'venta_abono' => 'PI',
            'venta_credito_abono' => 'PI',
            'compra_directa' => 'PE',
            'otro_gasto' => 'PE',
            'otro_ingreso' => 'PI',
            'caja_apertura' => 'PD',
            'caja_cierre' => 'PD',
            'boveda_deposito' => 'PD',
            'boveda_retiro' => 'PD',
        ];

        $codigo = $mapaTiposPoliza[$tipoOperacion] ?? 'PD';
        $tipo = CtbTipoPoliza::where('codigo', $codigo)->first();

        return $tipo ? $tipo->id : (CtbTipoPoliza::first()?->id ?? 1);
    }

    /**
     * Mapear tipo de operación a tipo de origen en diario
     */
    public function mapearTipoOrigen(string $tipoOperacion): string
    {
        $mapaOrigenDiario = [
            'credito_desembolso' => 'credito_prendario',
            'credito_pago' => 'credito_prendario',
            'credito_pago_completo' => 'credito_prendario',
            'credito_pago_capital' => 'credito_prendario',
            'credito_pago_interes' => 'credito_prendario',
            'credito_pago_mora' => 'credito_prendario',
            'credito_gastos' => 'credito_prendario',
            'credito_refrendo' => 'credito_prendario',
            'credito_renovacion' => 'credito_prendario',
            'venta_contado' => 'venta_prenda',
            'venta_credito' => 'venta_prenda',
            'venta_enganche' => 'venta_prenda',
            'venta_abono' => 'venta_prenda',
            'venta_credito_enganche' => 'venta_prenda',
            'venta_credito_abono' => 'venta_prenda',
            'compra_directa' => 'compra',
            'otro_gasto' => 'gasto',
            'otro_ingreso' => 'caja',
            'caja_apertura' => 'caja',
            'caja_cierre' => 'caja',
            'boveda_deposito' => 'caja',
            'boveda_retiro' => 'caja',
        ];

        return $mapaOrigenDiario[$tipoOperacion] ?? 'otro';
    }

    /**
     * Generar glosa automática
     */
    public function generarGlosa(string $tipoOperacion, array $datos): string
    {
        $codigo = $datos['codigo_credito'] ?? $datos['numero_recibo'] ?? $datos['codigo_compra'] ?? $datos['numero_documento'] ?? '';

        $glosas = [
            'credito_desembolso' => "Desembolso de crédito prendario " . $codigo,
            'credito_pago' => "Pago de crédito prendario " . $codigo,
            'credito_pago_completo' => "Pago de crédito prendario " . $codigo,
            'credito_pago_capital' => "Pago de capital - Crédito " . $codigo,
            'credito_pago_interes' => "Pago de intereses - Crédito " . $codigo,
            'credito_pago_mora' => "Pago de mora - Crédito " . $codigo,
            'credito_gastos' => "Gastos de crédito - " . $codigo,
            'credito_refrendo' => "Refrendo/Renovación de crédito - " . $codigo,
            'credito_renovacion' => "Renovación de crédito - " . $codigo,
            'venta_contado' => "Venta al contado - Recibo " . $codigo,
            'venta_enganche' => "Venta a crédito - Enganche - Recibo " . $codigo,
            'venta_abono' => "Abono a venta a crédito - Recibo " . $codigo,
            'venta_credito_enganche' => "Venta a crédito - Enganche - " . $codigo,
            'venta_credito_abono' => "Abono cuota venta a crédito - " . $codigo,
            'compra_directa' => "Compra directa de prenda - " . $codigo,
            'otro_gasto' => "Registro de gasto operativo - " . ($datos['concepto'] ?? $codigo),
            'boveda_deposito' => "Depósito de efectivo en bóveda - " . $codigo,
            'boveda_retiro' => "Retiro de efectivo desde bóveda - " . $codigo,
        ];

        return $glosas[$tipoOperacion] ?? "Operación: {$tipoOperacion}";
    }

    /**
     * Anular un asiento contable
     */
    public function anularAsiento(int $diarioId, string $motivo, ?int $usuarioId = null): bool
    {
        try {
            $diario = CtbDiario::findOrFail($diarioId);

            if ($diario->estado === 'anulado') {
                return true;
            }

            $diario->update([
                'estado' => 'anulado',
                'anulado_por' => $usuarioId ?? Auth::id(),
                'fecha_anulacion' => now(),
                'motivo_anulacion' => $motivo,
                'editable' => false,
            ]);

            Log::info("Asiento contable anulado", [
                'numero_comprobante' => $diario->numero_comprobante,
                'motivo' => $motivo,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error("Error al anular asiento contable", [
                'diario_id' => $diarioId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
