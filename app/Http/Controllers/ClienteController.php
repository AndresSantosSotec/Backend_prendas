<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\CreditoPrendario;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    /**
     * Listar todos los clientes con paginación
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Cliente::query()->withCount([
                'creditosPrendarios as total_creditos',
                'creditosPrendarios as activos_creditos' => function($q) {
                    $q->whereIn('estado', ['vigente', 'en_mora']);
                },
                'creditosPrendarios as vencidos_creditos' => function($q) {
                    $q->where('estado', 'vencido');
                }
            ]);

            // Filtros
            if ($request->has('estado') && $request->estado !== '' && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }

            if ($request->has('tipo_cliente') && $request->tipo_cliente !== '' && $request->tipo_cliente !== 'todos') {
                $query->where('tipo_cliente', $request->tipo_cliente);
            }

            if ($request->has('genero') && $request->genero !== '' && $request->genero !== 'todos') {
                $query->where('genero', $request->genero);
            }

            if ($request->has('sucursal') && $request->sucursal !== '' && $request->sucursal !== 'todos') {
                $query->where('sucursal', $request->sucursal);
            }

            if ($request->has('busqueda') && $request->busqueda !== '') {
                $busqueda = $request->busqueda;
                $query->where(function ($q) use ($busqueda) {
                    $q->where('nombres', 'like', "%{$busqueda}%")
                      ->orWhere('apellidos', 'like', "%{$busqueda}%")
                      ->orWhere('dpi', 'like', "%{$busqueda}%")
                      ->orWhere('nit', 'like', "%{$busqueda}%")
                      ->orWhere('telefono', 'like', "%{$busqueda}%")
                      ->orWhere('email', 'like', "%{$busqueda}%");
                });
            }

            // Excluir eliminados
            $query->where('eliminado', false);

            // Ordenamiento
            $orderBy = $request->get('order_by', 'created_at');
            $orderDir = $request->get('order_dir', 'desc');
            $allowedOrderFields = ['nombres', 'apellidos', 'dpi', 'created_at', 'estado', 'tipo_cliente'];

            if (in_array($orderBy, $allowedOrderFields)) {
                $query->orderBy($orderBy, $orderDir === 'asc' ? 'asc' : 'desc');
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // Estadísticas OPTIMIZADAS - UNA sola consulta usando DB::raw con CASE
            $statsRaw = Cliente::where('eliminado', false)
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = "activo" THEN 1 ELSE 0 END) as activos,
                    SUM(CASE WHEN estado = "inactivo" THEN 1 ELSE 0 END) as inactivos,
                    SUM(CASE WHEN tipo_cliente = "vip" THEN 1 ELSE 0 END) as vip,
                    SUM(CASE WHEN genero = "masculino" THEN 1 ELSE 0 END) as masculino,
                    SUM(CASE WHEN genero = "femenino" THEN 1 ELSE 0 END) as femenino,
                    SUM(CASE WHEN genero = "otro" THEN 1 ELSE 0 END) as otro
                ')
                ->first();

            $stats = [
                'total' => (int) $statsRaw->total,
                'activos' => (int) $statsRaw->activos,
                'inactivos' => (int) $statsRaw->inactivos,
                'vip' => (int) $statsRaw->vip,
                'por_genero' => [
                    'masculino' => (int) $statsRaw->masculino,
                    'femenino' => (int) $statsRaw->femenino,
                    'otro' => (int) $statsRaw->otro,
                ],
            ];

            // Sucursales únicas
            $sucursales = Cliente::where('eliminado', false)
                ->whereNotNull('sucursal')
                ->where('sucursal', '!=', '')
                ->distinct()
                ->pluck('sucursal')
                ->toArray();

            // Paginación optimizada con chunks (mínimo 10, máximo 100)
            $perPage = (int) $request->get('per_page', 10);
            $perPage = max(10, min(100, $perPage)); // Asegurar rango 10-100
            $page = (int) $request->get('page', 1);

            // Usar cursor pagination para mejor performance en datasets grandes
            $useCursor = $request->get('use_cursor', false);

            if ($useCursor && $request->has('cursor')) {
                // Cursor pagination - más eficiente para datasets grandes
                $cursor = $request->get('cursor');
                $clientes = $query
                    ->where('id', '>', $cursor)
                    ->take($perPage)
                    ->get();

                $nextCursor = $clientes->last()?->id;
                $hasMore = $clientes->count() === $perPage;

                return response()->json([
                    'success' => true,
                    'data' => $clientes->map(function ($cliente) {
                        return $this->formatCliente($cliente);
                    }),
                    'cursor' => [
                        'next' => $hasMore ? $nextCursor : null,
                        'has_more' => $hasMore,
                        'per_page' => $perPage,
                    ],
                    'stats' => $stats,
                    'sucursales' => $sucursales,
                ], 200, [], JSON_UNESCAPED_UNICODE);
            }

            // Paginación tradicional mejorada - usar lazy() para chunks
            $totalFiltrado = (clone $query)->count();

            // Si hay muchos registros (>1000), usar lazy loading
            if ($totalFiltrado > 1000) {
                $clientes = $query
                    ->skip(($page - 1) * $perPage)
                    ->take($perPage)
                    ->lazy(100) // Carga en chunks de 100
                    ->map(function ($cliente) {
                        return $this->formatCliente($cliente);
                    })
                    ->values();
            } else {
                $clientes = $query
                    ->skip(($page - 1) * $perPage)
                    ->take($perPage)
                    ->get()
                    ->map(function ($cliente) {
                        return $this->formatCliente($cliente);
                    });
            }

            return response()->json([
                'success' => true,
                'data' => $clientes,
                'pagination' => [
                    'total' => $totalFiltrado,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($totalFiltrado / $perPage),
                    'from' => (($page - 1) * $perPage) + 1,
                    'to' => min($page * $perPage, $totalFiltrado),
                ],
                'stats' => $stats,
                'sucursales' => $sucursales,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en ClienteController@index', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener solo clientes activos (para selects en frontend)
     */
    public function activos(): JsonResponse
    {
        $clientes = Cliente::where('eliminado', false)
            ->where('estado', 'activo')
            ->orderBy('nombres', 'asc')
            ->orderBy('apellidos', 'asc')
            ->get(['id', 'nombres', 'apellidos', 'dpi', 'nit', 'email']);

        return response()->json([
            'success' => true,
            'data' => $clientes->map(function ($cliente) {
                return [
                    'id' => (string) $cliente->id,
                    'nombres' => $cliente->nombres,
                    'apellidos' => $cliente->apellidos,
                    'dpi' => $cliente->dpi,
                    'nit' => $cliente->nit,
                    'email' => $cliente->email,
                ];
            })
        ]);
    }

    /**
     * Obtener un cliente por ID
     */
    public function show(string $id): JsonResponse
    {
        $cliente = Cliente::where('id', $id)
            ->where('eliminado', false)
            ->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatCliente($cliente)
        ]);
    }

    /**
     * Obtener historial de créditos prendarios de un cliente
     *
     * IMPORTANTE: Este método incluye créditos eliminados (soft deleted) para mostrar
     * el historial completo del cliente. Los créditos eliminados se marcan con 'eliminado: true'
     * y las prendas eliminadas también se incluyen para mantener la trazabilidad.
     */
    public function creditosPrendarios(Request $request, string $id): JsonResponse
    {
        $cliente = Cliente::where('id', $id)
            ->where('eliminado', false)
            ->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        // withTrashed() para incluir créditos eliminados (soft deleted) en el historial
        // También incluimos prendas eliminadas en la relación para trazabilidad completa
        $query = CreditoPrendario::withTrashed()
            ->with(['sucursal', 'prendas' => function ($q) {
                $q->withTrashed(); // Incluir prendas eliminadas también
            }])
            ->where('cliente_id', $cliente->id)
            ->orderBy('fecha_solicitud', 'desc')
            ->orderBy('id', 'desc');

        $perPage = min((int) $request->get('per_page', 50), 200);
        $page = (int) $request->get('page', 1);

        $total = (clone $query)->count();
        $creditos = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => $creditos->map(function (CreditoPrendario $c) {
                return [
                    'id' => (string) $c->id,
                    'numero_credito' => $c->numero_credito,
                    'estado' => $c->estado,
                    'eliminado' => $c->trashed(), // Indicar si el crédito fue eliminado
                    'fecha_eliminacion' => $c->deleted_at?->toISOString(),
                    'fecha_solicitud' => $c->fecha_solicitud?->toISOString(),
                    'fecha_vencimiento' => $c->fecha_vencimiento?->toISOString(),
                    'monto_aprobado' => (float) $c->monto_aprobado,
                    'monto_solicitado' => (float) $c->monto_solicitado,
                    'capital_pendiente' => (float) $c->capital_pendiente,
                    'intereses_pendientes' => (float) ($c->interes_generado - $c->interes_pagado),
                    'mora_pendiente' => (float) ($c->mora_generada - $c->mora_pagada),
                    'numero_cuotas' => $c->numero_cuotas,
                    'tasa_interes' => (float) $c->tasa_interes,
                    'tipo_interes' => $c->tipo_interes,
                    'sucursal' => $c->sucursal ? [
                        'id' => (string) $c->sucursal->id,
                        'nombre' => $c->sucursal->nombre,
                    ] : null,
                    'prendas' => $c->prendas->map(function ($p) {
                        return [
                            'id' => (string) $p->id,
                            'codigo_prenda' => $p->codigo_prenda,
                            'descripcion' => $p->descripcion,
                            'eliminado' => $p->trashed(), // Indicar si la prenda fue eliminada
                        ];
                    })->values(),
                ];
            })->values(),
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
                'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
                'to' => min($page * $perPage, $total),
            ],
        ]);
    }

    /**
     * Obtener la Ficha Detallada del Cliente (Ficha del Cliente)
     */
    public function ficha(string $id): JsonResponse
    {
        $cliente = Cliente::where('id', $id)
            ->where('eliminado', false)
            ->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        // Estadísticas de créditos
        $creditos = CreditoPrendario::where('cliente_id', $cliente->id)->get();

        $stats = [
            'total_historial' => $creditos->count(),
            'activos' => $creditos->whereIn('estado', ['vigente', 'en_mora', 'aprobado'])->count(),
            'pagados' => $creditos->where('estado', 'pagado')->count(),
            'vencidos' => $creditos->where('estado', 'vencido')->count(),
            'monto_total_prestado' => (float) $creditos->sum('monto_aprobado'),
            'monto_capital_pendiente' => (float) $creditos->sum('capital_pendiente'),
            'total_interes_pagado' => (float) $creditos->sum('interes_pagado'),
            'total_mora_pagada' => (float) $creditos->sum('mora_pagada'),
            'saldo_actual_total' => (float) $creditos->sum(function($c) {
                return $c->capital_pendiente + ($c->interes_generado - $c->interes_pagado) + ($c->mora_generada - $c->mora_pagada);
            }),
            'ultima_operacion' => $creditos->max('updated_at')?->toISOString(),
            'primer_credito' => $creditos->min('fecha_solicitud')?->format('Y-m-d'),
        ];

        // Comportamiento de pago (Credit Score Simple)
        $movimientosPago = \App\Models\CreditoMovimiento::whereIn('credito_prendario_id', $creditos->pluck('id'))
            ->where('tipo_movimiento', '!=', 'desembolso')
            ->get();

        $pagosPuntuales = 0;
        $pagosTotal = $movimientosPago->count();

        // Calcular puntualidad - considera pagos a tiempo O adelantados
        foreach ($movimientosPago as $mov) {
            $cuota = \App\Models\CreditoPlanPago::find($mov->cuota_id);
            if ($cuota) {
                // Pago puntual = hecho antes o el mismo día del vencimiento
                if ($mov->fecha_movimiento <= $cuota->fecha_vencimiento) {
                    $pagosPuntuales++;
                }
            }
        }

        $tasaPuntualidad = $pagosTotal > 0 ? ($pagosPuntuales / $pagosTotal) * 100 : null;

        $comportamiento = [
            'pagos_puntuales' => $pagosPuntuales,
            'pagos_total' => $pagosTotal,
            'tasa_puntualidad' => $tasaPuntualidad,
            'comportamiento' => 'SIN DATOS',
        ];

        if ($tasaPuntualidad !== null) {
            if ($tasaPuntualidad >= 90) {
                $comportamiento['comportamiento'] = 'Puntual';
            } elseif ($tasaPuntualidad >= 70) {
                $comportamiento['comportamiento'] = 'Regular';
            } else {
                $comportamiento['comportamiento'] = 'Moroso';
            }
        }

        // Score de riesgo
        $score = [
            'puntos' => 100,
            'nivel' => 'Excelente',
            'color' => 'green',
        ];

        if ($stats['vencidos'] > 0) {
            $score['puntos'] -= ($stats['vencidos'] * 20);
        }

        // Penalización por mora activa
        $creditosMora = $creditos->where('estado', 'en_mora')->count();
        if ($creditosMora > 0) {
            $score['puntos'] -= ($creditosMora * 10);
        }

        if ($score['puntos'] < 40) {
            $score['nivel'] = 'Riesgoso';
            $score['color'] = 'red';
        } elseif ($score['puntos'] < 70) {
            $score['nivel'] = 'Regular';
            $score['color'] = 'orange';
        }

        // Añadir saldo_pendiente_actual a stats
        $stats['saldo_pendiente_actual'] = $stats['saldo_actual_total'];

        return response()->json([
            'success' => true,
            'data' => [
                'cliente' => $this->formatCliente($cliente),
                'stats' => $stats, // Cambiar de resumen_financiero a stats
                'comportamiento' => array_merge($comportamiento, $score),
                'creditos_recientes' => $creditos->sortByDesc('fecha_solicitud')->take(5)->map(function($c) {
                    return [
                        'id' => (string) $c->id,
                        'numero' => $c->numero_credito,
                        'monto' => (float) $c->monto_aprobado,
                        'fecha' => $c->fecha_solicitud?->format('Y-m-d'),
                        'estado' => $c->estado,
                        'saldo' => (float) ($c->capital_pendiente + ($c->interes_generado - $c->interes_pagado) + ($c->mora_generada - $c->mora_pagada))
                    ];
                })->values()
            ]
        ]);
    }

    /**
     * Generar y descargar ficha completa del cliente en PDF
     */
    public function descargarFichaCliente(string $id)
    {
        try {
            $cliente = Cliente::findOrFail($id);

            // Obtener todos los créditos del cliente (activos y archivados)
            $creditos = \App\Models\CreditoPrendario::where('cliente_id', $id)
                ->with(['prendas', 'planPagos', 'movimientos'])
                ->get();

            // Estadísticas financieras
            $stats = [
                'total_historial' => $creditos->count(),
                'activos' => $creditos->where('estado', '!=', 'liquidado')->where('eliminado', false)->count(),
                'liquidados' => $creditos->where('estado', 'liquidado')->count(),
                'vencidos' => $creditos->where('estado', 'vencido')->count(),
                'monto_total_prestado' => $creditos->sum('monto_aprobado'),
                'saldo_actual_total' => $creditos->where('estado', '!=', 'liquidado')->sum(function($c) {
                    return $c->capital_pendiente + ($c->interes_generado - $c->interes_pagado) + ($c->mora_generada - $c->mora_pagada);
                }),
            ];

            // Comportamiento de pago
            $movimientosPago = \App\Models\CreditoMovimiento::whereIn('credito_prendario_id', $creditos->pluck('id'))
                ->where('tipo_movimiento', '!=', 'desembolso')
                ->get();

            $pagosPuntuales = 0;
            $pagosTotal = $movimientosPago->count();

            foreach ($movimientosPago as $mov) {
                $cuota = \App\Models\CreditoPlanPago::find($mov->cuota_id);
                if ($cuota && $mov->fecha_movimiento <= $cuota->fecha_vencimiento) {
                    $pagosPuntuales++;
                }
            }

            $tasaPuntualidad = $pagosTotal > 0 ? ($pagosPuntuales / $pagosTotal) * 100 : null;

            $comportamiento = [
                'pagos_puntuales' => $pagosPuntuales,
                'pagos_total' => $pagosTotal,
                'tasa_puntualidad' => $tasaPuntualidad,
                'comportamiento' => 'SIN DATOS',
            ];

            if ($tasaPuntualidad !== null) {
                if ($tasaPuntualidad >= 90) {
                    $comportamiento['comportamiento'] = 'Puntual';
                } elseif ($tasaPuntualidad >= 70) {
                    $comportamiento['comportamiento'] = 'Regular';
                } else {
                    $comportamiento['comportamiento'] = 'Moroso';
                }
            }

            // Score de riesgo
            $score = [
                'puntos' => 100,
                'nivel' => 'Excelente',
                'color' => 'green',
            ];

            if ($stats['vencidos'] > 0) {
                $score['puntos'] -= ($stats['vencidos'] * 20);
            }

            $creditosMora = $creditos->where('estado', 'en_mora')->count();
            if ($creditosMora > 0) {
                $score['puntos'] -= ($creditosMora * 10);
            }

            if ($score['puntos'] < 40) {
                $score['nivel'] = 'Riesgoso';
                $score['color'] = 'red';
            } elseif ($score['puntos'] < 70) {
                $score['nivel'] = 'Regular';
                $score['color'] = 'orange';
            }

            $data = [
                'cliente' => $cliente,
                'stats' => $stats,
                'comportamiento' => array_merge($comportamiento, $score),
                'creditos_activos' => $creditos->where('estado', '!=', 'liquidado')->where('eliminado', false)->sortByDesc('fecha_solicitud')->take(10),
                'creditos_liquidados' => $creditos->where('estado', 'liquidado')->sortByDesc('fecha_solicitud')->take(5),
                'fechaGeneracion' => now()->format('d/m/Y H:i:s'),
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('clientes.ficha', $data);
            $pdf->setPaper('letter', 'portrait');

            $nombreArchivo = 'Ficha_Cliente_' . ($cliente->codigo_cliente ?? $cliente->id) . '_' . date('Ymd') . '.pdf';

            return $pdf->download($nombreArchivo);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Cliente no encontrado para ficha PDF: ' . $id);
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al generar ficha del cliente PDF: ' . $e->getMessage(), [
                'cliente_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al generar la ficha del cliente',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Crear un nuevo cliente
     */
    public function store(StoreClienteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['estado'] = $data['estado'] ?? 'activo';
        $data['tipo_cliente'] = $data['tipo_cliente'] ?? 'regular';
        $data['eliminado'] = false;

        // Procesar fotografía si es base64
        if (!empty($data['fotografia']) && str_starts_with($data['fotografia'], 'data:image')) {
            $data['fotografia'] = $this->savePhotoFromBase64($data['fotografia']);
        }

        $cliente = Cliente::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Cliente creado exitosamente',
            'data' => $this->formatCliente($cliente)
        ], 201);
    }

    /**
     * Actualizar un cliente
     */
    public function update(UpdateClienteRequest $request, string $id): JsonResponse
    {
        $cliente = Cliente::where('id', $id)
            ->where('eliminado', false)
            ->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        $data = $request->validated();

        // Procesar fotografía si es base64
        if (isset($data['fotografia']) && str_starts_with($data['fotografia'], 'data:image')) {
            // Eliminar foto anterior si existe
            if ($cliente->fotografia && !str_starts_with($cliente->fotografia, 'data:image')) {
                Storage::disk('public')->delete($cliente->fotografia);
            }
            $data['fotografia'] = $this->savePhotoFromBase64($data['fotografia']);
        }

        $cliente->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cliente actualizado exitosamente',
            'data' => $this->formatCliente($cliente)
        ]);
    }

    /**
     * Eliminar un cliente (soft delete)
     */
    public function destroy(string $id): JsonResponse
    {
        $cliente = Cliente::where('id', $id)
            ->where('eliminado', false)
            ->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        $cliente->update([
            'eliminado' => true,
            'eliminado_en' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cliente eliminado exitosamente'
        ]);
    }

    /**
     * Subir fotografía del cliente
     */
    public function uploadPhoto(Request $request, string $id): JsonResponse
    {
        $cliente = Cliente::where('id', $id)
            ->where('eliminado', false)
            ->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'fotografia' => 'required|string', // Base64
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $base64 = $request->fotografia;

        if (!str_starts_with($base64, 'data:image')) {
            return response()->json([
                'success' => false,
                'message' => 'Formato de imagen inválido'
            ], 422);
        }

        // Eliminar foto anterior si existe
        if ($cliente->fotografia && !str_starts_with($cliente->fotografia, 'data:image')) {
            Storage::disk('public')->delete($cliente->fotografia);
        }

        $path = $this->savePhotoFromBase64($base64);
        $cliente->update(['fotografia' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Fotografía subida exitosamente',
            'data' => [
                'fotografia' => $cliente->fotografia_url
            ]
        ]);
    }

    /**
     * Guardar foto desde base64
     */
    private function savePhotoFromBase64(string $base64): string
    {
        // Extraer datos de la imagen
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $extension = $matches[1];
            $imageData = base64_decode(substr($base64, strpos($base64, ',') + 1));

            // Generar nombre único
            $filename = 'clientes/' . uniqid() . '_' . time() . '.' . $extension;

            // Guardar en storage
            Storage::disk('public')->put($filename, $imageData);

            return $filename;
        }

        // Si no es base64 válido, retornar como está
        return $base64;
    }

    /**
     * Formatear cliente para respuesta
     */
    private function formatCliente(Cliente $cliente): array
    {
        return [
            'id' => (string) $cliente->id,
            'nombres' => $cliente->nombres,
            'apellidos' => $cliente->apellidos,
            'dpi' => $cliente->dpi,
            'nit' => $cliente->nit,
            'fechaNacimiento' => $cliente->fecha_nacimiento->format('Y-m-d'),
            'genero' => $cliente->genero,
            'telefono' => $cliente->telefono,
            'telefonoSecundario' => $cliente->telefono_secundario,
            'email' => $cliente->email,
            'direccion' => $cliente->direccion,
            'municipio' => $cliente->municipio,
            'departamentoGeonameId' => $cliente->departamento_geoname_id,
            'municipioGeonameId' => $cliente->municipio_geoname_id,
            'fotografia' => $cliente->fotografia_url,
            'estado' => $cliente->estado,
            'sucursal' => $cliente->sucursal,
            'tipoCliente' => $cliente->tipo_cliente,
            'notas' => $cliente->notas,
            'eliminado' => $cliente->eliminado,
            'creadoEn' => $cliente->created_at->toISOString(),
            'actualizadoEn' => $cliente->updated_at->toISOString(),
            'creditosInfo' => [
                'total' => $cliente->total_creditos ?? 0,
                'activos' => $cliente->activos_creditos ?? 0,
                'vencidos' => $cliente->vencidos_creditos ?? 0,
            ],
        ];
    }
}

