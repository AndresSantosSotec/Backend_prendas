<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Moneda;
use App\Models\Denominacion;
use Illuminate\Support\Facades\Log;

class DenominacionController extends Controller
{
    /**
     * Obtener todas las monedas activas con sus denominaciones
     */
    public function index()
    {
        $monedas = Moneda::activas()
            ->with(['denominaciones' => function ($query) {
                $query->where('activa', true)->orderBy('orden');
            }])
            ->get();

        return response()->json([
            'data' => $monedas
        ]);
    }

    /**
     * Obtener la moneda base del sistema con sus denominaciones
     */
    public function getMonedaBase()
    {
        $moneda = Moneda::where('es_moneda_base', true)
            ->with(['denominaciones' => function ($query) {
                $query->where('activa', true)->orderBy('orden');
            }])
            ->first();

        if (!$moneda) {
            return response()->json([
                'error' => 'No se ha configurado una moneda base en el sistema'
            ], 404);
        }

        // Separar billetes y monedas para facilitar el consumo en frontend
        $billetes = $moneda->denominaciones->where('tipo', 'billete')->values();
        $monedas = $moneda->denominaciones->where('tipo', 'moneda')->values();

        return response()->json([
            'data' => [
                'moneda' => [
                    'id' => $moneda->id,
                    'codigo' => $moneda->codigo,
                    'nombre' => $moneda->nombre,
                    'simbolo' => $moneda->simbolo,
                ],
                'billetes' => $billetes,
                'monedas' => $monedas,
                'todas' => $moneda->denominaciones
            ]
        ]);
    }

    /**
     * Obtener denominaciones de una moneda específica
     */
    public function getDenominacionesByMoneda($monedaId)
    {
        $moneda = Moneda::with(['denominaciones' => function ($query) {
            $query->where('activa', true)->orderBy('orden');
        }])->find($monedaId);

        if (!$moneda) {
            return response()->json(['error' => 'Moneda no encontrada'], 404);
        }

        return response()->json([
            'data' => [
                'moneda' => $moneda,
                'billetes' => $moneda->denominaciones->where('tipo', 'billete')->values(),
                'monedas' => $moneda->denominaciones->where('tipo', 'moneda')->values()
            ]
        ]);
    }

    /**
     * Crear una nueva moneda
     */
    public function createMoneda(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string|max:3|unique:monedas,codigo',
            'nombre' => 'required|string|max:50',
            'simbolo' => 'required|string|max:5',
            'tipo_cambio' => 'required|numeric|min:0',
            'es_moneda_base' => 'boolean'
        ]);

        // Si se marca como moneda base, desmarcar la anterior
        if ($request->es_moneda_base) {
            Moneda::where('es_moneda_base', true)->update(['es_moneda_base' => false]);
        }

        $moneda = Moneda::create([
            'codigo' => strtoupper($request->codigo),
            'nombre' => $request->nombre,
            'simbolo' => $request->simbolo,
            'tipo_cambio' => $request->tipo_cambio,
            'es_moneda_base' => $request->es_moneda_base ?? false,
            'activa' => true
        ]);

        return response()->json([
            'message' => 'Moneda creada correctamente',
            'data' => $moneda
        ], 201);
    }

    /**
     * Crear una nueva denominación
     */
    public function createDenominacion(Request $request)
    {
        $request->validate([
            'moneda_id' => 'required|exists:monedas,id',
            'valor' => 'required|numeric|min:0.01',
            'tipo' => 'required|in:billete,moneda',
            'descripcion' => 'nullable|string|max:100',
            'orden' => 'integer|min:0'
        ]);

        // Verificar que no exista ya esta denominación
        $existe = Denominacion::where('moneda_id', $request->moneda_id)
            ->where('valor', $request->valor)
            ->where('tipo', $request->tipo)
            ->exists();

        if ($existe) {
            return response()->json([
                'error' => 'Ya existe esta denominación para esta moneda'
            ], 400);
        }

        $denominacion = Denominacion::create([
            'moneda_id' => $request->moneda_id,
            'valor' => $request->valor,
            'tipo' => $request->tipo,
            'descripcion' => $request->descripcion,
            'orden' => $request->orden ?? 0,
            'activa' => true
        ]);

        return response()->json([
            'message' => 'Denominación creada correctamente',
            'data' => $denominacion
        ], 201);
    }

    /**
     * Activar/Desactivar una denominación
     */
    public function toggleDenominacion($id)
    {
        $denominacion = Denominacion::findOrFail($id);
        $denominacion->activa = !$denominacion->activa;
        $denominacion->save();

        return response()->json([
            'message' => $denominacion->activa ? 'Denominación activada' : 'Denominación desactivada',
            'data' => $denominacion
        ]);
    }
}
