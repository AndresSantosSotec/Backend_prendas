<?php

namespace App\Http\Controllers;

use App\Models\Prenda;
use App\Models\CreditoPrendario;
use App\Models\Cliente;
use App\Http\Resources\PrendaResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PrendaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Prenda::with(['categoriaProducto', 'creditoPrendario.cliente']);

        // Búsqueda
        if ($request->has('busqueda') && !empty($request->busqueda)) {
            $busqueda = $request->busqueda;
            $query->where(function ($q) use ($busqueda) {
                $q->where('descripcion', 'like', "%{$busqueda}%")
                  ->orWhere('codigo_prenda', 'like', "%{$busqueda}%")
                  ->orWhere('marca', 'like', "%{$busqueda}%")
                  ->orWhere('modelo', 'like', "%{$busqueda}%");
            });
        }

        // Filtro por estado
        if ($request->has('estado') && !empty($request->estado)) {
            $query->where('estado', $request->estado);
        }

        // Filtro por categoría
        if ($request->has('categoria') && !empty($request->categoria)) {
            $query->where('categoria_producto_id', $request->categoria);
        }

        // Filtro por sucursal
        if ($request->has('sucursal') && !empty($request->sucursal)) {
            $query->whereHas('creditoPrendario', function ($q) use ($request) {
                $q->where('sucursal_id', $request->sucursal);
            });
        }

        // Filtro por rango de fechas
        if ($request->has('fecha_desde') && !empty($request->fecha_desde)) {
            $query->where('fecha_ingreso', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta') && !empty($request->fecha_hasta)) {
            $query->where('fecha_ingreso', '<=', $request->fecha_hasta);
        }

        // Ordenamiento
        $orderBy = $request->get('order_by', 'fecha_ingreso');
        $orderDir = $request->get('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        // Paginación
        $perPage = $request->get('per_page', 50);
        $prendas = $query->paginate($perPage);

        // Estadísticas
        $stats = [
            'total' => Prenda::count(),
            'empeniadas' => Prenda::where('estado', 'en_custodia')->count(),
            'vencidas' => Prenda::where('estado', 'vencida')->count(),
            'en_venta' => Prenda::where('estado', 'en_venta')->count(),
            'vendidas' => Prenda::where('estado', 'vendida')->count(),
            'recuperadas' => Prenda::where('estado', 'recuperada')->count(),
            'valor_total_avaluo' => Prenda::sum('valor_tasacion'),
            'valor_total_venta' => Prenda::where('estado', 'en_venta')->sum('valor_venta'),
        ];

        return response()->json([
            'success' => true,
            'data' => PrendaResource::collection($prendas->items()),
            'pagination' => [
                'total' => $prendas->total(),
                'per_page' => $prendas->perPage(),
                'current_page' => $prendas->currentPage(),
                'last_page' => $prendas->lastPage(),
                'from' => $prendas->firstItem(),
                'to' => $prendas->lastItem(),
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $prenda = Prenda::with([
            'categoriaProducto',
            'creditoPrendario.cliente',
            'tasador',
            'comprador',
            'tasaciones'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new PrendaResource($prenda),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'credito_prendario_id' => 'required|exists:credito_prendarios,id',
            'categoria_producto_id' => 'required|exists:categoria_productos,id',
            'descripcion' => 'required|string|max:500',
            'valor_estimado_cliente' => 'nullable|numeric|min:0',
            'valor_tasacion' => 'nullable|numeric|min:0',
            'valor_prestamo' => 'nullable|numeric|min:0',
            'fotos' => 'nullable|array',
            'fotos.*' => 'string', // Base64 strings
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Generar código único para la prenda
            $codigoPrenda = $this->generarCodigoPrenda();

            $prenda = Prenda::create([
                'credito_prendario_id' => $request->credito_prendario_id,
                'categoria_producto_id' => $request->categoria_producto_id,
                'codigo_prenda' => $codigoPrenda,
                'descripcion' => $request->descripcion,
                'marca' => $request->marca,
                'modelo' => $request->modelo,
                'serie' => $request->serie,
                'color' => $request->color,
                'caracteristicas' => $request->caracteristicas,
                'valor_estimado_cliente' => $request->valor_estimado_cliente,
                'valor_tasacion' => $request->valor_tasacion,
                'valor_prestamo' => $request->valor_prestamo,
                'estado' => $request->estado ?? 'en_custodia',
                'condicion' => $request->condicion ?? 'buena',
                'ubicacion_fisica' => $request->ubicacion_fisica,
                'seccion' => $request->seccion,
                'estante' => $request->estante,
                'fecha_ingreso' => now(),
                'tasador_id' => Auth::id(),
                'observaciones' => $request->observaciones,
            ]);

            // Procesar fotos si existen
            if ($request->has('fotos') && is_array($request->fotos)) {
                $fotosGuardadas = [];
                foreach ($request->fotos as $index => $fotoBase64) {
                    $fotoUrl = $this->guardarFotoBase64($fotoBase64, $codigoPrenda, $index);
                    if ($fotoUrl) {
                        $fotosGuardadas[] = $fotoUrl;
                    }
                }
                $prenda->fotos = $fotosGuardadas;
                if (count($fotosGuardadas) > 0) {
                    $prenda->foto_principal = $fotosGuardadas[0];
                }
                $prenda->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prenda registrada exitosamente',
                'data' => new PrendaResource($prenda->load(['categoriaProducto', 'creditoPrendario.cliente'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la prenda: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $prenda = Prenda::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'descripcion' => 'sometimes|string|max:500',
            'valor_tasacion' => 'sometimes|numeric|min:0',
            'valor_prestamo' => 'sometimes|numeric|min:0',
            'valor_venta' => 'sometimes|numeric|min:0',
            'ubicacion_fisica' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $prenda->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Prenda actualizada exitosamente',
            'data' => new PrendaResource($prenda->load(['categoriaProducto', 'creditoPrendario.cliente'])),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $prenda = Prenda::findOrFail($id);
        $prenda->delete();

        return response()->json([
            'success' => true,
            'message' => 'Prenda eliminada exitosamente',
        ]);
    }

    /**
     * Subir foto de prenda
     */
    public function uploadPhoto(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'required|string', // Base64
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $prenda = Prenda::findOrFail($id);
        $fotoUrl = $this->guardarFotoBase64(
            $request->foto,
            $prenda->codigo_prenda,
            count($prenda->fotos ?? [])
        );

        if ($fotoUrl) {
            $fotos = $prenda->fotos ?? [];
            $fotos[] = $fotoUrl;
            $prenda->fotos = $fotos;

            if (empty($prenda->foto_principal)) {
                $prenda->foto_principal = $fotoUrl;
            }

            $prenda->save();

            return response()->json([
                'success' => true,
                'message' => 'Foto subida exitosamente',
                'data' => ['url' => $fotoUrl],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Error al guardar la foto',
        ], 500);
    }

    /**
     * Marcar prenda como recuperada
     */
    public function marcarRecuperada(Request $request, string $id)
    {
        $prenda = Prenda::findOrFail($id);

        $prenda->update([
            'estado' => 'recuperada',
            'fecha_recuperacion' => $request->fecha_recuperacion ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prenda marcada como recuperada',
            'data' => $prenda,
        ]);
    }

    /**
     * Marcar prenda en venta
     */
    public function marcarEnVenta(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'valor_venta' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $prenda = Prenda::findOrFail($id);

        $prenda->update([
            'estado' => 'en_venta',
            'valor_venta' => $request->valor_venta,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prenda puesta en venta',
            'data' => $prenda,
        ]);
    }

    /**
     * Marcar prenda como vendida
     */
    public function marcarVendida(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'valor_venta_final' => 'required|numeric|min:0',
            'comprador_id' => 'nullable|exists:clientes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $prenda = Prenda::findOrFail($id);

        $prenda->update([
            'estado' => 'vendida',
            'precio_venta' => $request->valor_venta_final,
            'comprador_id' => $request->comprador_id,
            'fecha_venta' => $request->fecha_venta ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prenda marcada como vendida',
            'data' => $prenda,
        ]);
    }

    /**
     * Obtener estadísticas de prendas
     */
    public function getEstadisticas()
    {
        $stats = [
            'total' => Prenda::count(),
            'en_custodia' => Prenda::where('estado', 'en_custodia')->count(),
            'vencidas' => Prenda::where('estado', 'vencida')->count(),
            'en_venta' => Prenda::where('estado', 'en_venta')->count(),
            'vendidas' => Prenda::where('estado', 'vendida')->count(),
            'recuperadas' => Prenda::where('estado', 'recuperada')->count(),
            'valor_total_avaluo' => Prenda::sum('valor_tasacion'),
            'valor_total_venta' => Prenda::where('estado', 'en_venta')->sum('valor_venta'),
            'valor_total_vendido' => Prenda::where('estado', 'vendida')->sum('precio_venta'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Obtener prendas en venta
     */
    public function getEnVenta()
    {
        $prendas = Prenda::with(['categoriaProducto', 'creditoPrendario.cliente'])
            ->where('estado', 'en_venta')
            ->orderBy('fecha_ingreso', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => PrendaResource::collection($prendas),
        ]);
    }

    /**
     * Generar código único para prenda
     */
    private function generarCodigoPrenda()
    {
        $año = date('Y');
        $ultimaPrenda = Prenda::whereYear('created_at', $año)
            ->orderBy('id', 'desc')
            ->first();

        $numero = $ultimaPrenda ? intval(substr($ultimaPrenda->codigo_prenda, -6)) + 1 : 1;

        return 'PRE-' . $año . '-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Guardar foto en base64
     */
    private function guardarFotoBase64($base64String, $codigoPrenda, $index)
    {
        try {
            // Verificar si es base64
            if (strpos($base64String, 'data:image') === 0) {
                // Extraer tipo de imagen y datos
                preg_match('/data:image\/(\w+);base64,/', $base64String, $matches);
                $imageType = $matches[1] ?? 'jpeg';
                $base64String = substr($base64String, strpos($base64String, ',') + 1);
            } else {
                $imageType = 'jpeg';
            }

            $imageData = base64_decode($base64String);

            if ($imageData === false) {
                return null;
            }

            // Crear nombre único para el archivo
            $fileName = $codigoPrenda . '_' . $index . '_' . time() . '.' . $imageType;
            $path = 'prendas/' . $fileName;

            // Guardar en storage
            Storage::disk('public')->put($path, $imageData);

            // Retornar URL pública
            return Storage::url($path);
        } catch (\Exception $e) {
            Log::error('Error al guardar foto de prenda: ' . $e->getMessage());
            return null;
        }
    }
}
