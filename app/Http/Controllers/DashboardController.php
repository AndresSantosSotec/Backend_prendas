<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\CreditoPrendario;
use App\Models\Venta;
use App\Models\Prenda;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Cuando el usuario no tiene permiso de dashboard, se devuelve
     * un payload seguro para que frontend muestre bienvenida genérica.
     */
    private function getWelcomeDashboardData(): array
    {
        return [
            'total_clientes' => 0,
            'creditos_activos' => 0,
            'creditos_vencidos' => 0,
            'total_prestado' => 0,
            'total_saldo' => 0,
            'total_recuperado' => 0,
            'creditos_por_estado' => [],
            'ventas_mes_actual' => 0,
            'prendas_en_inventario' => 0,
            'creditos_proximos_vencer' => 0,
            'fecha_consulta' => Carbon::now()->toIso8601String(),
            'modo_bienvenida' => true,
            'mensaje_bienvenida' => '¡Bienvenido a DigiPrenda! 👋, Todo está listo para comenzar.Utiliza el menú lateral para acceder a las herramientas disponibles.',
        ];
    }

    private function userCanViewDashboard(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user instanceof User) {
            return false;
        }

        return $user->hasPermission('dashboard', 'ver');
    }

    /**
     * Obtiene las estadísticas generales del dashboard
     */
    public function index(): JsonResponse
    {
        if (!$this->userCanViewDashboard()) {
            return response()->json([
                'success' => true,
                'message' => 'Acceso al dashboard restringido. Mostrando bienvenida.',
                'data' => $this->getWelcomeDashboardData(),
            ]);
        }

        try {
            // Total de clientes activos
            $totalClientes = Cliente::where('estado', 'activo')
                ->where('eliminado', false)
                ->count();

            // Créditos activos (vigente, en_mora, vencido)
            $creditosActivos = CreditoPrendario::whereIn('estado', [
                'vigente',
                'en_mora',
                'vencido'
            ])->count();

            // Créditos vencidos específicamente
            $creditosVencidos = CreditoPrendario::where('estado', 'vencido')
                ->orWhere('estado', 'en_mora')
                ->count();

            // Total prestado en créditos activos
            $totalPrestado = CreditoPrendario::whereIn('estado', [
                'vigente',
                'en_mora',
                'vencido'
            ])->sum('monto_desembolsado');

            // Saldo pendiente total (capital + intereses + mora)
            $totalCapitalPendiente = CreditoPrendario::whereIn('estado', [
                'vigente',
                'en_mora',
                'vencido'
            ])->sum('capital_pendiente');

            $totalInteresPendiente = CreditoPrendario::whereIn('estado', [
                'vigente',
                'en_mora',
                'vencido'
            ])->get()->sum(function ($credito) {
                return $credito->interes_generado - $credito->interes_pagado;
            });

            $totalMoraPendiente = CreditoPrendario::whereIn('estado', [
                'vigente',
                'en_mora',
                'vencido'
            ])->get()->sum(function ($credito) {
                return $credito->mora_generada - $credito->mora_pagada;
            });

            $totalSaldo = $totalCapitalPendiente + $totalInteresPendiente + $totalMoraPendiente;

            // Estadísticas adicionales
            $creditosPorEstado = CreditoPrendario::select('estado', DB::raw('count(*) as total'))
                ->groupBy('estado')
                ->get()
                ->pluck('total', 'estado');

            // Ventas del mes actual
            $ventasMesActual = Venta::whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->where('estado', 'pagada')
                ->sum('total_final');

            // Prendas en inventario
            $prendasEnInventario = Prenda::whereIn('estado', ['en_custodia', 'en_venta'])
                ->count();

            // Créditos próximos a vencer (próximos 7 días)
            $creditosProximosVencer = CreditoPrendario::where('estado', 'vigente')
                ->whereBetween('fecha_vencimiento', [
                    Carbon::now(),
                    Carbon::now()->addDays(7)
                ])
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    // Datos principales del dashboard
                    'total_clientes' => $totalClientes,
                    'creditos_activos' => $creditosActivos,
                    'creditos_vencidos' => $creditosVencidos,
                    'total_prestado' => (float) $totalPrestado,
                    'total_saldo' => (float) $totalSaldo,
                    'total_recuperado' => (float) ($totalPrestado - $totalSaldo),

                    // Estadísticas adicionales
                    'creditos_por_estado' => $creditosPorEstado,
                    'ventas_mes_actual' => (float) $ventasMesActual,
                    'prendas_en_inventario' => $prendasEnInventario,
                    'creditos_proximos_vencer' => $creditosProximosVencer,

                    // Metadata
                    'fecha_consulta' => Carbon::now()->toIso8601String(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas del dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene gráficas y reportes para el dashboard
     */
    public function graficas(): JsonResponse
    {
        if (!$this->userCanViewDashboard()) {
            return response()->json([
                'success' => true,
                'message' => 'Acceso al dashboard restringido. Gráficas no disponibles.',
                'data' => [
                    'creditos_por_mes' => [],
                    'ventas_por_mes' => [],
                    'modo_bienvenida' => true,
                ]
            ]);
        }

        try {
            // Créditos por mes (últimos 6 meses)
            $creditosPorMes = CreditoPrendario::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as mes'),
                DB::raw('count(*) as total'),
                DB::raw('sum(monto_aprobado) as monto_total')
            )
                ->where('created_at', '>=', Carbon::now()->subMonths(6))
                ->groupBy('mes')
                ->orderBy('mes')
                ->get();

            // Ventas por mes (últimos 6 meses)
            $ventasPorMes = Venta::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as mes'),
                DB::raw('count(*) as total'),
                DB::raw('sum(total_final) as monto_total')
            )
                ->where('created_at', '>=', Carbon::now()->subMonths(6))
                ->where('estado', 'pagada')
                ->groupBy('mes')
                ->orderBy('mes')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'creditos_por_mes' => $creditosPorMes,
                    'ventas_por_mes' => $ventasPorMes,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener gráficas del dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los créditos que requieren atención inmediata
     */
    public function alertas(): JsonResponse
    {
        if (!$this->userCanViewDashboard()) {
            return response()->json([
                'success' => true,
                'message' => 'Acceso al dashboard restringido. Alertas no disponibles.',
                'data' => [
                    'creditos_vencidos_hoy' => [],
                    'creditos_en_mora' => [],
                    'creditos_proximos_vencer' => [],
                    'modo_bienvenida' => true,
                ]
            ]);
        }

        try {
            // Créditos vencidos hoy
            $creditosVencidosHoy = CreditoPrendario::whereDate('fecha_vencimiento', Carbon::today())
                ->where('estado', 'vigente')
                ->with(['cliente', 'prendas'])
                ->get();

            // Créditos en mora
            $creditosEnMora = CreditoPrendario::where('estado', 'en_mora')
                ->with(['cliente', 'prendas'])
                ->orderBy('fecha_vencimiento', 'asc')
                ->limit(10)
                ->get();

            // Créditos próximos a vencer (próximos 3 días)
            $creditosProximosVencer = CreditoPrendario::where('estado', 'vigente')
                ->whereBetween('fecha_vencimiento', [
                    Carbon::tomorrow(),
                    Carbon::now()->addDays(3)
                ])
                ->with(['cliente', 'prendas'])
                ->orderBy('fecha_vencimiento', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'creditos_vencidos_hoy' => $creditosVencidosHoy,
                    'creditos_en_mora' => $creditosEnMora,
                    'creditos_proximos_vencer' => $creditosProximosVencer,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener alertas del dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
