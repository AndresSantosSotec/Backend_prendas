<?php

namespace App\Http\Controllers;

use App\Models\CreditoPrendario;
use App\Models\Prenda;
use App\Models\PrendaDatoAdicional;
use App\Models\PrendaImagen;
use App\Models\Tasacion;
use App\Models\Cliente;
use App\Models\CreditoMovimiento;
use App\Models\CreditoPlanPago;
use App\Models\IdempotencyKey;
use App\Models\AuditoriaCredito;
use App\Models\CodigoPrereservado;
use App\Services\CajaService;
use App\Enums\EstadoCredito;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Controlador para gestión de créditos prendarios
 *
 * @phpstan-ignore-next-line
 */
class CreditoPrendarioController extends Controller
{
    /**
     * Listar todos los créditos prendarios con paginación y filtros
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // OPTIMIZACIÓN: Limitar eager loading solo a lo necesario
            $query = CreditoPrendario::with([
                'cliente:id,nombres,apellidos,dpi,codigo_cliente', // Solo campos necesarios
                'sucursal:id,nombre',
                'prendas:id,credito_prendario_id,codigo_prenda,descripcion,marca,modelo,categoria_producto_id,valor_tasacion,valor_prestamo,estado', // Campos básicos de prendas con categoría
                'prendas.categoriaProducto:id,nombre', // Cargar categoría de las prendas
                // NO cargar imágenes aquí - muy pesado. Cargarlas solo en show()
            ]);

            // Filtros
            if ($request->has('busqueda') && $request->busqueda !== '') {
                $busqueda = $request->busqueda;
                $query->where(function ($q) use ($busqueda) {
                    $q->where('numero_credito', 'like', "%{$busqueda}%")
                      ->orWhereHas('cliente', function ($q) use ($busqueda) {
                          $q->where('nombres', 'like', "%{$busqueda}%")
                            ->orWhere('apellidos', 'like', "%{$busqueda}%")
                            ->orWhere('dpi', 'like', "%{$busqueda}%")
                            ->orWhere('codigo_cliente', 'like', "%{$busqueda}%");
                      });
                });
            }

            if ($request->has('estado') && $request->estado !== '' && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }

            if ($request->has('monto_min') && $request->monto_min !== '') {
                $query->where('monto_aprobado', '>=', $request->monto_min);
            }

            if ($request->has('monto_max') && $request->monto_max !== '') {
                $query->where('monto_aprobado', '<=', $request->monto_max);
            }

            if ($request->has('fecha_desde') && $request->fecha_desde !== '') {
                $query->where('fecha_solicitud', '>=', $request->fecha_desde);
            }

            if ($request->has('fecha_hasta') && $request->fecha_hasta !== '') {
                $query->where('fecha_solicitud', '<=', $request->fecha_hasta);
            }

            // Ordenamiento: Por defecto del más reciente al más antiguo (id desc)
            $orderBy = $request->get('order_by', 'id');
            $orderDir = $request->get('order_dir', 'desc');
            $allowedOrderFields = ['id', 'fecha_solicitud', 'fecha_vencimiento', 'monto_aprobado', 'estado', 'numero_credito', 'created_at'];

            if (in_array($orderBy, $allowedOrderFields)) {
                $query->orderBy($orderBy, $orderDir === 'asc' ? 'asc' : 'desc');
            } else {
                // Orden por defecto: más reciente primero
                $query->orderBy('id', 'desc');
            }

            // Paginación optimizada con chunks (mínimo 10, máximo 100)
            $perPage = (int) $request->get('per_page', 10);
            $perPage = max(10, min(100, $perPage)); // Asegurar rango 10-100
            $page = (int) $request->get('page', 1);

            // Usar cursor pagination para mejor performance en datasets grandes
            $useCursor = $request->get('use_cursor', false);

            if ($useCursor && $request->has('cursor')) {
                // Cursor pagination - más eficiente para datasets grandes
                $cursor = $request->get('cursor');
                $creditos = $query
                    ->where('id', '>', $cursor)
                    ->take($perPage)
                    ->get();

                $nextCursor = $creditos->last()?->id;
                $hasMore = $creditos->count() === $perPage;

                return response()->json([
                    'success' => true,
                    'data' => $creditos->map(function ($credito) {
                        return $this->formatCreditoList($credito);
                    }),
                    'cursor' => [
                        'next' => $hasMore ? $nextCursor : null,
                        'has_more' => $hasMore,
                        'per_page' => $perPage,
                    ],
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            // Paginación tradicional mejorada con lazy loading para datasets grandes
            $totalFiltrado = (clone $query)->count();

            // Si hay muchos registros (>1000), usar lazy loading
            if ($totalFiltrado > 1000) {
                $creditos = $query
                    ->skip(($page - 1) * $perPage)
                    ->take($perPage)
                    ->lazy(100) // Carga en chunks de 100
                    ->map(function ($credito) {
                        return $this->formatCreditoList($credito);
                    })
                    ->values();
            } else {
                $creditos = $query
                    ->skip(($page - 1) * $perPage)
                    ->take($perPage)
                    ->get()
                    ->map(function ($credito) {
                        return $this->formatCreditoList($credito);
                    });
            }

            return response()->json([
                'success' => true,
                'data' => $creditos,
                'pagination' => [
                    'total' => $totalFiltrado,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($totalFiltrado / $perPage),
                    'from' => (($page - 1) * $perPage) + 1,
                    'to' => min($page * $perPage, $totalFiltrado),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error en CreditoPrendarioController@index', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar créditos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de créditos prendarios
     */
    public function getEstadisticas(): JsonResponse
    {
        try {
            // OPTIMIZACIÓN: Una sola consulta con CASE para todas las estadísticas
            $statsRaw = CreditoPrendario::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN estado = "solicitado" THEN 1 ELSE 0 END) as solicitados,
                SUM(CASE WHEN estado = "en_analisis" THEN 1 ELSE 0 END) as en_analisis,
                SUM(CASE WHEN estado = "aprobado" THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN estado = "vigente" THEN 1 ELSE 0 END) as vigentes,
                SUM(CASE WHEN estado = "vencido" THEN 1 ELSE 0 END) as vencidos,
                SUM(CASE WHEN estado = "en_mora" THEN 1 ELSE 0 END) as en_mora,
                SUM(CASE WHEN estado = "pagado" THEN 1 ELSE 0 END) as pagados,
                SUM(CASE WHEN estado = "cancelado" THEN 1 ELSE 0 END) as cancelados,
                SUM(CASE WHEN estado IN ("vigente", "vencido", "en_mora", "pagado") THEN monto_desembolsado ELSE 0 END) as monto_total_prestado,
                SUM(CASE WHEN estado IN ("vigente", "vencido", "en_mora") THEN capital_pendiente ELSE 0 END) as monto_capital_pendiente,
                SUM(CASE WHEN estado IN ("vigente", "vencido", "en_mora") THEN (interes_generado - interes_pagado) ELSE 0 END) as monto_interes_pendiente,
                SUM(CASE WHEN estado IN ("vigente", "vencido", "en_mora") THEN (mora_generada - mora_pagada) ELSE 0 END) as monto_mora_pendiente
            ')
            ->first();

            $stats = [
                'total' => (int) $statsRaw->total,
                'solicitados' => (int) $statsRaw->solicitados,
                'en_analisis' => (int) $statsRaw->en_analisis,
                'aprobados' => (int) $statsRaw->aprobados,
                'vigentes' => (int) $statsRaw->vigentes,
                'vencidos' => (int) $statsRaw->vencidos,
                'en_mora' => (int) $statsRaw->en_mora,
                'pagados' => (int) $statsRaw->pagados,
                'cancelados' => (int) $statsRaw->cancelados,
                'monto_total_prestado' => (float) $statsRaw->monto_total_prestado,
                'monto_capital_pendiente' => (float) $statsRaw->monto_capital_pendiente,
                'monto_interes_pendiente' => (float) $statsRaw->monto_interes_pendiente,
                'monto_mora_pendiente' => (float) $statsRaw->monto_mora_pendiente,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error en CreditoPrendarioController@getEstadisticas', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo crédito prendario
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cliente_id' => 'required|exists:clientes,id',
            'sucursal_id' => 'required|exists:sucursales,id',
            'monto_solicitado' => 'required|numeric|min:0',
            'monto_aprobado' => 'nullable|numeric|min:0',
            'valor_tasacion' => 'nullable|numeric|min:0',
            'tasa_interes' => 'nullable|numeric|min:0|max:100',
            'tasa_mora' => 'nullable|numeric|min:0|max:10',
            'tipo_interes' => 'nullable|in:diario,semanal,catorcenal,quincenal,cada_28_dias,mensual',
            'metodo_calculo' => 'nullable|in:francesa,flat',
            'afecta_interes_mensual' => 'nullable|boolean',
            'permite_pago_capital_diferente' => 'nullable|boolean',
            'plazo_dias' => 'nullable|integer|min:1',
            'dias_gracia' => 'nullable|integer|min:0',
            'numero_cuotas' => 'nullable|integer|min:1',
            'monto_cuota' => 'nullable|numeric|min:0',
            'fecha_desembolso' => 'nullable|date',
            'fecha_primer_pago' => 'nullable|date',
            'observaciones' => 'nullable|string|max:1000',
            'tasador_id' => 'nullable|exists:users,id',
            // Códigos pre-reservados del wizard
            'numero_credito' => 'nullable|string|max:30',
            'codigo_prenda' => 'nullable|string|max:50',
            'session_token' => 'nullable|string|max:100',
            // Gastos adicionales
            'gas_ids' => 'nullable|array',
            'gas_ids.*' => 'integer|exists:gastos,id_gasto',
            'prendas' => 'required|array|min:1',
            'prendas.*.categoria_producto_id' => 'required|exists:categoria_productos,id',
            'prendas.*.descripcion_general' => 'required|string|max:500',
            'prendas.*.marca' => 'nullable|string|max:100',
            'prendas.*.modelo' => 'nullable|string|max:100',
            'prendas.*.numero_serie' => 'nullable|string|max:100',
            'prendas.*.condicion_fisica' => 'nullable|in:excelente,muy_buena,buena,bueno,regular,deteriorado,mala',
            'prendas.*.fotos' => 'nullable|array',
            'prendas.*.datos_adicionales' => 'nullable|array',
        ], [
            'cliente_id.required' => 'El ID del cliente es requerido',
            'cliente_id.exists' => 'El cliente no existe en la base de datos',
            'sucursal_id.required' => 'El ID de la sucursal es requerido',
            'sucursal_id.exists' => 'La sucursal no existe en la base de datos',
            'monto_solicitado.required' => 'El monto solicitado es requerido',
            'monto_solicitado.numeric' => 'El monto debe ser un número',
            'monto_solicitado.min' => 'El monto debe ser mayor o igual a 0',
            'prendas.required' => 'Debe incluir al menos una prenda',
            'prendas.array' => 'Las prendas deben ser un array',
            'prendas.min' => 'Debe incluir al menos una prenda',
            'prendas.*.categoria_producto_id.required' => 'La categoría de la prenda es requerida',
            'prendas.*.categoria_producto_id.exists' => 'La categoría de producto no existe',
            'prendas.*.descripcion_general.required' => 'La descripción de la prenda es requerida',
            'prendas.*.descripcion_general.string' => 'La descripción debe ser texto',
            'prendas.*.descripcion_general.max' => 'La descripción no puede exceder 500 caracteres',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // VALIDACIÓN: Verificar que las prendas no estén en estado "en_venta"
            // Una prenda en venta no puede ser empeñada
            foreach ($request->prendas as $prendaData) {
                // Esta validación solo aplica si se está intentando crear con una prenda existente
                // (normalmente se crean prendas nuevas, pero por si acaso)
                if (isset($prendaData['id'])) {
                    $prendaExistente = Prenda::find($prendaData['id']);
                    if ($prendaExistente && $prendaExistente->estado === 'en_venta') {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'No se puede crear un crédito con prendas en estado "en venta". La prenda debe cambiarse a otro estado primero.',
                            'errors' => ['prenda' => ['La prenda está en venta y no puede ser empeñada']]
                        ], 422);
                    }
                }
            }

            // Usar código pre-reservado si se proporciona, sino generar uno nuevo
            $numeroCredito = $request->numero_credito;

            if (!$numeroCredito) {
                // 🔒 PROTECCIÓN CONTRA RACE CONDITION: Reintentar hasta 5 veces si hay duplicado
                $intentos = 0;
                $maxIntentos = 5;

                while ($intentos < $maxIntentos) {
                    try {
                        // 🏢 ORGANIZACIÓN: Código configurable desde .env (2 dígitos)
                        $organizacion = str_pad(env('ORGANIZATION_CODE', '01'), 2, '0', STR_PAD_LEFT);

                        // Generar número de crédito único en formato ORGDDMMYYAACORRELATIVO (sin guiones)
                        $fecha = now()->format('dmy'); // Día, Mes, Año (2 dígitos cada uno)

                        // Obtener agencia (solo números, sin letras)
                        $sucursalId = $request->sucursal_id ?? 1;
                        $agencia = str_pad($sucursalId, 2, '0', STR_PAD_LEFT);

                        // 🔐 BLOQUEO: Usar lockForUpdate para prevenir race condition
                        $hoy = now()->format('Y-m-d');
                        $prefijoBusqueda = $organizacion . $fecha . $agencia;

                        $ultimoCredito = CreditoPrendario::withTrashed()
                            ->whereDate('created_at', $hoy)
                            ->whereNotNull('numero_credito')
                            // Solo buscar códigos con el nuevo formato (no inician con CR-)
                            ->where('numero_credito', 'NOT LIKE', 'CR-%')
                            ->where('numero_credito', 'LIKE', $prefijoBusqueda . '%')
                            ->lockForUpdate() // 🔒 Bloqueo para lectura concurrente
                            ->orderBy('id', 'desc')
                            ->first();

                        if ($ultimoCredito && $ultimoCredito->numero_credito) {
                            // Extraer correlativo del formato ORGDDMMYYAACORRELATIVO (últimos 6 dígitos)
                            $ultimoCorrelativo = (int) substr($ultimoCredito->numero_credito, -6);
                            $correlativo = $ultimoCorrelativo + 1;
                        } else {
                            $correlativo = 1;
                        }

                        // Formato: ORGDDMMYYAACORRELATIVO (16 dígitos sin guiones)
                        // Ejemplo: 0102022601000001 = Org:01, 02/02/26, Agencia:01, Correlativo:000001
                        $numeroCredito = $organizacion . $fecha . $agencia . str_pad($correlativo, 6, '0', STR_PAD_LEFT);

                        // ✅ Verificar que no exista (doble verificación)
                        $existe = CreditoPrendario::withTrashed()
                            ->where('numero_credito', $numeroCredito)
                            ->exists();

                        if ($existe) {
                            throw new \Exception("Código duplicado detectado: {$numeroCredito}");
                        }

                        break; // ✅ Código generado exitosamente, salir del loop

                    } catch (\Exception $e) {
                        $intentos++;

                        if ($intentos >= $maxIntentos) {
                            Log::error('ERROR CRÍTICO: No se pudo generar código único después de ' . $maxIntentos . ' intentos', [
                                'error' => $e->getMessage(),
                                'organizacion' => $organizacion ?? null,
                                'fecha' => $fecha ?? null,
                                'agencia' => $agencia ?? null
                            ]);

                            return response()->json([
                                'success' => false,
                                'message' => 'Error al generar código de crédito único. Por favor, intente nuevamente.'
                            ], 500);
                        }

                        // Esperar un momento antes de reintentar (backoff exponencial)
                        usleep(100000 * $intentos); // 100ms, 200ms, 300ms, etc.
                    }
                }
            }

            // Calcular fecha de vencimiento si hay plazo
            $fechaVencimiento = null;
            if ($request->plazo_dias) {
                $fechaVencimiento = now()->addDays($request->plazo_dias);
            }

            // Calcular monto de cuota si hay número de cuotas
            $montoCuota = null;
            if ($request->monto_aprobado && $request->numero_cuotas && $request->numero_cuotas > 0) {
                $montoCuota = $request->monto_aprobado / $request->numero_cuotas;
            }

            // Obtener parámetros de la categoría si no se proporcionan
            $categoriaId = $request->prendas[0]['categoria_producto_id'] ?? null;
            $categoria = null;
            if ($categoriaId) {
                $categoria = \App\Models\CategoriaProducto::find($categoriaId);
            }

            // Crear crédito prendario con todos los campos
            $datosCredito = [
                'numero_credito' => $numeroCredito,
                'cliente_id' => $request->cliente_id,
                'sucursal_id' => $request->sucursal_id,
                'tasador_id' => $request->tasador_id ?? Auth::id(),
                'monto_solicitado' => $request->monto_solicitado,
                'monto_aprobado' => $request->monto_aprobado ?? $request->monto_solicitado,
                'valor_tasacion' => $request->valor_tasacion,
                'tasa_interes' => $request->tasa_interes ?? ($categoria?->tasa_interes_default ?? 0),
                'tasa_mora' => $request->tasa_mora ?? ($categoria?->tasa_mora_default ?? 0),
                'tipo_interes' => $request->tipo_interes ?? 'mensual',
                'metodo_calculo' => 'flat', // Siempre 'flat' para créditos prendarios
                'afecta_interes_mensual' => $request->afecta_interes_mensual ?? ($categoria?->afecta_interes_mensual ?? false),
                'permite_pago_capital_diferente' => $request->permite_pago_capital_diferente ?? ($categoria?->permite_pago_capital_diferente ?? false),
                'plazo_dias' => $request->plazo_dias,
                'dias_gracia' => $request->dias_gracia ?? 0,
                'numero_cuotas' => $request->numero_cuotas ?? 1,
                'monto_cuota' => $montoCuota,
                'fecha_desembolso' => $request->fecha_desembolso ? Carbon::parse($request->fecha_desembolso) : null,
                'observaciones' => $request->observaciones,
                // Estado inicial: vigente (el crédito se aprueba y desembolsa al crearse desde el wizard)
                'estado' => 'vigente',
                'fecha_solicitud' => now(),
                'fecha_aprobacion' => now(),
                'fecha_desembolso' => $request->fecha_desembolso ? Carbon::parse($request->fecha_desembolso) : now(),
                'monto_desembolsado' => $request->monto_aprobado ?? $request->monto_solicitado,
                'fecha_vencimiento' => $fechaVencimiento,
                // Inicializar campos de capital e intereses
                'capital_pendiente' => $request->monto_aprobado ?? $request->monto_solicitado,
                'capital_pagado' => 0,
                'interes_generado' => 0,
                'interes_pagado' => 0,
                'mora_generada' => 0,
                'mora_pagada' => 0,
            ];

            // Agregar fecha_primer_pago solo si la columna existe en la base de datos
            // Esto permite que el código funcione antes de ejecutar la migración
            if ($request->fecha_primer_pago) {
                try {
                    // Verificar si la columna existe consultando el esquema
                    $columnExists = DB::select("SHOW COLUMNS FROM `creditos_prendarios` LIKE 'fecha_primer_pago'");
                    if (!empty($columnExists)) {
                        $datosCredito['fecha_primer_pago'] = Carbon::parse($request->fecha_primer_pago);
                        Log::info('fecha_primer_pago agregada al crédito', [
                            'fecha_primer_pago' => $request->fecha_primer_pago,
                            'parseada' => $datosCredito['fecha_primer_pago']->format('Y-m-d'),
                        ]);
                    } else {
                        Log::warning('Columna fecha_primer_pago NO existe en la tabla');
                    }
                } catch (\Exception $e) {
                    // Si hay error al verificar, simplemente no agregamos el campo
                    Log::warning('No se pudo verificar columna fecha_primer_pago: ' . $e->getMessage());
                }
            } else {
                Log::info('No se recibió fecha_primer_pago en el request', [
                    'fecha_desembolso' => $request->fecha_desembolso,
                    'tiene_fecha_primer_pago' => $request->has('fecha_primer_pago'),
                    'valor' => $request->input('fecha_primer_pago'),
                ]);
            }

            $credito = CreditoPrendario::create($datosCredito);

            // Generar código único de prenda (una vez para todas las prendas)
            $ultimaPrenda = Prenda::withTrashed()
                ->whereNotNull('codigo_prenda')
                ->orderBy('id', 'desc')
                ->first();

            $numeroPrendaBase = 1;
            if ($ultimaPrenda && $ultimaPrenda->codigo_prenda) {
                // Extraer número del formato PRN-XXXXXX
                $ultimoNumero = (int) substr($ultimaPrenda->codigo_prenda, 4);
                $numeroPrendaBase = $ultimoNumero;
            }

            // Crear prendas y tasaciones
            $primeraPrendaId = null;
            foreach ($request->prendas as $index => $prendaData) {
                // Usar código pre-reservado para la primera prenda si se proporciona
                $codigoPrenda = null;
                if ($index === 0 && $request->codigo_prenda) {
                    $codigoPrenda = $request->codigo_prenda;
                } else {
                    $numeroPrenda = $numeroPrendaBase + $index + 1;
                    $codigoPrenda = 'PRN-' . str_pad($numeroPrenda, 6, '0', STR_PAD_LEFT);
                }

                // Mapear condición física del frontend al formato de BD
                // Frontend: excelente, bueno, regular, deteriorado
                // BD: excelente, muy_buena, buena, regular, mala
                $condicionMap = [
                    'excelente' => 'excelente',
                    'muy_buena' => 'muy_buena',
                    'muy_bueno' => 'muy_buena',  // Mapeo masculino->femenino
                    'bueno' => 'buena',  // Mapeo masculino->femenino
                    'buena' => 'buena',
                    'regular' => 'regular',
                    'deteriorado' => 'mala',
                    'deteriorada' => 'mala',
                    'mala' => 'mala',
                    'malo' => 'mala'
                ];

                $condicionFisica = $prendaData['condicion_fisica'] ?? 'bueno';
                $condicion = $condicionMap[$condicionFisica] ?? 'buena';

                // Validar que fotos sea un array válido
                $fotos = [];
                if (isset($prendaData['fotos'])) {
                    if (is_array($prendaData['fotos'])) {
                        $fotos = $prendaData['fotos'];
                    } elseif (is_string($prendaData['fotos'])) {
                        // Intentar decodificar si es JSON string
                        $decoded = json_decode($prendaData['fotos'], true);
                        $fotos = is_array($decoded) ? $decoded : [];
                    }
                }

                $prenda = Prenda::create([
                    'credito_prendario_id' => $credito->id,
                    'categoria_producto_id' => $prendaData['categoria_producto_id'],
                    'codigo_prenda' => $codigoPrenda,
                    'descripcion' => $prendaData['descripcion_general'],
                    'marca' => $prendaData['marca'] ?? null,
                    'modelo' => $prendaData['modelo'] ?? null,
                    'serie' => $prendaData['numero_serie'] ?? null,
                    'condicion' => $condicion,
                    'fotos' => $fotos,
                    'estado' => 'en_custodia',
                    'fecha_ingreso' => now(),
                    // Valores de tasación
                    'valor_estimado_cliente' => $prendaData['valor_estimado_cliente'] ?? null,
                    'valor_tasacion' => $prendaData['valor_tasacion'] ?? $request->valor_tasacion,
                    'valor_prestamo' => $prendaData['valor_prestamo'] ?? $request->monto_aprobado,
                    'porcentaje_prestamo' => $prendaData['porcentaje_prestamo'] ?? null,
                    // Datos adicionales dinámicos según la categoría
                    'datos_adicionales' => $prendaData['datos_adicionales'] ?? null,
                ]);

                // Guardar datos adicionales en tabla normalizada (EAV)
                if (!empty($prendaData['datos_adicionales']) && is_array($prendaData['datos_adicionales'])) {
                    $orden = 0;
                    foreach ($prendaData['datos_adicionales'] as $campoNombre => $campoValor) {
                        if ($campoValor !== null && $campoValor !== '') {
                            // Determinar el tipo de campo basado en el valor
                            $campoTipo = 'text';
                            if (is_numeric($campoValor)) {
                                $campoTipo = 'number';
                            } elseif (is_bool($campoValor)) {
                                $campoTipo = 'checkbox';
                            }

                            // Crear etiqueta legible desde el nombre del campo (snake_case a Title Case)
                            $campoLabel = ucwords(str_replace('_', ' ', $campoNombre));

                            PrendaDatoAdicional::create([
                                'prenda_id' => $prenda->id,
                                'campo_nombre' => $campoNombre,
                                'campo_valor' => is_array($campoValor) ? json_encode($campoValor) : (string) $campoValor,
                                'campo_tipo' => $campoTipo,
                                'campo_label' => $campoLabel,
                                'orden' => $orden++,
                            ]);
                        }
                    }
                }

                // Guardar imágenes en tabla normalizada
                if (!empty($fotos) && is_array($fotos)) {
                    foreach ($fotos as $ordenFoto => $foto) {
                        $tipoImagen = 'general';
                        $base64Data = null;
                        $urlExterna = null;

                        // Determinar si es base64 o URL
                        if (is_string($foto)) {
                            if (strpos($foto, 'data:image') === 0) {
                                $base64Data = $foto;
                            } else {
                                $urlExterna = $foto;
                            }
                        } elseif (is_array($foto)) {
                            $base64Data = $foto['data'] ?? $foto['base64'] ?? null;
                            $urlExterna = $foto['url'] ?? null;
                            $tipoImagen = $foto['tipo'] ?? $foto['tipo_imagen'] ?? 'general';
                        }

                        if ($base64Data) {
                            // Guardar imagen desde base64
                            PrendaImagen::crearDesdeBase64(
                                $prenda->id,
                                $base64Data,
                                $tipoImagen,
                                Auth::id(),
                                $ordenFoto
                            );
                        } elseif ($urlExterna) {
                            // Guardar referencia a URL externa
                            PrendaImagen::create([
                                'prenda_id' => $prenda->id,
                                'nombre_archivo' => basename(parse_url($urlExterna, PHP_URL_PATH) ?: 'imagen_' . $ordenFoto),
                                'ruta_almacenamiento' => $urlExterna,
                                'url_publica' => $urlExterna,
                                'tipo_imagen' => $tipoImagen,
                                'es_principal' => $ordenFoto === 0,
                                'orden' => $ordenFoto,
                                'subida_por' => Auth::id(),
                            ]);
                        }
                    }
                }

                if ($index === 0) {
                    $primeraPrendaId = $prenda->id;
                }
            }

            // Crear registro de tasación si se proporcionaron datos
            if ($request->has('tasacion') && !empty($request->tasacion) && $primeraPrendaId) {
                $tasacionData = $request->tasacion;

                // Generar número de tasación
                $ultimaTasacion = Tasacion::withTrashed()
                    ->whereNotNull('numero_tasacion')
                    ->orderBy('id', 'desc')
                    ->first();

                $numeroTasacion = 1;
                if ($ultimaTasacion && $ultimaTasacion->numero_tasacion) {
                    $ultimoNumero = (int) substr($ultimaTasacion->numero_tasacion, 4);
                    $numeroTasacion = $ultimoNumero + 1;
                }

                $numeroTasacion = 'TAS-' . str_pad($numeroTasacion, 6, '0', STR_PAD_LEFT);

                // Preparar referencias de mercado si existen factores aplicados
                $referenciasMercado = [];
                if (isset($tasacionData['factores_aplicados'])) {
                    $factores = is_string($tasacionData['factores_aplicados'])
                        ? json_decode($tasacionData['factores_aplicados'], true)
                        : $tasacionData['factores_aplicados'];

                    if (is_array($factores)) {
                        $referenciasMercado = [
                            'porcentaje_base' => $tasacionData['porcentaje_base'] ?? null,
                            'antiguedad' => $tasacionData['antiguedad'] ?? null,
                            'demanda_local' => $tasacionData['demanda_local'] ?? null,
                            'riesgo_almacenamiento' => $tasacionData['riesgo_almacenamiento'] ?? null,
                            'dificultad_reventa' => $tasacionData['dificultad_reventa'] ?? null,
                            'ajuste_manual' => $tasacionData['ajuste_manual'] ?? null,
                            'factores' => $factores
                        ];
                    }
                }

                Tasacion::create([
                    'prenda_id' => $primeraPrendaId,
                    'tasador_id' => $request->tasador_id ?? Auth::id(),
                    'credito_prendario_id' => $credito->id,
                    'numero_tasacion' => $numeroTasacion,
                    'fecha_tasacion' => now(),
                    'valor_mercado' => $tasacionData['valor_mercado'] ?? null,
                    'valor_comercial' => $tasacionData['valor_comercial'] ?? $tasacionData['valor_final_asignado'] ?? null,
                    'valor_liquidacion' => $tasacionData['valor_liquidacion'] ?? null,
                    'valor_final_asignado' => $tasacionData['valor_final_asignado'] ?? $tasacionData['valor_comercial'] ?? null,
                    'condicion_fisica' => $condicion,  // Usar el valor mapeado correctamente
                    'antiguedad_estimada' => $tasacionData['antiguedad'] ?? null,
                    'metodo_tasacion' => $this->mapearMetodoTasacion($tasacionData['metodo_valuacion'] ?? 'comparativo'),
                    'observaciones' => $tasacionData['observaciones'] ?? null,
                    'referencias_mercado' => $referenciasMercado,
                    'fotos_tasacion' => $tasacionData['fotos_tasacion'] ?? [],
                    'estado' => 'aprobada',
                    'aprobado_por' => Auth::id(),
                    'fecha_aprobacion' => now(),
                ]);
            }

            // Generar plan de pagos automáticamente
            // Pasar fecha_primer_pago del request directamente para asegurar que se use
            if ($credito->numero_cuotas && $credito->numero_cuotas > 0 && $credito->monto_aprobado) {
                $fechaPrimerPagoRequest = $request->fecha_primer_pago ? Carbon::parse($request->fecha_primer_pago) : null;
                $this->generarPlanPagos($credito, $fechaPrimerPagoRequest);
            }

            // Marcar códigos pre-reservados como usados si se proporcionó session_token
            if ($request->session_token) {
                try {
                    CodigoPrereservado::where('session_token', $request->session_token)
                        ->where('estado', 'reservado')
                        ->update([
                            'estado' => 'usado'
                        ]);
                } catch (\Exception $e) {
                    // Log del error pero no fallar la transacción
                    Log::warning('Error al marcar códigos como usados: ' . $e->getMessage());
                }
            }

            // ========================================
            // SINCRONIZAR GASTOS ADICIONALES (SI SE ENVIARON)
            // ========================================
            if ($request->has('gas_ids') && is_array($request->gas_ids) && count($request->gas_ids) > 0) {
                try {
                    $gastosService = app(\App\Services\GastosService::class);
                    $gastosService->sincronizarGastos($credito, $request->gas_ids);

                    Log::info('Gastos asociados al crédito', [
                        'credito_id' => $credito->id,
                        'gas_ids' => $request->gas_ids
                    ]);

                    // Prorratear gastos en el plan de pagos
                    $this->prorratearGastosEnPlanPagos($credito);
                } catch (\Exception $gastoEx) {
                    // Log pero no fallar la transacción si hay error con gastos
                    Log::warning('Error al sincronizar gastos: ' . $gastoEx->getMessage());
                }
            }

            // ========================================
            // REGISTRAR DESEMBOLSO EN CAJA (EGRESO)
            // ========================================
            // Solo si el desembolso es en efectivo y hay caja abierta
            $formaDesembolso = $request->forma_desembolso ?? 'efectivo';
            if ($formaDesembolso === 'efectivo') {
                $montoDesembolso = $credito->monto_aprobado ?? $credito->monto_solicitado;
                $clienteNombre = null;
                if ($credito->cliente) {
                    $clienteNombre = $credito->cliente->nombres . ' ' . $credito->cliente->apellidos;
                }

                try {
                    CajaService::registrarDesembolso(
                        $montoDesembolso,
                        $credito->numero_credito,
                        $clienteNombre,
                        $formaDesembolso
                    );
                } catch (\Exception $cajaEx) {
                    // Log pero no fallar si no hay caja abierta
                    Log::warning('No se pudo registrar desembolso en caja: ' . $cajaEx->getMessage());
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Crédito prendario creado exitosamente',
                'data' => $this->formatCredito($credito->fresh(['cliente', 'sucursal', 'prendas', 'planPagos']))
            ], 201);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            // Detectar errores específicos de SQL para devolver mensajes más claros
            $errorMessage = 'Error al crear el crédito prendario';

            // Error ENUM: Data truncated
            if (strpos($e->getMessage(), '1265') !== false || strpos($e->getMessage(), 'Data truncated') !== false) {
                if (strpos($e->getMessage(), 'condicion_fisica') !== false) {
                    $errorMessage = 'Valor inválido para condición física. Valores permitidos: excelente, muy_buena, buena, regular, mala';
                } else {
                    // Intentar extraer el nombre de la columna del mensaje de error
                    preg_match("/for column '([^']+)'/", $e->getMessage(), $matches);
                    $columna = $matches[1] ?? 'campo';
                    $errorMessage = "Valor inválido para el campo '{$columna}'";
                }
            }
            // Error de clave duplicada
            else if (strpos($e->getMessage(), '1062') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $errorMessage = 'Ya existe un registro con estos datos';
            }
            // Error de clave foránea
            else if (strpos($e->getMessage(), '1452') !== false || strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $errorMessage = 'Error de referencia: verifique que todos los datos existan';
            }

            // Log del error completo para debugging
            Log::error('Error SQL al crear crédito prendario: ' . $e->getMessage(), [
                'code' => $e->getCode(),
                'sql' => $e->getSql() ?? 'N/A',
                'bindings' => $e->getBindings() ?? [],
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error' => config('app.debug') ? $e->getMessage() : null,
                'code' => $e->getCode()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();

            // Log del error completo para debugging
            Log::error('Error al crear crédito prendario: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el crédito prendario',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Obtener un crédito por ID
     */
    public function show(string $id): JsonResponse
    {
        $credito = CreditoPrendario::with([
            'cliente',
            'sucursal',
            'prendas.imagenesNormalizadas',
            'prendas.imagenPrincipal',
            'prendas.datosAdicionalesNormalizados',
            'prendas.categoriaProducto',
            'tasaciones',
            'movimientos',
            'planPagos' => function($query) {
                $query->orderBy('numero_cuota', 'asc');
            },
            'gastos'
        ])->find($id);

        if (!$credito) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito prendario no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatCredito($credito)
        ]);
    }

    /**
     * Obtener plan de pagos de un crédito
     */
    public function getPlanPagos(string $id): JsonResponse
    {
        $credito = CreditoPrendario::with('gastos')->find($id);

        if (!$credito) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito prendario no encontrado'
            ], 404);
        }

        $planPagos = $credito->planPagos()->orderBy('numero_cuota')->get();
        $numeroCuotas = $planPagos->count();

        // Calcular gastos del crédito
        $gastosService = app(\App\Services\GastosService::class);
        $montoOtorgado = (float) ($credito->monto_aprobado ?? $credito->monto_solicitado ?? 0);
        $gastosResult = $gastosService->obtenerGastosCredito($credito);
        $totalGastos = $gastosResult['total_gastos'];

        // Calcular prorrateo de gastos por cuota
        $gastosPorCuota = $numeroCuotas > 0
            ? $gastosService->calcularProrrateoPorCuota($totalGastos, $numeroCuotas)
            : [];

        // Calcular totales
        $totalCapital = 0;
        $totalInteres = 0;
        $cuotasFormateadas = [];
        $index = 0;

        foreach ($planPagos as $cuota) {
            $capital = (float) $cuota->capital_proyectado;
            $interes = (float) $cuota->interes_proyectado;
            $mora = (float) $cuota->mora_proyectada;
            $gastoCuota = $gastosPorCuota[$index] ?? 0;
            $cuotaBase = $capital + $interes;
            $cuotaTotal = $cuotaBase + $gastoCuota + $mora;

            $totalCapital += $capital;
            $totalInteres += $interes;

            $cuotasFormateadas[] = [
                'id' => (string) $cuota->id,
                'numero_cuota' => $cuota->numero_cuota,
                'fecha_vencimiento' => $cuota->fecha_vencimiento?->toISOString(),
                'fecha_pago' => $cuota->fecha_pago?->toISOString(),
                'estado' => $cuota->estado,
                // Componentes de la cuota
                'capital' => round($capital, 2),
                'interes' => round($interes, 2),
                'gastos' => round($gastoCuota, 2),
                'mora' => round($mora, 2),
                'cuota_base' => round($cuotaBase, 2),
                'cuota_total' => round($cuotaTotal, 2),
                // Datos originales (compatibilidad)
                'capital_proyectado' => round($capital, 2),
                'interes_proyectado' => round($interes, 2),
                'mora_proyectada' => round($mora, 2),
                'monto_cuota_proyectado' => round($cuotaTotal, 2),
                // Pagos realizados
                'capital_pagado' => (float) $cuota->capital_pagado,
                'interes_pagado' => (float) $cuota->interes_pagado,
                'mora_pagada' => (float) $cuota->mora_pagada,
                'monto_total_pagado' => (float) $cuota->monto_total_pagado,
                // Pendientes
                'capital_pendiente' => (float) $cuota->capital_pendiente,
                'interes_pendiente' => (float) $cuota->interes_pendiente,
                'mora_pendiente' => (float) $cuota->mora_pendiente,
                'monto_pendiente' => (float) $cuota->monto_pendiente,
                'saldo_capital_credito' => (float) $cuota->saldo_capital_credito,
                'dias_mora' => $cuota->dias_mora,
                'es_cuota_gracia' => (bool) $cuota->es_cuota_gracia,
                'observaciones' => $cuota->observaciones,
            ];
            $index++;
        }

        // Calcular total a pagar
        $totalAPagar = round($montoOtorgado + $totalInteres + $totalGastos, 2);

        return response()->json([
            'success' => true,
            'data' => $cuotasFormateadas,
            'resumen' => [
                'monto_otorgado' => round($montoOtorgado, 2),
                'total_capital' => round($totalCapital, 2),
                'total_interes' => round($totalInteres, 2),
                'total_gastos' => round($totalGastos, 2),
                'total_a_pagar' => $totalAPagar,
                'numero_cuotas' => $numeroCuotas,
                'gastos_detalle' => $gastosResult['gastos'],
            ],
        ]);
    }

    /**
     * Formatear crédito para respuesta
     */
    private function formatCredito(CreditoPrendario $credito): array
    {
        return [
            'id' => (string) $credito->id,
            'numero_credito' => $credito->numero_credito,
            'codigo_credito' => $credito->numero_credito, // Alias para compatibilidad con frontend
            'cliente' => $credito->cliente ? [
                'id' => (string) $credito->cliente->id,
                'codigo_cliente' => $credito->cliente->codigo_cliente,
                'nombres' => $credito->cliente->nombres,
                'apellidos' => $credito->cliente->apellidos,
                'dpi' => $credito->cliente->dpi,
                'telefono' => $credito->cliente->telefono,
            ] : null,
            'sucursal' => $credito->sucursal ? [
                'id' => (string) $credito->sucursal->id,
                'nombre' => $credito->sucursal->nombre,
            ] : null,
            'estado' => $credito->estado,
            'fecha_solicitud' => $credito->fecha_solicitud?->toISOString(),
            'fecha_vencimiento' => $credito->fecha_vencimiento?->toISOString(),
            'monto_solicitado' => (float) $credito->monto_solicitado,
            'monto_aprobado' => (float) $credito->monto_aprobado,
            'monto_desembolsado' => (float) $credito->monto_desembolsado,
            'capital_pendiente' => (float) $credito->capital_pendiente,
            'capital_pagado' => (float) $credito->capital_pagado,
            'interes_generado' => (float) $credito->interes_generado,
            'interes_pagado' => (float) $credito->interes_pagado,
            'intereses_pendientes' => (float) ($credito->interes_generado - $credito->interes_pagado), // Calcular intereses pendientes
            'mora_generada' => (float) $credito->mora_generada,
            'mora_pagada' => (float) $credito->mora_pagada,
            'mora_pendiente' => (float) ($credito->mora_generada - $credito->mora_pagada), // Calcular mora pendiente
            'tasa_interes' => (float) $credito->tasa_interes,
            'tasa_mora' => (float) $credito->tasa_mora,
            'plazo_dias' => $credito->plazo_dias,
            'dias_mora' => $credito->dias_mora,
            'prendas' => $credito->prendas->map(function ($prenda) {
                return [
                    'id' => (string) $prenda->id,
                    'codigo_prenda' => $prenda->codigo_prenda,
                    'descripcion' => $prenda->descripcion,
                    'descripcion_general' => $prenda->descripcion, // Alias para compatibilidad con frontend
                    'marca' => $prenda->marca,
                    'modelo' => $prenda->modelo,
                    'numero_serie' => $prenda->numero_serie,
                    'categoria' => $prenda->categoriaProducto?->nombre,
                    'categoria_producto_id' => $prenda->categoria_producto_id ? (string) $prenda->categoria_producto_id : null,
                    'valor_tasacion' => $prenda->valor_tasacion ? (float) $prenda->valor_tasacion : null,
                    'valor_prestamo' => $prenda->valor_prestamo ? (float) $prenda->valor_prestamo : null,
                    'estado' => $prenda->estado,
                    'condicion_fisica' => $prenda->condicion_fisica,
                    'fotos' => is_array($prenda->fotos) ? $prenda->fotos : (is_string($prenda->fotos) ? json_decode($prenda->fotos, true) ?? [] : []),
                    // Imágenes normalizadas
                    'imagenes' => $prenda->imagenesNormalizadas->map(function ($img) {
                        return [
                            'id' => (string) $img->id,
                            'url' => $img->url,
                            'thumbnail_url' => $img->thumbnail_url,
                            'tipo' => $img->tipo_imagen,
                            'etiqueta' => $img->etiqueta,
                            'descripcion' => $img->descripcion,
                            'es_principal' => $img->es_principal,
                            'orden' => $img->orden,
                            'dimensiones' => $img->dimensiones,
                            'tamano' => $img->tamano_formateado,
                        ];
                    }),
                    'imagen_principal' => $prenda->imagenPrincipal ? [
                        'id' => (string) $prenda->imagenPrincipal->id,
                        'url' => $prenda->imagenPrincipal->url,
                        'thumbnail_url' => $prenda->imagenPrincipal->thumbnail_url,
                    ] : null,
                    // Datos adicionales normalizados
                    'datos_adicionales_normalizados' => $prenda->datosAdicionalesNormalizados->map(function ($dato) {
                        return [
                            'campo' => $dato->campo_nombre,
                            'valor' => $dato->campo_valor,
                            'tipo' => $dato->campo_tipo,
                            'label' => $dato->campo_label,
                        ];
                    }),
                ];
            }),
            'plan_pagos' => $credito->planPagos ? $credito->planPagos->sortBy('numero_cuota')->map(function ($cuota) {
                return [
                    'id' => (string) $cuota->id,
                    'numero_cuota' => $cuota->numero_cuota,
                    'fecha_vencimiento' => $cuota->fecha_vencimiento?->toISOString(),
                    'fecha_pago' => $cuota->fecha_pago?->toISOString(),
                    'estado' => $cuota->estado,
                    'capital_proyectado' => (float) $cuota->capital_proyectado,
                    'interes_proyectado' => (float) $cuota->interes_proyectado,
                    'mora_proyectada' => (float) $cuota->mora_proyectada,
                    'gastos_proyectado' => (float) $cuota->otros_cargos_proyectados, // Gastos prorrateados por cuota
                    'monto_cuota_proyectado' => (float) $cuota->monto_cuota_proyectado,
                    'capital_pagado' => (float) $cuota->capital_pagado,
                    'interes_pagado' => (float) $cuota->interes_pagado,
                    'mora_pagada' => (float) $cuota->mora_pagada,
                    'gastos_pagado' => (float) $cuota->otros_cargos_pagados, // Gastos pagados
                    'monto_total_pagado' => (float) $cuota->monto_total_pagado,
                    'capital_pendiente' => (float) $cuota->capital_pendiente,
                    'interes_pendiente' => (float) $cuota->interes_pendiente,
                    'mora_pendiente' => (float) $cuota->mora_pendiente,
                    'gastos_pendiente' => (float) $cuota->otros_cargos_pendientes, // Gastos pendientes
                    'monto_pendiente' => (float) $cuota->monto_pendiente,
                    'saldo_capital_credito' => (float) $cuota->saldo_capital_credito,
                    'dias_mora' => $cuota->dias_mora,
                    'es_cuota_gracia' => (bool) $cuota->es_cuota_gracia,
                    'tipo_modificacion' => $cuota->tipo_modificacion ?? 'original',
                    'motivo_modificacion' => $cuota->motivo_modificacion,
                    'fecha_modificacion' => $cuota->fecha_modificacion?->toISOString(),
                ];
            })->values() : [],
            // Gastos asociados al crédito
            'gastos' => $credito->gastos ? $credito->gastos->map(function ($gasto) {
                return [
                    'id_gasto' => $gasto->id_gasto,
                    'nombre' => $gasto->nombre,
                    'tipo' => $gasto->tipo,
                    'porcentaje' => $gasto->porcentaje ? (float) $gasto->porcentaje : null,
                    'monto' => $gasto->monto ? (float) $gasto->monto : null,
                    'valor_calculado' => (float) ($gasto->pivot->valor_calculado ?? 0),
                ];
            }) : [],
            'total_gastos' => $credito->gastos ? $credito->gastos->sum(function ($g) {
                return (float) ($g->pivot->valor_calculado ?? 0);
            }) : 0,
            'creadoEn' => $credito->created_at->toISOString(),
            'actualizadoEn' => $credito->updated_at->toISOString(),
        ];
    }

    /**
     * Formatear crédito para listado (versión ligera sin relaciones pesadas)
     */
    private function formatCreditoList(CreditoPrendario $credito): array
    {
        return [
            'id' => (string) $credito->id,
            'numero_credito' => $credito->numero_credito,
            'codigo_credito' => $credito->numero_credito,
            'cliente' => $credito->cliente ? [
                'id' => (string) $credito->cliente->id,
                'codigo_cliente' => $credito->cliente->codigo_cliente,
                'nombres' => $credito->cliente->nombres,
                'apellidos' => $credito->cliente->apellidos,
                'dpi' => $credito->cliente->dpi,
            ] : null,
            'sucursal' => $credito->sucursal ? [
                'id' => (string) $credito->sucursal->id,
                'nombre' => $credito->sucursal->nombre,
            ] : null,
            'estado' => $credito->estado,
            'fecha_solicitud' => $credito->fecha_solicitud?->toISOString(),
            'fecha_vencimiento' => $credito->fecha_vencimiento?->toISOString(),
            'monto_solicitado' => (float) $credito->monto_solicitado,
            'monto_aprobado' => (float) $credito->monto_aprobado,
            'capital_pendiente' => (float) $credito->capital_pendiente,
            'intereses_pendientes' => (float) ($credito->interes_generado - $credito->interes_pagado),
            'mora_pendiente' => (float) ($credito->mora_generada - $credito->mora_pagada),
            'tasa_interes' => (float) $credito->tasa_interes,
            'plazo_dias' => $credito->plazo_dias,
            'dias_mora' => $credito->dias_mora,
            // Incluir información básica de prendas para el listado
            'prendas' => $credito->prendas ? $credito->prendas->map(function ($prenda) {
                return [
                    'id' => (string) $prenda->id,
                    'codigo_prenda' => $prenda->codigo_prenda,
                    'descripcion' => $prenda->descripcion,
                    'marca' => $prenda->marca,
                    'modelo' => $prenda->modelo,
                    'categoria' => $prenda->categoriaProducto ? $prenda->categoriaProducto->nombre : null,
                    'categoria_producto_id' => $prenda->categoria_producto_id ? (string) $prenda->categoria_producto_id : null,
                    'valor_tasacion' => $prenda->valor_tasacion ? (float) $prenda->valor_tasacion : null,
                    'valor_prestamo' => $prenda->valor_prestamo ? (float) $prenda->valor_prestamo : null,
                    'estado' => $prenda->estado,
                ];
            })->values() : [],
            'creadoEn' => $credito->created_at->toISOString(),
            'actualizadoEn' => $credito->updated_at->toISOString(),
        ];
    }

    /**
     * Generar plan de pagos automáticamente al crear un crédito
     * Soporta dos métodos: francesa (amortización) y flat (interés fijo)
     *
     * @param CreditoPrendario $credito El crédito para el cual generar el plan
     * @param Carbon|null $fechaPrimerPagoRequest La fecha del primer pago del request (opcional)
     */
    private function generarPlanPagos(CreditoPrendario $credito, ?Carbon $fechaPrimerPagoRequest = null): void
    {
        $montoAprobado = $credito->monto_aprobado;
        $numeroCuotas = $credito->numero_cuotas ?? 1;
        $tasaInteres = $credito->tasa_interes ?? 0;
        $tipoInteres = $credito->tipo_interes ?? 'mensual';
        $diasGracia = $credito->dias_gracia ?? 0;
        $tasaMora = $credito->tasa_mora ?? 0;
        // Método de cálculo siempre es 'flat' para créditos prendarios
        $metodoCalculo = 'flat';
        $afectaInteresMensual = $credito->afecta_interes_mensual ?? false;
        $permitePagoCapitalDiferente = $credito->permite_pago_capital_diferente ?? false;

        // Calcular fecha de inicio (fecha de desembolso o fecha del primer pago si está definida)
        $fechaDesembolso = $credito->fecha_desembolso ? Carbon::parse($credito->fecha_desembolso) : now();

        // Prioridad: usar fecha_primer_pago del request, luego del crédito guardado, luego calcular desde desembolso
        $fechaPrimerPago = $fechaPrimerPagoRequest;
        if (!$fechaPrimerPago && $credito->fecha_primer_pago) {
            $fechaPrimerPago = Carbon::parse($credito->fecha_primer_pago);
        }

        // DEBUG: Log para identificar problemas de fecha
        Log::info('generarPlanPagos - Fechas recibidas', [
            'credito_id' => $credito->id,
            'numero_credito' => $credito->numero_credito,
            'fechaPrimerPagoRequest' => $fechaPrimerPagoRequest?->format('Y-m-d'),
            'credito_fecha_primer_pago' => $credito->fecha_primer_pago,
            'fechaPrimerPago_final' => $fechaPrimerPago?->format('Y-m-d'),
            'fechaDesembolso' => $fechaDesembolso->format('Y-m-d'),
            'dias_gracia' => $diasGracia,
        ]);

        // Calcular días entre cuotas según tipo de interés
        $diasEntreCuotas = $this->calcularDiasEntreCuotasPorTipo($tipoInteres);

        // Calcular tasa de interés por período
        $tasaPorPeriodo = $this->calcularTasaPorPeriodo($tasaInteres, $tipoInteres);

        // Calcular según método de cálculo
        if ($metodoCalculo === 'flat') {
            // MÉTODO FLAT: Interés fijo sobre monto total en cada cuota, capital fijo
            // Para créditos prendarios la tasa ya viene como MENSUAL (ej: 15% = 0.15 por mes)
            //
            // Fórmulas para método FLAT:
            //   Interés_por_cuota = Monto × Tasa_mensual (interés constante cada cuota)
            //   Capital_por_cuota = Monto / Número_cuotas
            //   Cuota = Capital_por_cuota + Interés_por_cuota
            //
            // Ejemplo: Q 700 al 15% mensual en 4 cuotas
            //   Interés_por_cuota = 700 × 0.15 = Q 105
            //   Capital_por_cuota = 700 / 4 = Q 175
            //   Cuota = 175 + 105 = Q 280

            // La tasa ya es mensual, convertir de porcentaje a decimal
            $tasaMensual = $tasaInteres / 100;

            // Interés por cuota = Monto × Tasa_mensual (interés fijo cada mes sobre el capital original)
            $interesPorCuota = $montoAprobado * $tasaMensual;

            // Capital por cuota = Monto / Número_cuotas
            $capitalPorCuota = $montoAprobado / $numeroCuotas;

            // Cuota total = Capital_por_cuota + Interés_por_cuota
            $cuotaPeriodo = round($capitalPorCuota + $interesPorCuota, 2);

            $saldoCapital = $montoAprobado;
        } else {
            // MÉTODO FRANCESA: Amortización francesa (método original)
            $cuotaExacta = 0;
            if ($tasaPorPeriodo > 0 && $numeroCuotas > 0) {
                // Fórmula de amortización francesa: Cuota = Monto * (tasa * (1+tasa)^n) / ((1+tasa)^n - 1)
                $base = 1 + $tasaPorPeriodo;
                $baseElevado = pow($base, $numeroCuotas);
                $cuotaExacta = ($montoAprobado * $tasaPorPeriodo * $baseElevado) / ($baseElevado - 1);
            } else {
                // Sin interés, solo capital
                $cuotaExacta = $montoAprobado / $numeroCuotas;
            }

            // Redondear cuota a 2 decimales
            $cuotaPeriodo = round($cuotaExacta, 2);
            $saldoCapital = $montoAprobado;
        }

        $totalCapital = 0;
        $totalInteres = 0;

        // Generar cada cuota
        for ($numeroCuota = 1; $numeroCuota <= $numeroCuotas; $numeroCuota++) {
            // Calcular fecha de vencimiento (en base a meses calendario, no días fijos)
            if ($fechaPrimerPago) {
                if ($numeroCuota === 1) {
                    $fechaVencimiento = $fechaPrimerPago->copy();
                } else {
                    // Sumar meses en lugar de días para mantener el mismo día del mes
                    $fechaVencimiento = $fechaPrimerPago->copy()->addMonths($numeroCuota - 1);
                }
            } else {
                // Sumar meses en lugar de días para mantener el mismo día del mes
                $fechaVencimiento = $fechaDesembolso->copy()->addMonths($numeroCuota - 1);
            }

            // Si hay días de gracia en la primera cuota, agregarlos
            if ($numeroCuota === 1 && $diasGracia > 0 && !$fechaPrimerPago) {
                $fechaVencimiento->addDays($diasGracia);
            }

            // DEBUG: Log para la primera cuota
            if ($numeroCuota === 1) {
                Log::info('generarPlanPagos - Primera cuota calculada', [
                    'credito_id' => $credito->id,
                    'fechaPrimerPago_existe' => !is_null($fechaPrimerPago),
                    'fechaVencimiento' => $fechaVencimiento->format('Y-m-d'),
                    'diasGracia' => $diasGracia,
                    'fecha_base' => $fechaPrimerPago ? 'fechaPrimerPago' : 'fechaDesembolso',
                ]);
            }

            // Calcular interés y capital según método
            if ($metodoCalculo === 'flat') {
                // Método FLAT: Interés constante, capital fijo
                // Fórmula: Interés_total = Monto × Tasa
                //          Capital_por_cuota = Monto / Número_periodos
                //          Cuota = Capital_por_cuota + (Interés_total / Número_periodos)

                // Interés por cuota es constante (Interés_total / Número_cuotas)
                $interesProyectado = round($interesPorCuota, 2);

                // Capital por cuota es constante (Monto / Número_cuotas)
                $capitalProyectado = round($capitalPorCuota, 2);

                // Si es la última cuota, ajustar para evitar desfases por redondeo
                if ($numeroCuota === $numeroCuotas) {
                    $capitalProyectado = round($saldoCapital, 2);
                    // Mantener el interés por cuota constante
                    // No recalcular para mantener consistencia con la fórmula flat
                }

                // Cuota = Capital_por_cuota + Interés_por_cuota
                $montoCuotaProyectado = round($capitalProyectado + $interesProyectado, 2);
            } else {
                // Método FRANCESA: Amortización
                $interesProyectado = round($saldoCapital * $tasaPorPeriodo, 2);

                // Si es la última cuota, ajustar para evitar desfases por redondeo
                if ($numeroCuota === $numeroCuotas) {
                    $capitalProyectado = round($saldoCapital, 2);
                    $montoCuotaProyectado = round($capitalProyectado + $interesProyectado, 2);
                } else {
                    $capitalProyectado = round($cuotaPeriodo - $interesProyectado, 2);
                    $montoCuotaProyectado = $cuotaPeriodo;
                }
            }

            // Determinar si es cuota de gracia (solo interés, sin capital)
            $esCuotaGracia = ($numeroCuota === 1 && $diasGracia > 0 && $credito->dias_gracia > 0);

            if ($esCuotaGracia) {
                // En cuota de gracia, solo se cobra interés, el capital se prorratea en las demás cuotas
                $capitalProyectado = 0;
                $montoCuotaProyectado = round($interesProyectado, 2);
            }

            $diasCuota = $diasEntreCuotas;
            if ($numeroCuota === 1 && $diasGracia > 0) {
                $diasCuota += $diasGracia;
            }

            // Crear registro de cuota
            \App\Models\CreditoPlanPago::create([
                'credito_prendario_id' => $credito->id,
                'numero_cuota' => $numeroCuota,
                'fecha_vencimiento' => $fechaVencimiento,
                'estado' => 'pendiente',
                'capital_proyectado' => $capitalProyectado,
                'interes_proyectado' => $interesProyectado,
                'mora_proyectada' => 0,
                'otros_cargos_proyectados' => 0,
                'monto_cuota_proyectado' => $montoCuotaProyectado,
                'capital_pagado' => 0,
                'interes_pagado' => 0,
                'mora_pagada' => 0,
                'otros_cargos_pagados' => 0,
                'monto_total_pagado' => 0,
                'capital_pendiente' => $capitalProyectado,
                'interes_pendiente' => $interesProyectado,
                'mora_pendiente' => 0,
                'otros_cargos_pendientes' => 0,
                'monto_pendiente' => $montoCuotaProyectado,
                'saldo_capital_credito' => round($saldoCapital - $capitalProyectado, 2),
                'dias_mora' => 0,
                'tasa_interes_aplicada' => $tasaInteres,
                'tasa_mora_aplicada' => $tasaMora,
                'dias_cuota' => $diasCuota,
                'es_cuota_gracia' => $esCuotaGracia,
                'permite_pago_parcial' => $permitePagoCapitalDiferente || true,
                'tipo_modificacion' => 'original',
            ]);

            // Actualizar saldos para la siguiente cuota
            $saldoCapital -= $capitalProyectado;
            $totalCapital += $capitalProyectado;
            $totalInteres += $interesProyectado;
        }
    }

    /**
     * Prorratear gastos adicionales en el plan de pagos
     * Los gastos se distribuyen uniformemente entre las cuotas
     * NO generan interés y NO se suman al capital
     *
     * @param CreditoPrendario $credito El crédito con gastos ya sincronizados
     */
    private function prorratearGastosEnPlanPagos(CreditoPrendario $credito): void
    {
        // Cargar gastos frescos
        $credito->load('gastos', 'planPagos');

        if (!$credito->gastos || $credito->gastos->count() === 0) {
            return;
        }

        if (!$credito->planPagos || $credito->planPagos->count() === 0) {
            return;
        }

        // Calcular total de gastos
        $totalGastos = $credito->gastos->sum(function ($gasto) {
            return (float) ($gasto->pivot->valor_calculado ?? 0);
        });

        if ($totalGastos <= 0) {
            return;
        }

        $numeroCuotas = $credito->planPagos->count();
        $gastoPorCuota = floor(($totalGastos / $numeroCuotas) * 100) / 100; // Redondear hacia abajo a 2 decimales
        $gastoAcumulado = 0;

        // Actualizar cada cuota con su parte prorrateada de gastos
        $cuotas = $credito->planPagos->sortBy('numero_cuota');
        foreach ($cuotas as $index => $cuota) {
            if ($index === $numeroCuotas - 1) {
                // Última cuota: asignar el resto para evitar desfases por redondeo
                $gastoEnCuota = round($totalGastos - $gastoAcumulado, 2);
            } else {
                $gastoEnCuota = $gastoPorCuota;
                $gastoAcumulado += $gastoPorCuota;
            }

            // Actualizar cuota con gastos
            $cuota->update([
                'otros_cargos_proyectados' => $gastoEnCuota,
                'otros_cargos_pendientes' => $gastoEnCuota,
                // Los gastos se suman al monto total de la cuota y al pendiente
                'monto_cuota_proyectado' => (float) $cuota->monto_cuota_proyectado + $gastoEnCuota,
                'monto_pendiente' => (float) $cuota->monto_pendiente + $gastoEnCuota,
            ]);
        }

        Log::info('Gastos prorrateados en plan de pagos', [
            'credito_id' => $credito->id,
            'total_gastos' => $totalGastos,
            'gasto_por_cuota' => $gastoPorCuota,
            'numero_cuotas' => $numeroCuotas
        ]);
    }

    /**
     * Calcular días entre cuotas según tipo de interés (sin depender de plazo_dias)
     */
    private function calcularDiasEntreCuotasPorTipo(string $tipoInteres): int
    {
        switch ($tipoInteres) {
            case 'diario':
                return 1;
            case 'semanal':
                return 7;
            case 'catorcenal':
                return 14;
            case 'quincenal':
                return 15;
            case 'cada_28_dias':
                return 28;
            case 'mensual':
            default:
                return 30;
        }
    }

    /**
     * Calcular tasa de interés por período según tipo
     */
    /**
     * Mapear método de valuación del frontend a valores permitidos en BD
     */
    private function mapearMetodoTasacion(?string $metodoValuacion): string
    {
        $mapeo = [
            'comparacion_mercado' => 'comparativo',
            'catalogo' => 'comparativo',
            'valor_reposicion' => 'costo',
            'valor_liquidacion' => 'ingreso',
            'pericial' => 'mixto',
            'comparativo' => 'comparativo',
            'costo' => 'costo',
            'ingreso' => 'ingreso',
            'mixto' => 'mixto',
        ];

        return $mapeo[$metodoValuacion] ?? 'comparativo';
    }

    private function calcularTasaPorPeriodo(float $tasaAnual, string $tipoInteres): float
    {
        switch ($tipoInteres) {
            case 'diario':
                return $tasaAnual / 100 / 365;
            case 'semanal':
                return $tasaAnual / 100 / 52;
            case 'catorcenal':
                return $tasaAnual / 100 / 26; // 26 catorcenas al año
            case 'quincenal':
                return $tasaAnual / 100 / 24;
            case 'cada_28_dias':
                return $tasaAnual / 100 / 13; // 13 períodos de 28 días al año
            case 'mensual':
            default:
                return $tasaAnual / 100 / 12;
        }
    }

    // ============================================
    // FASE 2: PAGOS Y DESEMBOLSOS
    // ============================================

    /**
     * Obtener movimientos (kardex) de un crédito
     */
    public function getMovimientos(string $id): JsonResponse
    {
        $credito = CreditoPrendario::find($id);

        if (!$credito) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito prendario no encontrado'
            ], 404);
        }

        $movimientos = $credito->movimientos()
            ->orderBy('fecha_movimiento', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $movimientos->map(function ($movimiento) {
                return [
                    'id' => (string) $movimiento->id,
                    'numero_movimiento' => $movimiento->numero_movimiento,
                    'numero_recibo' => $movimiento->numero_recibo,
                    'tipo_movimiento' => $movimiento->tipo_movimiento,
                    'numero_cuota' => $movimiento->numero_cuota,
                    'fecha_movimiento' => $movimiento->fecha_movimiento?->toISOString(),
                    'fecha_registro' => $movimiento->fecha_registro?->toISOString(),
                    'monto_total' => (float) $movimiento->monto_total,
                    'capital' => (float) $movimiento->capital,
                    'interes' => (float) $movimiento->interes,
                    'mora' => (float) $movimiento->mora,
                    'otros_cargos' => (float) $movimiento->otros_cargos,
                    'saldo_capital' => (float) $movimiento->saldo_capital,
                    'saldo_interes' => (float) $movimiento->saldo_interes,
                    'saldo_mora' => (float) $movimiento->saldo_mora,
                    'forma_pago' => $movimiento->forma_pago,
                    'concepto' => $movimiento->concepto,
                    'observaciones' => $movimiento->observaciones,
                    'estado' => $movimiento->estado,
                    'usuario' => $movimiento->usuario ? [
                        'id' => (string) $movimiento->usuario->id,
                        'nombre' => $movimiento->usuario->name,
                    ] : null,
                ];
            })
        ]);
    }

    /**
     * Obtener saldo actual calculado desde kardex
     */
    public function getSaldo(string $id): JsonResponse
    {
        $credito = CreditoPrendario::find($id);

        if (!$credito) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito prendario no encontrado'
            ], 404);
        }

        $saldo = $credito->getSaldoDesdeKardex();

        return response()->json([
            'success' => true,
            'data' => $saldo
        ]);
    }

    /**
     * Desembolsar crédito (Estado: aprobado → vigente)
     */
    public function desembolsar(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'forma_desembolso' => 'required|in:efectivo,transferencia,cheque,deposito_bancario',
            'referencia_desembolso' => 'nullable|string|max:100',
            'idempotency_key' => 'required|string|max:255',
        ], [
            'forma_desembolso.required' => 'La forma de desembolso es requerida',
            'idempotency_key.required' => 'El idempotency_key es requerido',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $credito = CreditoPrendario::find($id);

        if (!$credito) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito prendario no encontrado'
            ], 404);
        }

        // Validar estado (debe ser 'aprobado' o 'solicitado' para permitir desembolso directo)
        if (!in_array($credito->estado, ['aprobado', 'solicitado'])) {
            return response()->json([
                'success' => false,
                'message' => "No se puede desembolsar un crédito con estado '{$credito->estado}'. Debe estar 'aprobado' o 'solicitado'"
            ], 422);
        }

        // Validar que no se haya desembolsado ya
        if ($credito->monto_desembolsado && $credito->monto_desembolsado > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Este crédito ya ha sido desembolsado'
            ], 422);
        }

        // Validar idempotency_key
        $idempotencyKey = $request->idempotency_key;
        if (IdempotencyKey::existe($idempotencyKey)) {
            // Si ya existe, retornar el resultado guardado
            $resultado = IdempotencyKey::obtenerResultado($idempotencyKey);
            if ($resultado) {
                return response()->json([
                    'success' => true,
                    'message' => 'Desembolso ya procesado (idempotencia)',
                    'data' => $resultado
                ]);
            }
        }

        try {
            DB::beginTransaction();

            // Generar número único de movimiento
            $numeroMovimiento = 'DES-' . str_pad($credito->id, 6, '0', STR_PAD_LEFT) . '-' . now()->format('YmdHis');

            // Crear movimiento en kardex
            $movimiento = CreditoMovimiento::create([
                'credito_prendario_id' => $credito->id,
                'usuario_id' => Auth::id(),
                'sucursal_id' => $credito->sucursal_id,
                'cuota_id' => null,
                'numero_movimiento' => $numeroMovimiento,
                'numero_recibo' => $request->referencia_desembolso,
                'tipo_movimiento' => 'desembolso',
                'numero_cuota' => 0,
                'fecha_movimiento' => now(),
                'fecha_registro' => now(),
                'monto_total' => $credito->monto_aprobado,
                'capital' => $credito->monto_aprobado,
                'interes' => 0,
                'mora' => 0,
                'otros_cargos' => 0,
                'saldo_capital' => $credito->monto_aprobado,
                'saldo_interes' => 0,
                'saldo_mora' => 0,
                'forma_pago' => $request->forma_desembolso,
                'concepto' => 'Desembolso inicial del crédito',
                'observaciones' => $request->referencia_desembolso ? "Referencia: {$request->referencia_desembolso}" : null,
                'estado' => 'activo',
                'moneda' => 'GTQ',
                'tipo_cambio' => 1,
            ]);

            // Actualizar crédito
            $credito->update([
                'estado' => 'vigente',
                'fecha_desembolso' => now(),
                'monto_desembolsado' => $credito->monto_aprobado,
                'cajero_id' => Auth::id(),
            ]);

            // Recalcular saldos desde kardex
            $credito->recalcularSaldosDesdeKardex();

            // Guardar idempotency_key
            $creditoFresh = $credito->fresh(['cliente', 'sucursal', 'prendas', 'planPagos']);
            $resultado = $this->formatCredito($creditoFresh);

            IdempotencyKey::guardar(
                $idempotencyKey,
                'desembolso',
                $credito->id,
                $movimiento->id,
                $resultado
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Crédito desembolsado exitosamente',
                'data' => $resultado
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al desembolsar crédito: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al desembolsar el crédito',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Registrar pago de cuota
     */
    public function pagar(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cuota_id' => 'required|exists:credito_plan_pagos,id',
            'monto_capital' => 'nullable|numeric|min:0',
            'monto_interes' => 'nullable|numeric|min:0',
            'monto_mora' => 'nullable|numeric|min:0',
            'forma_pago' => 'required|in:efectivo,transferencia,cheque,tarjeta_debito,tarjeta_credito,deposito_bancario,mixto',
            'referencia' => 'nullable|string|max:100',
            'idempotency_key' => 'required|string|max:255',
            'es_pago_completo' => 'nullable|boolean',
        ], [
            'cuota_id.required' => 'El ID de la cuota es requerido',
            'cuota_id.exists' => 'La cuota no existe',
            'forma_pago.required' => 'La forma de pago es requerida',
            'idempotency_key.required' => 'El idempotency_key es requerido',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $credito = CreditoPrendario::find($id);

        if (!$credito) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito prendario no encontrado'
            ], 404);
        }

        // Validar estado del crédito
        if (!in_array($credito->estado, ['vigente', 'en_mora', 'vencido'])) {
            return response()->json([
                'success' => false,
                'message' => "No se puede registrar un pago en un crédito con estado '{$credito->estado}'"
            ], 422);
        }

        // Obtener cuota
        $cuota = CreditoPlanPago::where('id', $request->cuota_id)
            ->where('credito_prendario_id', $credito->id)
            ->first();

        if (!$cuota) {
            return response()->json([
                'success' => false,
                'message' => 'La cuota no pertenece a este crédito'
            ], 404);
        }

        // Validar estado de la cuota
        if (!in_array($cuota->estado, ['pendiente', 'pagada_parcial', 'vencida', 'en_mora'])) {
            return response()->json([
                'success' => false,
                'message' => "No se puede pagar una cuota con estado '{$cuota->estado}'"
            ], 422);
        }

        // Validar idempotency_key
        $idempotencyKey = $request->idempotency_key;
        if (IdempotencyKey::existe($idempotencyKey)) {
            $resultado = IdempotencyKey::obtenerResultado($idempotencyKey);
            if ($resultado) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pago ya procesado (idempotencia)',
                    'data' => $resultado
                ]);
            }
        }

        try {
            DB::beginTransaction();

            // Calcular montos
            $esPagoCompleto = $request->boolean('es_pago_completo', true);

            if ($esPagoCompleto) {
                // Pago completo: usar montos pendientes de la cuota
                $montoCapital = $cuota->capital_pendiente;
                $montoInteres = $cuota->interes_pendiente;
                $montoMora = $cuota->mora_pendiente;
            } else {
                // Pago parcial: usar montos proporcionados
                $montoCapital = $request->monto_capital ?? 0;
                $montoInteres = $request->monto_interes ?? 0;
                $montoMora = $request->monto_mora ?? 0;

                // Validar que no exceda lo pendiente
                if ($montoCapital > $cuota->capital_pendiente ||
                    $montoInteres > $cuota->interes_pendiente ||
                    $montoMora > $cuota->mora_pendiente) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Los montos exceden lo pendiente de la cuota'
                    ], 422);
                }
            }

            $montoTotal = $montoCapital + $montoInteres + $montoMora;

            if ($montoTotal <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El monto total debe ser mayor a cero'
                ], 422);
            }

            // Generar número único de movimiento
            $numeroMovimiento = 'PAG-' . str_pad($credito->id, 6, '0', STR_PAD_LEFT) . '-' .
                                str_pad($cuota->numero_cuota, 3, '0', STR_PAD_LEFT) . '-' .
                                now()->format('YmdHis');

            // Calcular saldos después del pago
            $saldoCapitalDespues = $credito->capital_pendiente - $montoCapital;
            $saldoInteresDespues = ($credito->interes_generado - $credito->interes_pagado) - $montoInteres;
            $saldoMoraDespues = ($credito->mora_generada - $credito->mora_pagada) - $montoMora;

            // Crear movimiento en kardex
            $movimiento = CreditoMovimiento::create([
                'credito_prendario_id' => $credito->id,
                'usuario_id' => Auth::id(),
                'sucursal_id' => $credito->sucursal_id,
                'cuota_id' => $cuota->id,
                'numero_movimiento' => $numeroMovimiento,
                'numero_recibo' => $request->referencia,
                'tipo_movimiento' => $esPagoCompleto ? 'pago' : 'pago_parcial',
                'numero_cuota' => $cuota->numero_cuota,
                'fecha_movimiento' => now(),
                'fecha_registro' => now(),
                'monto_total' => $montoTotal,
                'capital' => $montoCapital,
                'interes' => $montoInteres,
                'mora' => $montoMora,
                'otros_cargos' => 0,
                'saldo_capital' => $saldoCapitalDespues,
                'saldo_interes' => $saldoInteresDespues,
                'saldo_mora' => $saldoMoraDespues,
                'forma_pago' => $request->forma_pago,
                'concepto' => "Pago de cuota {$cuota->numero_cuota}",
                'observaciones' => $request->referencia ? "Referencia: {$request->referencia}" : null,
                'estado' => 'activo',
                'moneda' => 'GTQ',
                'tipo_cambio' => 1,
            ]);

            // Actualizar cuota
            $capitalPagadoNuevo = $cuota->capital_pagado + $montoCapital;
            $interesPagadoNuevo = $cuota->interes_pagado + $montoInteres;
            $moraPagadaNueva = $cuota->mora_pagada + $montoMora;
            $montoTotalPagadoNuevo = $cuota->monto_total_pagado + $montoTotal;

            $capitalPendienteNuevo = $cuota->capital_proyectado - $capitalPagadoNuevo;
            $interesPendienteNuevo = $cuota->interes_proyectado - $interesPagadoNuevo;
            $moraPendienteNueva = $cuota->mora_proyectada - $moraPagadaNueva;
            $montoPendienteNuevo = $cuota->monto_cuota_proyectado - $montoTotalPagadoNuevo;

            // Determinar nuevo estado de la cuota
            $nuevoEstadoCuota = 'pagada_parcial';
            if ($montoPendienteNuevo <= 0) {
                $nuevoEstadoCuota = 'pagada';
            }

            $cuota->update([
                'capital_pagado' => $capitalPagadoNuevo,
                'interes_pagado' => $interesPagadoNuevo,
                'mora_pagada' => $moraPagadaNueva,
                'monto_total_pagado' => $montoTotalPagadoNuevo,
                'capital_pendiente' => $capitalPendienteNuevo,
                'interes_pendiente' => $interesPendienteNuevo,
                'mora_pendiente' => $moraPendienteNueva,
                'monto_pendiente' => $montoPendienteNuevo,
                'estado' => $nuevoEstadoCuota,
                'fecha_pago' => $nuevoEstadoCuota === 'pagada' ? now() : null,
                'usuario_pago_id' => Auth::id(),
                'ultimo_movimiento_id' => $movimiento->id,
            ]);

            // Recalcular saldos del crédito desde kardex
            $credito->recalcularSaldosDesdeKardex();

            // Verificar si todas las cuotas están pagadas
            $cuotasPendientes = $credito->planPagos()
                ->whereIn('estado', ['pendiente', 'pagada_parcial', 'vencida', 'en_mora'])
                ->count();

            if ($cuotasPendientes === 0) {
                $credito->update([
                    'estado' => 'rescatado',
                    'fecha_cancelacion' => now(),
                ]);
            }

            // Actualizar fecha de último pago
            $credito->update([
                'fecha_ultimo_pago' => now(),
            ]);

            // Guardar idempotency_key
            $creditoFresh = $credito->fresh(['cliente', 'sucursal', 'prendas', 'planPagos', 'movimientos']);
            $resultado = $this->formatCredito($creditoFresh);

            IdempotencyKey::guardar(
                $idempotencyKey,
                'pago',
                $credito->id,
                $movimiento->id,
                $resultado
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado exitosamente',
                'data' => $resultado
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al registrar pago: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el pago',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    // ============================================
    // FASE 3: ESTADOS Y APROBACIONES
    // ============================================

    /**
     * Aprobar crédito (Estado: solicitado/en_analisis → aprobado)
     */
    public function aprobar(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'observaciones' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $credito = CreditoPrendario::find($id);

        if (!$credito) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito prendario no encontrado'
            ], 404);
        }

        // Validar estado actual
        if (!in_array($credito->estado, [EstadoCredito::SOLICITADO->value, EstadoCredito::EN_ANALISIS->value])) {
            return response()->json([
                'success' => false,
                'message' => "No se puede aprobar un crédito con estado '{$credito->estado}'. Debe estar 'solicitado' o 'en_analisis'"
            ], 422);
        }

        // Validar transición
        if (!EstadoCredito::transicionValida($credito->estado, EstadoCredito::APROBADO->value)) {
            return response()->json([
                'success' => false,
                'message' => "Transición no válida: de '{$credito->estado}' a 'aprobado'"
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Actualizar crédito
            $credito->update([
                'estado' => EstadoCredito::APROBADO->value,
                'fecha_aprobacion' => now(),
                'analista_id' => Auth::id(),
                'observaciones' => $request->observaciones ?
                    ($credito->observaciones ? $credito->observaciones . "\n\nAprobado: " . $request->observaciones :
                     "Aprobado: " . $request->observaciones) :
                    $credito->observaciones,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Crédito aprobado exitosamente',
                'data' => $this->formatCredito($credito->fresh(['cliente', 'sucursal', 'prendas', 'planPagos']))
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al aprobar crédito: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'credito_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar el crédito',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Rechazar crédito (Estado: solicitado/en_analisis → rechazado)
     */
    public function rechazar(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'motivo_rechazo' => 'required|string|max:1000',
        ], [
            'motivo_rechazo.required' => 'El motivo del rechazo es requerido',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $credito = CreditoPrendario::find($id);

        if (!$credito) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito prendario no encontrado'
            ], 404);
        }

        // Validar estado actual
        if (!in_array($credito->estado, [EstadoCredito::SOLICITADO->value, EstadoCredito::EN_ANALISIS->value])) {
            return response()->json([
                'success' => false,
                'message' => "No se puede rechazar un crédito con estado '{$credito->estado}'. Debe estar 'solicitado' o 'en_analisis'"
            ], 422);
        }

        // Validar transición
        if (!EstadoCredito::transicionValida($credito->estado, EstadoCredito::RECHAZADO->value)) {
            return response()->json([
                'success' => false,
                'message' => "Transición no válida: de '{$credito->estado}' a 'rechazado'"
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Actualizar crédito
            $credito->update([
                'estado' => EstadoCredito::RECHAZADO->value,
                'fecha_analisis' => now(),
                'analista_id' => Auth::id(),
                'motivo_rechazo' => $request->motivo_rechazo,
                'observaciones' => $request->motivo_rechazo,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Crédito rechazado exitosamente',
                'data' => $this->formatCredito($credito->fresh(['cliente', 'sucursal', 'prendas', 'planPagos']))
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al rechazar crédito: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'credito_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar el crédito',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener transiciones válidas para un crédito
     */
    public function getTransiciones(string $id): JsonResponse
    {
        $credito = CreditoPrendario::find($id);

        if (!$credito) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito prendario no encontrado'
            ], 404);
        }

        $transiciones = EstadoCredito::transicionesDesde($credito->estado);

        $transicionesFormateadas = array_map(function ($estado) {
            $enum = EstadoCredito::from($estado);
            return [
                'valor' => $estado,
                'etiqueta' => $enum->etiqueta(),
                'color' => $enum->color(),
            ];
        }, $transiciones);

            return response()->json([
                'success' => true,
                'data' => [
                    'estado_actual' => $credito->estado,
                    'transiciones_disponibles' => $transicionesFormateadas,
                ]
            ]);
    }

    // ============================================
    // FASE 4: AUDITORÍA Y ANULACIÓN
    // ============================================

    /**
     * Obtener historial de auditoría de un crédito
     */
    public function getAuditoria(string $id): JsonResponse
    {
        $credito = CreditoPrendario::find($id);

        if (!$credito) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito prendario no encontrado'
            ], 404);
        }

        $auditoria = AuditoriaCredito::porCredito($credito->id)
            ->recientes()
            ->with('usuario:id,name,email')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $auditoria->map(function ($registro) {
                return [
                    'id' => (string) $registro->id,
                    'accion' => $registro->accion,
                    'campo_modificado' => $registro->campo_modificado,
                    'valor_anterior' => $registro->valor_anterior,
                    'valor_nuevo' => $registro->valor_nuevo,
                    'observaciones' => $registro->observaciones,
                    'usuario' => $registro->usuario ? [
                        'id' => (string) $registro->usuario->id,
                        'nombre' => $registro->usuario->name,
                        'email' => $registro->usuario->email,
                    ] : null,
                    'ip_address' => $registro->ip_address,
                    'fecha' => $registro->created_at->toISOString(),
                ];
            })
        ]);
    }

    /**
     * Anular movimiento (Estado: activo → anulado)
     */
    public function anularMovimiento(Request $request, string $id, string $movimientoId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'motivo' => 'required|string|max:1000',
            'idempotency_key' => 'required|string|max:255',
        ], [
            'motivo.required' => 'El motivo de anulación es requerido',
            'idempotency_key.required' => 'El idempotency_key es requerido',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $credito = CreditoPrendario::find($id);

        if (!$credito) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito prendario no encontrado'
            ], 404);
        }

        $movimiento = CreditoMovimiento::where('id', $movimientoId)
            ->where('credito_prendario_id', $credito->id)
            ->first();

        if (!$movimiento) {
            return response()->json([
                'success' => false,
                'message' => 'Movimiento no encontrado o no pertenece a este crédito'
            ], 404);
        }

        // Validar estado del movimiento
        if ($movimiento->estado !== 'activo') {
            return response()->json([
                'success' => false,
                'message' => "No se puede anular un movimiento con estado '{$movimiento->estado}'. Debe estar 'activo'"
            ], 422);
        }

        // Validar que no sea un desembolso (no se pueden anular desembolsos)
        if ($movimiento->tipo_movimiento === 'desembolso') {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden anular movimientos de desembolso'
            ], 422);
        }

        // Validar idempotency_key
        $idempotencyKey = $request->idempotency_key;
        if (IdempotencyKey::existe($idempotencyKey)) {
            $resultado = IdempotencyKey::obtenerResultado($idempotencyKey);
            if ($resultado) {
                return response()->json([
                    'success' => true,
                    'message' => 'Anulación ya procesada (idempotencia)',
                    'data' => $resultado
                ]);
            }
        }

        try {
            DB::beginTransaction();

            // Actualizar movimiento: marcar como anulado
            $movimiento->update([
                'estado' => 'anulado',
                'reversado_por' => Auth::id(),
                'fecha_reversion' => now(),
                'motivo_reversion' => $request->motivo,
            ]);

            // Si el movimiento era un pago, revertir la cuota
            if (in_array($movimiento->tipo_movimiento, ['pago', 'pago_parcial'])) {
                if ($movimiento->cuota_id) {
                    $cuota = CreditoPlanPago::find($movimiento->cuota_id);

                    if ($cuota) {
                        // Revertir montos pagados
                        $cuota->update([
                            'capital_pagado' => max(0, $cuota->capital_pagado - $movimiento->capital),
                            'interes_pagado' => max(0, $cuota->interes_pagado - $movimiento->interes),
                            'mora_pagada' => max(0, $cuota->mora_pagada - $movimiento->mora),
                            'monto_total_pagado' => max(0, $cuota->monto_total_pagado - $movimiento->monto_total),
                        ]);

                        // Recalcular pendientes
                        $cuota->update([
                            'capital_pendiente' => $cuota->capital_proyectado - $cuota->capital_pagado,
                            'interes_pendiente' => $cuota->interes_proyectado - $cuota->interes_pagado,
                            'mora_pendiente' => $cuota->mora_proyectada - $cuota->mora_pagada,
                            'monto_pendiente' => $cuota->monto_cuota_proyectado - $cuota->monto_total_pagado,
                            'estado' => $cuota->monto_pendiente <= 0 ? 'pagada' : 'pagada_parcial',
                            'fecha_pago' => $cuota->monto_pendiente <= 0 ? $cuota->fecha_pago : null,
                        ]);
                    }
                }
            }

            // Recalcular saldos del crédito desde kardex (excluyendo el movimiento anulado)
            $credito->recalcularSaldosDesdeKardex();

            // Crear movimiento de reverso
            $numeroMovimientoReverso = 'REV-' . str_pad($credito->id, 6, '0', STR_PAD_LEFT) . '-' . now()->format('YmdHis');

            $movimientoReverso = CreditoMovimiento::create([
                'credito_prendario_id' => $credito->id,
                'usuario_id' => Auth::id(),
                'sucursal_id' => $credito->sucursal_id,
                'cuota_id' => $movimiento->cuota_id,
                'numero_movimiento' => $numeroMovimientoReverso,
                'tipo_movimiento' => 'reversion',
                'numero_cuota' => $movimiento->numero_cuota,
                'fecha_movimiento' => now(),
                'fecha_registro' => now(),
                'monto_total' => -$movimiento->monto_total,
                'capital' => -$movimiento->capital,
                'interes' => -$movimiento->interes,
                'mora' => -$movimiento->mora,
                'otros_cargos' => -$movimiento->otros_cargos,
                'saldo_capital' => $credito->capital_pendiente,
                'saldo_interes' => $credito->interes_generado - $credito->interes_pagado,
                'saldo_mora' => $credito->mora_generada - $credito->mora_pagada,
                'forma_pago' => $movimiento->forma_pago,
                'concepto' => "Reversión de movimiento {$movimiento->numero_movimiento}",
                'observaciones' => "Motivo: {$request->motivo}",
                'estado' => 'activo',
                'movimiento_reversa_id' => $movimiento->id,
                'moneda' => 'GTQ',
                'tipo_cambio' => 1,
            ]);

            // Registrar auditoría
            AuditoriaCredito::registrar(
                $credito->id,
                'movimiento_anulado',
                'estado',
                'activo',
                'anulado',
                "Movimiento {$movimiento->numero_movimiento} anulado. Motivo: {$request->motivo}"
            );

            // Guardar idempotency_key
            $creditoFresh = $credito->fresh(['cliente', 'sucursal', 'prendas', 'planPagos', 'movimientos']);
            $resultado = $this->formatCredito($creditoFresh);

            IdempotencyKey::guardar(
                $idempotencyKey,
                'anulacion',
                $credito->id,
                $movimientoReverso->id,
                $resultado
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Movimiento anulado exitosamente',
                'data' => $resultado
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al anular movimiento: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'movimiento_id' => $movimientoId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al anular el movimiento',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Eliminar un crédito prendario (soft delete con borrado en cascada)
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $credito = CreditoPrendario::find($id);

            if (!$credito) {
                return response()->json([
                    'success' => false,
                    'message' => 'Crédito no encontrado'
                ], 404);
            }

            // Validar que el crédito pueda ser eliminado
            // No permitir eliminar créditos que ya fueron desembolsados o tienen pagos
            if ($credito->estado === EstadoCredito::VIGENTE->value ||
                $credito->estado === EstadoCredito::EN_MORA->value ||
                $credito->estado === EstadoCredito::VENCIDO->value) {

                $tieneMovimientos = CreditoMovimiento::where('credito_prendario_id', $credito->id)
                    ->where('estado', '!=', 'anulado')
                    ->exists();

                if ($tieneMovimientos) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede eliminar un crédito que ya tiene movimientos registrados. Use la opción de anulación en su lugar.'
                    ], 422);
                }
            }

            DB::beginTransaction();

            try {
                // Registrar auditoría antes de eliminar
                $observaciones = 'Eliminación del crédito. Usuario: ' . (Auth::user()?->name ?? 'Desconocido');
                AuditoriaCredito::registrar(
                    (int) $credito->id,
                    'anulado',
                    'estado',
                    $credito->estado,
                    'anulado',
                    $observaciones,
                    Auth::id()
                );

                // Eliminar registros relacionados manualmente (los que no tienen cascade en BD)
                // Nota: prendas, credito_plan_pagos, credito_movimientos, auditoria_creditos
                // se eliminan automáticamente por cascade en la base de datos

                // Eliminar idempotency keys relacionadas (hard delete, son temporales)
                // Estas no tienen cascade porque son registros temporales
                IdempotencyKey::where('credito_prendario_id', $credito->id)->delete();

                // Las auditorías se mantienen para trazabilidad histórica
                // No se eliminan

                // Eliminar el crédito (soft delete)
                $credito->delete();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Crédito eliminado exitosamente'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error al eliminar crédito: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                    'credito_id' => $id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al eliminar el crédito',
                    'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error al eliminar crédito: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'credito_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el crédito',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Generar y descargar plan de pagos en PDF
     */
    public function descargarPlanPagos(string $id)
    {
        try {
            $credito = CreditoPrendario::with(['cliente', 'sucursal', 'planPagos' => function($query) {
                $query->orderBy('numero_cuota', 'asc');
            }])->findOrFail($id);

            $planPagos = $credito->planPagos;

            if ($planPagos->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este crédito no tiene plan de pagos registrado'
                ], 404);
            }

            $data = [
                'credito' => $credito,
                'cliente' => $credito->cliente,
                'sucursal' => $credito->sucursal,
                'planPagos' => $planPagos,
                'fechaGeneracion' => now()->format('d/m/Y H:i:s'),
            ];

            $pdf = Pdf::loadView('creditos.plan-pagos', $data);
            $pdf->setPaper('letter', 'portrait');

            $nombreArchivo = 'Plan_Pagos_' . ($credito->codigo_credito ?? $credito->numero_credito) . '_' . date('Ymd') . '.pdf';

            return $pdf->download($nombreArchivo);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Crédito no encontrado para plan de pagos PDF: ' . $id);
            return response()->json([
                'success' => false,
                'message' => 'Crédito no encontrado'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al generar plan de pagos PDF: ' . $e->getMessage(), [
                'credito_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el plan de pagos',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Generar y descargar contrato de crédito prendario en PDF
     */
    public function descargarContrato(string $id)
    {
        try {
            $credito = CreditoPrendario::with([
                'cliente',
                'sucursal',
                'prendas.categoriaProducto',
                'planPagos' => function($query) {
                    $query->orderBy('numero_cuota');
                }
            ])->findOrFail($id);

            // Formatear fecha en español
            Carbon::setLocale('es');
            $fechaContrato = Carbon::now();
            $meses = [
                1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
                5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
                9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
            ];
            $fechaFormateada = $fechaContrato->day . ' de ' . $meses[$fechaContrato->month] . ' de ' . $fechaContrato->year;

            $data = [
                'credito' => $credito,
                'cliente' => $credito->cliente,
                'sucursal' => $credito->sucursal,
                'prendas' => $credito->prendas,
                'planPagos' => $credito->planPagos,
                'fechaGeneracion' => now()->format('d/m/Y H:i:s'),
                'fechaContrato' => $fechaFormateada,
            ];

            $pdf = Pdf::loadView('creditos.contrato', $data);
            $pdf->setPaper('letter', 'portrait');

            $nombreArchivo = 'Contrato_Credito_' . ($credito->codigo_credito ?? $credito->numero_credito) . '_' . date('Ymd') . '.pdf';

            return $pdf->download($nombreArchivo);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Crédito no encontrado para contrato PDF: ' . $id);
            return response()->json([
                'success' => false,
                'message' => 'Crédito no encontrado'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al generar contrato PDF: ' . $e->getMessage(), [
                'credito_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el contrato',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Generar recibo preliminar desde datos del wizard (antes de crear el crédito)
     */
    public function generarReciboPreliminar(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cliente' => 'required|array',
                'cliente.id' => 'required',
                'cliente.nombres' => 'required|string',
                'cliente.apellidos' => 'required|string',
                'cliente.dpi' => 'nullable|string',
                'cliente.telefono' => 'nullable|string',
                'prenda' => 'required|array',
                'prenda.descripcion_general' => 'required|string',
                'prenda.marca' => 'nullable|string',
                'prenda.modelo' => 'nullable|string',
                'prenda.codigo_prenda' => 'nullable|string',
                'credito' => 'required|array',
                'credito.numero_credito' => 'required|string',
                'credito.monto_aprobado' => 'required|numeric',
                'credito.tasa_interes' => 'required|numeric',
                'credito.tipo_interes' => 'required|string',
                'credito.plazo_dias' => 'nullable|integer',
                'credito.numero_cuotas' => 'nullable|integer',
                'credito.fecha_desembolso' => 'nullable|date',
                'credito.fecha_vencimiento' => 'nullable|date',
                'tasacion' => 'nullable|array',
                'tasacion.valor_mercado' => 'nullable|numeric',
                'sucursal' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Obtener sucursal
            $sucursalId = $request->sucursal['id'] ?? '1';
            $sucursal = \App\Models\Sucursal::find($sucursalId);

            // Generar código de barras del número de crédito
            $numeroCredito = $request->credito['numero_credito'];
            $generator = new BarcodeGeneratorPNG();
            $barcodeData = $generator->getBarcode(
                $numeroCredito,
                $generator::TYPE_CODE_128,
                2,
                60
            );
            $barcodeImage = base64_encode($barcodeData);

            // Preparar datos para la vista
            // Preparar prenda con todas las propiedades necesarias para la vista
            $prendaData = array_merge($request->prenda, [
                'descripcion' => $request->prenda['descripcion_general'], // Alias para la vista
                'valor_tasacion' => $request->tasacion['valor_mercado'] ?? $request->credito['monto_aprobado'],
            ]);

            $data = [
                'credito' => (object) array_merge($request->credito, [
                    'numero_credito' => $numeroCredito,
                    'codigo_credito' => $numeroCredito, // Alias para compatibilidad
                    'valor_tasacion' => $request->tasacion['valor_mercado'] ?? $request->credito['monto_aprobado'],
                ]),
                'cliente' => (object) $request->cliente,
                'sucursal' => $sucursal,
                'prendas' => [(object) $prendaData],
                'barcodeImage' => $barcodeImage,
                'fechaGeneracion' => now()->format('d/m/Y H:i:s'),
            ];

            $pdf = Pdf::loadView('creditos.recibo', $data);
            $pdf->setPaper('letter', 'portrait');

            $nombreArchivo = 'Recibo_Preliminar_' . $numeroCredito . '_' . date('Ymd') . '.pdf';

            return $pdf->download($nombreArchivo);

        } catch (\Exception $e) {
            Log::error('Error al generar recibo preliminar: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el recibo preliminar',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Generar contrato preliminar desde datos del wizard (antes de crear el crédito)
     */
    public function generarContratoPreliminar(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cliente' => 'required|array',
                'cliente.id' => 'required',
                'cliente.nombres' => 'required|string',
                'cliente.apellidos' => 'required|string',
                'cliente.dpi' => 'nullable|string',
                'cliente.telefono' => 'nullable|string',
                'cliente.direccion' => 'nullable|string',
                'prenda' => 'required|array',
                'prenda.descripcion_general' => 'required|string',
                'prenda.marca' => 'nullable|string',
                'prenda.modelo' => 'nullable|string',
                'credito' => 'required|array',
                'credito.numero_credito' => 'required|string',
                'credito.monto_aprobado' => 'required|numeric',
                'credito.tasa_interes' => 'required|numeric',
                'credito.tipo_interes' => 'required|string',
                'credito.plazo_dias' => 'nullable|integer',
                'credito.numero_cuotas' => 'nullable|integer',
                'credito.fecha_vencimiento' => 'nullable|date',
                'credito.dias_gracia' => 'nullable|integer',
                'tasacion' => 'nullable|array',
                'tasacion.valor_mercado' => 'nullable|numeric',
                'sucursal' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Obtener sucursal
            $sucursalId = $request->sucursal['id'] ?? '1';
            $sucursal = \App\Models\Sucursal::find($sucursalId);

            // Formatear fecha en español
            Carbon::setLocale('es');
            $fechaContrato = Carbon::now();
            $meses = [
                1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
                5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
                9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
            ];
            $fechaFormateada = $fechaContrato->day . ' de ' . $meses[$fechaContrato->month] . ' de ' . $fechaContrato->year;

            // Preparar datos para la vista
            $numeroCredito = $request->credito['numero_credito'];

            // Preparar prenda con todas las propiedades necesarias para la vista
            $prendaData = array_merge($request->prenda, [
                'descripcion' => $request->prenda['descripcion_general'], // Alias para la vista
                'valor_tasacion' => $request->tasacion['valor_mercado'] ?? $request->credito['monto_aprobado'] ?? 0,
            ]);

            // Preparar crédito con todas las propiedades necesarias
            $creditoData = array_merge($request->credito, [
                'codigo_credito' => $numeroCredito, // Alias para compatibilidad con la vista
                'fecha_vencimiento' => $request->credito['fecha_vencimiento'] ?? Carbon::now()->addDays($request->credito['plazo_dias'] ?? 30)->format('Y-m-d'),
                'dias_gracia' => $request->credito['dias_gracia'] ?? 0,
            ]);

            $data = [
                'credito' => (object) $creditoData,
                'cliente' => (object) $request->cliente,
                'sucursal' => $sucursal,
                'prendas' => [(object) $prendaData],
                'planPagos' => collect([]), // Sin plan de pagos en preliminar
                'fechaGeneracion' => now()->format('d/m/Y H:i:s'),
                'fechaContrato' => $fechaFormateada,
            ];

            $pdf = Pdf::loadView('creditos.contrato', $data);
            $pdf->setPaper('letter', 'portrait');

            $nombreArchivo = 'Contrato_Preliminar_' . $request->credito['numero_credito'] . '_' . date('Ymd') . '.pdf';

            return $pdf->download($nombreArchivo);

        } catch (\Exception $e) {
            Log::error('Error al generar contrato preliminar: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el contrato preliminar',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Generar plan de pagos preliminar desde datos del wizard (antes de crear el crédito)
     */
    /**
     * Simular plan de pagos (Retorna JSON)
     *
     * Ahora incluye cálculo de gastos si se proporcionan gas_ids
     */
    public function simularPlan(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'monto' => 'required|numeric|min:0',
                'tasa_interes' => 'required|numeric|min:0',
                'tipo_interes' => 'required|string',
                'numero_cuotas' => 'required|integer|min:1',
                'plazo_dias' => 'nullable|integer',
                'fecha_desembolso' => 'nullable|date',
                'fecha_primer_pago' => 'nullable|date|after_or_equal:fecha_desembolso',
                'gas_ids' => 'nullable|array',
                'gas_ids.*' => 'integer|exists:gastos,id_gasto',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $montoAprobado = $request->monto;
            $numeroCuotas = $request->numero_cuotas;
            $tasaInteres = $request->tasa_interes;
            $tipoInteres = $request->tipo_interes;
            $diasGracia = $request->dias_gracia ?? 0;
            $fechaDesembolso = isset($request->fecha_desembolso) ? Carbon::parse($request->fecha_desembolso) : Carbon::now();
            $fechaPrimerPago = isset($request->fecha_primer_pago) ? Carbon::parse($request->fecha_primer_pago) : null;

            // Calcular gastos si se proporcionan
            $totalGastos = 0;
            $gastosDetalle = [];
            $gastosPorCuota = [];

            if (!empty($request->gas_ids)) {
                $gastosService = app(\App\Services\GastosService::class);
                $gastos = \App\Models\Gasto::whereIn('id_gasto', $request->gas_ids)->activos()->get();
                $resultado = $gastosService->calcularValoresGastos($gastos, $montoAprobado);
                $totalGastos = $resultado['total_gastos'];
                $gastosDetalle = $resultado['gastos'];
                $gastosPorCuota = $gastosService->calcularProrrateoPorCuota($totalGastos, $numeroCuotas);
            }

            // Cálculo tipo "flat"
            $tasaMensual = $tasaInteres / 100;
            $interesPorCuota = round($montoAprobado * $tasaMensual, 2);
            $capitalPorCuota = round($montoAprobado / $numeroCuotas, 2);

            $diasEntreCuotas = $this->calcularDiasEntreCuotasPorTipo($tipoInteres);

            $planPagos = [];
            $saldoCapital = $montoAprobado;
            $totalInteres = 0;

            for ($i = 1; $i <= $numeroCuotas; $i++) {
                if ($fechaPrimerPago) {
                    if ($i === 1) {
                         $fechaVencimiento = $fechaPrimerPago->copy();
                    } else {
                         // Sumar meses en lugar de días para mantener el mismo día del mes
                         $fechaVencimiento = $fechaPrimerPago->copy()->addMonths($i - 1);
                    }
                } else {
                    // Sumar meses en lugar de días para mantener el mismo día del mes
                    $fechaVencimiento = $fechaDesembolso->copy()->addMonths($i - 1);
                }

                if ($i === 1 && $diasGracia > 0 && !$fechaPrimerPago) {
                    $fechaVencimiento->addDays($diasGracia);
                }

                $abonoCapital = $capitalPorCuota;
                if ($i === $numeroCuotas) {
                    $abonoCapital = $saldoCapital;
                }

                $gastoCuota = $gastosPorCuota[$i - 1] ?? 0;
                $cuotaBase = $abonoCapital + $interesPorCuota;
                $totalCuota = $cuotaBase + $gastoCuota;

                $totalInteres += $interesPorCuota;

                $planPagos[] = [
                    'numero_cuota' => $i,
                    'fecha_vencimiento' => $fechaVencimiento->format('Y-m-d'),
                    'capital' => round($abonoCapital, 2),
                    'interes' => round($interesPorCuota, 2),
                    'gastos' => round($gastoCuota, 2),
                    'cuota_base' => round($cuotaBase, 2),
                    'cuota_total' => round($totalCuota, 2),
                    // Compatibilidad con formato anterior
                    'otros' => round($gastoCuota, 2),
                    'cuota' => round($totalCuota, 2),
                    'saldo' => max(0, round($saldoCapital - $abonoCapital, 2))
                ];

                $saldoCapital -= $abonoCapital;
            }

            // Calcular total a pagar
            $totalAPagar = round($montoAprobado + $totalInteres + $totalGastos, 2);

            return response()->json([
                'success' => true,
                'data' => $planPagos,
                'resumen' => [
                    'monto_otorgado' => round($montoAprobado, 2),
                    'total_interes' => round($totalInteres, 2),
                    'total_gastos' => round($totalGastos, 2),
                    'total_a_pagar' => $totalAPagar,
                    'numero_cuotas' => $numeroCuotas,
                    'gastos_detalle' => $gastosDetalle,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error en simulación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al simular plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar plan de pagos preliminar desde datos del wizard (antes de crear el crédito)
     */
    public function generarPlanPagosPreliminar(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cliente' => 'nullable|array', // Allow nullable for simulation download
                'credito' => 'required|array',
                'credito.numero_credito' => 'required|string',
                'credito.monto_aprobado' => 'required|numeric',
                'credito.tasa_interes' => 'required|numeric',
                'credito.tipo_interes' => 'required|string',
                'credito.numero_cuotas' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Datos del crédito
            $datosCredito = $request->credito;
            $montoAprobado = $datosCredito['monto_aprobado'];
            $numeroCuotas = $datosCredito['numero_cuotas'];
            $tasaInteres = $datosCredito['tasa_interes'];
            $tipoInteres = $datosCredito['tipo_interes'];
            $diasGracia = $datosCredito['dias_gracia'] ?? 0;
            $fechaDesembolso = isset($datosCredito['fecha_desembolso']) ? Carbon::parse($datosCredito['fecha_desembolso']) : Carbon::now();
            $fechaPrimerPago = isset($datosCredito['fecha_primer_pago']) ? Carbon::parse($datosCredito['fecha_primer_pago']) : null;

            // Calcular gastos si están incluidos
            $gastosIds = $datosCredito['gas_ids'] ?? [];
            $totalGastos = 0;
            $gastosPorCuota = 0;

            if (!empty($gastosIds)) {
                $gastos = \App\Models\Gasto::whereIn('id_gasto', $gastosIds)->get();
                foreach ($gastos as $gasto) {
                    if ($gasto->tipo === 'FIJO') {
                        $totalGastos += $gasto->monto;
                    } else {
                        $totalGastos += ($montoAprobado * $gasto->porcentaje) / 100;
                    }
                }
                $gastosPorCuota = round($totalGastos / $numeroCuotas, 2);
            }

            // Cálculo tipo "flat"
            $tasaMensual = $tasaInteres / 100;
            $interesPorCuota = round($montoAprobado * $tasaMensual, 2);
            $capitalPorCuota = round($montoAprobado / $numeroCuotas, 2);

            $diasEntreCuotas = $this->calcularDiasEntreCuotasPorTipo($tipoInteres);

            $planPagos = collect([]);
            $saldoCapital = $montoAprobado;

             for ($i = 1; $i <= $numeroCuotas; $i++) {
                if ($fechaPrimerPago) {
                    if ($i === 1) {
                         $fechaVencimiento = $fechaPrimerPago->copy();
                    } else {
                         // Sumar meses en lugar de días para mantener el mismo día del mes
                         $fechaVencimiento = $fechaPrimerPago->copy()->addMonths($i - 1);
                    }
                } else {
                    // Sumar meses en lugar de días para mantener el mismo día del mes
                    $fechaVencimiento = $fechaDesembolso->copy()->addMonths($i - 1);
                }

                if ($i === 1 && $diasGracia > 0 && !$fechaPrimerPago) {
                    $fechaVencimiento->addDays($diasGracia);
                }

                $abonoCapital = $capitalPorCuota;
                if ($i === $numeroCuotas) {
                    $abonoCapital = $saldoCapital;
                }

                // Ajustar gastos en la última cuota si hay diferencia por redondeo
                $gastosEstaCuota = $gastosPorCuota;
                if ($i === $numeroCuotas && $totalGastos > 0) {
                    $gastosEstaCuota = $totalGastos - ($gastosPorCuota * ($numeroCuotas - 1));
                }

                $totalCuota = $abonoCapital + $interesPorCuota + $gastosEstaCuota;

                $cuota = (object)[
                    'numero_cuota' => $i,
                    'fecha_vencimiento' => $fechaVencimiento->format('Y-m-d'),
                    'capital_proyectado' => $abonoCapital,
                    'interes_proyectado' => $interesPorCuota,
                    'mora_proyectada' => 0,
                    'otros_proyectados' => $gastosEstaCuota,
                    'monto_cuota_proyectado' => $totalCuota,
                    'estado' => 'pendiente'
                ];

                $planPagos->push($cuota);
                $saldoCapital -= $abonoCapital;
            }

            // Preparar objeto crédito fake para la vista
             $creditoObj = (object) array_merge($datosCredito, [
                'codigo_credito' => $datosCredito['numero_credito'],
                'monto_aprobado' => $montoAprobado,
                'tasa_interes' => $tasaInteres,
                'tipo_interes' => $tipoInteres,
                'numero_cuotas' => $numeroCuotas,
                'fecha_desembolso' => $fechaDesembolso->format('Y-m-d')
            ]);

            // Obtener sucursal
            $sucursalId = $request->sucursal['id'] ?? '1';
            $sucursal = \App\Models\Sucursal::find($sucursalId);

            // Preparar objeto cliente (puede ser dummy para simulación)
            $clienteData = $request->cliente ?? [];
            $clienteObj = (object) [
                'nombres' => $clienteData['nombres'] ?? 'Consumidor',
                'apellidos' => $clienteData['apellidos'] ?? 'Final',
                'dpi' => $clienteData['dpi'] ?? $clienteData['numero_documento'] ?? 'CF',
                'numero_documento' => $clienteData['numero_documento'] ?? $clienteData['dpi'] ?? 'CF',
                'telefono' => $clienteData['telefono'] ?? null,
                'direccion' => $clienteData['direccion'] ?? null,
            ];

            $data = [
                'credito' => $creditoObj,
                'cliente' => $clienteObj,
                'sucursal' => $sucursal,
                'planPagos' => $planPagos,
                'fechaGeneracion' => now()->format('d/m/Y H:i:s'),
                'esPreliminar' => true,
            ];

            $pdf = Pdf::loadView('creditos.plan-pagos', $data);
            $pdf->setPaper('letter', 'portrait');

            $nombreArchivo = 'Plan_Pagos_Simulacion_' . ($datosCredito['numero_credito'] ?? 'SIM') . '.pdf';

            return $pdf->download($nombreArchivo);

        } catch (\Exception $e) {
            Log::error('Error al generar plan de pagos preliminar: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el plan de pagos preliminar',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Generar y descargar recibo de crédito prendario en PDF con código de barras
     */
    public function descargarRecibo(string $id)
    {
        try {
            $credito = CreditoPrendario::with([
                'cliente',
                'sucursal',
                'prendas.categoriaProducto'
            ])->findOrFail($id);

            // Generar código de barras
            $generator = new BarcodeGeneratorPNG();
            $barcodeData = $generator->getBarcode(
                $credito->numero_credito,
                $generator::TYPE_CODE_128,
                2,
                60
            );
            $barcodeImage = base64_encode($barcodeData);

            $data = [
                'credito' => $credito,
                'cliente' => $credito->cliente,
                'sucursal' => $credito->sucursal,
                'prendas' => $credito->prendas,
                'barcodeImage' => $barcodeImage,
                'fechaGeneracion' => now()->format('d/m/Y H:i:s'),
            ];

            $pdf = Pdf::loadView('creditos.recibo', $data);
            $pdf->setPaper('letter', 'portrait');

            $nombreArchivo = 'Recibo_Credito_' . ($credito->numero_credito) . '_' . date('Ymd') . '.pdf';

            return $pdf->download($nombreArchivo);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Crédito no encontrado para recibo PDF: ' . $id);
            return response()->json([
                'success' => false,
                'message' => 'Crédito no encontrado'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al generar recibo PDF: ' . $e->getMessage(), [
                'credito_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el recibo',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Generar y descargar historial de pagos en PDF
     */
    public function descargarHistorialPagos(string $id)
    {
        try {
            $credito = CreditoPrendario::with([
                'cliente',
                'sucursal',
                'movimientos' => function($query) {
                    $query->orderBy('fecha_movimiento', 'desc')
                          ->orderBy('created_at', 'desc');
                },
                'movimientos.usuario'
            ])->findOrFail($id);

            $movimientos = $credito->movimientos;

            // Calcular totales
            $totales = [
                'total_pagado' => 0,
                'capital_pagado' => 0,
                'interes_pagado' => 0,
                'mora_pagada' => 0,
                'total_desembolsado' => 0,
            ];

            foreach ($movimientos as $mov) {
                if ($mov->estado === 'activo') {
                    if ($mov->tipo_movimiento !== 'desembolso') {
                        $totales['total_pagado'] += (float) $mov->monto_total;
                        $totales['capital_pagado'] += (float) $mov->capital;
                        $totales['interes_pagado'] += (float) $mov->interes;
                        $totales['mora_pagada'] += (float) $mov->mora;
                    } else {
                        $totales['total_desembolsado'] += (float) $mov->monto_total;
                    }
                }
            }

            $data = [
                'credito' => $credito,
                'cliente' => $credito->cliente,
                'sucursal' => $credito->sucursal,
                'movimientos' => $movimientos,
                'totales' => $totales,
                'fechaGeneracion' => now()->format('d/m/Y H:i:s'),
            ];

            $pdf = Pdf::loadView('creditos.historial-pagos', $data);
            $pdf->setPaper('letter', 'portrait');

            $nombreArchivo = 'Historial_Pagos_' . ($credito->codigo_credito ?? $credito->numero_credito) . '_' . date('Ymd') . '.pdf';

            return $pdf->download($nombreArchivo);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Crédito no encontrado para historial de pagos PDF: ' . $id);
            return response()->json([
                'success' => false,
                'message' => 'Crédito no encontrado'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al generar historial de pagos PDF: ' . $e->getMessage(), [
                'credito_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el historial de pagos',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Reactivar un crédito (cambiar de vencido/anulado a vigente)
     * Extiende el plazo y recalcula fechas de vencimiento
     */
    public function reactivar(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'observaciones' => 'nullable|string|max:1000',
            'nuevo_plazo_dias' => 'nullable|integer|min:1',
            'extender_plan_pagos' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $credito = CreditoPrendario::with(['cliente', 'sucursal', 'prendas', 'planPagos'])->findOrFail($id);

            // Validar que el crédito pueda ser reactivado
            $estadosPermitidos = [
                EstadoCredito::VENCIDO->value,
                EstadoCredito::EN_MORA->value,
                EstadoCredito::ANULADO->value,
            ];

            if (!in_array($credito->estado, $estadosPermitidos)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este crédito no puede ser reactivado. Solo se pueden reactivar créditos vencidos, en mora o anulados.',
                    'estado_actual' => $credito->estado
                ], 422);
            }

            // Calcular nuevo plazo si se proporciona, sino usar el plazo original
            $nuevoPlazoDias = $request->nuevo_plazo_dias ?? $credito->plazo_dias ?? 30;

            // Calcular nueva fecha de vencimiento
            $fechaBase = $credito->fecha_vencimiento ? Carbon::parse($credito->fecha_vencimiento) : now();
            $nuevaFechaVencimiento = $fechaBase->copy()->addDays($nuevoPlazoDias);

            // Actualizar crédito
            $estadoAnterior = $credito->estado;
            $credito->update([
                'estado' => EstadoCredito::VIGENTE->value,
                'plazo_dias' => $nuevoPlazoDias,
                'fecha_vencimiento' => $nuevaFechaVencimiento,
                'observaciones' => $request->observaciones
                    ? ($credito->observaciones ? $credito->observaciones . "\n\nReactivado: " . $request->observaciones : "Reactivado: " . $request->observaciones)
                    : $credito->observaciones,
            ]);

            // Si se solicita extender el plan de pagos, recalcular las fechas
            if ($request->extender_plan_pagos && $credito->planPagos) {
                $planPagos = $credito->planPagos()->where('estado', '!=', 'pagada')->orderBy('numero_cuota')->get();

                if ($planPagos->isNotEmpty()) {
                    // Obtener la última cuota pagada para calcular desde ahí
                    $ultimaCuotaPagada = $credito->planPagos()
                        ->where('estado', 'pagada')
                        ->orderBy('numero_cuota', 'desc')
                        ->first();

                    $fechaInicio = $ultimaCuotaPagada && $ultimaCuotaPagada->fecha_vencimiento
                        ? Carbon::parse($ultimaCuotaPagada->fecha_vencimiento)
                        : now();

                    // Calcular días entre cuotas según tipo de interés
                    $diasEntreCuotas = $this->calcularDiasEntreCuotasPorTipo($credito->tipo_interes ?? 'mensual');

                    foreach ($planPagos as $index => $cuota) {
                        $nuevaFechaVencimiento = $fechaInicio->copy()->addDays(($index + 1) * $diasEntreCuotas);
                        $cuota->update([
                            'fecha_vencimiento' => $nuevaFechaVencimiento,
                        ]);
                    }
                }
            }

            // Registrar en auditoría
            AuditoriaCredito::registrar(
                $credito->id,
                'reactivado',
                'estado',
                $estadoAnterior,
                EstadoCredito::VIGENTE->value,
                $request->observaciones ?? 'Crédito reactivado'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Crédito reactivado exitosamente',
                'data' => $this->formatCredito($credito->fresh(['cliente', 'sucursal', 'prendas', 'planPagos']))
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al reactivar crédito: ' . $e->getMessage(), [
                'credito_id' => $id,
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al reactivar el crédito',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Actualizar crédito prendario (especialmente el estado)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $credito = CreditoPrendario::with(['prendas', 'cliente'])->findOrFail($id);

            // Validar datos
            $validator = Validator::make($request->all(), [
                'estado' => 'sometimes|string|in:' . implode(',', EstadoCredito::valores()),
                'observaciones' => 'nullable|string|max:500',
                'monto_aprobado' => 'sometimes|numeric|min:0',
                'plazo_dias' => 'sometimes|integer|min:1',
                'tasa_interes' => 'sometimes|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errores de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Guardar estado anterior para auditoría
            $estadoAnterior = $credito->estado;

            // Actualizar campos permitidos
            $camposActualizables = [
                'estado',
                'monto_aprobado',
                'plazo_dias',
                'tasa_interes',
                'observaciones',
            ];

            foreach ($camposActualizables as $campo) {
                if ($request->has($campo)) {
                    $credito->{$campo} = $request->{$campo};
                }
            }

            // Lógica especial para cambios de estado
            if ($request->has('estado') && $request->estado !== $estadoAnterior) {
                $nuevoEstado = $request->estado;

                // Validar transición de estado
                $transicionesValidas = EstadoCredito::transicionesDesde($estadoAnterior);

                if (!in_array($nuevoEstado, $transicionesValidas)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "No se puede cambiar de estado '{$estadoAnterior}' a '{$nuevoEstado}'",
                        'transiciones_validas' => $transicionesValidas
                    ], 400);
                }

                // Actualizar fechas según el nuevo estado
                switch ($nuevoEstado) {
                    case EstadoCredito::APROBADO->value:
                        $credito->fecha_aprobacion = now();
                        break;

                    case EstadoCredito::VIGENTE->value:
                        if (!$credito->fecha_desembolso) {
                            $credito->fecha_desembolso = now();
                        }
                        break;

                    case EstadoCredito::RESCATADO->value:
                    case EstadoCredito::VENDIDO->value:
                        $credito->fecha_liquidacion = now();
                        break;

                    case EstadoCredito::VENCIDO->value:
                    case EstadoCredito::EN_MORA->value:
                        if (!$credito->fecha_vencimiento || $credito->fecha_vencimiento->isFuture()) {
                            $credito->fecha_vencimiento = now();
                        }
                        break;
                }

                // Si cambia a VENDIDO o REMATADO, actualizar prendas a "vendida"
                if (in_array($nuevoEstado, [EstadoCredito::VENDIDO->value, EstadoCredito::REMATADO->value])) {
                    foreach ($credito->prendas as $prenda) {
                        $prenda->update([
                            'estado' => 'vendida',
                            'fecha_venta' => now(),
                        ]);
                    }
                }

                // Si cambia a EN_INVENTARIO, actualizar prendas a "en_venta"
                if ($nuevoEstado === EstadoCredito::EN_INVENTARIO->value) {
                    foreach ($credito->prendas as $prenda) {
                        $prenda->update([
                            'estado' => 'en_venta',
                            'fecha_publicacion_venta' => now(),
                        ]);
                    }
                }

                // Registrar auditoría del cambio de estado
                AuditoriaCredito::registrar(
                    $credito->id,
                    'estado_actualizado',
                    'estado',
                    $estadoAnterior,
                    $nuevoEstado,
                    $request->observaciones ?? "Estado actualizado de '{$estadoAnterior}' a '{$nuevoEstado}'"
                );
            }

            $credito->save();

            DB::commit();

            Log::info("Crédito {$credito->numero_credito} actualizado", [
                'credito_id' => $credito->id,
                'cambios' => $request->only($camposActualizables),
                'usuario' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Crédito actualizado exitosamente',
                'data' => $this->formatCredito($credito->fresh(['cliente', 'sucursal', 'prendas']))
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito no encontrado',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al actualizar crédito: ' . $e->getMessage(), [
                'credito_id' => $id,
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el crédito',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }
}

