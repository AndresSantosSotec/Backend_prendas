<?php

namespace App\Http\Controllers;

use App\Services\MigracionService;
use App\Services\MigracionMantenimientoService;
use App\Models\MigracionDatosLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MigracionController extends Controller
{
    protected MigracionService $migracionService;
    protected MigracionMantenimientoService $mantenimientoService;

    public function __construct(
        MigracionService $migracionService,
        MigracionMantenimientoService $mantenimientoService
    ) {
        $this->migracionService = $migracionService;
        $this->mantenimientoService = $mantenimientoService;
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
            $fullPath = Storage::path($path);

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
            $fullPath = Storage::path($request->temp_path);

            if (!file_exists($fullPath)) {
                return response()->json(['success' => false, 'message' => 'El archivo temporal ha expirado.'], 404);
            }

            $log = $this->migracionService->executeImport(
                $request->modelo,
                $fullPath,
                $request->user()->id
            );

            $cuadre = $log->resumen['cuadre'] ?? null;
            $msg = 'Importación completada con éxito';
            if (is_array($cuadre) && array_key_exists('cuadra', $cuadre) && !$cuadre['cuadra']) {
                $msg .= ' (advertencia: saldos del lote no cuadran; puedes revertir el lote)';
            }

            return response()->json([
                'success' => true,
                'message' => $msg,
                'log' => $log,
                'cuadre' => $cuadre,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la importación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar CSV de cuadre de un lote importado.
     */
    public function descargarCuadre(Request $request, int $id)
    {
        $this->checkSuperAdmin($request->user());

        $log = MigracionDatosLog::findOrFail($id);
        $csv = $this->migracionService->generarCsvCuadre($log);
        $filename = 'cuadre_lote_' . ($log->codigo_lote ?: $log->id) . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Revertir un lote de migración completado (deshace clientes/créditos/prendas del lote).
     */
    public function revertir(Request $request, int $id): JsonResponse
    {
        $this->checkSuperAdmin($request->user());

        try {
            $log = MigracionDatosLog::findOrFail($id);
            $log = $this->migracionService->revertirLote($log);

            return response()->json([
                'success' => true,
                'message' => 'Lote revertido. Los registros creados por esa migración fueron eliminados.',
                'log' => $log,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Crear copia de seguridad completa de la BD y devolver metadatos (+ link de descarga).
     */
    public function crearRespaldo(Request $request): JsonResponse
    {
        $this->checkSuperAdmin($request->user());

        try {
            $info = $this->mantenimientoService->crearRespaldoSeguridad();
            Log::warning('Respaldo BD creado desde Migraciones', [
                'user_id' => $request->user()->id,
                'filename' => $info['filename'],
                'size' => $info['size'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Copia de seguridad creada. Descárgala ahora.',
                'data' => $info,
                'download_url' => url('/api/v1/migraciones/respaldos/' . urlencode($info['filename']) . '/descargar'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo crear el respaldo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar respaldos existentes.
     */
    public function listarRespaldos(Request $request): JsonResponse
    {
        $this->checkSuperAdmin($request->user());

        return response()->json([
            'success' => true,
            'data' => $this->mantenimientoService->listarRespaldos(),
        ]);
    }

    /**
     * Descargar un archivo .sql de respaldo.
     */
    public function descargarRespaldo(Request $request, string $filename)
    {
        $this->checkSuperAdmin($request->user());

        try {
            return $this->mantenimientoService->descargarRespaldo($filename);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Limpiar BD transaccional desde la UI (conserva users, permisos, sucursales, catálogos).
     */
    public function limpiarBd(Request $request): JsonResponse
    {
        $this->checkSuperAdmin($request->user());

        $request->validate([
            'confirmacion' => 'required|string|in:LIMPIAR',
            'mantener_clientes' => 'sometimes|boolean',
            'crear_respaldo_antes' => 'sometimes|boolean',
        ]);

        try {
            $respaldo = null;
            if ($request->boolean('crear_respaldo_antes', true)) {
                $respaldo = $this->mantenimientoService->crearRespaldoSeguridad();
            }

            $resultado = $this->mantenimientoService->limpiarExceptoUsers(
                $request->boolean('mantener_clientes', false)
            );

            Log::warning('Limpieza BD desde Migraciones UI', [
                'user_id' => $request->user()->id,
                'vaciadas' => $resultado['vaciadas'],
                'respaldo' => $respaldo['filename'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => "BD limpiada: {$resultado['vaciadas']} tablas vaciadas. Users y catálogos intactos.",
                'data' => $resultado,
                'respaldo' => $respaldo,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar BD: ' . $e->getMessage(),
            ], 500);
        }
    }
}
