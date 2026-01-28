<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrendaResource extends JsonResource
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
            'descripcion' => $this->descripcion,
            'categoria' => $this->categoriaProducto ? $this->categoriaProducto->nombre : $this->categoria_producto_id,
            'categoriaId' => $this->categoria_producto_id,
            'fotos' => $this->fotos ?? [],
            'fotoPrincipal' => $this->foto_principal,
            'avaluo' => (float) $this->valor_tasacion ?? 0,
            'precioVenta' => (float) $this->valor_venta ?? 0,
            'precioVentaReal' => (float) $this->precio_venta ?? null, // Precio al que realmente se vendió
            'precioMinimo' => (float) ($this->valor_venta * 0.8) ?? 0, // 20% de descuento máximo
            'descuentoMaximo' => 20,
            'estado' => $this->mapearEstado($this->estado),
            'fechaIngreso' => $this->fecha_ingreso ? $this->fecha_ingreso->toISOString() : null,
            'fechaVenta' => $this->fecha_venta ? $this->fecha_venta->toISOString() : null,
            'fechaPasaVenta' => $this->fecha_venta ? $this->fecha_venta->toISOString() : null,
            'creditoId' => $this->credito_prendario_id,
            'tasadorId' => $this->tasador_id,
            'ubicacionBodega' => $this->ubicacion_fisica ?? ($this->seccion ? "{$this->seccion}-{$this->estante}" : null),
            'sucursal' => $this->creditoPrendario && $this->creditoPrendario->sucursal ? $this->creditoPrendario->sucursal->nombre : null,
            'codigoQR' => $this->codigo_prenda,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'serie' => $this->serie,
            'color' => $this->color,
            'condicion' => $this->condicion,
            'observaciones' => $this->observaciones,

            // Datos adicionales
            'codigoPrenda' => $this->codigo_prenda,
            'valorPrestamo' => (float) $this->valor_prestamo ?? 0,

            // Información del cliente si está disponible
            'cliente' => $this->when($this->creditoPrendario && $this->creditoPrendario->cliente, function () {
                return [
                    'id' => $this->creditoPrendario->cliente->id,
                    'nombres' => $this->creditoPrendario->cliente->nombres,
                    'apellidos' => $this->creditoPrendario->cliente->apellidos,
                    'dpi' => $this->creditoPrendario->cliente->dpi,
                ];
            }),

            // Metadatos
            'creadoEn' => $this->created_at ? $this->created_at->toISOString() : null,
            'actualizadoEn' => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }

    /**
     * Mapear estado de BD a estado del frontend
     */
    private function mapearEstado($estadoBD)
    {
        $mapeo = [
            'en_custodia' => 'empeniado',
            'vencida' => 'vencido',
            'en_evaluacion' => 'evaluacion_venta',
            'en_venta' => 'en_venta',
            'apartada' => 'apartado',
            'vendida' => 'vendido',
            'recuperada' => 'recuperado',
            'extraviada' => 'cancelado',
        ];

        return $mapeo[$estadoBD] ?? $estadoBD;
    }
}
