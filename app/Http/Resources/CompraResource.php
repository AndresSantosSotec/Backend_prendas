<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompraResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_compra' => $this->codigo_compra,
            'codigo_prenda_generado' => $this->codigo_prenda_generado,

            // Cliente
            'cliente_id' => $this->cliente_id,
            'cliente_nombre' => $this->cliente_nombre,
            'cliente_documento' => $this->cliente_documento,
            'cliente_telefono' => $this->cliente_telefono,
            'cliente_codigo' => $this->cliente_codigo,
            'cliente' => $this->whenLoaded('cliente', function () {
                return [
                    'id' => $this->cliente->id,
                    'nombre_completo' => trim($this->cliente->nombres . ' ' . $this->cliente->apellidos),
                    'codigo' => $this->cliente->codigo_cliente,
                ];
            }),

            // Categoría
            'categoria_producto_id' => $this->categoria_producto_id,
            'categoria_nombre' => $this->categoria_nombre,
            'categoria' => $this->whenLoaded('categoriaProducto', function () {
                return [
                    'id' => $this->categoriaProducto->id,
                    'nombre' => $this->categoriaProducto->nombre,
                ];
            }),

            // Detalles de la prenda
            'descripcion' => $this->descripcion,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'serie' => $this->serie,
            'color' => $this->color,
            'condicion' => $this->condicion,

            // Valores
            'valor_tasacion' => (float) $this->valor_tasacion,
            'monto_pagado' => (float) $this->monto_pagado,
            'precio_venta_sugerido' => (float) $this->precio_venta_sugerido,
            'margen_esperado' => $this->margen_esperado,

            // Financiero
            'metodo_pago' => $this->metodo_pago,
            'genera_egreso_caja' => (bool) $this->genera_egreso_caja,

            // Tracking
            'estado' => $this->estado,
            'observaciones' => $this->observaciones,
            'fecha_compra' => $this->fecha_compra?->format('Y-m-d H:i:s'),
            'fecha_compra_formateada' => $this->fecha_compra?->format('d/m/Y H:i'),

            // Relaciones
            'prenda_id' => $this->prenda_id,
            'prenda' => $this->whenLoaded('prenda', function () {
                return [
                    'id' => $this->prenda->id,
                    'codigo_prenda' => $this->prenda->codigo_prenda,
                    'estado' => $this->prenda->estado,
                ];
            }),

            'sucursal' => $this->whenLoaded('sucursal', function () {
                return [
                    'id' => $this->sucursal->id,
                    'nombre' => $this->sucursal->nombre,
                ];
            }),

            'usuario' => $this->whenLoaded('usuario', function () {
                return [
                    'id' => $this->usuario->id,
                    'nombre' => $this->usuario->name ?? $this->usuario->username,
                ];
            }),

            'movimiento_caja' => $this->whenLoaded('movimientoCaja', function () {
                return $this->movimientoCaja ? [
                    'id' => $this->movimientoCaja->id,
                    'monto' => (float) $this->movimientoCaja->monto,
                    'fecha' => $this->movimientoCaja->created_at?->format('d/m/Y H:i'),
                ] : null;
            }),

            // Campos dinámicos (tabla relacional)
            'campos_dinamicos' => $this->whenLoaded('camposDinamicos', function () {
                return $this->camposDinamicos->map(function ($campo) {
                    return [
                        'campo_nombre' => $campo->campo_nombre,
                        'campo_tipo' => $campo->campo_tipo,
                        'valor' => $campo->valor,
                        'valor_formateado' => $campo->valor_formateado,
                    ];
                });
            }),

            // Campos dinámicos (JSON en datos_adicionales)
            'datos_adicionales' => $this->datos_adicionales,

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
