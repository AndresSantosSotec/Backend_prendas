<?php

namespace App\Services;

use App\Models\Contabilidad\CtbDiario;
use App\Models\Contabilidad\CtbMovimiento;
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
        if (!config('contabilidad.auto_asientos', false)) {
            Log::info("Contabilidad automática deshabilitada para: {$tipoOperacion}");
            return null;
        }

        try {
            DB::beginTransaction();

            // Obtener parametrización para esta operación
            $parametrizaciones = $this->obtenerParametrizaciones($tipoOperacion, $datos['sucursal_id'] ?? null);

            if ($parametrizaciones->isEmpty()) {
                Log::warning("No hay parametrización contable para: {$tipoOperacion}");
                DB::rollBack();
                return null;
            }

            // Crear el asiento en el diario
            $diario = $this->crearAsientoDiario($tipoOperacion, $datos);

            // Crear los movimientos según parametrización
            $this->crearMovimientos($diario, $parametrizaciones, $datos);

            // Verificar que cuadre
            if (!$diario->fresh()->validarCuadre()) {
                throw new Exception("El asiento contable no cuadra (Debe ≠ Haber)");
            }

            DB::commit();

            Log::info("Asiento contable creado exitosamente", [
                'tipo_operacion' => $tipoOperacion,
                'numero_comprobante' => $diario->numero_comprobante,
                'debe' => $diario->fresh()->total_debe,
                'haber' => $diario->fresh()->total_haber,
            ]);

            return $diario->fresh()->load('movimientos.cuentaContable');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al registrar asiento contable", [
                'tipo_operacion' => $tipoOperacion,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // No lanzar la excepción para que no interfiera con la operación principal
            return null;
        }
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
     * Crear el ası encabezado del asiento en el diario
     */
    private function crearAsientoDiario(string $tipoOperacion, array $datos): CtbDiario
    {
        // Generar número de comprobante
        $numeroComprobante = $this->generarNumeroComprobante($datos['sucursal_id'] ?? null);

        // Determinar tipo de póliza
        $tipoPolizaId = $this->determinarTipoPoliza($tipoOperacion);

        // Obtener moneda
        $monedaId = $datos['moneda_id'] ?? Moneda::where('codigo', config('contabilidad.moneda_defecto', 'GTQ'))->first()?->id;

        return CtbDiario::create([
            'numero_comprobante' => $numeroComprobante,
            'tipo_poliza_id' => $tipoPolizaId,
            'moneda_id' => $monedaId,
            'tipo_origen' => $this->mapearTipoOrigen($tipoOperacion),
            'credito_prendario_id' => $datos['credito_id'] ?? null,
            'movimiento_credito_id' => $datos['movimiento_credito_id'] ?? null,
            'venta_id' => $datos['venta_id'] ?? null,
            'caja_id' => $datos['caja_id'] ?? null,
            'compra_id' => $datos['compra_id'] ?? null,
            'numero_documento' => $datos['numero_documento'] ?? $numeroComprobante,
            'glosa' => $datos['glosa'] ?? $this->generarGlosa($tipoOperacion, $datos),
            'fecha_documento' => $datos['fecha_documento'] ?? now(),
            'fecha_contabilizacion' => $datos['fecha_contabilizacion'] ?? now(),
            'sucursal_id' => $datos['sucursal_id'] ?? null,
            'usuario_id' => $datos['usuario_id'] ?? Auth::id(),
            'estado' => 'registrado',
            'editable' => false,
        ]);
    }

    /**
     * Crear los movimientos del asiento según parametrización
     */
    private function crearMovimientos(CtbDiario $diario, $parametrizaciones, array $datos): void
    {
        foreach ($parametrizaciones as $parametrizacion) {
            // Calcular monto para este movimiento
            $monto = $this->calcularMonto($parametrizacion->tipo_operacion, $parametrizacion->tipo_movimiento, $datos);

            if ($monto <= 0) {
                continue; // Omitir movimientos con monto 0
            }

            // Verificar que la cuenta acepte movimientos
            if (!$parametrizacion->cuentaContable->acepta_movimientos) {
                Log::warning("La cuenta {$parametrizacion->cuentaContable->codigo_cuenta} no acepta movimientos");
                continue;
            }

            CtbMovimiento::create([
                'diario_id' => $diario->id,
                'cuenta_contable_id' => $parametrizacion->cuenta_contable_id,
                'debe' => $parametrizacion->tipo_movimiento === 'debe' ? $monto : 0,
                'haber' => $parametrizacion->tipo_movimiento === 'haber' ? $monto : 0,
                'numero_comprobante' => $diario->numero_comprobante,
                'detalle' => $parametrizacion->descripcion ?? $diario->glosa,
            ]);
        }
    }

    /**
     * Calcular el monto según el tipo de operación y movimiento
     */
    private function calcularMonto(string $tipoOperacion, string $tipoMovimiento, array $datos): float
    {
        // Mapeo de operaciones a campos de monto
        $mapaMontos = [
            'credito_desembolso' => [
                'debe_creditos_por_cobrar' => $datos['monto_capital'] ?? 0,
                'debe_intereses_por_cobrar' => $datos['monto_intereses'] ?? 0,
                'haber_caja' => ($datos['monto_capital'] ?? 0) + ($datos['monto_intereses'] ?? 0) + ($datos['gastos_totales'] ?? 0),
            ],
            'credito_pago_capital' => [
                'debe_caja' => $datos['monto_capital'] ?? $datos['monto'] ?? 0,
                'haber_creditos_por_cobrar' => $datos['monto_capital'] ?? $datos['monto'] ?? 0,
            ],
            'credito_pago_interes' => [
                'debe_caja' => $datos['monto_intereses'] ?? $datos['monto_interes'] ?? $datos['monto'] ?? 0,
                'haber_ingresos_intereses' => $datos['monto_intereses'] ?? $datos['monto_interes'] ?? $datos['monto'] ?? 0,
            ],
            'credito_pago_mora' => [
                'debe_caja' => $datos['monto_mora'] ?? $datos['monto'] ?? 0,
                'haber_ingresos_mora' => $datos['monto_mora'] ?? $datos['monto'] ?? 0,
            ],
            'credito_gastos' => [
                'debe_caja' => $datos['gastos_totales'] ?? 0,
                'haber_ingresos_comisiones' => $datos['gastos_totales'] ?? 0,
            ],
            'venta_contado' => [
                'debe_caja' => $datos['total'] ?? 0,
                'haber_ventas' => $datos['total'] ?? 0,
            ],
            'venta_enganche' => [
                'debe_caja' => $datos['enganche'] ?? 0,
                'debe_creditos_por_cobrar' => $datos['saldo_financiar'] ?? 0,
                'haber_ventas' => $datos['total'] ?? 0,
            ],
            'venta_abono' => [
                'debe_caja' => $datos['monto_abono'] ?? 0,
                'haber_creditos_por_cobrar' => $datos['monto_abono'] ?? 0,
            ],
            'compra_directa' => [
                'debe_inventario' => $datos['monto_compra'] ?? 0,
                'haber_caja' => $datos['monto_compra'] ?? 0,
            ],
        ];

        // Construir clave de búsqueda
        $clave = $tipoMovimiento . '_' . str_replace('_', '_', $tipoOperacion);

        // Buscar en el map específico de la operación
        if (isset($mapaMontos[$tipoOperacion])) {
            foreach ($mapaMontos[$tipoOperacion] as $key => $monto) {
                if (str_starts_with($key, $tipoMovimiento)) {
                    return (float) $monto;
                }
            }
        }

        // Fallback: usar el monto total si existe
        return (float) ($datos['monto'] ?? $datos['total'] ?? 0);
    }

    /**
     * Generar número de comprobante único
     */
    private function generarNumeroComprobante(?int $sucursalId): string
    {
        $prefijo = $sucursalId ? str_pad($sucursalId, 3, '0', STR_PAD_LEFT) : '000';
        $fecha = now()->format('Ymd');

        // Obtener el último comprobante del día
        $ultimo = CtbDiario::where('numero_comprobante', 'like', "{$prefijo}-{$fecha}-%")
            ->orderBy('numero_comprobante', 'desc')
            ->first();

        if ($ultimo) {
            $ultimoNumero = (int) substr($ultimo->numero_comprobante, -4);
            $nuevoNumero = $ultimoNumero + 1;
        } else {
            $nuevoNumero = 1;
        }

        return sprintf('%s-%s-%04d', $prefijo, $fecha, $nuevoNumero);
    }

    /**
     * Determinar tipo de póliza según operación
     */
    private function determinarTipoPoliza(string $tipoOperacion): ?int
    {
        $mapaTiposPoliza = [
            'credito_desembolso' => 'PE', // Póliza de Egreso
            'credito_pago_capital' => 'PI', // Póliza de Ingreso
            'credito_pago_interes' => 'PI',
            'credito_pago_mora' => 'PI',
            'venta_contado' => 'PI',
            'venta_enganche' => 'PI',
            'venta_abono' => 'PI',
            'compra_directa' => 'PE',
            'caja_apertura' => 'PD', // Póliza de Diario
            'caja_ingreso' => 'PI',
            'caja_egreso' => 'PE',
        ];

        $codigo = $mapaTiposPoliza[$tipoOperacion] ?? 'PD';

        return CtbTipoPoliza::where('codigo', $codigo)->first()?->id;
    }

    /**
     * Mapear tipo de operación a tipo de origen en diario
     */
    private function mapearTipoOrigen(string $tipoOperacion): string
    {
        $mapaOrigenDiario = [
            'credito_desembolso' => 'credito_prendario',
            'credito_pago_capital' => 'credito_prendario',
            'credito_pago_interes' => 'credito_prendario',
            'credito_pago_mora' => 'credito_prendario',
            'credito_gastos' => 'credito_prendario',
            'venta_contado' => 'venta_prenda',
            'venta_credito' => 'venta_prenda',
            'venta_enganche' => 'venta_prenda',
            'venta_abono' => 'venta_prenda',
            // Nuevos tipos para ventas a crédito con plan de pagos
            'venta_credito_enganche' => 'venta_prenda',
            'venta_credito_abono' => 'venta_prenda',
            'compra_directa' => 'compra',
            'caja_apertura' => 'caja',
            'caja_cierre' => 'caja',
            'caja_ingreso' => 'caja',
            'caja_egreso' => 'caja',
        ];

        return $mapaOrigenDiario[$tipoOperacion] ?? 'otro';
    }

    /**
     * Generar glosa automática
     */
    private function generarGlosa(string $tipoOperacion, array $datos): string
    {
        $codigo = $datos['codigo_credito'] ?? $datos['numero_recibo'] ?? $datos['codigo_compra'] ?? $datos['numero_caja'] ?? '';

        $glosas = [
            'credito_desembolso' => "Desembolso de crédito prendario " . $codigo,
            'credito_pago_capital' => "Pago de capital - Crédito " . $codigo,
            'credito_pago_interes' => "Pago de intereses - Crédito " . $codigo,
            'credito_pago_mora' => "Pago de mora - Crédito " . $codigo,
            'credito_gastos' => "Gastos de crédito - " . $codigo,
            'venta_contado' => "Venta al contado - Recibo " . $codigo,
            'venta_enganche' => "Venta a crédito - Enganche - Recibo " . $codigo,
            'venta_abono' => "Abono a venta a crédito - Recibo " . $codigo,
            // Nuevos tipos para ventas a crédito con plan de pagos
            'venta_credito_enganche' => "Venta a crédito con plan de pagos - Enganche - " . $codigo,
            'venta_credito_abono' => "Abono a cuota de venta a crédito - " . $codigo,
            'compra_directa' => "Compra directa de prenda - " . $codigo,
            'caja_apertura' => "Apertura de caja - " . $codigo,
            'caja_cierre' => "Cierre de caja - " . $codigo,
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

            if (!$diario->puedeAnularse()) {
                throw new Exception("El asiento no se puede anular en su estado actual");
            }

            $diario->update([
                'estado' => 'anulado',
                'anulado_por' => $usuarioId ?? Auth::id(),
                'fecha_anulacion' => now(),
                'motivo_anulacion' => $motivo,
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
