<?php

namespace App\Http\Controllers;

use App\Models\Contabilidad\CtbDiario;
use App\Models\Contabilidad\CtbMovimiento;
use App\Models\Contabilidad\CtbTipoPoliza;
use App\Models\Moneda;
use App\Services\ContabilidadAutomaticaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\PdfDocumentoService;

class DiarioContableController extends Controller
{
    protected $contabilidadService;

    public function __construct(ContabilidadAutomaticaService $contabilidadService)
    {
        $this->contabilidadService = $contabilidadService;
    }

    /**
     * Listar asientos contables (partidas)
     */
    public function index(Request $request): JsonResponse
    {
        $query = CtbDiario::with([
            'tipoPoliza',
            'sucursal',
            'usuario',
            'movimientos.cuentaContable'
        ]);

        // Filtros de fecha
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_contabilizacion', '>=', $request->fecha_desde);
        } elseif ($request->filled('fecha_inicio')) {
            $query->where('fecha_contabilizacion', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_contabilizacion', '<=', $request->fecha_hasta);
        } elseif ($request->filled('fecha_fin')) {
            $query->where('fecha_contabilizacion', '<=', $request->fecha_fin);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('tipo_poliza_id')) {
            $query->where('tipo_poliza_id', $request->tipo_poliza_id);
        }

        if ($request->filled('tipo_origen')) {
            $query->where('tipo_origen', $request->tipo_origen);
        }

        if ($request->filled('sucursal_id')) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        if ($request->filled('busqueda')) {
            $busqueda = trim($request->busqueda);
            $query->where(function ($q) use ($busqueda) {
                $q->where('numero_comprobante', 'like', "%{$busqueda}%")
                  ->orWhere('numero_documento', 'like', "%{$busqueda}%")
                  ->orWhere('glosa', 'like', "%{$busqueda}%");
            });
        } elseif ($request->filled('numero_comprobante')) {
            $query->where('numero_comprobante', 'like', '%' . $request->numero_comprobante . '%');
        }

        $perPage = (int) ($request->per_page ?? 20);
        $asientos = $query->orderBy('fecha_contabilizacion', 'desc')
            ->orderBy('numero_comprobante', 'desc')
            ->paginate($perPage);

        // Formatear asientos calculando totales y cuadre en tiempo real
        $items = collect($asientos->items())->map(function ($asiento) {
            $totalDebe = round((float) $asiento->movimientos->sum('debe'), 2);
            $totalHaber = round((float) $asiento->movimientos->sum('haber'), 2);
            $asiento->total_debe = $totalDebe;
            $asiento->total_haber = $totalHaber;
            $asiento->cuadrado = abs($totalDebe - $totalHaber) < 0.01;
            return $asiento;
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $asientos->total(),
                'per_page' => $asientos->perPage(),
                'current_page' => $asientos->currentPage(),
                'last_page' => $asientos->lastPage(),
            ],
        ]);
    }

    /**
     * Ver detalle de un asiento
     */
    public function show(int $id): JsonResponse
    {
        $asiento = CtbDiario::with([
            'tipoPoliza',
            'sucursal',
            'usuario',
            'usuarioAprobador',
            'usuarioAnulador',
            'movimientos.cuentaContable',
            'creditoPrendario',
            'venta',
            'compra'
        ])->findOrFail($id);

        $totalDebe = round((float) $asiento->movimientos->sum('debe'), 2);
        $totalHaber = round((float) $asiento->movimientos->sum('haber'), 2);
        $asiento->total_debe = $totalDebe;
        $asiento->total_haber = $totalHaber;
        $asiento->cuadrado = abs($totalDebe - $totalHaber) < 0.01;

        return response()->json([
            'success' => true,
            'data' => $asiento,
        ]);
    }

    /**
     * Crear una nueva partida contable manual
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if ($user && !$user->hasPermission('contabilidad', 'crear_partida') && !$user->hasRole(['superadmin', 'administrador', 'contador'])) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para crear partidas contables',
            ], 403);
        }

        $validated = $request->validate([
            'tipo_poliza_id' => 'required|exists:ctb_tipo_poliza,id',
            'fecha_contabilizacion' => 'required|date',
            'glosa' => 'required|string|max:500',
            'numero_documento' => 'nullable|string|max:50',
            'sucursal_id' => 'nullable|exists:sucursales,id',
            'movimientos' => 'required|array|min:2',
            'movimientos.*.cuenta_contable_id' => 'required|exists:ctb_nomenclatura,id',
            'movimientos.*.debe' => 'required|numeric|min:0',
            'movimientos.*.haber' => 'required|numeric|min:0',
            'movimientos.*.detalle' => 'nullable|string|max:255',
        ], [
            'movimientos.min' => 'Una partida contable debe tener al menos dos líneas (partida doble).',
            'movimientos.*.cuenta_contable_id.required' => 'Cada línea debe tener una cuenta contable seleccionada.',
        ]);

        // Validar cuadre exacto de partida doble (SUM(Debe) == SUM(Haber))
        $totalDebe = round((float) collect($validated['movimientos'])->sum('debe'), 2);
        $totalHaber = round((float) collect($validated['movimientos'])->sum('haber'), 2);
        $diferencia = abs($totalDebe - $totalHaber);

        if ($diferencia >= 0.01) {
            return response()->json([
                'success' => false,
                'message' => "La partida contable no cuadra: Total Debe (Q" . number_format($totalDebe, 2) . ") ≠ Total Haber (Q" . number_format($totalHaber, 2) . "). Descuadre de Q" . number_format($diferencia, 2),
                'totales' => [
                    'total_debe' => $totalDebe,
                    'total_haber' => $totalHaber,
                    'diferencia' => $diferencia,
                ]
            ], 422);
        }

        if ($totalDebe <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'El total de la partida debe ser mayor a cero.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tipoPoliza = CtbTipoPoliza::findOrFail($validated['tipo_poliza_id']);
            $codigoPoliza = $tipoPoliza->codigo ?? 'PD';
            $sucursalId = $validated['sucursal_id'] ?? ($user?->sucursal_id ?? null);
            $numeroComprobante = $this->contabilidadService->generarNumeroComprobante($codigoPoliza, $sucursalId);
            $monedaId = Moneda::where('codigo', config('contabilidad.moneda_defecto', 'GTQ'))->first()?->id ?? 1;

            $diario = CtbDiario::create([
                'numero_comprobante' => $numeroComprobante,
                'tipo_poliza_id' => $validated['tipo_poliza_id'],
                'moneda_id' => $monedaId,
                'tipo_origen' => 'otro',
                'numero_documento' => $validated['numero_documento'] ?? $numeroComprobante,
                'glosa' => $validated['glosa'],
                'fecha_documento' => $validated['fecha_contabilizacion'],
                'fecha_contabilizacion' => $validated['fecha_contabilizacion'],
                'sucursal_id' => $sucursalId,
                'usuario_id' => $user?->id ?? 1,
                'estado' => 'registrado',
                'editable' => true,
            ]);

            foreach ($validated['movimientos'] as $mov) {
                $debe = round((float) ($mov['debe'] ?? 0), 2);
                $haber = round((float) ($mov['haber'] ?? 0), 2);

                if ($debe <= 0 && $haber <= 0) {
                    continue;
                }

                CtbMovimiento::create([
                    'diario_id' => $diario->id,
                    'cuenta_contable_id' => $mov['cuenta_contable_id'],
                    'debe' => $debe,
                    'haber' => $haber,
                    'numero_comprobante' => $numeroComprobante,
                    'detalle' => $mov['detalle'] ?? $validated['glosa'],
                ]);
            }

            DB::commit();

            $asientoCargado = $diario->fresh(['tipoPoliza', 'sucursal', 'usuario', 'movimientos.cuentaContable']);
            $asientoCargado->total_debe = $totalDebe;
            $asientoCargado->total_haber = $totalHaber;
            $asientoCargado->cuadrado = true;

            return response()->json([
                'success' => true,
                'message' => 'Partida contable registrada exitosamente',
                'data' => $asientoCargado,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al registrar partida manual: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la partida contable: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar una partida contable existente
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if ($user && !$user->hasPermission('contabilidad', 'editar_partida') && !$user->hasRole(['superadmin', 'administrador', 'contador'])) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para modificar partidas contables',
            ], 403);
        }

        $diario = CtbDiario::findOrFail($id);

        if ($diario->estado === 'anulado') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede editar una partida que ya está anulada',
            ], 422);
        }

        $validated = $request->validate([
            'tipo_poliza_id' => 'sometimes|required|exists:ctb_tipo_poliza,id',
            'fecha_contabilizacion' => 'sometimes|required|date',
            'glosa' => 'sometimes|required|string|max:500',
            'numero_documento' => 'nullable|string|max:50',
            'sucursal_id' => 'nullable|exists:sucursales,id',
            'movimientos' => 'sometimes|required|array|min:2',
            'movimientos.*.cuenta_contable_id' => 'required|exists:ctb_nomenclatura,id',
            'movimientos.*.debe' => 'required|numeric|min:0',
            'movimientos.*.haber' => 'required|numeric|min:0',
            'movimientos.*.detalle' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Si se envían movimientos, validar cuadre estricto
            if (isset($validated['movimientos'])) {
                $totalDebe = round((float) collect($validated['movimientos'])->sum('debe'), 2);
                $totalHaber = round((float) collect($validated['movimientos'])->sum('haber'), 2);
                $diferencia = abs($totalDebe - $totalHaber);

                if ($diferencia >= 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => "La partida contable no cuadra: Total Debe (Q" . number_format($totalDebe, 2) . ") ≠ Total Haber (Q" . number_format($totalHaber, 2) . "). Descuadre de Q" . number_format($diferencia, 2),
                    ], 422);
                }

                // Reemplazar movimientos
                $diario->movimientos()->delete();

                foreach ($validated['movimientos'] as $mov) {
                    $debe = round((float) ($mov['debe'] ?? 0), 2);
                    $haber = round((float) ($mov['haber'] ?? 0), 2);

                    if ($debe <= 0 && $haber <= 0) {
                        continue;
                    }

                    CtbMovimiento::create([
                        'diario_id' => $diario->id,
                        'cuenta_contable_id' => $mov['cuenta_contable_id'],
                        'debe' => $debe,
                        'haber' => $haber,
                        'numero_comprobante' => $diario->numero_comprobante,
                        'detalle' => $mov['detalle'] ?? ($validated['glosa'] ?? $diario->glosa),
                    ]);
                }
            }

            // Actualizar encabezado
            $diario->update(array_filter([
                'tipo_poliza_id' => $validated['tipo_poliza_id'] ?? null,
                'fecha_contabilizacion' => $validated['fecha_contabilizacion'] ?? null,
                'fecha_documento' => $validated['fecha_contabilizacion'] ?? null,
                'glosa' => $validated['glosa'] ?? null,
                'numero_documento' => $validated['numero_documento'] ?? null,
                'sucursal_id' => $validated['sucursal_id'] ?? null,
            ]));

            DB::commit();

            $asientoActualizado = $diario->fresh(['tipoPoliza', 'sucursal', 'usuario', 'movimientos.cuentaContable']);
            $totalDebe = round((float) $asientoActualizado->movimientos->sum('debe'), 2);
            $totalHaber = round((float) $asientoActualizado->movimientos->sum('haber'), 2);
            $asientoActualizado->total_debe = $totalDebe;
            $asientoActualizado->total_haber = $totalHaber;
            $asientoActualizado->cuadrado = abs($totalDebe - $totalHaber) < 0.01;

            return response()->json([
                'success' => true,
                'message' => 'Partida contable modificada exitosamente',
                'data' => $asientoActualizado,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar partida: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la partida: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar una partida contable
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        if ($user && !$user->hasPermission('contabilidad', 'eliminar_partida') && !$user->hasRole(['superadmin', 'administrador', 'contador'])) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar partidas contables',
            ], 403);
        }

        $diario = CtbDiario::findOrFail($id);

        try {
            DB::beginTransaction();

            $diario->movimientos()->delete();
            $diario->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Partida contable eliminada exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar partida: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la partida: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Anular asiento
     */
    public function anular(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if ($user && !$user->hasPermission('contabilidad', 'eliminar_partida') && !$user->hasRole(['superadmin', 'administrador', 'contador'])) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para anular partidas contables',
            ], 403);
        }

        $validated = $request->validate([
            'motivo' => 'required|string|min:5',
        ]);

        $exito = $this->contabilidadService->anularAsiento(
            $id,
            $validated['motivo'],
            Auth::id()
        );

        if (!$exito) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo anular la partida contable',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Partida contable anulada exitosamente',
        ]);
    }

    /**
     * Registrar asiento manualmente desde plantilla/operación
     */
    public function registrarManual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_operacion' => 'required|string',
            'datos' => 'required|array',
            'datos.sucursal_id' => 'required|exists:sucursales,id',
            'datos.glosa' => 'required|string',
            'datos.fecha_documento' => 'required|date',
            'datos.numero_documento' => 'required|string',
        ]);

        $asiento = $this->contabilidadService->registrarAsiento(
            $validated['tipo_operacion'],
            array_merge($validated['datos'], ['usuario_id' => Auth::id()])
        );

        if (!$asiento) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo registrar el asiento contable',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Asiento contable registrado exitosamente',
            'data' => $asiento,
        ], 201);
    }

    /**
     * Obtener estadísticas contables
     */
    public function estadisticas(Request $request): JsonResponse
    {
        $fechaDesde = $request->fecha_desde ?? now()->startOfMonth();
        $fechaHasta = $request->fecha_hasta ?? now();

        $stats = [
            'total_asientos' => CtbDiario::whereBetween('fecha_contabilizacion', [$fechaDesde, $fechaHasta])
                ->where('estado', 'registrado')
                ->count(),
            'por_tipo_origen' => CtbDiario::whereBetween('fecha_contabilizacion', [$fechaDesde, $fechaHasta])
                ->where('estado', 'registrado')
                ->selectRaw('tipo_origen, COUNT(*) as total')
                ->groupBy('tipo_origen')
                ->get(),
            'asientos_por_dia' => CtbDiario::whereBetween('fecha_contabilizacion', [$fechaDesde, $fechaHasta])
                ->where('estado', 'registrado')
                ->selectRaw('DATE(fecha_contabilizacion) as fecha, COUNT(*) as total')
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Descargar comprobante PDF de una partida contable individual
     */
    public function descargarPdf(int $id)
    {
        $asiento = CtbDiario::with([
            'tipoPoliza:id,codigo,nombre',
            'sucursal:id,nombre',
            'usuario:id,name',
            'movimientos.cuentaContable:id,codigo_cuenta,nombre_cuenta'
        ])->findOrFail($id);

        $datosPlantilla = PdfDocumentoService::obtenerDatosPlantilla(
            'partida_contable',
            $asiento->sucursal_id,
            [
                'asiento' => $asiento,
                'fecha_generacion' => now()->format('d/m/Y H:i'),
            ]
        );

        $vista = PdfDocumentoService::resolverVista(
            'partida_contable',
            'reportes.contabilidad.partida-contable',
            $asiento->sucursal_id
        );

        $pdf = Pdf::loadView($vista, $datosPlantilla)->setPaper('letter', 'portrait');

        $cleanComprobante = preg_replace('/[^A-Za-z0-9\-_]/', '-', $asiento->numero_comprobante);
        $filename = 'partida-' . $cleanComprobante . '.pdf';

        return $pdf->download($filename);
    }
}
