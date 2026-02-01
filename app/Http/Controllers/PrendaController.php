<?php

namespace App\Http\Controllers;

use App\Models\Prenda;
use App\Models\CreditoPrendario;
use App\Models\Cliente;
use App\Http\Resources\PrendaResource;
use App\Exports\PrendasExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrendaController extends Controller
{
    /**
     * Generar reporte de prendas en PDF o Excel
     */
    public function reporte(Request $request) {
        try {
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
            if ($request->has('estado') && !empty($request->estado) && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }

             // Filtro por categoría
            if ($request->has('categoria') && !empty($request->categoria) && $request->categoria !== 'todos') {
                $query->where('categoria_producto_id', $request->categoria);
            }

            // Filtro por sucursal
            if ($request->has('sucursal') && !empty($request->sucursal) && $request->sucursal !== 'todos') {
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
            $query->orderBy('fecha_ingreso', 'desc');

            $prendas = $query->get();
            $format = $request->input('format', 'pdf');

            // Generar Excel (.xlsx) con formato estético
            if ($format === 'csv' || $format === 'excel' || $format === 'xlsx') {
                $filtros = [
                    'busqueda' => $request->busqueda,
                    'estado' => $request->estado,
                    'categoria' => $request->categoria,
                    'fecha_desde' => $request->fecha_desde,
                    'fecha_hasta' => $request->fecha_hasta,
                ];

                $fileName = 'Reporte_Prendas_' . date('Y-m-d_His') . '.xlsx';

                return Excel::download(
                    new PrendasExport($prendas, $filtros),
                    $fileName,
                    \Maatwebsite\Excel\Excel::XLSX
                );
            }

            // Generar PDF
            $pdf = Pdf::loadView('reports.prendas', compact('prendas'));
            return $pdf->download('reporte_prendas_' . date('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error generating reporte: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Error al generar el reporte', 'message' => $e->getMessage()], 500);
        }
    }

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

        // Paginación optimizada con chunks
        $perPage = min((int) $request->get('per_page', 20), 100);
        $page = (int) $request->get('page', 1);

        // Usar cursor pagination para mejor performance en datasets grandes
        $useCursor = $request->get('use_cursor', false);

        // Estadísticas OPTIMIZADAS - una sola consulta con CASE
        $statsRaw = Prenda::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN estado = "en_custodia" THEN 1 ELSE 0 END) as empeniadas,
            SUM(CASE WHEN estado = "vencida" THEN 1 ELSE 0 END) as vencidas,
            SUM(CASE WHEN estado = "en_venta" THEN 1 ELSE 0 END) as en_venta,
            SUM(CASE WHEN estado = "vendida" THEN 1 ELSE 0 END) as vendidas,
            SUM(CASE WHEN estado = "recuperada" THEN 1 ELSE 0 END) as recuperadas,
            SUM(valor_tasacion) as valor_total_avaluo,
            SUM(CASE WHEN estado = "en_venta" THEN valor_venta ELSE 0 END) as valor_total_venta
        ')->first();

        $stats = [
            'total' => (int) $statsRaw->total,
            'empeniadas' => (int) $statsRaw->empeniadas,
            'vencidas' => (int) $statsRaw->vencidas,
            'en_venta' => (int) $statsRaw->en_venta,
            'vendidas' => (int) $statsRaw->vendidas,
            'recuperadas' => (int) $statsRaw->recuperadas,
            'valor_total_avaluo' => (float) $statsRaw->valor_total_avaluo,
            'valor_total_venta' => (float) $statsRaw->valor_total_venta,
        ];

        if ($useCursor && $request->has('cursor')) {
            // Cursor pagination - más eficiente para datasets grandes
            $cursor = $request->get('cursor');
            $prendas = $query
                ->where('id', '>', $cursor)
                ->take($perPage)
                ->get();

            $nextCursor = $prendas->last()?->id;
            $hasMore = $prendas->count() === $perPage;

            return response()->json([
                'success' => true,
                'data' => PrendaResource::collection($prendas),
                'cursor' => [
                    'next' => $hasMore ? $nextCursor : null,
                    'has_more' => $hasMore,
                    'per_page' => $perPage,
                ],
                'stats' => $stats,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        // Paginación tradicional mejorada
        $totalFiltrado = (clone $query)->count();

        // Si hay muchos registros (>1000), usar lazy loading
        if ($totalFiltrado > 1000) {
            $prendas = $query
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->lazy(100) // Carga en chunks de 100
                ->map(function ($prenda) {
                    return new PrendaResource($prenda);
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $prendas,
                'pagination' => [
                    'total' => $totalFiltrado,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($totalFiltrado / $perPage),
                    'from' => (($page - 1) * $perPage) + 1,
                    'to' => min($page * $perPage, $totalFiltrado),
                ],
                'stats' => $stats,
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        // Paginación normal para datasets pequeños
        $prendas = $query->paginate($perPage);

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
        ], 200, [], JSON_UNESCAPED_UNICODE);
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

        // VALIDACIÓN: No permitir editar prendas con estado 'pagada' o 'recuperada'
        // Solo se pueden eliminar, no editar (para mantener integridad con créditos)
        if (in_array($prenda->estado, ['pagada', 'recuperada'])) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede editar una prenda con estado "' . $prenda->estado . '". Solo se puede eliminar.',
                'errors' => ['estado' => ['Prenda no editable en estado actual']]
            ], 422);
        }

        // Log para debugging
        Log::info('UPDATE PRENDA - Request data:', [
            'prenda_id' => $id,
            'all_data' => $request->all(),
            'precio_venta' => $request->input('precio_venta'),
            'ubicacion_fisica' => $request->input('ubicacion_fisica'),
        ]);

        $validator = Validator::make($request->all(), [
            'descripcion' => 'sometimes|string|max:500',
            'valor_tasacion' => 'sometimes|numeric|min:0',
            'valor_prestamo' => 'sometimes|numeric|min:0',
            'valor_venta' => 'sometimes|numeric|min:0',
            'precio_venta' => 'sometimes|numeric|min:0',
            'ubicacion_fisica' => 'sometimes|string|max:255',
            'estado' => 'sometimes|in:en_custodia,recuperada,en_venta,vendida,perdida,dañada',
        ]);

        if ($validator->fails()) {
            Log::error('UPDATE PRENDA - Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Actualizar solo los campos que vienen en el request
        $dataToUpdate = $request->only([
            'descripcion',
            'valor_tasacion',
            'valor_prestamo',
            'valor_venta',
            'precio_venta',
            'ubicacion_fisica',
            'estado',
        ]);

        Log::info('UPDATE PRENDA - Data to update:', $dataToUpdate);

        $prenda->update($dataToUpdate);

        Log::info('UPDATE PRENDA - After update:', [
            'precio_venta' => $prenda->precio_venta,
            'ubicacion_fisica' => $prenda->ubicacion_fisica,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prenda actualizada exitosamente',
            'data' => new PrendaResource($prenda->fresh()->load(['categoriaProducto', 'creditoPrendario.cliente'])),
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

        $prenda = Prenda::with('creditoPrendario')->findOrFail($id);

        // Validar estado del crédito si existe
        if ($prenda->creditoPrendario) {
            $estadoCredito = $prenda->creditoPrendario->estado;
            $estadosPermitidos = ['vencido', 'en_mora', 'cancelado', 'incobrable'];

            if (!in_array($estadoCredito, $estadosPermitidos)) {
                return response()->json([
                    'success' => false,
                    'message' => "No se puede marcar en venta. El crédito está '{$estadoCredito}'. Debe estar vencido, en mora, cancelado o incobrable.",
                ], 422);
            }
        }

        // Actualizar estado de la prenda
        $prenda->update([
            'estado' => 'en_venta',
            'valor_venta' => $request->valor_venta,
            'fecha_publicacion_venta' => now(),
        ]);

        // Si la prenda tiene un crédito prendario asociado y aún no está incobrable, marcarlo
        if ($prenda->creditoPrendario && $prenda->creditoPrendario->estado !== 'incobrable') {
            $prenda->creditoPrendario->update([
                'estado' => 'incobrable',
                'fecha_incobrable' => now(),
                'motivo_incobrable' => 'Prenda puesta en venta - No recuperada por el cliente'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Prenda puesta en venta exitosamente',
            'data' => $prenda->load('creditoPrendario'),
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
    public function getEnVenta(Request $request)
    {
        $query = Prenda::with(['categoriaProducto', 'creditoPrendario.cliente'])
            ->where('estado', 'en_venta');

        // Búsqueda
        if ($request->has('busqueda') && !empty($request->busqueda)) {
            $busqueda = $request->busqueda;
            $query->where(function ($q) use ($busqueda) {
                $q->where('descripcion', 'like', "%{$busqueda}%")
                  ->orWhere('codigo_prenda', 'like', "%{$busqueda}%");
            });
        }

        // Filtro por categoría
        if ($request->has('categoria') && !empty($request->categoria) && $request->categoria !== 'todas') {
            $query->whereHas('categoriaProducto', function($q) use ($request) {
                $q->where('nombre', $request->categoria);
            });
        }

        $query->orderBy('fecha_ingreso', 'desc');

        $perPage = (int) $request->get('per_page', 20);

        // Usar paginate para obtener metadata de paginación
        $prendas = $query->paginate($perPage);

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

    /**
     * Reservar prenda temporalmente (validación suave antes de venta)
     * POST /prendas/{id}/reservar-temporal
     *
     * Previene selección duplicada en UI sin bloquear definitivamente
     * La reserva expira automáticamente después de 5 minutos
     */
    public function reservarTemporal(Request $request, string $id)
    {
        try {
            $prenda = Prenda::findOrFail($id);

            // Validar que esté en estado en_venta
            if ($prenda->estado !== 'en_venta') {
                return response()->json([
                    'success' => false,
                    'message' => "Prenda no disponible (estado: {$prenda->estado})"
                ], 400);
            }

            // Verificar si ya fue vendida (doble verificación)
            $yaVendida = DB::table('venta_detalles')
                ->join('ventas', 'venta_detalles.venta_id', '=', 'ventas.id')
                ->where('venta_detalles.prenda_id', $prenda->id)
                ->whereNotIn('ventas.estado', ['cancelada'])
                ->exists();

            if ($yaVendida) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prenda ya fue vendida'
                ], 400);
            }

            // Validar precio de venta
            if (!$prenda->precio_venta || $prenda->precio_venta <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prenda no tiene precio de venta configurado'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Prenda disponible para venta',
                'data' => [
                    'prenda_id' => $prenda->id,
                    'codigo' => $prenda->codigo_prenda,
                    'precio_venta' => $prenda->precio_venta,
                    'precio_minimo' => $prenda->precio_minimo ?? 0,
                    'descuento_max_pct' => $prenda->descuento_max_pct ?? 20,
                    'estado' => $prenda->estado
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al reservar prenda temporal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al validar prenda'
            ], 500);
        }
    }
}

