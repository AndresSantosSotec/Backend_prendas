<?php

namespace App\Http\Controllers;

use App\Models\SystemErrorLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SystemErrorController extends Controller
{
    /**
     * Listar errores del sistema
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para acceder a este módulo'
            ], 403);
        }

        $query = SystemErrorLog::with('user:id,name,username')
            ->orderBy('created_at', 'desc');

        if ($request->has('exception')) {
            $query->where('exception', 'like', "%{$request->exception}%");
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhere('file', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%");
            });
        }

        $errors = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $errors->items(),
            'pagination' => [
                'total' => $errors->total(),
                'current_page' => $errors->currentPage(),
                'last_page' => $errors->lastPage(),
            ]
        ]);
    }

    /**
     * Ver detalle de un error
     */
    public function show($id, Request $request): JsonResponse
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json(['success' => false], 403);
        }

        $error = SystemErrorLog::with('user:id,name,username')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $error
        ]);
    }

    /**
     * Limpiar logs antiguos
     */
    public function clear(Request $request): JsonResponse
    {
        if ($request->user()->rol !== 'superadmin') {
            return response()->json(['success' => false], 403);
        }

        $days = $request->get('days', 30);
        SystemErrorLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'success' => true,
            'message' => "Logs anteriores a {$days} días eliminados"
        ]);
    }
}
