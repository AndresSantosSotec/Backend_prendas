<?php

namespace App\Services;

use App\Models\Contabilidad\CtbDiario;
use App\Models\Contabilidad\CtbMovimiento;
use App\Models\Contabilidad\CtbNomenclatura;
use App\Models\Contabilidad\CtbTipoPoliza;
use App\Models\CreditoMovimiento;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContabilidadService
{
    /**
     * Generar asiento contable automáticamente según el tipo de operación
     */
    public function generarAsientoAutomatico($origen, string $tipoOperacion)
    {
        try {
            DB::beginTransaction();

            $asiento = match ($tipoOperacion) {
                'desembolso_credito' => $this->generarAsientoDesembolso($origen),
                'pago_credito' => $this->generarAsientoPago($origen),
                'venta_prenda' => $this->generarAsientoVenta($origen),
                default => throw new \Exception("Tipo de operación no soportado: {$tipoOperacion}")
            };

            // Validar que cuadre
            if (!$asiento->validarCuadre()) {
                throw new \Exception('El asiento no cuadra. Total Debe != Total Haber');
            }

            DB::commit();

            Log::info("Asiento contable generado: {$asiento->numero_comprobante}", [
                'asiento_id' => $asiento->id,
                'tipo_operacion' => $tipoOperacion,
            ]);

            return $asiento;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al generar asiento contable: " . $e->getMessage(), [
                'tipo_operacion' => $tipoOperacion,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Generar asiento de desembolso de crédito
     *
     * DEBE: Créditos Prendarios por Cobrar
     * HABER: Caja General / Banco
     */
    public function generarAsientoDesembolso(CreditoMovimiento $movimiento)
    {
        $credito = $movimiento->creditoPrendario;

        // Obtener el tipo de póliza "PE" (Póliza de Egresos)
        $tipoPoliza = CtbTipoPoliza::porCodigo('PE')->first();

        if (!$tipoPoliza) {
            throw new \Exception('No se encontró el tipo de póliza PE (Póliza de Egresos)');
        }

        // Crear el asiento
        $diario = CtbDiario::create([
            'numero_comprobante' => CtbDiario::generarNumeroComprobante('PE', $movimiento->sucursal_id),
            'tipo_poliza_id' => $tipoPoliza->id,
            'moneda_id' => $credito->moneda_id ?? 1,
            'tipo_origen' => 'credito_prendario',
            'credito_prendario_id' => $movimiento->credito_prendario_id,
            'movimiento_credito_id' => $movimiento->id,
            'numero_documento' => $movimiento->numero_recibo ?? $movimiento->numero_movimiento,
            'glosa' => "Desembolso de crédito prendario {$credito->numero_credito} - Cliente: {$credito->cliente->nombre_completo}",
            'fecha_documento' => $movimiento->fecha_movimiento,
            'fecha_contabilizacion' => now()->toDateString(),
            'sucursal_id' => $movimiento->sucursal_id,
            'usuario_id' => $movimiento->usuario_id,
            'estado' => 'registrado',
            'editable' => false,
        ]);

        // DEBE: Créditos Prendarios por Cobrar
        $cuentaCreditos = $this->obtenerCuenta('1101.02.001');
        CtbMovimiento::create([
            'diario_id' => $diario->id,
            'cuenta_contable_id' => $cuentaCreditos->id,
            'debe' => $movimiento->monto_total,
            'haber' => 0,
            'numero_comprobante' => $diario->numero_comprobante,
            'detalle' => "Desembolso crédito {$credito->numero_credito}",
            'cliente_id' => $credito->cliente_id,
        ]);

        // HABER: Caja General o Banco (según forma de desembolso)
        $cuentaEgreso = $this->determinarCuentaEgreso($movimiento->forma_pago);
        CtbMovimiento::create([
            'diario_id' => $diario->id,
            'cuenta_contable_id' => $cuentaEgreso->id,
            'debe' => 0,
            'haber' => $movimiento->monto_total,
            'numero_comprobante' => $diario->numero_comprobante,
            'detalle' => "Desembolso crédito {$credito->numero_credito} - {$movimiento->forma_pago}",
        ]);

        return $diario;
    }

    /**
     * Generar asiento de pago de crédito
     *
     * DEBE: Caja General / Banco
     * HABER: Créditos Prendarios por Cobrar (capital)
     * HABER: Intereses sobre Créditos (intereses)
     * HABER: Mora sobre Créditos (mora)
     */
    public function generarAsientoPago(CreditoMovimiento $movimiento)
    {
        $credito = $movimiento->creditoPrendario;

        // Obtener el tipo de póliza "PI" (Póliza de Ingresos)
        $tipoPoliza = CtbTipoPoliza::porCodigo('PI')->first();

        if (!$tipoPoliza) {
            throw new \Exception('No se encontró el tipo de póliza PI (Póliza de Ingresos)');
        }

        // Crear el asiento
        $diario = CtbDiario::create([
            'numero_comprobante' => CtbDiario::generarNumeroComprobante('PI', $movimiento->sucursal_id),
            'tipo_poliza_id' => $tipoPoliza->id,
            'moneda_id' => $credito->moneda_id ?? 1,
            'tipo_origen' => 'credito_prendario',
            'credito_prendario_id' => $movimiento->credito_prendario_id,
            'movimiento_credito_id' => $movimiento->id,
            'numero_documento' => $movimiento->numero_recibo ?? $movimiento->numero_movimiento,
            'glosa' => "Pago de crédito prendario {$credito->numero_credito} - Cliente: {$credito->cliente->nombre_completo}",
            'fecha_documento' => $movimiento->fecha_movimiento,
            'fecha_contabilizacion' => now()->toDateString(),
            'sucursal_id' => $movimiento->sucursal_id,
            'usuario_id' => $movimiento->usuario_id,
            'estado' => 'registrado',
            'editable' => false,
        ]);

        // DEBE: Caja General o Banco (según forma de pago)
        $cuentaIngreso = $this->determinarCuentaIngreso($movimiento->forma_pago);
        CtbMovimiento::create([
            'diario_id' => $diario->id,
            'cuenta_contable_id' => $cuentaIngreso->id,
            'debe' => $movimiento->monto_total,
            'haber' => 0,
            'numero_comprobante' => $diario->numero_comprobante,
            'detalle' => "Pago crédito {$credito->numero_credito} - {$movimiento->forma_pago}",
        ]);

        // HABER: Créditos Prendarios por Cobrar (capital)
        if ($movimiento->capital > 0) {
            $cuentaCreditos = $this->obtenerCuenta('1101.02.001');
            CtbMovimiento::create([
                'diario_id' => $diario->id,
                'cuenta_contable_id' => $cuentaCreditos->id,
                'debe' => 0,
                'haber' => $movimiento->capital,
                'numero_comprobante' => $diario->numero_comprobante,
                'detalle' => "Pago capital crédito {$credito->numero_credito}",
                'cliente_id' => $credito->cliente_id,
            ]);
        }

        // HABER: Intereses sobre Créditos Prendarios
        if ($movimiento->interes > 0) {
            $cuentaIntereses = $this->obtenerCuenta('4101.01');
            CtbMovimiento::create([
                'diario_id' => $diario->id,
                'cuenta_contable_id' => $cuentaIntereses->id,
                'debe' => 0,
                'haber' => $movimiento->interes,
                'numero_comprobante' => $diario->numero_comprobante,
                'detalle' => "Intereses crédito {$credito->numero_credito}",
                'cliente_id' => $credito->cliente_id,
            ]);
        }

        // HABER: Mora sobre Créditos
        if ($movimiento->mora > 0) {
            $cuentaMora = $this->obtenerCuenta('4101.02');
            CtbMovimiento::create([
                'diario_id' => $diario->id,
                'cuenta_contable_id' => $cuentaMora->id,
                'debe' => 0,
                'haber' => $movimiento->mora,
                'numero_comprobante' => $diario->numero_comprobante,
                'detalle' => "Mora crédito {$credito->numero_credito}",
                'cliente_id' => $credito->cliente_id,
            ]);
        }

        // HABER: Otros cargos (si aplica)
        if ($movimiento->otros_cargos > 0) {
            $cuentaComisiones = $this->obtenerCuenta('4101.03');
            CtbMovimiento::create([
                'diario_id' => $diario->id,
                'cuenta_contable_id' => $cuentaComisiones->id,
                'debe' => 0,
                'haber' => $movimiento->otros_cargos,
                'numero_comprobante' => $diario->numero_comprobante,
                'detalle' => "Otros cargos crédito {$credito->numero_credito}",
                'cliente_id' => $credito->cliente_id,
            ]);
        }

        return $diario;
    }

    /**
     * Generar asiento de venta de prenda
     *
     * DEBE: Caja General
     * DEBE: Costo de Prendas Vendidas
     * HABER: Venta de Prendas
     * HABER: Prendas para Venta (inventario)
     */
    public function generarAsientoVenta(Venta $venta)
    {
        // Obtener el tipo de póliza "PI" (Póliza de Ingresos)
        $tipoPoliza = CtbTipoPoliza::porCodigo('PI')->first();

        if (!$tipoPoliza) {
            throw new \Exception('No se encontró el tipo de póliza PI (Póliza de Ingresos)');
        }

        // Crear el asiento
        $diario = CtbDiario::create([
            'numero_comprobante' => CtbDiario::generarNumeroComprobante('PI', $venta->sucursal_id),
            'tipo_poliza_id' => $tipoPoliza->id,
            'moneda_id' => $venta->moneda_id ?? 1,
            'tipo_origen' => 'venta_prenda',
            'venta_id' => $venta->id,
            'numero_documento' => $venta->codigo_venta ?? "VENTA-{$venta->id}",
            'glosa' => "Venta de prenda - Documento {$venta->codigo_venta}",
            'fecha_documento' => $venta->fecha_venta ?? now(),
            'fecha_contabilizacion' => now()->toDateString(),
            'sucursal_id' => $venta->sucursal_id,
            'usuario_id' => $venta->vendedor_id ?? auth()->id(),
            'estado' => 'registrado',
            'editable' => false,
        ]);

        // DEBE: Caja General (total de la venta)
        $cuentaCaja = $this->obtenerCuenta('1101.01.001');
        CtbMovimiento::create([
            'diario_id' => $diario->id,
            'cuenta_contable_id' => $cuentaCaja->id,
            'debe' => $venta->total_final,
            'haber' => 0,
            'numero_comprobante' => $diario->numero_comprobante,
            'detalle' => "Venta {$venta->numero_documento}",
        ]);

        // DEBE: Costo de Prendas Vendidas (precio de costo)
        if (isset($venta->costo_total) && $venta->costo_total > 0) {
            $cuentaCosto = $this->obtenerCuenta('6101');
            CtbMovimiento::create([
                'diario_id' => $diario->id,
                'cuenta_contable_id' => $cuentaCosto->id,
                'debe' => $venta->costo_total,
                'haber' => 0,
                'numero_comprobante' => $diario->numero_comprobante,
                'detalle' => "Costo venta {$venta->numero_documento}",
            ]);
        }

        // HABER: Venta de Prendas (ingreso por venta)
        $cuentaVentas = $this->obtenerCuenta('4101.04');
        CtbMovimiento::create([
            'diario_id' => $diario->id,
            'cuenta_contable_id' => $cuentaVentas->id,
            'debe' => 0,
            'haber' => $venta->total_final,
            'numero_comprobante' => $diario->numero_comprobante,
            'detalle' => "Venta {$venta->numero_documento}",
        ]);

        // HABER: Prendas para Venta (salida de inventario)
        if (isset($venta->costo_total) && $venta->costo_total > 0) {
            $cuentaInventario = $this->obtenerCuenta('1101.03.002');
            CtbMovimiento::create([
                'diario_id' => $diario->id,
                'cuenta_contable_id' => $cuentaInventario->id,
                'debe' => 0,
                'haber' => $venta->costo_total,
                'numero_comprobante' => $diario->numero_comprobante,
                'detalle' => "Salida inventario venta {$venta->numero_documento}",
            ]);
        }

        return $diario;
    }

    /**
     * Obtener cuenta contable por código
     */
    private function obtenerCuenta(string $codigo): CtbNomenclatura
    {
        $cuenta = CtbNomenclatura::porCodigo($codigo)->first();

        if (!$cuenta) {
            throw new \Exception("No se encontró la cuenta contable: {$codigo}");
        }

        if (!$cuenta->acepta_movimientos) {
            throw new \Exception("La cuenta {$codigo} no acepta movimientos");
        }

        return $cuenta;
    }

    /**
     * Determinar cuenta de egreso según forma de pago
     */
    private function determinarCuentaEgreso(string $formaPago): CtbNomenclatura
    {
        return match ($formaPago) {
            'efectivo' => $this->obtenerCuenta('1101.01.001'), // Caja General
            'transferencia', 'cheque' => $this->obtenerCuenta('1101.01.003'), // Bancos
            default => $this->obtenerCuenta('1101.01.001'), // Por defecto Caja
        };
    }

    /**
     * Determinar cuenta de ingreso según forma de pago
     */
    private function determinarCuentaIngreso(string $formaPago): CtbNomenclatura
    {
        return match ($formaPago) {
            'efectivo' => $this->obtenerCuenta('1101.01.001'), // Caja General
            'transferencia', 'cheque' => $this->obtenerCuenta('1101.01.003'), // Bancos
            default => $this->obtenerCuenta('1101.01.001'), // Por defecto Caja
        };
    }

    /**
     * Anular un asiento contable
     */
    public function anularAsiento(CtbDiario $diario, int $usuarioId, string $motivo)
    {
        $diario->anular($usuarioId, $motivo);

        Log::info("Asiento contable anulado: {$diario->numero_comprobante}", [
            'asiento_id' => $diario->id,
            'motivo' => $motivo,
            'usuario_id' => $usuarioId,
        ]);
    }
}
