<?php

namespace App\Services;

use App\Models\Prenda;
use App\Models\MovimientoCaja;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Enums\EstadoPrenda;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CompraService
{
    /**
     * Procesar una compra directa de un artículo
     *
     * @param array $data {
     *   cliente_id: int,
     *   categoria_producto_id: int,
     *   descripcion: string,
     *   valor_tasacion: float,
     *   monto_pagado: float, (se guardará en valor_prestamo)
     *   precio_venta: float,
     *   metodo_pago: string,
     *   observaciones: string,
     *   sucursal_id: int (opcional)
     * }
     * @return Prenda
     */
    public function procesarCompraDirecta(array $data): Prenda
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();
            $sucursalId = $data['sucursal_id'] ?? $user->sucursal_id;

            if (!$sucursalId) {
                $sucursalId = Sucursal::first()?->id;
            }

            // 1. Crear la Prenda
            $prenda = Prenda::create([
                'credito_prendario_id' => null,
                'categoria_producto_id' => $data['categoria_producto_id'],
                'tasador_id' => $user->id,
                'codigo_prenda' => $this->generarCodigoPrenda($data['categoria_producto_id']),
                'descripcion' => $data['descripcion'],
                'marca' => $data['marca'] ?? null,
                'modelo' => $data['modelo'] ?? null,
                'serie' => $data['serie'] ?? null,
                'color' => $data['color'] ?? null,
                'valor_tasacion' => $data['valor_tasacion'] ?? $data['monto_pagado'],
                'valor_prestamo' => $data['monto_pagado'], // Lo que pagamos por ella
                'precio_venta' => $data['precio_venta'],   // A lo que la venderemos
                'estado' => EstadoPrenda::EN_VENTA->value, // Directamente a la venta
                'condicion' => $data['condicion'] ?? 'buena',
                'fecha_ingreso' => now(),
                'fecha_tasacion' => now(),
                'tipo_ingreso' => 'compra_directa',
                'sucursal_id' => $sucursalId,
                'observaciones' => $data['observaciones'] ?? 'Compra directa realizada',
                'codigo_cliente_propietario' => $this->obtenerCodigoCliente($data['cliente_id']),
            ]);

            // 2. Registrar el egreso en Caja (si es efectivo)
            if (($data['metodo_pago'] ?? 'efectivo') === 'efectivo') {
                $this->registrarEgresoCaja($prenda, $data['monto_pagado'], $sucursalId);
            }

            Log::info("Compra directa procesada. Prenda: {$prenda->codigo_prenda}, Monto: {$data['monto_pagado']}");

            return $prenda;
        });
    }

    /**
     * Generar código único para la prenda
     */
    private function generarCodigoPrenda($categoriaId): string
    {
        $prefix = 'CMP-' . str_pad($categoriaId, 2, '0', STR_PAD_LEFT);
        $count = Prenda::where('codigo_prenda', 'like', $prefix . '%')->count() + 1;
        
        do {
            $codigo = $prefix . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
            $existe = Prenda::where('codigo_prenda', $codigo)->exists();
            if ($existe) $count++;
        } while ($existe);

        return $codigo;
    }

    /**
     * Obtener código del cliente
     */
    private function obtenerCodigoCliente($clienteId): ?string
    {
        if (!$clienteId) return null;
        return Cliente::find($clienteId)?->codigo_cliente;
    }

    /**
     * Registrar egreso en caja
     */
    private function registrarEgresoCaja(Prenda $prenda, float $monto, int $sucursalId): void
    {
        // Nota: Asumimos que existe una caja abierta en la sesión o la buscamos por usuario/sucursal
        $cajaAbiertaId = session('caja_abierta_id') ?? $this->obtenerCajaAbiertaId($sucursalId);

        if (!$cajaAbiertaId) {
            Log::warning("No se pudo registrar egreso en caja para compra {$prenda->codigo_prenda}: No hay caja abierta");
            return;
        }

        MovimientoCaja::create([
            'caja_apertura_cierre_id' => $cajaAbiertaId,
            'tipo' => 'egreso',
            'concepto' => 'compra_prenda',
            'descripcion' => "Compra directa de prenda {$prenda->codigo_prenda}",
            'monto' => $monto,
            'referencia' => $prenda->codigo_prenda,
            'usuario_id' => Auth::id(),
            'created_at' => now()
        ]);
    }

    /**
     * Obtener ID de caja abierta (fallback si no está en sesión)
     */
    private function obtenerCajaAbiertaId(int $sucursalId): ?int
    {
        return \App\Models\CajaAperturaCierre::where('sucursal_id', $sucursalId)
            ->whereNull('fecha_cierre')
            ->orderBy('created_at', 'desc')
            ->first()?->id;
    }
}
