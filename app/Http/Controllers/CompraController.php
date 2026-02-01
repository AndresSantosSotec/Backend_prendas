<?php

namespace App\Http\Controllers;

use App\Services\CompraService;
use App\Http\Resources\PrendaResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompraController extends Controller
{
    protected $compraService;

    public function __construct(CompraService $compraService)
    {
        $this->compraService = $compraService;
    }

    /**
     * Registrar una nueva compra directa
     */
    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'categoria_producto_id' => 'required|exists:categoria_productos,id',
            'descripcion' => 'required|string|max:500',
            'valor_tasacion' => 'required|numeric|min:0',
            'monto_pagado' => 'required|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
            'metodo_pago' => 'nullable|string|in:efectivo,transferencia,cheque',
            'observaciones' => 'nullable|string|max:1000',
            'condicion' => 'nullable|string|in:excelente,muy_buena,buena,regular,mala',
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'serie' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
        ]);

        try {
            $prenda = $this->compraService->procesarCompraDirecta($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Compra registrada exitosamente',
                'data' => new PrendaResource($prenda)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al registrar compra directa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la compra',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 400);
        }
    }
}
