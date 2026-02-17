<?php

namespace App\Http\Controllers;

use App\Services\MigracionService;
use App\Models\MigracionDatosLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class MigracionController extends Controller
{
    protected $migracionService;

    public function __construct(MigracionService $migracionService)
    {
        $this->migracionService = $migracionService;
    }

    /**
     * Verificar permisos de SuperAdmin
     */
    protected function checkSuperAdmin($user)
    {
        if ($user->rol !== 'superadmin') {
            abort(403, 'No tienes permiso para acceder a este módulo. Solo SuperAdmin.');
        }
    }

    /**
     * Listar historial de migraciones
     */
    public function index(Request $request): JsonResponse
    {
        $this->checkSuperAdmin($request->user());

        $logs = MigracionDatosLog::with('usuario:id,name,username')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }

    /**
     * Descargar plantilla Excel (.xlsx)
     */
    public function downloadTemplate(Request $request, string $modelo)
    {
        $this->checkSuperAdmin($request->user());

        try {
            $filePath = $this->migracionService->generateTemplate($modelo);

            return response()->download(
                $filePath,
                "plantilla_{$modelo}.xlsx",
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            )->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Subir y validar archivo Excel
     */
    public function upload(Request $request): JsonResponse
    {
        $this->checkSuperAdmin($request->user());

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'modelo' => 'required|string'
        ]);

        try {
            $path = $request->file('file')->store('temp/migraciones');
            $fullPath = storage_path('app/' . $path);

            $validation = $this->migracionService->validateImport(
                $request->modelo,
                $fullPath
            );

            return response()->json([
                'success' => true,
                'validation' => $validation,
                'temp_path' => $path
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ejecutar importación final
     */
    public function execute(Request $request): JsonResponse
    {
        $this->checkSuperAdmin($request->user());

        $request->validate([
            'temp_path' => 'required|string',
            'modelo' => 'required|string'
        ]);

        try {
            $fullPath = storage_path('app/' . $request->temp_path);

            if (!file_exists($fullPath)) {
                return response()->json(['success' => false, 'message' => 'El archivo temporal ha expirado.'], 404);
            }

            $log = $this->migracionService->executeImport(
                $request->modelo,
                $fullPath,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Importación completada con éxito',
                'log' => $log
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la importación: ' . $e->getMessage()
            ], 500);
        }
    }
}
