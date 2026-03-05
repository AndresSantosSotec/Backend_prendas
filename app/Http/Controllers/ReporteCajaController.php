<?php

namespace App\Http\Controllers;

use App\Models\CajaAperturaCierre;
use App\Models\MovimientoCaja;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ReporteCajaController extends Controller
{
    /**
     * Obtener reporte de movimientos de caja (Excel/JSON)
     */
    public function reporteMovimientos(Request $request)
    {
        $request->validate([
            'caja_id' => 'nullable|exists:caja_apertura_cierres,id',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
        ]);

        $query = MovimientoCaja::with(['user', 'caja']);

        if ($request->caja_id) {
            $query->where('caja_id', $request->caja_id);
        }

        if ($request->fecha_inicio) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }

        if ($request->fecha_fin) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }

        $movimientos = $query->orderBy('created_at', 'desc')->get();

        $totalIngresos = $movimientos->whereIn('tipo', ['incremento', 'ingreso_pago'])->sum('monto');
        $totalEgresos = $movimientos->whereIn('tipo', ['decremento', 'egreso_desembolso'])->sum('monto');

        return response()->json([
            'movimientos' => $movimientos,
            'resumen' => [
                'total_movimientos' => $movimientos->count(),
                'total_ingresos' => $totalIngresos,
                'total_egresos' => $totalEgresos,
                'saldo_neto' => $totalIngresos - $totalEgresos,
            ],
        ]);
    }

    /**
     * Obtener consolidado de cajas (solo administradores)
     * Soporta filtros: fecha_inicio/fecha_fin, user_ids[], caja_ids[], incluir_movimientos
     */
    public function consolidado(Request $request)
    {
        // Verificar que el usuario sea administrador o superadmin
        if (!in_array(Auth::user()->rol, ['administrador', 'superadmin'])) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'caja_ids' => 'nullable|array',
            'caja_ids.*' => 'integer|exists:caja_apertura_cierres,id',
            'incluir_movimientos' => 'nullable|boolean',
        ]);

        $relations = ['user', 'sucursal'];
        if ($request->boolean('incluir_movimientos', false)) {
            $relations[] = 'movimientos';
            $relations[] = 'movimientos.user';
        }

        $query = CajaAperturaCierre::with($relations);

        // Soportar ambos formatos de parámetros de fecha
        $fechaInicio = $request->fecha_inicio ?? $request->fecha_desde;
        $fechaFin = $request->fecha_fin ?? $request->fecha_hasta;

        if ($fechaInicio) {
            $query->whereDate('fecha_apertura', '>=', $fechaInicio);
        }
        if ($fechaFin) {
            $query->whereDate('fecha_apertura', '<=', $fechaFin);
        }

        // Filtro por usuarios específicos
        if ($request->has('user_ids') && is_array($request->user_ids) && count($request->user_ids) > 0) {
            $query->whereIn('user_id', $request->user_ids);
        }

        // Filtro por cajas específicas
        if ($request->has('caja_ids') && is_array($request->caja_ids) && count($request->caja_ids) > 0) {
            $query->whereIn('id', $request->caja_ids);
        }

        $cajas = $query->orderBy('fecha_apertura', 'desc')->get();

        // Calcular totales de movimientos por cada caja
        $cajasConTotales = $cajas->map(function ($caja) {
            $movimientos = $caja->movimientos ?? collect();
            $totalIngresos = $movimientos->whereIn('tipo', ['incremento', 'ingreso_pago'])->sum('monto');
            $totalEgresos = $movimientos->whereIn('tipo', ['decremento', 'egreso_desembolso'])->sum('monto');
            $caja->total_ingresos = $totalIngresos;
            $caja->total_egresos = $totalEgresos;
            $caja->total_esperado = $caja->saldo_inicial + $totalIngresos - $totalEgresos;
            $caja->total_movimientos = $movimientos->count();
            return $caja;
        });

        $estadisticas = [
            'total_cajas' => $cajas->count(),
            'cajas_abiertas' => $cajas->where('estado', 'abierta')->count(),
            'cajas_cerradas' => $cajas->where('estado', 'cerrada')->count(),
            'total_saldo_inicial' => $cajas->sum('saldo_inicial'),
            'total_saldo_final' => $cajas->where('estado', 'cerrada')->sum('saldo_final'),
            'total_diferencia' => $cajas->where('estado', 'cerrada')->sum('diferencia'),
            'total_ingresos' => $cajasConTotales->sum('total_ingresos'),
            'total_egresos' => $cajasConTotales->sum('total_egresos'),
            'total_esperado' => $cajasConTotales->sum('total_esperado'),
            'total_movimientos' => $cajasConTotales->sum('total_movimientos'),
        ];

        // Lista de usuarios que tienen cajas (para el selector del frontend)
        $usuarios = \App\Models\User::select('id', 'name', 'email', 'rol')
            ->whereHas('cajasApertura')
            ->orderBy('name')
            ->get();

        return response()->json([
            'cajas' => $cajasConTotales,
            'estadisticas' => $estadisticas,
            'usuarios_disponibles' => $usuarios,
        ]);
    }

    /**
     * Generar PDF de reporte de movimientos de caja
     */
    public function reportePDF(Request $request)
    {
        $request->validate([
            'caja_id' => 'nullable|exists:caja_apertura_cierres,id',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date',
        ]);

        // Si se envía caja_id específico
        if ($request->caja_id) {
            $caja = CajaAperturaCierre::with(['movimientos.user', 'user'])->findOrFail($request->caja_id);

            $movimientos = $caja->movimientos;
            $totalIngresos = $movimientos->whereIn('tipo', ['incremento', 'ingreso_pago'])->sum('monto');
            $totalEgresos = $movimientos->whereIn('tipo', ['decremento', 'egreso_desembolso'])->sum('monto');

            $data = [
                'caja' => $caja,
                'movimientos' => $movimientos,
                'totalIngresos' => $totalIngresos,
                'totalEgresos' => $totalEgresos,
                'saldoNeto' => $totalIngresos - $totalEgresos,
                'fecha_generacion' => now()->format('d/m/Y H:i:s'),
            ];

            $pdf = Pdf::loadView('reportes.caja-movimientos', $data);
            return $pdf->download('reporte-caja-' . $caja->id . '.pdf');
        }

        // Si se envían fechas, generar reporte por rango
        $query = MovimientoCaja::with(['user', 'caja']);

        if ($request->fecha_desde) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }
        if ($request->fecha_hasta) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }
        if ($request->sucursal_id) {
            $query->whereHas('caja', function ($q) use ($request) {
                $q->where('sucursal_id', $request->sucursal_id);
            });
        }

        $movimientos = $query->orderBy('created_at', 'desc')->get();
        $totalIngresos = $movimientos->whereIn('tipo', ['incremento', 'ingreso_pago'])->sum('monto');
        $totalEgresos = $movimientos->whereIn('tipo', ['decremento', 'egreso_desembolso'])->sum('monto');

        // Obtener la primera caja relacionada para el template
        $caja = CajaAperturaCierre::with(['user'])
            ->when($request->fecha_desde, fn($q) => $q->whereDate('fecha_apertura', '>=', $request->fecha_desde))
            ->when($request->fecha_hasta, fn($q) => $q->whereDate('fecha_apertura', '<=', $request->fecha_hasta))
            ->first();

        $data = [
            'caja' => $caja,
            'movimientos' => $movimientos,
            'totalIngresos' => $totalIngresos,
            'totalEgresos' => $totalEgresos,
            'saldoNeto' => $totalIngresos - $totalEgresos,
            'fecha_generacion' => now()->format('d/m/Y H:i:s'),
        ];

        $pdf = Pdf::loadView('reportes.caja-movimientos', $data);
        return $pdf->download('reporte-caja-movimientos.pdf');
    }

    /**
     * Generar PDF consolidado (solo administradores)
     * Soporta filtros: fecha_inicio/fecha_fin, user_ids[], caja_ids[], incluir_movimientos
     */
    public function consolidadoPDF(Request $request)
    {
        // Verificar que el usuario sea administrador o superadmin
        if (!in_array(Auth::user()->rol, ['administrador', 'superadmin'])) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer',
            'caja_ids' => 'nullable|array',
            'caja_ids.*' => 'integer',
            'incluir_movimientos' => 'nullable|boolean',
        ]);

        $incluirMovimientos = $request->boolean('incluir_movimientos', false);
        $relations = ['user', 'sucursal'];
        if ($incluirMovimientos) {
            $relations[] = 'movimientos';
            $relations[] = 'movimientos.user';
        }

        $query = CajaAperturaCierre::with($relations);

        // Soportar ambos formatos de parámetros de fecha
        $fechaInicio = $request->fecha_inicio ?? $request->fecha_desde;
        $fechaFin = $request->fecha_fin ?? $request->fecha_hasta;

        if ($fechaInicio) {
            $query->whereDate('fecha_apertura', '>=', $fechaInicio);
        }
        if ($fechaFin) {
            $query->whereDate('fecha_apertura', '<=', $fechaFin);
        }
        if ($request->has('user_ids') && is_array($request->user_ids) && count($request->user_ids) > 0) {
            $query->whereIn('user_id', $request->user_ids);
        }
        if ($request->has('caja_ids') && is_array($request->caja_ids) && count($request->caja_ids) > 0) {
            $query->whereIn('id', $request->caja_ids);
        }

        $cajas = $query->orderBy('fecha_apertura', 'desc')->get();

        // Calcular totales por caja
        $cajas->each(function ($caja) {
            $movimientos = $caja->movimientos ?? collect();
            $caja->total_ingresos = $movimientos->whereIn('tipo', ['incremento', 'ingreso_pago'])->sum('monto');
            $caja->total_egresos = $movimientos->whereIn('tipo', ['decremento', 'egreso_desembolso'])->sum('monto');
            $caja->total_esperado = $caja->saldo_inicial + $caja->total_ingresos - $caja->total_egresos;
        });

        $estadisticas = [
            'total_cajas' => $cajas->count(),
            'cajas_abiertas' => $cajas->where('estado', 'abierta')->count(),
            'cajas_cerradas' => $cajas->where('estado', 'cerrada')->count(),
            'total_saldo_inicial' => $cajas->sum('saldo_inicial'),
            'total_saldo_final' => $cajas->where('estado', 'cerrada')->sum('saldo_final'),
            'total_diferencia' => $cajas->where('estado', 'cerrada')->sum('diferencia'),
            'total_ingresos' => $cajas->sum('total_ingresos'),
            'total_egresos' => $cajas->sum('total_egresos'),
            'total_esperado' => $cajas->sum('total_esperado'),
        ];

        $data = [
            'cajas' => $cajas,
            'estadisticas' => $estadisticas,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'incluir_movimientos' => $incluirMovimientos,
            'fecha_generacion' => now()->format('d/m/Y H:i:s'),
        ];

        $pdf = Pdf::loadView('reportes.caja-consolidado', $data);
        $pdf->setPaper('letter', $incluirMovimientos ? 'landscape' : 'portrait');
        return $pdf->download('consolidado-cajas.pdf');
    }

    /**
     * Exportar movimientos a Excel
     */
    public function exportarExcel(Request $request)
    {
        $request->validate([
            'caja_id' => 'nullable|exists:caja_apertura_cierres,id',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
        ]);

        $query = MovimientoCaja::with(['user', 'caja']);

        if ($request->caja_id) {
            $query->where('caja_id', $request->caja_id);
        }

        if ($request->fecha_inicio) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }

        if ($request->fecha_fin) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }

        $movimientos = $query->orderBy('created_at', 'desc')->get();

        // Convertir a CSV
        $filename = 'movimientos-caja-' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($movimientos) {
            $file = fopen('php://output', 'w');

            // Encabezados
            fputcsv($file, ['ID', 'Fecha/Hora', 'Tipo', 'Concepto', 'Monto', 'Usuario', 'Estado']);

            // Datos
            foreach ($movimientos as $mov) {
                fputcsv($file, [
                    $mov->id,
                    $mov->created_at->format('Y-m-d H:i:s'),
                    $mov->tipo,
                    $mov->concepto,
                    $mov->monto,
                    $mov->user->name ?? 'N/A',
                    $mov->estado,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
