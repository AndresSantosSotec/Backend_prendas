<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AuditoriaController extends Controller
{
    /**
     * Listar logs de auditoría con filtros y paginación
     * Solo accesible para superadmin
     */
    public function index(Request $request): JsonResponse
    {
        // Verificar que sea superadmin
        if ($request->user()->rol !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para acceder a este módulo'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'modulo' => 'string|max:50',
            'accion' => 'string|max:50',
            'user_id' => 'integer|exists:users,id',
            'sucursal_id' => 'integer|exists:sucursales,id',
            'fecha_desde' => 'date',
            'fecha_hasta' => 'date|after_or_equal:fecha_desde',
            'search' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = AuditoriaLog::with(['usuario:id,name,username,rol', 'sucursal:id,codigo,nombre'])
            ->orderBy('created_at', 'desc');

        // Aplicar filtros
        if ($request->filled('modulo')) {
            $query->where('modulo', $request->modulo);
        }

        if ($request->filled('accion')) {
            $query->where('accion', $request->accion);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('sucursal_id')) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $query->whereBetween('created_at', [
                $request->fecha_desde . ' 00:00:00',
                $request->fecha_hasta . ' 23:59:59'
            ]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('descripcion', 'like', "%{$search}%")
                    ->orWhere('tabla', 'like', "%{$search}%")
                    ->orWhere('registro_id', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 50);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ]
        ]);
    }

    /**
     * Obtener estadísticas de auditoría
     */
    public function estadisticas(Request $request): JsonResponse
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para acceder a este módulo'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $fechaDesde = $request->fecha_desde . ' 00:00:00';
        $fechaHasta = $request->fecha_hasta . ' 23:59:59';

        // Total de acciones
        $totalAcciones = AuditoriaLog::whereBetween('created_at', [$fechaDesde, $fechaHasta])->count();

        // Acciones por módulo
        $accionesPorModulo = AuditoriaLog::whereBetween('created_at', [$fechaDesde, $fechaHasta])
            ->selectRaw('modulo, COUNT(*) as total')
            ->groupBy('modulo')
            ->orderByDesc('total')
            ->get();

        // Acciones por usuario (top 10)
        $accionesPorUsuario = AuditoriaLog::with('usuario:id,name,username')
            ->whereBetween('created_at', [$fechaDesde, $fechaHasta])
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Acciones por sucursal
        $accionesPorSucursal = AuditoriaLog::with('sucursal:id,codigo,nombre')
            ->whereBetween('created_at', [$fechaDesde, $fechaHasta])
            ->selectRaw('sucursal_id, COUNT(*) as total')
            ->groupBy('sucursal_id')
            ->orderByDesc('total')
            ->get();

        // Acciones por tipo
        $accionesPorTipo = AuditoriaLog::whereBetween('created_at', [$fechaDesde, $fechaHasta])
            ->selectRaw('accion, COUNT(*) as total')
            ->groupBy('accion')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_acciones' => $totalAcciones,
                'por_modulo' => $accionesPorModulo,
                'por_usuario' => $accionesPorUsuario,
                'por_sucursal' => $accionesPorSucursal,
                'por_tipo' => $accionesPorTipo,
            ]
        ]);
    }

    /**
     * Obtener detalle de un log específico
     */
    public function show(Request $request, $id): JsonResponse
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para acceder a este módulo'
            ], 403);
        }

        $log = AuditoriaLog::with(['usuario', 'sucursal'])->find($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Log no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $log
        ]);
    }

    /**
     * Obtener lista de módulos disponibles
     */
    public function modulos(Request $request): JsonResponse
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para acceder a este módulo'
            ], 403);
        }

        $modulos = AuditoriaLog::distinct()
            ->orderBy('modulo')
            ->pluck('modulo');

        return response()->json([
            'success' => true,
            'data' => $modulos
        ]);
    }

    /**
     * Obtener lista de acciones disponibles
     */
    public function acciones(Request $request): JsonResponse
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para acceder a este módulo'
            ], 403);
        }

        $acciones = AuditoriaLog::distinct()
            ->orderBy('accion')
            ->pluck('accion');

        return response()->json([
            'success' => true,
            'data' => $acciones
        ]);
    }
}
