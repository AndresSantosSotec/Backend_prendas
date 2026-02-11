<?php

namespace App\Http\Controllers;

use App\Models\Cotizacion;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class CotizacionController extends Controller
{
    /**
     * Listar cotizaciones con filtros
     */
    public function index(Request $request)
    {
        try {
            $query = Cotizacion::with(['cliente', 'sucursal', 'usuario', 'venta'])
                ->orderBy('created_at', 'desc');

            // Filtros
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->filled('tipo_venta')) {
                $query->where('tipo_venta', $request->tipo_venta);
            }

            if ($request->filled('fecha_inicio')) {
                $query->whereDate('fecha', '>=', $request->fecha_inicio);
            }

            if ($request->filled('fecha_fin')) {
                $query->whereDate('fecha', '<=', $request->fecha_fin);
            }

            if ($request->filled('cliente_id')) {
                $query->where('cliente_id', $request->cliente_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('numero_cotizacion', 'like', "%{$search}%")
                      ->orWhere('cliente_nombre', 'like', "%{$search}%");
                });
            }

            $cotizaciones = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $cotizaciones->items(),
                'meta' => [
                    'current_page' => $cotizaciones->currentPage(),
                    'last_page' => $cotizaciones->lastPage(),
                    'per_page' => $cotizaciones->perPage(),
                    'total' => $cotizaciones->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar cotizaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las cotizaciones',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Crear nueva cotización
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'cliente_nombre' => 'nullable|string|max:200',
            'productos' => 'required|array|min:1',
            'productos.*.prenda_id' => 'required|exists:prendas,id',
            'productos.*.codigo_prenda' => 'required|string',
            'productos.*.descripcion' => 'required|string',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
            'productos.*.cantidad' => 'required|integer|min:1',
            'subtotal' => 'required|numeric|min:0',
            'descuento' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'tipo_venta' => 'required|in:contado,credito',
            'plan_pagos' => 'required_if:tipo_venta,credito|nullable|array',
            'plan_pagos.numero_cuotas' => 'required_if:tipo_venta,credito|integer|min:1',
            'plan_pagos.tasa_interes' => 'required_if:tipo_venta,credito|numeric|min:0',
            'plan_pagos.monto_cuota' => 'required_if:tipo_venta,credito|numeric|min:0',
            'plan_pagos.total_con_intereses' => 'required_if:tipo_venta,credito|numeric|min:0',
            'observaciones' => 'nullable|string|max:1000',
            'vigencia_dias' => 'nullable|integer|min:1|max:90',
        ]);

        try {
            return DB::transaction(function () use ($request, $validated) {
                $user = Auth::user();
                $sucursalId = $request->sucursal_id ?? $user->sucursal_id ?? 1;

                // Generar número de cotización
                $numeroCotizacion = $this->generarNumeroCotizacion($sucursalId);

                // Calcular fecha de vencimiento (por defecto 15 días)
                $vigenciaDias = $validated['vigencia_dias'] ?? 15;
                $fechaVencimiento = Carbon::now()->addDays($vigenciaDias);

                // Crear cotización
                $cotizacion = Cotizacion::create([
                    'numero_cotizacion' => $numeroCotizacion,
                    'fecha' => now(),
                    'cliente_id' => $validated['cliente_id'] ?? null,
                    'cliente_nombre' => $validated['cliente_nombre'] ?? ($validated['cliente_id'] ? Cliente::find($validated['cliente_id'])->nombre_completo : 'Cliente General'),
                    'sucursal_id' => $sucursalId,
                    'user_id' => $user->id,
                    'productos' => $validated['productos'],
                    'subtotal' => $validated['subtotal'],
                    'descuento' => $validated['descuento'] ?? 0,
                    'total' => $validated['total'],
                    'tipo_venta' => $validated['tipo_venta'],
                    'plan_pagos' => $validated['plan_pagos'] ?? null,
                    'observaciones' => $validated['observaciones'] ?? null,
                    'estado' => 'pendiente',
                    'fecha_vencimiento' => $fechaVencimiento,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Cotización generada exitosamente',
                    'data' => $cotizacion->load(['cliente', 'sucursal', 'usuario'])
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Error al crear cotización: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar la cotización',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Ver detalle de una cotización
     */
    public function show($id)
    {
        try {
            $cotizacion = Cotizacion::with(['cliente', 'sucursal', 'usuario', 'venta'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $cotizacion
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener cotización: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Actualizar cotización
     */
    public function update(Request $request, $id)
    {
        try {
            $cotizacion = Cotizacion::findOrFail($id);

            if ($cotizacion->estado !== 'pendiente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden editar cotizaciones pendientes'
                ], 400);
            }

            $validated = $request->validate([
                'observaciones' => 'nullable|string|max:1000',
                'estado' => 'nullable|in:pendiente,cancelada',
            ]);

            $cotizacion->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Cotización actualizada',
                'data' => $cotizacion->load(['cliente', 'sucursal', 'usuario'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar cotización: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la cotización',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Eliminar/cancelar cotización
     */
    public function destroy($id)
    {
        try {
            $cotizacion = Cotizacion::findOrFail($id);

            if ($cotizacion->estado === 'convertida') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar una cotización ya convertida en venta'
                ], 400);
            }

            $cotizacion->cancelar();
            $cotizacion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cotización eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar cotización: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la cotización',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Generar PDF de cotización
     */
    public function generarPDF($id)
    {
        try {
            $cotizacion = Cotizacion::with(['cliente', 'sucursal', 'usuario'])
                ->findOrFail($id);

            // Seleccionar vista según tipo de venta
            if ($cotizacion->tipo_venta === 'credito') {
                $vista = 'cotizaciones.credito';
            } else {
                $vista = 'cotizaciones.contado';
            }

            $pdf = PDF::loadView($vista, compact('cotizacion'));

            $nombreArchivo = 'cotizacion_' . $cotizacion->numero_cotizacion . '.pdf';

            return $pdf->download($nombreArchivo);
        } catch (\Exception $e) {
            Log::error('Error al generar PDF de cotización: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el PDF',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Convertir cotización a venta
     */
    public function convertirAVenta($id)
    {
        try {
            $cotizacion = Cotizacion::findOrFail($id);

            if (!$cotizacion->puedeConvertirse()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cotización no puede convertirse en venta (estado: ' . $cotizacion->estado . ')'
                ], 400);
            }

            // TODO: Implementar lógica de creación de venta
            // Por ahora solo retornamos la cotización preparada para conversión

            return response()->json([
                'success' => true,
                'message' => 'Cotización lista para convertir',
                'data' => $cotizacion
            ]);
        } catch (\Exception $e) {
            Log::error('Error al convertir cotización: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al convertir la cotización',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Generar número único de cotización
     */
    private function generarNumeroCotizacion($sucursalId): string
    {
        $fecha = now();
        $año = $fecha->format('Y');
        $mes = $fecha->format('m');

        // Formato: COT-SUCURSAL-AÑO-MES-SECUENCIA
        $prefijo = "COT-{$sucursalId}-{$año}{$mes}-";

        // Obtener último número de la serie
        $ultimaCotizacion = Cotizacion::where('numero_cotizacion', 'like', $prefijo . '%')
            ->orderBy('numero_cotizacion', 'desc')
            ->first();

        if ($ultimaCotizacion) {
            $ultimoNumero = (int) substr($ultimaCotizacion->numero_cotizacion, -4);
            $nuevoNumero = $ultimoNumero + 1;
        } else {
            $nuevoNumero = 1;
        }

        return $prefijo . str_pad($nuevoNumero, 4, '0', STR_PAD_LEFT);
    }
}
