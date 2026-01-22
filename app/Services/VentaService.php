<?php

namespace App\Services;

use App\Models\Prenda;
use App\Models\CreditoPrendario;
use App\Models\Venta;
use App\Models\CajaAperturaCierre;
use App\Services\CajaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VentaService
{
    /**
     * Obtener todas las prendas disponibles para la venta
     */
    public function getPrendasEnVenta(array $filtros = [])
    {
        $query = Prenda::with(['categoriaProducto', 'creditoPrendario.cliente'])
            ->where('estado', '=', 'en_venta')
            ->whereNotNull('valor_venta')
            ->where('valor_venta', '>', 0);

        // Filtros
        if (!empty($filtros['busqueda'])) {
            $busqueda = $filtros['busqueda'];
            $query->where(function($q) use ($busqueda) {
                $q->where('descripcion', 'like', "%{$busqueda}%")
                  ->orWhere('marca', 'like', "%{$busqueda}%")
                  ->orWhere('modelo', 'like', "%{$busqueda}%")
                  ->orWhere('codigo_prenda', 'like', "%{$busqueda}%");
            });
        }

        if (!empty($filtros['categoria_id'])) {
            $query->where('categoria_producto_id', $filtros['categoria_id']);
        }

        if (!empty($filtros['precio_min'])) {
            $query->where('valor_venta', '>=', $filtros['precio_min']);
        }

        if (!empty($filtros['precio_max'])) {
            $query->where('valor_venta', '<=', $filtros['precio_max']);
        }

        if (!empty($filtros['condicion'])) {
            $query->where('condicion', $filtros['condicion']);
        }

        // Ordenamiento
        $ordenar = $filtros['ordenar'] ?? 'fecha_publicacion_venta';
        $direccion = $filtros['direccion'] ?? 'desc';
        $query->orderBy($ordenar, $direccion);

        return $query->paginate($filtros['per_page'] ?? 50);
    }

    /**
     * Marcar prenda como en venta (desde crédito vencido)
     */
    public function marcarPrendaEnVenta(Prenda $prenda, array $data)
    {
        return DB::transaction(function () use ($prenda, $data) {
            // Validar que la prenda pueda pasar a venta
            if (!in_array($prenda->estado, ['empeniado', 'vencido', 'evaluacion_venta'])) {
                throw new \Exception("La prenda debe estar empeñada, vencida o en evaluación para pasar a venta");
            }

            // Calcular precio de venta (puede venir del data o calcular automáticamente)
            $valorVenta = $data['valor_venta'] ?? ($prenda->valor_tasacion * 1.3); // 30% sobre tasación por defecto

            // Actualizar prenda
            $prenda->update([
                'estado' => 'en_venta',
                'valor_venta' => $valorVenta,
                'fecha_publicacion_venta' => now(),
                'observaciones' => ($prenda->observaciones ?? '') . "\n" .
                    "Pasó a venta el " . now()->format('d/m/Y H:i') .
                    " - " . ($data['motivo'] ?? 'Crédito vencido')
            ]);

            // Actualizar estado del crédito asociado
            if ($prenda->creditoPrendario) {
                $credito = $prenda->creditoPrendario;

                // Verificar si todas las prendas del crédito están en venta o vendidas
                $prendasRestantes = $credito->prendas()
                    ->whereNotIn('estado', ['en_venta', 'vendido', 'recuperada'])
                    ->count();

                if ($prendasRestantes === 0) {
                    $credito->update(['estado' => 'vendido']);
                }
            }

            return $prenda->fresh(['categoriaProducto', 'creditoPrendario']);
        });
    }

    /**
     * Procesar venta de prenda
     */
    public function procesarVenta(Prenda $prenda, array $data)
    {
        return DB::transaction(function () use ($prenda, $data) {
            // Validar que la prenda esté en venta
            if ($prenda->estado !== 'en_venta') {
                throw new \Exception("La prenda debe estar en estado 'en_venta' para poder venderse");
            }

            // Validar monto de venta
            $precioFinal = (float) $data['precio_final'];
            $precioMinimo = $prenda->valor_prestamo ?? ($prenda->valor_tasacion * 0.8);

            if ($precioFinal < $precioMinimo) {
                throw new \Exception("El precio de venta no puede ser menor a Q" . number_format($precioMinimo, 2));
            }

            // Obtener caja abierta del usuario
            $cajaAbierta = CajaService::getCajaAbierta();
            $metodoPago = $data['metodo_pago'] ?? 'efectivo';

            // Calcular utilidad (precio venta - valor préstamo)
            $valorPrestamo = $prenda->valor_prestamo ?? 0;
            $utilidad = $precioFinal - $valorPrestamo;

            // Registrar venta
            $venta = Venta::create([
                'prenda_id' => $prenda->id,
                'credito_prendario_id' => $prenda->credito_prendario_id,
                'codigo_venta' => $this->generarCodigoVenta(),
                'cliente_nombre' => $data['cliente_nombre'] ?? 'Cliente General',
                'cliente_nit' => $data['cliente_nit'] ?? 'C/F',
                'cliente_telefono' => $data['cliente_telefono'] ?? null,
                'cliente_email' => $data['cliente_email'] ?? null,
                'precio_publicado' => $prenda->valor_venta ?? 0,
                'precio_final' => $precioFinal,
                'descuento' => ($prenda->valor_venta ?? 0) - $precioFinal,
                'utilidad' => max(0, $utilidad),
                'metodo_pago' => $metodoPago,
                // Campos de pago detallado
                'monto_efectivo' => $metodoPago === 'efectivo' ? $precioFinal : ($data['monto_efectivo'] ?? 0),
                'monto_tarjeta' => $metodoPago === 'tarjeta' ? $precioFinal : ($data['monto_tarjeta'] ?? 0),
                'monto_transferencia' => $metodoPago === 'transferencia' ? $precioFinal : ($data['monto_transferencia'] ?? 0),
                'monto_cheque' => $metodoPago === 'cheque' ? $precioFinal : ($data['monto_cheque'] ?? 0),
                'referencia_pago' => $data['referencia_pago'] ?? null,
                'referencia_tarjeta' => $data['referencia_tarjeta'] ?? null,
                'referencia_transferencia' => $data['referencia_transferencia'] ?? null,
                'referencia_cheque' => $data['referencia_cheque'] ?? null,
                'banco' => $data['banco'] ?? null,
                'vendedor_id' => Auth::id() ?? 1,
                'sucursal_id' => Auth::user()->sucursal_id ?? 1,
                'caja_id' => $cajaAbierta?->id,
                'fecha_venta' => now(),
                'observaciones' => $data['observaciones'] ?? null,
                'estado' => 'completada'
            ]);

            // Actualizar prenda
            $prenda->update([
                'estado' => 'vendido',
                'fecha_venta' => now(),
                'comprador_id' => $venta->id,
                'precio_venta' => $precioFinal
            ]);

            // Si el crédito existe, actualizar saldo con la venta
            if ($prenda->creditoPrendario) {
                $this->aplicarVentaACredito($prenda->creditoPrendario, $precioFinal);
            }

            // ========================================
            // REGISTRAR INGRESO A CAJA
            // ========================================
            // Solo registrar en caja si el pago incluye efectivo
            $montoEfectivo = 0;
            if ($metodoPago === 'efectivo') {
                $montoEfectivo = $precioFinal;
            } elseif ($metodoPago === 'mixto') {
                $montoEfectivo = (float) ($data['monto_efectivo'] ?? 0);
            }

            if ($montoEfectivo > 0) {
                CajaService::registrarVenta(
                    $montoEfectivo,
                    $venta->codigo_venta,
                    $prenda->codigo_prenda,
                    $metodoPago,
                    $venta->cliente_nombre
                );
            }

            return $venta->fresh(['prenda', 'vendedor']);
        });
    }

    /**
     * Aplicar el monto de venta al saldo del crédito
     */
    private function aplicarVentaACredito(CreditoPrendario $credito, float $montoVenta)
    {
        // Calcular deuda actual
        $deudaTotal = $credito->capital_pendiente +
                     ($credito->interes_generado - $credito->interes_pagado) +
                     ($credito->mora_generada - $credito->mora_pagada);

        if ($montoVenta >= $deudaTotal) {
            // La venta cubre toda la deuda
            $credito->update([
                'capital_pendiente' => 0,
                'interes_pagado' => $credito->interes_generado,
                'mora_pagada' => $credito->mora_generada,
                'estado' => 'pagado',
                'fecha_cancelacion' => now()
            ]);
        } else {
            // La venta cubre parcialmente - aplicar prelación: mora > interés > capital
            $restante = $montoVenta;

            $moraPendiente = $credito->mora_generada - $credito->mora_pagada;
            $pagoMora = min($restante, $moraPendiente);
            $restante -= $pagoMora;

            $interesPendiente = $credito->interes_generado - $credito->interes_pagado;
            $pagoInteres = min($restante, $interesPendiente);
            $restante -= $pagoInteres;

            $pagoCapital = $restante;

            $credito->update([
                'capital_pendiente' => max(0, $credito->capital_pendiente - $pagoCapital),
                'capital_pagado' => (float)$credito->capital_pagado + $pagoCapital,
                'interes_pagado' => (float)$credito->interes_pagado + $pagoInteres,
                'mora_pagada' => (float)$credito->mora_pagada + $pagoMora
            ]);
        }
    }

    /**
     * Generar código único de venta
     */
    private function generarCodigoVenta(): string
    {
        do {
            $codigo = 'VEN-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $existe = Venta::where('codigo_venta', $codigo)->exists();
        } while ($existe);

        return $codigo;
    }

    /**
     * Cancelar venta y devolver prenda a inventario
     */
    public function cancelarVenta(Venta $venta, string $motivo)
    {
        return DB::transaction(function () use ($venta, $motivo) {
            if ($venta->estado === 'cancelada') {
                throw new \Exception("Esta venta ya fue cancelada");
            }

            // Devolver prenda a estado en_venta
            $venta->prenda->update([
                'estado' => 'en_venta',
                'comprador_id' => null,
                'observaciones' => ($venta->prenda->observaciones ?? '') . "\n" .
                    "Venta cancelada el " . now()->format('d/m/Y H:i') . " - " . $motivo
            ]);

            // Marcar venta como cancelada
            $venta->update([
                'estado' => 'cancelada',
                'fecha_cancelacion' => now(),
                'motivo_cancelacion' => $motivo
            ]);

            // Revertir aplicación al crédito si existe
            if ($venta->creditoPrendario) {
                // Aquí podrías implementar la reversión del pago si es necesario
                // Por ahora solo registramos la cancelación
            }

            return $venta->fresh(['prenda']);
        });
    }
}
