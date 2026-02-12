<?php

namespace App\Http\Controllers;

use App\Models\Contabilidad\CtbDiario;
use App\Models\Contabilidad\CtbMovimiento;
use App\Models\Contabilidad\CtbNomenclatura;
use App\Models\Contabilidad\CtbTipoPoliza;
use App\Models\Contabilidad\CtbBanco;
use App\Services\ContabilidadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class ContabilidadController extends Controller
{
    protected ContabilidadService $contabilidadService;

    public function __construct(ContabilidadService $contabilidadService)
    {
        $this->contabilidadService = $contabilidadService;
    }

    // ==================== NOMENCLATURA (PLAN DE CUENTAS) ====================

    /**
     * Listar plan de cuentas con estructura jerárquica
     */
    public function nomenclatura(Request $request): JsonResponse
    {
        $query = CtbNomenclatura::query()
            ->with(['padre:id,codigo_cuenta,nombre_cuenta']);

        // Filtros
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('nivel')) {
            $query->where('nivel', $request->nivel);
        }

        if ($request->filled('activas') && $request->activas === 'true') {
            $query->where('estado', true);
        }

        if ($request->filled('con_movimientos') && $request->con_movimientos === 'true') {
            $query->where('acepta_movimientos', true);
        }

        if ($request->filled('busqueda')) {
            $busqueda = $request->busqueda;
            $query->where(function($q) use ($busqueda) {
                $q->where('codigo_cuenta', 'like', "%{$busqueda}%")
                  ->orWhere('nombre_cuenta', 'like', "%{$busqueda}%");
            });
        }

        // Ordenamiento
        $query->orderBy('codigo_cuenta');

        // Paginación
        $perPage = $request->input('per_page', 50);
        $cuentas = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $cuentas->items(),
            'pagination' => [
                'total' => $cuentas->total(),
                'per_page' => $cuentas->perPage(),
                'current_page' => $cuentas->currentPage(),
                'last_page' => $cuentas->lastPage(),
            ]
        ]);
    }

    /**
     * Obtener estructura jerárquica del plan de cuentas (árbol)
     */
    public function nomenclaturaArbol(): JsonResponse
    {
        $cuentas = CtbNomenclatura::where('estado', true)
            ->whereNull('cuenta_padre_id')
            ->with(['hijos' => function($q) {
                $q->where('estado', true)
                  ->with(['hijos' => function($q2) {
                      $q2->where('estado', true);
                  }])
                  ->orderBy('codigo_cuenta');
            }])
            ->orderBy('codigo_cuenta')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cuentas
        ]);
    }

    /**
     * Crear nueva cuenta contable
     */
    public function crearCuenta(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo_cuenta' => 'required|string|max:20|unique:ctb_nomenclatura,codigo_cuenta',
            'nombre_cuenta' => 'required|string|max:255',
            'tipo' => 'required|in:activo,pasivo,patrimonio,ingreso,gasto,costos,cuentas_orden',
            'naturaleza' => 'required|in:deudora,acreedora',
            'nivel' => 'required|integer|min:1|max:5',
            'cuenta_padre_id' => 'nullable|exists:ctb_nomenclatura,id',
            'acepta_movimientos' => 'boolean',
            'requiere_auxiliar' => 'boolean',
            'categoria_flujo' => 'in:operacion,inversion,financiamiento,ninguno',
        ]);

        $cuenta = CtbNomenclatura::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cuenta contable creada exitosamente',
            'data' => $cuenta
        ], 201);
    }

    /**
     * Actualizar cuenta contable
     */
    public function actualizarCuenta(Request $request, int $id): JsonResponse
    {
        $cuenta = CtbNomenclatura::findOrFail($id);

        $validated = $request->validate([
            'codigo_cuenta' => 'string|max:20|unique:ctb_nomenclatura,codigo_cuenta,' . $id,
            'nombre_cuenta' => 'string|max:255',
            'tipo' => 'in:activo,pasivo,patrimonio,ingreso,gasto,costos,cuentas_orden',
            'naturaleza' => 'in:deudora,acreedora',
            'nivel' => 'integer|min:1|max:5',
            'cuenta_padre_id' => 'nullable|exists:ctb_nomenclatura,id',
            'acepta_movimientos' => 'boolean',
            'requiere_auxiliar' => 'boolean',
            'categoria_flujo' => 'in:operacion,inversion,financiamiento,ninguno',
            'estado' => 'boolean',
        ]);

        $cuenta->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cuenta contable actualizada',
            'data' => $cuenta
        ]);
    }

    // ==================== TIPOS DE PÓLIZA ====================

    /**
     * Listar tipos de póliza
     */
    public function tiposPoliza(): JsonResponse
    {
        $tipos = CtbTipoPoliza::where('activo', true)
            ->orderBy('codigo')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tipos
        ]);
    }

    // ==================== LIBRO DIARIO ====================

    /**
     * Listar asientos contables (libro diario)
     */
    public function diario(Request $request): JsonResponse
    {
        $query = CtbDiario::query()
            ->with([
                'tipoPoliza:id,codigo,nombre',
                'sucursal:id,nombre',
                'usuario:id,name',
                'movimientos:id,diario_id,cuenta_contable_id,debe,haber',
                'movimientos.cuentaContable:id,codigo_cuenta,nombre_cuenta'
            ]);

        // Filtros de fecha
        if ($request->filled('fecha_inicio')) {
            $query->where('fecha_contabilizacion', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->where('fecha_contabilizacion', '<=', $request->fecha_fin);
        }

        // Filtros específicos
        if ($request->filled('tipo_poliza_id')) {
            $query->where('tipo_poliza_id', $request->tipo_poliza_id);
        }

        if ($request->filled('tipo_origen')) {
            $query->where('tipo_origen', $request->tipo_origen);
        }

        if ($request->filled('sucursal_id')) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('busqueda')) {
            $busqueda = $request->busqueda;
            $query->where(function($q) use ($busqueda) {
                $q->where('numero_comprobante', 'like', "%{$busqueda}%")
                  ->orWhere('numero_documento', 'like', "%{$busqueda}%")
                  ->orWhere('glosa', 'like', "%{$busqueda}%");
            });
        }

        // Ordenamiento
        $query->orderBy('fecha_contabilizacion', 'desc')
              ->orderBy('numero_comprobante', 'desc');

        // Paginación
        $perPage = $request->input('per_page', 20);
        $asientos = $query->paginate($perPage);

        // Calcular totales
        $totales = $asientos->getCollection()->map(function($asiento) {
            $totalDebe = $asiento->movimientos->sum('debe');
            $totalHaber = $asiento->movimientos->sum('haber');
            $asiento->total_debe = $totalDebe;
            $asiento->total_haber = $totalHaber;
            $asiento->cuadrado = abs($totalDebe - $totalHaber) < 0.01;
            return $asiento;
        });

        return response()->json([
            'success' => true,
            'data' => $totales,
            'pagination' => [
                'total' => $asientos->total(),
                'per_page' => $asientos->perPage(),
                'current_page' => $asientos->currentPage(),
                'last_page' => $asientos->lastPage(),
            ]
        ]);
    }

    /**
     * Ver detalle de un asiento contable
     */
    public function verAsiento(int $id): JsonResponse
    {
        $asiento = CtbDiario::with([
            'tipoPoliza',
            'moneda',
            'sucursal',
            'usuario',
            'creditoPrendario',
            'movimientoCredito',
            'venta',
            'caja',
            'aprobadoPor',
            'anuladoPor',
            'movimientos' => function($q) {
                $q->with(['cuentaContable', 'cliente', 'bancoCTB']);
            }
        ])->findOrFail($id);

        // Calcular totales
        $asiento->total_debe = $asiento->movimientos->sum('debe');
        $asiento->total_haber = $asiento->movimientos->sum('haber');
        $asiento->cuadrado = abs($asiento->total_debe - $asiento->total_haber) < 0.01;

        return response()->json([
            'success' => true,
            'data' => $asiento
        ]);
    }

    // ==================== BALANCES Y MAYORES ====================

    /**
     * Balance de comprobación (sumas y saldos)
     */
    public function balanceComprobacion(Request $request): JsonResponse
    {
        $fechaInicio = $request->input('fecha_inicio', date('Y-01-01'));
        $fechaFin = $request->input('fecha_fin', date('Y-m-d'));
        $sucursalId = $request->input('sucursal_id');

        $query = DB::table('ctb_movimientos as m')
            ->join('ctb_diario as d', 'm.diario_id', '=', 'd.id')
            ->join('ctb_nomenclatura as n', 'm.cuenta_contable_id', '=', 'n.id')
            ->select(
                'n.id',
                'n.codigo_cuenta',
                'n.nombre_cuenta',
                'n.tipo',
                'n.naturaleza',
                DB::raw('SUM(m.debe) as total_debe'),
                DB::raw('SUM(m.haber) as total_haber'),
                DB::raw('SUM(m.debe) - SUM(m.haber) as saldo')
            )
            ->where('d.estado', '!=', 'anulado')
            ->whereBetween('d.fecha_contabilizacion', [$fechaInicio, $fechaFin]);

        // Filtro por sucursal
        if ($sucursalId) {
            $query->where('d.sucursal_id', $sucursalId);
        }

        $balance = $query
            ->groupBy('n.id', 'n.codigo_cuenta', 'n.nombre_cuenta', 'n.tipo', 'n.naturaleza')
            ->orderBy('n.codigo_cuenta')
            ->get();

        // Calcular totales generales
        $totalDebe = $balance->sum('total_debe');
        $totalHaber = $balance->sum('total_haber');
        $diferencia = abs($totalDebe - $totalHaber);

        return response()->json([
            'success' => true,
            'data' => [
                'cuentas' => $balance,
                'totales' => [
                    'total_debe' => $totalDebe,
                    'total_haber' => $totalHaber,
                    'diferencia' => $diferencia,
                    'cuadrado' => $diferencia < 0.01
                ],
                'periodo' => [
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin
                ],
                'sucursal_id' => $sucursalId
            ]
        ]);
    }

    /**
     * Libro mayor de una cuenta
     */
    public function libroMayor(Request $request): JsonResponse
    {
        $request->validate([
            'cuenta_id' => 'required|exists:ctb_nomenclatura,id'
        ]);

        $fechaInicio = $request->input('fecha_inicio', date('Y-01-01'));
        $fechaFin = $request->input('fecha_fin', date('Y-m-d'));
        $sucursalId = $request->input('sucursal_id');

        $cuenta = CtbNomenclatura::findOrFail($request->cuenta_id);

        // Saldo inicial (antes del período)
        $querySaldoInicial = DB::table('ctb_movimientos as m')
            ->join('ctb_diario as d', 'm.diario_id', '=', 'd.id')
            ->where('m.cuenta_contable_id', $request->cuenta_id)
            ->where('d.estado', '!=', 'anulado')
            ->where('d.fecha_contabilizacion', '<', $fechaInicio);

        if ($sucursalId) {
            $querySaldoInicial->where('d.sucursal_id', $sucursalId);
        }

        $saldoInicial = $querySaldoInicial
            ->select(DB::raw('COALESCE(SUM(m.debe), 0) - COALESCE(SUM(m.haber), 0) as saldo'))
            ->first()->saldo ?? 0;

        // Movimientos del período
        $queryMovimientos = DB::table('ctb_movimientos as m')
            ->join('ctb_diario as d', 'm.diario_id', '=', 'd.id')
            ->where('m.cuenta_contable_id', $request->cuenta_id)
            ->where('d.estado', '!=', 'anulado')
            ->whereBetween('d.fecha_contabilizacion', [$fechaInicio, $fechaFin]);

        if ($sucursalId) {
            $queryMovimientos->where('d.sucursal_id', $sucursalId);
        }

        $movimientos = $queryMovimientos
            ->select(
                'd.fecha_contabilizacion as fecha',
                'd.numero_comprobante',
                'd.numero_documento',
                'd.glosa',
                'm.detalle',
                'm.debe',
                'm.haber'
            )
            ->orderBy('d.fecha_contabilizacion')
            ->orderBy('d.numero_comprobante')
            ->get();

        // Calcular saldo acumulado
        $saldoActual = $saldoInicial;
        $movimientosConSaldo = $movimientos->map(function($mov) use (&$saldoActual) {
            $saldoActual += $mov->debe - $mov->haber;
            $mov->saldo = $saldoActual;
            return $mov;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'cuenta' => $cuenta,
                'saldo_inicial' => $saldoInicial,
                'movimientos' => $movimientosConSaldo,
                'saldo_final' => $saldoActual,
                'totales' => [
                    'total_debe' => $movimientos->sum('debe'),
                    'total_haber' => $movimientos->sum('haber')
                ],
                'periodo' => [
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin
                ]
            ]
        ]);
    }

    // ==================== REPORTES PDF ====================

    /**
     * Generar PDF del libro diario
     */
    public function libroDiarioPdf(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', date('Y-m-01'));
        $fechaFin = $request->input('fecha_fin', date('Y-m-d'));
        $sucursalId = $request->input('sucursal_id');

        $query = CtbDiario::with([
            'tipoPoliza:id,codigo,nombre',
            'sucursal:id,nombre',
            'movimientos.cuentaContable:id,codigo_cuenta,nombre_cuenta'
        ])
        ->where('estado', '!=', 'anulado')
        ->whereBetween('fecha_contabilizacion', [$fechaInicio, $fechaFin]);

        if ($sucursalId) {
            $query->where('sucursal_id', $sucursalId);
        }

        $asientos = $query
            ->orderBy('fecha_contabilizacion')
            ->orderBy('numero_comprobante')
            ->get();

        // Calcular totales por asiento
        $asientos->transform(function($asiento) {
            $asiento->total_debe = $asiento->movimientos->sum('debe');
            $asiento->total_haber = $asiento->movimientos->sum('haber');
            return $asiento;
        });

        $totalGeneral = [
            'debe' => $asientos->sum('total_debe'),
            'haber' => $asientos->sum('total_haber')
        ];

        $pdf = Pdf::loadView('reportes.contabilidad.libro-diario', [
            'asientos' => $asientos,
            'totalGeneral' => $totalGeneral,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'generado_por' => Auth::user()->name ?? 'Sistema',
            'fecha_generacion' => now()->format('d/m/Y H:i')
        ]);

        return $pdf->download('libro-diario-' . $fechaInicio . '-' . $fechaFin . '.pdf');
    }

    /**
     * Generar PDF del balance de comprobación
     */
    public function balanceComprobacionPdf(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', date('Y-01-01'));
        $fechaFin = $request->input('fecha_fin', date('Y-m-d'));
        $sucursalId = $request->input('sucursal_id');

        $query = DB::table('ctb_movimientos as m')
            ->join('ctb_diario as d', 'm.diario_id', '=', 'd.id')
            ->join('ctb_nomenclatura as n', 'm.cuenta_contable_id', '=', 'n.id')
            ->select(
                'n.codigo_cuenta',
                'n.nombre_cuenta',
                'n.tipo',
                'n.naturaleza',
                DB::raw('SUM(m.debe) as total_debe'),
                DB::raw('SUM(m.haber) as total_haber'),
                DB::raw('SUM(m.debe) - SUM(m.haber) as saldo')
            )
            ->where('d.estado', '!=', 'anulado')
            ->whereBetween('d.fecha_contabilizacion', [$fechaInicio, $fechaFin]);

        if ($sucursalId) {
            $query->where('d.sucursal_id', $sucursalId);
        }

        $balance = $query
            ->groupBy('n.codigo_cuenta', 'n.nombre_cuenta', 'n.tipo', 'n.naturaleza')
            ->orderBy('n.codigo_cuenta')
            ->get();

        $totales = [
            'debe' => $balance->sum('total_debe'),
            'haber' => $balance->sum('total_haber'),
            'diferencia' => abs($balance->sum('total_debe') - $balance->sum('total_haber'))
        ];

        $pdf = Pdf::loadView('reportes.contabilidad.balance-comprobacion', [
            'cuentas' => $balance,
            'totales' => $totales,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'generado_por' => Auth::user()->name ?? 'Sistema',
            'fecha_generacion' => now()->format('d/m/Y H:i')
        ]);

        return $pdf->download('balance-comprobacion-' . $fechaInicio . '-' . $fechaFin . '.pdf');
    }

    /**
     * Generar PDF del libro mayor
     */
    public function libroMayorPdf(Request $request)
    {
        $request->validate([
            'cuenta_id' => 'required|exists:ctb_nomenclatura,id'
        ]);

        $fechaInicio = $request->input('fecha_inicio', date('Y-01-01'));
        $fechaFin = $request->input('fecha_fin', date('Y-m-d'));
        $sucursalId = $request->input('sucursal_id');

        $cuenta = CtbNomenclatura::findOrFail($request->cuenta_id);

        // Saldo inicial
        $querySaldo = DB::table('ctb_movimientos as m')
            ->join('ctb_diario as d', 'm.diario_id', '=', 'd.id')
            ->where('m.cuenta_contable_id', $request->cuenta_id)
            ->where('d.estado', '!=', 'anulado')
            ->where('d.fecha_contabilizacion', '<', $fechaInicio);

        if ($sucursalId) {
            $querySaldo->where('d.sucursal_id', $sucursalId);
        }

        $saldoInicial = $querySaldo
            ->select(DB::raw('COALESCE(SUM(m.debe), 0) - COALESCE(SUM(m.haber), 0) as saldo'))
            ->first()->saldo ?? 0;

        // Movimientos del período
        $queryMov = DB::table('ctb_movimientos as m')
            ->join('ctb_diario as d', 'm.diario_id', '=', 'd.id')
            ->where('m.cuenta_contable_id', $request->cuenta_id)
            ->where('d.estado', '!=', 'anulado')
            ->whereBetween('d.fecha_contabilizacion', [$fechaInicio, $fechaFin]);

        if ($sucursalId) {
            $queryMov->where('d.sucursal_id', $sucursalId);
        }

        $movimientos = $queryMov
            ->select(
                'd.fecha_contabilizacion as fecha',
                'd.numero_comprobante',
                'd.numero_documento',
                'd.glosa',
                'm.detalle',
                'm.debe',
                'm.haber'
            )
            ->orderBy('d.fecha_contabilizacion')
            ->orderBy('d.numero_comprobante')
            ->get();

        // Calcular saldo acumulado
        $saldoActual = $saldoInicial;
        $movimientosConSaldo = $movimientos->map(function($mov) use (&$saldoActual) {
            $saldoActual += $mov->debe - $mov->haber;
            $mov->saldo = $saldoActual;
            return $mov;
        });

        $pdf = Pdf::loadView('reportes.contabilidad.libro-mayor', [
            'cuenta' => $cuenta,
            'saldoInicial' => $saldoInicial,
            'movimientos' => $movimientosConSaldo,
            'saldoFinal' => $saldoActual,
            'totales' => [
                'debe' => $movimientos->sum('debe'),
                'haber' => $movimientos->sum('haber')
            ],
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'generado_por' => Auth::user()->name ?? 'Sistema',
            'fecha_generacion' => now()->format('d/m/Y H:i')
        ]);

        return $pdf->download('libro-mayor-' . $cuenta->codigo_cuenta . '-' . $fechaFin . '.pdf');
    }

    // ==================== DASHBOARD CONTABLE ====================

    /**
     * Resumen contable para dashboard
     */
    public function dashboard(): JsonResponse
    {
        $inicioMes = date('Y-m-01');
        $hoy = date('Y-m-d');

        // Totales del mes
        $totalesMes = DB::table('ctb_movimientos as m')
            ->join('ctb_diario as d', 'm.diario_id', '=', 'd.id')
            ->where('d.estado', '!=', 'anulado')
            ->whereBetween('d.fecha_contabilizacion', [$inicioMes, $hoy])
            ->select(
                DB::raw('SUM(m.debe) as total_debe'),
                DB::raw('SUM(m.haber) as total_haber'),
                DB::raw('COUNT(DISTINCT d.id) as total_asientos')
            )
            ->first();

        // Asientos por tipo de origen
        $porTipoOrigen = CtbDiario::where('estado', '!=', 'anulado')
            ->whereBetween('fecha_contabilizacion', [$inicioMes, $hoy])
            ->select('tipo_origen', DB::raw('COUNT(*) as cantidad'))
            ->groupBy('tipo_origen')
            ->get();

        // Últimos asientos
        $ultimosAsientos = CtbDiario::with(['tipoPoliza:id,codigo,nombre'])
            ->where('estado', '!=', 'anulado')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'totales_mes' => [
                    'total_debe' => $totalesMes->total_debe ?? 0,
                    'total_haber' => $totalesMes->total_haber ?? 0,
                    'total_asientos' => $totalesMes->total_asientos ?? 0,
                    'cuadrado' => abs(($totalesMes->total_debe ?? 0) - ($totalesMes->total_haber ?? 0)) < 0.01
                ],
                'por_tipo_origen' => $porTipoOrigen,
                'ultimos_asientos' => $ultimosAsientos,
                'periodo' => [
                    'inicio' => $inicioMes,
                    'fin' => $hoy
                ]
            ]
        ]);
    }
}
