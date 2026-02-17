<?php

namespace App\Http\Controllers;

use App\Models\Contabilidad\CtbDiario;
use App\Services\ContabilidadAutomaticaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DiarioContableController extends Controller
{
    protected $contabilidadService;

    public function __construct(ContabilidadAutomaticaService $contabilidadService)
    {
        $this->contabilidadService = $contabilidadService;
    }

    /**
     * Listar asientos contables
     */
    public function index(Request $request): JsonResponse
    {
        $query = CtbDiario::with([
            'tipoPoliza',
            'sucursal',
            'usuario',
            'movimientos.cuentaContable'
        ]);

        // Filtros
        if ($request->has('fecha_desde')) {
            $query->where('fecha_contabilizacion', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->where('fecha_contabilizacion', '<=', $request->fecha_hasta);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('tipo_origen')) {
            $query->where('tipo_origen', $request->tipo_origen);
        }

        if ($request->has('sucursal_id')) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        if ($request->has('numero_comprobante')) {
            $query->where('numero_comprobante', 'like', '%' . $request->numero_comprobante . '%');
        }

        $asientos = $query->orderBy('fecha_contabilizacion', 'desc')
            ->orderBy('numero_comprobante', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $asientos->items(),
            'pagination' => [
                'total' => $asientos->total(),
                'per_page' => $asientos->perPage(),
                'current_page' => $asientos->currentPage(),
                'last_page' => $asientos->lastPage(),
            ],
        ]);
    }

    /**
     * Ver detalle de un asiento
     */
    public function show(int $id): JsonResponse
    {
        $asiento = CtbDiario::with([
            'tipoPoliza',
            'sucursal',
            'usuario',
            'usuarioAprobador',
            'usuarioAnulador',
            'movimientos.cuentaContable',
            'creditoPrendario',
            'venta',
            'compra'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $asiento,
        ]);
    }

    /**
     * Anular asiento
     */
    public function anular(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'motivo' => 'required|string|min:10',
        ]);

        $exito = $this->contabilidadService->anularAsiento(
            $id,
            $validated['motivo'],
            Auth::id()
        );

        if (!$exito) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo anular el asiento contable',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Asiento contable anulado exitosamente',
        ]);
    }

    /**
     * Registrar asiento manualmente
     */
    public function registrarManual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_operacion' => 'required|string',
            'datos' => 'required|array',
            'datos.sucursal_id' => 'required|exists:sucursales,id',
            'datos.glosa' => 'required|string',
            'datos.fecha_documento' => 'required|date',
            'datos.numero_documento' => 'required|string',
        ]);

        $asiento = $this->contabilidadService->registrarAsiento(
            $validated['tipo_operacion'],
            array_merge($validated['datos'], ['usuario_id' => Auth::id()])
        );

        if (!$asiento) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo registrar el asiento contable',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Asiento contable registrado exitosamente',
            'data' => $asiento,
        ], 201);
    }

    /**
     * Obtener estadísticas contables
     */
    public function estadisticas(Request $request): JsonResponse
    {
        $fechaDesde = $request->fecha_desde ?? now()->startOfMonth();
        $fechaHasta = $request->fecha_hasta ?? now();

        $stats = [
            'total_asientos' => CtbDiario::whereBetween('fecha_contabilizacion', [$fechaDesde, $fechaHasta])
                ->where('estado', 'registrado')
                ->count(),
            'por_tipo_origen' => CtbDiario::whereBetween('fecha_contabilizacion', [$fechaDesde, $fechaHasta])
                ->where('estado', 'registrado')
                ->selectRaw('tipo_origen, COUNT(*) as total')
                ->groupBy('tipo_origen')
                ->get(),
            'asientos_por_dia' => CtbDiario::whereBetween('fecha_contabilizacion', [$fechaDesde, $fechaHasta])
                ->where('estado', 'registrado')
                ->selectRaw('DATE(fecha_contabilizacion) as fecha, COUNT(*) as total')
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
