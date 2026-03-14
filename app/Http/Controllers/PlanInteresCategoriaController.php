<?php

namespace App\Http\Controllers;

use App\Models\PlanInteresCategoria;
use App\Models\CategoriaProducto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PlanInteresCategoriaController extends Controller
{
    /**
     * Listar todos los planes de interés
     */
    public function index(Request $request)
    {
        try {
            $query = PlanInteresCategoria::with('categoria');

            // Filtros
            if ($request->has('categoria_id')) {
                $query->deCategoria($request->categoria_id);
            }

            if ($request->has('tipo_periodo')) {
                $query->porPeriodo($request->tipo_periodo);
            }

            if ($request->has('activo')) {
                $query->where('activo', $request->boolean('activo'));
            }

            if ($request->has('busqueda') && $request->busqueda !== '') {
                $q = $request->busqueda;
                $query->where(function ($sq) use ($q) {
                    $sq->where('nombre', 'like', "%{$q}%")
                       ->orWhere('codigo', 'like', "%{$q}%");
                });
            }

            // Ordenamiento
            $query->ordenados();

            // Paginación
            if ($request->has('page')) {
                $perPage = max(5, min(100, (int) $request->get('per_page', 10)));
                $paginated = $query->paginate($perPage);

                return response()->json([
                    'success' => true,
                    'data'    => $paginated->items(),
                    'pagination' => [
                        'total'        => $paginated->total(),
                        'per_page'     => $paginated->perPage(),
                        'current_page' => $paginated->currentPage(),
                        'last_page'    => $paginated->lastPage(),
                        'from'         => $paginated->firstItem(),
                        'to'           => $paginated->lastItem(),
                    ],
                ]);
            }

            // Sin paginación - compatiblidad con código existente
            $planes = $query->get();

            return response()->json([
                'success' => true,
                'data'    => $planes,
                'total'   => $planes->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener planes de interés',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener planes de una categoría específica
     */
    public function getPorCategoria($categoriaId)
    {
        try {
            $categoria = CategoriaProducto::findOrFail($categoriaId);

            $planes = PlanInteresCategoria::deCategoria($categoriaId)
                ->activos()
                ->ordenados()
                ->get();

            $planDefault = PlanInteresCategoria::obtenerPlanDefault($categoriaId);

            return response()->json([
                'success' => true,
                'data' => [
                    'categoria' => $categoria,
                    'planes' => $planes,
                    'plan_default' => $planDefault
                ],
                'total' => $planes->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener planes de la categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un plan específico
     */
    public function show($id)
    {
        try {
            $plan = PlanInteresCategoria::with('categoria', 'creditos')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'plan' => $plan,
                    'creditos_usando_plan' => $plan->creditos()->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Plan de interés no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Crear nuevo plan de interés
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'categoria_producto_id' => 'required|exists:categoria_productos,id',
            'nombre' => 'required|string|max:100',
            'codigo' => 'nullable|string|max:50',
            'tipo_periodo' => 'required|in:diario,semanal,quincenal,mensual',
            'plazo_numero' => 'required|integer|min:1',
            'plazo_unidad' => 'required|in:dias,semanas,quincenas,meses',
            'tasa_interes' => 'required|numeric|min:0|max:100',
            'tasa_almacenaje' => 'nullable|numeric|min:0|max:100',
            'tasa_moratorios' => 'nullable|numeric|min:0|max:100',
            'tipo_mora' => 'nullable|in:porcentaje,monto_fijo',
            'mora_monto_fijo' => 'nullable|numeric|min:0',
            'porcentaje_prestamo' => 'required|numeric|min:0|max:100',
            'monto_minimo' => 'nullable|numeric|min:0',
            'monto_maximo' => 'nullable|numeric|min:0',
            'dias_gracia' => 'nullable|integer|min:0',
            'dias_enajenacion' => 'nullable|integer|min:0',
            'cat' => 'nullable|numeric|min:0',
            'interes_anual' => 'nullable|numeric|min:0',
            'porcentaje_precio_venta' => 'nullable|numeric|min:0|max:200',
            'numero_refrendos_permitidos' => 'nullable|integer|min:0',
            'permite_refrendos' => 'boolean',
            'activo' => 'boolean',
            'es_default' => 'boolean',
            'orden' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Verificar código único si se proporciona
            if ($request->codigo) {
                $existe = PlanInteresCategoria::where('categoria_producto_id', $request->categoria_producto_id)
                    ->where('codigo', $request->codigo)
                    ->exists();

                if ($existe) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya existe un plan con ese código para esta categoría'
                    ], 422);
                }
            }

            $plan = PlanInteresCategoria::create($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Plan de interés creado exitosamente',
                'data' => $plan->load('categoria')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el plan de interés',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar plan de interés
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:100',
            'codigo' => 'nullable|string|max:50',
            'tipo_periodo' => 'sometimes|in:diario,semanal,quincenal,mensual',
            'plazo_numero' => 'sometimes|integer|min:1',
            'plazo_unidad' => 'sometimes|in:dias,semanas,quincenas,meses',
            'tasa_interes' => 'sometimes|numeric|min:0|max:100',
            'tasa_almacenaje' => 'nullable|numeric|min:0|max:100',
            'tasa_moratorios' => 'nullable|numeric|min:0|max:100',
            'tipo_mora' => 'nullable|in:porcentaje,monto_fijo',
            'mora_monto_fijo' => 'nullable|numeric|min:0',
            'porcentaje_prestamo' => 'sometimes|numeric|min:0|max:100',
            'monto_minimo' => 'nullable|numeric|min:0',
            'monto_maximo' => 'nullable|numeric|min:0',
            'dias_gracia' => 'nullable|integer|min:0',
            'dias_enajenacion' => 'nullable|integer|min:0',
            'cat' => 'nullable|numeric|min:0',
            'interes_anual' => 'nullable|numeric|min:0',
            'porcentaje_precio_venta' => 'nullable|numeric|min:0|max:200',
            'numero_refrendos_permitidos' => 'nullable|integer|min:0',
            'permite_refrendos' => 'boolean',
            'activo' => 'boolean',
            'es_default' => 'boolean',
            'orden' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $plan = PlanInteresCategoria::findOrFail($id);

            DB::beginTransaction();

            // Verificar código único si se cambia
            if ($request->has('codigo') && $request->codigo !== $plan->codigo) {
                $existe = PlanInteresCategoria::where('categoria_producto_id', $plan->categoria_producto_id)
                    ->where('codigo', $request->codigo)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($existe) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya existe un plan con ese código para esta categoría'
                    ], 422);
                }
            }

            $plan->update($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Plan de interés actualizado exitosamente',
                'data' => $plan->load('categoria')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el plan de interés',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Establecer plan como default para su categoría
     */
    public function setDefault($id)
    {
        try {
            $plan = PlanInteresCategoria::findOrFail($id);

            DB::beginTransaction();

            // Quitar default de otros planes de la misma categoría
            PlanInteresCategoria::where('categoria_producto_id', $plan->categoria_producto_id)
                ->where('id', '!=', $id)
                ->update(['es_default' => false]);

            // Establecer este como default
            $plan->update(['es_default' => true, 'activo' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Plan establecido como predeterminado',
                'data' => $plan
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al establecer plan predeterminado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activar/Desactivar plan
     */
    public function toggleActivo($id)
    {
        try {
            $plan = PlanInteresCategoria::findOrFail($id);

            $nuevoEstado = !$plan->activo;

            // Si se está desactivando y es el default, buscar otro para marcar como default
            if (!$nuevoEstado && $plan->es_default) {
                $otroActivo = PlanInteresCategoria::where('categoria_producto_id', $plan->categoria_producto_id)
                    ->where('id', '!=', $id)
                    ->where('activo', true)
                    ->first();

                if ($otroActivo) {
                    $otroActivo->update(['es_default' => true]);
                }

                $plan->update(['activo' => false, 'es_default' => false]);
            } else {
                $plan->update(['activo' => $nuevoEstado]);
            }

            return response()->json([
                'success' => true,
                'message' => $nuevoEstado ? 'Plan activado' : 'Plan desactivado',
                'data' => $plan
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado del plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar plan (soft delete)
     */
    public function destroy($id)
    {
        try {
            $plan = PlanInteresCategoria::findOrFail($id);

            // Verificar si hay créditos usando este plan
            $creditosCount = $plan->creditos()->count();

            if ($creditosCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "No se puede eliminar el plan porque tiene {$creditosCount} crédito(s) asociado(s)"
                ], 422);
            }

            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Plan de interés eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular proyección de un crédito con este plan
     */
    public function calcularProyeccion(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'monto_capital' => 'required|numeric|min:0',
            'valor_avaluo' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $plan = PlanInteresCategoria::findOrFail($id);

            $montoCapital = floatval($request->monto_capital);
            $valorAvaluo = floatval($request->valor_avaluo);

            // Validar que el monto esté en el rango permitido
            if (!$plan->validarMontoEnRango($montoCapital)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El monto no está dentro del rango permitido para este plan',
                    'rango' => [
                        'minimo' => $plan->monto_minimo,
                        'maximo' => $plan->monto_maximo
                    ]
                ], 422);
            }

            // Calcular proyección
            $interesTotal = $plan->calcularInteresTotal($montoCapital);
            $montoPrestadoSugerido = $plan->calcularMontoPrestamo($valorAvaluo);
            $fechaInicio = new \DateTime();
            $fechaVencimiento = $plan->calcularFechaVencimiento($fechaInicio);

            return response()->json([
                'success' => true,
                'data' => [
                    'plan' => $plan,
                    'proyeccion' => [
                        'monto_capital' => $montoCapital,
                        'interes_total' => $interesTotal,
                        'monto_total_pagar' => $montoCapital + $interesTotal,
                        'valor_avaluo' => $valorAvaluo,
                        'monto_prestado_sugerido' => $montoPrestadoSugerido,
                        'porcentaje_prestamo' => $plan->porcentaje_prestamo,
                        'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                        'fecha_vencimiento' => $fechaVencimiento->format('Y-m-d'),
                        'plazo_dias' => $plan->plazo_dias_total,
                        'tasa_interes_periodo' => $plan->tasa_interes,
                        'tasa_almacenaje_periodo' => $plan->tasa_almacenaje,
                        'tasa_total_periodo' => $plan->tasa_interes + $plan->tasa_almacenaje,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular proyección',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
