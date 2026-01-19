<?php

namespace App\Http\Controllers;

use App\Models\CreditoPrendario;
use App\Models\Prenda;
use App\Models\Tasacion;
use App\Models\Cliente;
use App\Models\CreditoMovimiento;
use App\Models\CreditoPlanPago;
use App\Models\IdempotencyKey;
use App\Models\AuditoriaCredito;
use App\Enums\EstadoCredito;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

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
        $query = CreditoPrendario::with(['cliente', 'sucursal', 'prendas']);

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

        // Ordenamiento
        $orderBy = $request->get('order_by', 'fecha_solicitud');
        $orderDir = $request->get('order_dir', 'desc');
        $allowedOrderFields = ['fecha_solicitud', 'fecha_vencimiento', 'monto_aprobado', 'estado', 'numero_credito'];

        if (in_array($orderBy, $allowedOrderFields)) {
            $query->orderBy($orderBy, $orderDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('fecha_solicitud', 'desc');
        }

        // Paginación
        $perPage = min((int) $request->get('per_page', 15), 100);
        $page = (int) $request->get('page', 1);

        $totalFiltrado = (clone $query)->count();
        $creditos = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => $creditos->map(function ($credito) {
                return $this->formatCredito($credito);
            }),
            'pagination' => [
                'total' => $totalFiltrado,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($totalFiltrado / $perPage),
                'from' => (($page - 1) * $perPage) + 1,
                'to' => min($page * $perPage, $totalFiltrado),
            ],
        ]);
    }

    /**
     * Obtener estadísticas de créditos prendarios
     */
    public function getEstadisticas(): JsonResponse
    {
        $stats = [
            'total' => CreditoPrendario::count(),
            'solicitados' => CreditoPrendario::where('estado', 'solicitado')->count(),
            'en_analisis' => CreditoPrendario::where('estado', 'en_analisis')->count(),
            'aprobados' => CreditoPrendario::where('estado', 'aprobado')->count(),
            'vigentes' => CreditoPrendario::where('estado', 'vigente')->count(),
            'vencidos' => CreditoPrendario::where('estado', 'vencido')->count(),
            'en_mora' => CreditoPrendario::where('estado', 'en_mora')->count(),
            'pagados' => CreditoPrendario::where('estado', 'pagado')->count(),
            'cancelados' => CreditoPrendario::where('estado', 'cancelado')->count(),
            'monto_total_prestado' => (float) CreditoPrendario::whereIn('estado', ['vigente', 'vencido', 'en_mora', 'pagado'])
                ->sum('monto_desembolsado'),
            'monto_capital_pendiente' => (float) CreditoPrendario::whereIn('estado', ['vigente', 'vencido', 'en_mora'])
                ->sum('capital_pendiente'),
            'monto_interes_pendiente' => (float) CreditoPrendario::whereIn('estado', ['vigente', 'vencido', 'en_mora'])
                ->sum(DB::raw('interes_generado - interes_pagado')),
            'monto_mora_pendiente' => (float) CreditoPrendario::whereIn('estado', ['vigente', 'vencido', 'en_mora'])
                ->sum(DB::raw('mora_generada - mora_pagada')),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
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
            'tipo_interes' => 'nullable|in:diario,semanal,quincenal,mensual',
            'plazo_dias' => 'nullable|integer|min:1',
            'dias_gracia' => 'nullable|integer|min:0',
            'numero_cuotas' => 'nullable|integer|min:1',
            'monto_cuota' => 'nullable|numeric|min:0',
            'fecha_desembolso' => 'nullable|date',
            'fecha_primer_pago' => 'nullable|date',
            'observaciones' => 'nullable|string|max:1000',
            'tasador_id' => 'nullable|exists:users,id',
            'prendas' => 'required|array|min:1',
            'prendas.*.categoria_producto_id' => 'required|exists:categoria_productos,id',
            'prendas.*.descripcion_general' => 'required|string|max:500',
            'prendas.*.marca' => 'nullable|string|max:100',
            'prendas.*.modelo' => 'nullable|string|max:100',
            'prendas.*.numero_serie' => 'nullable|string|max:100',
            'prendas.*.condicion_fisica' => 'nullable|in:excelente,bueno,regular,deteriorado',
            'prendas.*.fotos' => 'nullable|array',
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

            // Generar número de crédito único
            $ultimoCredito = CreditoPrendario::withTrashed()
                ->whereNotNull('numero_credito')
                ->orderBy('id', 'desc')
                ->first();

            if ($ultimoCredito && $ultimoCredito->numero_credito) {
                // Extraer número del formato CR-XXXXXX
                $ultimoNumero = (int) substr($ultimoCredito->numero_credito, 3);
                $numero = $ultimoNumero + 1;
            } else {
                $numero = 1;
            }

            $numeroCredito = 'CR-' . str_pad($numero, 6, '0', STR_PAD_LEFT);

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

            // Crear crédito prendario con todos los campos
            $datosCredito = [
                'numero_credito' => $numeroCredito,
                'cliente_id' => $request->cliente_id,
                'sucursal_id' => $request->sucursal_id,
                'tasador_id' => $request->tasador_id ?? Auth::id(),
                'monto_solicitado' => $request->monto_solicitado,
                'monto_aprobado' => $request->monto_aprobado ?? $request->monto_solicitado,
                'valor_tasacion' => $request->valor_tasacion,
                'tasa_interes' => $request->tasa_interes ?? 0,
                'tasa_mora' => $request->tasa_mora ?? 0,
                'tipo_interes' => $request->tipo_interes ?? 'mensual',
                'plazo_dias' => $request->plazo_dias,
                'dias_gracia' => $request->dias_gracia ?? 0,
                'numero_cuotas' => $request->numero_cuotas ?? 1,
                'monto_cuota' => $montoCuota,
                'fecha_desembolso' => $request->fecha_desembolso ? Carbon::parse($request->fecha_desembolso) : null,
                'observaciones' => $request->observaciones,
                'estado' => 'solicitado',
                'fecha_solicitud' => now(),
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
                    }
                } catch (\Exception $e) {
                    // Si hay error al verificar, simplemente no agregamos el campo
                    Log::warning('No se pudo verificar columna fecha_primer_pago: ' . $e->getMessage());
                }
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
                $numeroPrenda = $numeroPrendaBase + $index + 1;
                $codigoPrenda = 'PRN-' . str_pad($numeroPrenda, 6, '0', STR_PAD_LEFT);

                // Mapear condición física del frontend al formato de BD
                $condicionMap = [
                    'excelente' => 'excelente',
                    'bueno' => 'buena',
                    'regular' => 'regular',
                    'deteriorado' => 'mala'
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
                ]);

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
                    'condicion_fisica' => $prendaData['condicion_fisica'] ?? 'bueno',
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

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Crédito prendario creado exitosamente',
                'data' => $this->formatCredito($credito->fresh(['cliente', 'sucursal', 'prendas', 'planPagos']))
            ], 201);

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
        $credito = CreditoPrendario::with(['cliente', 'sucursal', 'prendas', 'tasaciones', 'movimientos', 'planPagos'])
            ->find($id);

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
        $credito = CreditoPrendario::find($id);

        if (!$credito) {
            return response()->json([
                'success' => false,
                'message' => 'Crédito prendario no encontrado'
            ], 404);
        }

        $planPagos = $credito->planPagos()->orderBy('numero_cuota')->get();

        return response()->json([
            'success' => true,
            'data' => $planPagos->map(function ($cuota) {
                return [
                    'id' => (string) $cuota->id,
                    'numero_cuota' => $cuota->numero_cuota,
                    'fecha_vencimiento' => $cuota->fecha_vencimiento?->toISOString(),
                    'fecha_pago' => $cuota->fecha_pago?->toISOString(),
                    'estado' => $cuota->estado,
                    'capital_proyectado' => (float) $cuota->capital_proyectado,
                    'interes_proyectado' => (float) $cuota->interes_proyectado,
                    'mora_proyectada' => (float) $cuota->mora_proyectada,
                    'monto_cuota_proyectado' => (float) $cuota->monto_cuota_proyectado,
                    'capital_pagado' => (float) $cuota->capital_pagado,
                    'interes_pagado' => (float) $cuota->interes_pagado,
                    'mora_pagada' => (float) $cuota->mora_pagada,
                    'monto_total_pagado' => (float) $cuota->monto_total_pagado,
                    'capital_pendiente' => (float) $cuota->capital_pendiente,
                    'interes_pendiente' => (float) $cuota->interes_pendiente,
                    'mora_pendiente' => (float) $cuota->mora_pendiente,
                    'monto_pendiente' => (float) $cuota->monto_pendiente,
                    'saldo_capital_credito' => (float) $cuota->saldo_capital_credito,
                    'dias_mora' => $cuota->dias_mora,
                    'es_cuota_gracia' => (bool) $cuota->es_cuota_gracia,
                    'observaciones' => $cuota->observaciones,
                ];
            })
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
                    'monto_cuota_proyectado' => (float) $cuota->monto_cuota_proyectado,
                    'capital_pagado' => (float) $cuota->capital_pagado,
                    'interes_pagado' => (float) $cuota->interes_pagado,
                    'mora_pagada' => (float) $cuota->mora_pagada,
                    'monto_total_pagado' => (float) $cuota->monto_total_pagado,
                    'capital_pendiente' => (float) $cuota->capital_pendiente,
                    'interes_pendiente' => (float) $cuota->interes_pendiente,
                    'mora_pendiente' => (float) $cuota->mora_pendiente,
                    'monto_pendiente' => (float) $cuota->monto_pendiente,
                    'saldo_capital_credito' => (float) $cuota->saldo_capital_credito,
                    'dias_mora' => $cuota->dias_mora,
                    'es_cuota_gracia' => (bool) $cuota->es_cuota_gracia,
                ];
            })->values() : [],
            'creadoEn' => $credito->created_at->toISOString(),
            'actualizadoEn' => $credito->updated_at->toISOString(),
        ];
    }

    /**
     * Generar plan de pagos automáticamente al crear un crédito
     * Usa amortización francesa (igual que el frontend) para consistencia
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

        // Calcular fecha de inicio (fecha de desembolso o fecha del primer pago si está definida)
        $fechaDesembolso = $credito->fecha_desembolso ? Carbon::parse($credito->fecha_desembolso) : now();

        // Prioridad: usar fecha_primer_pago del request, luego del crédito guardado, luego calcular desde desembolso
        $fechaPrimerPago = $fechaPrimerPagoRequest;
        if (!$fechaPrimerPago && $credito->fecha_primer_pago) {
            $fechaPrimerPago = Carbon::parse($credito->fecha_primer_pago);
        }

        // Calcular días entre cuotas según tipo de interés
        $diasEntreCuotas = $this->calcularDiasEntreCuotasPorTipo($tipoInteres);

        // Calcular tasa de interés por período (igual que frontend)
        $tasaPorPeriodo = $this->calcularTasaPorPeriodo($tasaInteres, $tipoInteres);

        // Calcular cuota usando amortización francesa (igual que frontend)
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
        $totalCapital = 0;
        $totalInteres = 0;

        // Generar cada cuota
        for ($numeroCuota = 1; $numeroCuota <= $numeroCuotas; $numeroCuota++) {
            // Calcular fecha de vencimiento
            if ($fechaPrimerPago) {
                // Si hay fecha_primer_pago, la primera cuota usa esa fecha exacta
                // Las siguientes se calculan desde ahí
                if ($numeroCuota === 1) {
                    // Primera cuota: usar la fecha del primer pago exacta
                    $fechaVencimiento = $fechaPrimerPago->copy();
                } else {
                    // Cuotas siguientes: calcular desde la fecha del primer pago
                    $fechaVencimiento = $fechaPrimerPago->copy()->addDays(($numeroCuota - 1) * $diasEntreCuotas);
                }
            } else {
                // Si no hay fecha_primer_pago, calcular desde fecha_desembolso
                $fechaVencimiento = $fechaDesembolso->copy()->addDays(($numeroCuota - 1) * $diasEntreCuotas);
            }

            // Si hay días de gracia en la primera cuota, agregarlos (solo si no hay fecha_primer_pago)
            // porque si hay fecha_primer_pago, esa fecha ya incluye el período de gracia
            if ($numeroCuota === 1 && $diasGracia > 0 && !$fechaPrimerPago) {
                $fechaVencimiento->addDays($diasGracia);
            }

            // Calcular interés y capital para esta cuota usando amortización francesa
            $interesProyectado = round($saldoCapital * $tasaPorPeriodo, 2);

            // Si es la última cuota, ajustar para evitar desfases por redondeo
            if ($numeroCuota === $numeroCuotas) {
                $capitalProyectado = round($saldoCapital, 2);
                $montoCuotaProyectado = round($capitalProyectado + $interesProyectado, 2);
            } else {
                $capitalProyectado = round($cuotaPeriodo - $interesProyectado, 2);
                $montoCuotaProyectado = $cuotaPeriodo;
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
                'permite_pago_parcial' => true,
                'tipo_modificacion' => 'original',
            ]);

            // Actualizar saldos para la siguiente cuota
            $saldoCapital -= $capitalProyectado;
            $totalCapital += $capitalProyectado;
            $totalInteres += $interesProyectado;
        }
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
            case 'quincenal':
                return 15;
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
            case 'quincenal':
                return $tasaAnual / 100 / 24;
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
                $query->orderBy('numero_cuota');
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
}
