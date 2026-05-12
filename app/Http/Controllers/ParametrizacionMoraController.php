<?php

namespace App\Http\Controllers;

use App\Models\ParametrizacionMora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParametrizacionMoraController extends Controller
{
    /**
     * Obtener la configuración de mora vigente (por sucursal o global).
     */
    public function index(Request $request): JsonResponse
    {
        $sucursalId = $request->query('sucursal_id');

        $configs = ParametrizacionMora::with('sucursal')
            ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId))
            ->orderBy('sucursal_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Obtener la configuración activa (la que aplicaría al cálculo).
     */
    public function activa(Request $request): JsonResponse
    {
        $sucursalId = $request->query('sucursal_id');
        $config = ParametrizacionMora::obtenerConfiguracion($sucursalId);

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Crear o actualizar la configuración de mora.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sucursal_id' => 'nullable|exists:sucursals,id',
            'lunes' => 'required|boolean',
            'martes' => 'required|boolean',
            'miercoles' => 'required|boolean',
            'jueves' => 'required|boolean',
            'viernes' => 'required|boolean',
            'sabado' => 'required|boolean',
            'domingo' => 'required|boolean',
            'max_dias_mora' => 'required|integer|min:0',
            'aplicar_tope_mora' => 'required|boolean',
            'dias_tope_mora' => 'required|integer|min:0',
            'aplicar_mora_completa' => 'required|boolean',
            'dias_para_mora_completa' => 'required|integer|min:1',
            'apartado_habilitado' => 'required|boolean',
            'dias_gracia' => 'required|integer|min:0',
            'frecuencia_defecto' => 'required|in:semanal,quincenal,mensual',
            'tasa_interes_defecto' => 'required|numeric|min:0',
            'notas' => 'nullable|string|max:500',
        ]);

        // Al menos un día debe estar activo
        $diasActivos = collect(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'])
            ->filter(fn($dia) => $validated[$dia])
            ->count();

        if ($diasActivos === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Debe seleccionar al menos un día laboral.',
            ], 422);
        }

        // Upsert: si ya existe para esa sucursal, actualizar; si no, crear
        $config = ParametrizacionMora::updateOrCreate(
            ['sucursal_id' => $validated['sucursal_id'] ?? null],
            array_merge($validated, ['activo' => true])
        );

        return response()->json([
            'success' => true,
            'message' => 'Configuración de mora guardada correctamente.',
            'data' => $config->load('sucursal'),
        ]);
    }

    /**
     * Actualizar configuración existente.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $config = ParametrizacionMora::findOrFail($id);

        $validated = $request->validate([
            'lunes' => 'sometimes|boolean',
            'martes' => 'sometimes|boolean',
            'miercoles' => 'sometimes|boolean',
            'jueves' => 'sometimes|boolean',
            'viernes' => 'sometimes|boolean',
            'sabado' => 'sometimes|boolean',
            'domingo' => 'sometimes|boolean',
            'max_dias_mora' => 'sometimes|integer|min:0',
            'aplicar_tope_mora' => 'sometimes|boolean',
            'dias_tope_mora' => 'sometimes|integer|min:0',
            'aplicar_mora_completa' => 'sometimes|boolean',
            'dias_para_mora_completa' => 'sometimes|integer|min:1',
            'apartado_habilitado' => 'sometimes|boolean',
            'dias_gracia' => 'sometimes|integer|min:0',
            'frecuencia_defecto' => 'sometimes|in:semanal,quincenal,mensual',
            'tasa_interes_defecto' => 'sometimes|numeric|min:0',
            'activo' => 'sometimes|boolean',
            'notas' => 'nullable|string|max:500',
        ]);

        $config->fill($validated);

        // Validar al menos un día activo
        $diasActivos = collect(['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'])
            ->filter(fn($dia) => $config->$dia)
            ->count();

        if ($diasActivos === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Debe seleccionar al menos un día laboral.',
            ], 422);
        }

        $config->save();

        return response()->json([
            'success' => true,
            'message' => 'Configuración de mora actualizada.',
            'data' => $config->load('sucursal'),
        ]);
    }

    /**
     * Eliminar configuración.
     */
    public function destroy(string $id): JsonResponse
    {
        $config = ParametrizacionMora::findOrFail($id);
        $config->delete();

        return response()->json([
            'success' => true,
            'message' => 'Configuración de mora eliminada.',
        ]);
    }
}
