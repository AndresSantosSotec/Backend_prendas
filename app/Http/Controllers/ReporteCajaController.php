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
     */
    public function consolidado(Request $request)
    {
        // Verificar que el usuario sea administrador
        if (Auth::user()->rol !== 'administrador') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
        ]);

        $query = CajaAperturaCierre::with(['movimientos', 'user']);

        if ($request->fecha_inicio) {
            $query->whereDate('fecha_apertura', '>=', $request->fecha_inicio);
        }

        if ($request->fecha_fin) {
            $query->whereDate('fecha_apertura', '<=', $request->fecha_fin);
        }

        $cajas = $query->orderBy('fecha_apertura', 'desc')->get();

        $estadisticas = [
            'total_cajas' => $cajas->count(),
            'cajas_abiertas' => $cajas->where('estado', 'abierta')->count(),
            'cajas_cerradas' => $cajas->where('estado', 'cerrada')->count(),
            'total_saldo_inicial' => $cajas->sum('saldo_inicial'),
            'total_saldo_final' => $cajas->where('estado', 'cerrada')->sum('saldo_final'),
            'total_diferencia' => $cajas->where('estado', 'cerrada')->sum('diferencia'),
        ];

        return response()->json([
            'cajas' => $cajas,
            'estadisticas' => $estadisticas,
        ]);
    }

    /**
     * Generar PDF de reporte de movimientos de caja
     */
    public function reportePDF(Request $request)
    {
        $request->validate([
            'caja_id' => 'required|exists:caja_apertura_cierres,id',
        ]);

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

    /**
     * Generar PDF consolidado (solo administradores)
     */
    public function consolidadoPDF(Request $request)
    {
        // Verificar que el usuario sea administrador
        if (Auth::user()->rol !== 'administrador') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
        ]);

        $query = CajaAperturaCierre::with(['movimientos', 'user']);

        if ($request->fecha_inicio) {
            $query->whereDate('fecha_apertura', '>=', $request->fecha_inicio);
        }

        if ($request->fecha_fin) {
            $query->whereDate('fecha_apertura', '<=', $request->fecha_fin);
        }

        $cajas = $query->orderBy('fecha_apertura', 'desc')->get();

        $estadisticas = [
            'total_cajas' => $cajas->count(),
            'cajas_abiertas' => $cajas->where('estado', 'abierta')->count(),
            'cajas_cerradas' => $cajas->where('estado', 'cerrada')->count(),
            'total_saldo_inicial' => $cajas->sum('saldo_inicial'),
            'total_saldo_final' => $cajas->where('estado', 'cerrada')->sum('saldo_final'),
            'total_diferencia' => $cajas->where('estado', 'cerrada')->sum('diferencia'),
        ];

        $data = [
            'cajas' => $cajas,
            'estadisticas' => $estadisticas,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'fecha_generacion' => now()->format('d/m/Y H:i:s'),
        ];

        $pdf = Pdf::loadView('reportes.caja-consolidado', $data);
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
