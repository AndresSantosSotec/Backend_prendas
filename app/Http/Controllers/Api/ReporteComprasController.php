<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\CategoriaProducto;
use App\Exports\ComprasExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ReporteComprasController extends Controller
{
    /**
     * Generar reporte PDF de compras
     */
    public function generarPDF(Request $request)
    {
        try {
            // Obtener filtros
            $filtros = $this->obtenerFiltros($request);

            // Obtener compras con filtros aplicados
            $compras = $this->obtenerComprasFiltradas($filtros);

            // Calcular estadísticas
            $estadisticas = $this->calcularEstadisticas($compras);

            // Obtener información adicional
            $sucursal = null;
            if (!empty($filtros['sucursal_id'])) {
                $sucursal = \App\Models\Sucursal::find($filtros['sucursal_id']);
            }

            // Obtener nombres de categorías si aplica
            $filtrosAplicados = $filtros;
            if (!empty($filtros['categoria_id'])) {
                $categoriaIds = is_array($filtros['categoria_id'])
                    ? $filtros['categoria_id']
                    : [$filtros['categoria_id']];

                $categoriasNombres = CategoriaProducto::whereIn('id', $categoriaIds)
                    ->pluck('nombre')
                    ->toArray();

                $filtrosAplicados['categorias'] = $categoriasNombres;
            }

            // Preparar datos para la vista
            $data = [
                'compras' => $compras,
                'estadisticas' => $estadisticas,
                'filtrosAplicados' => $filtrosAplicados,
                'fechaGeneracion' => Carbon::now()->format('d/m/Y H:i:s'),
                'sucursal' => $sucursal,
                'usuario' => $request->user(),
            ];

            // Generar PDF
            $pdf = Pdf::loadView('reportes.compras', $data);
            $pdf->setPaper('letter', 'landscape');

            $filename = 'reporte-compras-' . Carbon::now()->format('Y-m-d-His') . '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar el reporte PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar reporte Excel de compras
     */
    public function generarExcel(Request $request)
    {
        try {
            // Obtener filtros
            $filtros = $this->obtenerFiltros($request);

            $filename = 'reporte-compras-' . Carbon::now()->format('Y-m-d-His') . '.xlsx';

            return Excel::download(new ComprasExport($filtros), $filename);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al generar el reporte Excel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener vista previa de datos del reporte
     */
    public function vistaPrevia(Request $request)
    {
        try {
            // Obtener filtros
            $filtros = $this->obtenerFiltros($request);

            // Obtener compras con filtros aplicados (limitado)
            $compras = $this->obtenerComprasFiltradas($filtros, 10);

            // Calcular estadísticas
            $estadisticas = $this->calcularEstadisticas(
                $this->obtenerComprasFiltradas($filtros)
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'compras' => $compras,
                    'estadisticas' => $estadisticas,
                    'total_registros' => $estadisticas['total_compras'],
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener vista previa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener filtros del request
     */
    private function obtenerFiltros(Request $request): array
    {
        $filtros = [];

        if ($request->filled('sucursal_id')) {
            $filtros['sucursal_id'] = $request->sucursal_id;
        }

        if ($request->filled('estado')) {
            $filtros['estado'] = $request->estado;
        }

        if ($request->filled('categoria_id')) {
            // Puede ser un array o un solo valor
            $filtros['categoria_id'] = $request->categoria_id;
        }

        if ($request->filled('fecha_desde')) {
            $filtros['fecha_desde'] = $request->fecha_desde;
        }

        if ($request->filled('fecha_hasta')) {
            $filtros['fecha_hasta'] = $request->fecha_hasta;
        }

        if ($request->filled('search')) {
            $filtros['search'] = $request->search;
        }

        return $filtros;
    }

    /**
     * Obtener compras filtradas
     */
    private function obtenerComprasFiltradas(array $filtros, ?int $limit = null)
    {
        $query = Compra::query()
            ->with(['cliente', 'categoriaProducto', 'sucursal', 'usuario'])
            ->select('compras.*');

        // Filtro por sucursal
        if (!empty($filtros['sucursal_id'])) {
            $query->where('compras.sucursal_id', $filtros['sucursal_id']);
        }

        // Filtro por estado
        if (!empty($filtros['estado']) && $filtros['estado'] !== 'all') {
            $query->where('compras.estado', $filtros['estado']);
        }

        // Filtro por categoría (puede ser array para múltiple selección)
        if (!empty($filtros['categoria_id'])) {
            $categorias = is_array($filtros['categoria_id'])
                ? $filtros['categoria_id']
                : [$filtros['categoria_id']];
            $query->whereIn('compras.categoria_producto_id', $categorias);
        }

        // Filtro por rango de fechas
        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('compras.fecha_compra', '>=', $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('compras.fecha_compra', '<=', $filtros['fecha_hasta']);
        }

        // Filtro por búsqueda general
        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('compras.codigo_compra', 'like', "%{$search}%")
                    ->orWhere('compras.descripcion', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($q) use ($search) {
                        $q->where(DB::raw("CONCAT(nombres, ' ', apellidos)"), 'like', "%{$search}%")
                            ->orWhere('codigo_cliente', 'like', "%{$search}%");
                    });
            });
        }

        $query->orderBy('compras.fecha_compra', 'desc');

        if ($limit) {
            return $query->limit($limit)->get();
        }

        return $query->get();
    }

    /**
     * Calcular estadísticas de las compras
     */
    private function calcularEstadisticas($compras): array
    {
        $totalCompras = $compras->count();
        $totalInvertido = $compras->sum('monto_pagado');
        $valorInventario = $compras->where('estado', 'activa')->sum('precio_venta_sugerido');

        // Calcular margen promedio
        $margenPromedio = 0;
        if ($totalCompras > 0) {
            $margenPromedio = $compras->avg('margen_esperado');
        }

        return [
            'total_compras' => $totalCompras,
            'total_invertido' => $totalInvertido,
            'valor_inventario' => $valorInventario,
            'margen_promedio' => $margenPromedio,
            'compras_activas' => $compras->where('estado', 'activa')->count(),
            'compras_vendidas' => $compras->where('estado', 'vendida')->count(),
            'compras_canceladas' => $compras->where('estado', 'cancelada')->count(),
        ];
    }
}
