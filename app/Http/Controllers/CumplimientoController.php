<?php

namespace App\Http\Controllers;

use App\Services\CumplimientoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CumplimientoController extends Controller
{
    protected CumplimientoService $cumplimientoService;

    public function __construct(CumplimientoService $cumplimientoService)
    {
        $this->cumplimientoService = $cumplimientoService;
    }

    /**
     * Verificar si el usuario tiene permiso de visualización en cumplimiento
     */
    protected function authorizeVer(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'No autenticado.'], 401);
        }

        $tienePermiso = $user->hasPermission('cumplimiento', 'ver')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'administrador', 'contador']))
            || in_array($user->rol, ['superadmin', 'administrador', 'contador']);

        if (!$tienePermiso) {
            return response()->json([
                'error' => 'No autorizado',
                'message' => 'No dispones de permisos para visualizar reportes de cumplimiento regulatorio (cumplimiento.ver).'
            ], 403);
        }

        return null;
    }

    /**
     * Verificar si el usuario tiene permiso para exportar reportes
     */
    protected function authorizeExportar(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'No autenticado.'], 401);
        }

        $tienePermiso = $user->hasPermission('cumplimiento', 'exportar')
            || $user->hasPermission('cumplimiento', 'ver')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'administrador', 'contador']))
            || in_array($user->rol, ['superadmin', 'administrador', 'contador']);

        if (!$tienePermiso) {
            return response()->json([
                'error' => 'No autorizado',
                'message' => 'No dispones de permisos para exportar reportes de cumplimiento.'
            ], 403);
        }

        return null;
    }

    /**
     * Resumen de KPIs ejecutivos para dashboard de cumplimiento
     * GET /api/v1/cumplimiento/resumen
     */
    public function resumen(Request $request): JsonResponse
    {
        if ($denied = $this->authorizeVer($request)) {
            return $denied;
        }

        try {
            $filtros = $request->only(['fecha_inicio', 'fecha_fin', 'sucursal_id', 'umbral_efectivo']);
            $kpis = $this->cumplimientoService->getResumenKpis($filtros);

            return response()->json([
                'success' => true,
                'data' => $kpis,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en CumplimientoController@resumen', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al calcular resumen de cumplimiento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reporte RTE: Registro de Transacciones en Efectivo >= Umbral
     * GET /api/v1/cumplimiento/reportes/rte
     */
    public function reporteRTE(Request $request): JsonResponse
    {
        if ($denied = $this->authorizeVer($request)) {
            return $denied;
        }

        try {
            $filtros = $request->only([
                'fecha_inicio',
                'fecha_fin',
                'sucursal_id',
                'umbral_efectivo',
                'tipo_operacion',
                'buscar',
            ]);

            $reporte = $this->cumplimientoService->getReporteRTE($filtros);

            return response()->json([
                'success' => true,
                'data' => $reporte,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en CumplimientoController@reporteRTE', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al obtener reporte RTE: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Alertas de monitoreo / Transacciones Inusuales y Fraccionamiento
     * GET /api/v1/cumplimiento/reportes/alertas
     */
    public function alertas(Request $request): JsonResponse
    {
        if ($denied = $this->authorizeVer($request)) {
            return $denied;
        }

        try {
            $filtros = $request->only([
                'fecha_inicio',
                'fecha_fin',
                'sucursal_id',
                'umbral_efectivo',
            ]);

            $alertas = $this->cumplimientoService->getAlertasMonitoreo($filtros);

            return response()->json([
                'success' => true,
                'total' => count($alertas),
                'data' => $alertas,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en CumplimientoController@alertas', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al obtener alertas de monitoreo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Perfil KYC / Expediente del Cliente (IVE-BA)
     * GET /api/v1/cumplimiento/reportes/kyc-clientes
     */
    public function kycClientes(Request $request): JsonResponse
    {
        if ($denied = $this->authorizeVer($request)) {
            return $denied;
        }

        try {
            $filtros = $request->only([
                'estado_expediente',
                'buscar',
                'sucursal_id',
            ]);

            $reporte = $this->cumplimientoService->getReporteKycClientes($filtros);

            return response()->json([
                'success' => true,
                'data' => $reporte,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en CumplimientoController@kycClientes', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al evaluar perfil KYC de clientes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consolidado Estadístico Mensual para la IVE
     * GET /api/v1/cumplimiento/reportes/consolidado-mensual
     */
    public function consolidadoMensual(Request $request): JsonResponse
    {
        if ($denied = $this->authorizeVer($request)) {
            return $denied;
        }

        try {
            $filtros = $request->only([
                'anio',
                'sucursal_id',
                'umbral_efectivo',
            ]);

            $consolidado = $this->cumplimientoService->getConsolidadoMensual($filtros);

            return response()->json([
                'success' => true,
                'data' => $consolidado,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en CumplimientoController@consolidadoMensual', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al generar consolidado mensual: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parámetros regulatorios y umbrales vigentes
     * GET /api/v1/cumplimiento/configuracion
     */
    public function configuracion(Request $request): JsonResponse
    {
        if ($denied = $this->authorizeVer($request)) {
            return $denied;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'umbral_default_gtq' => CumplimientoService::UMBRAL_DEFAULT_GTQ,
                'umbral_usd_referencial' => 10000.00,
                'ley_referencia' => 'Decreto 67-2001 Ley Contra el Lavado de Dinero u Otros Activos (Guatemala)',
                'autoridad_reguladora' => 'Intendencia de Verificación Especial (IVE) - Superintendencia de Bancos (SIB)',
                'tipos_operacion_evaluados' => [
                    ['id' => 'todos', 'label' => 'Todas las operaciones en efectivo'],
                    ['id' => 'credito_desembolso', 'label' => 'Desembolsos de Créditos Prendarios'],
                    ['id' => 'pago_recibo', 'label' => 'Recibos de Pago / Abonos de Crédito'],
                    ['id' => 'compra_directa', 'label' => 'Compras Directas de Prendas'],
                    ['id' => 'venta_mercaderia', 'label' => 'Ventas de Mercadería en Efectivo'],
                ],
                'niveles_riesgo' => ['ALTO', 'MEDIO', 'BAJO'],
            ],
        ]);
    }

    /**
     * Exportación de reportes a archivo CSV/Excel
     * GET /api/v1/cumplimiento/exportar/{tipo}
     */
    public function exportar(Request $request, string $tipo): Response|JsonResponse
    {
        if ($denied = $this->authorizeExportar($request)) {
            return $denied;
        }

        try {
            $filtros = $request->only([
                'fecha_inicio',
                'fecha_fin',
                'sucursal_id',
                'umbral_efectivo',
                'tipo_operacion',
                'buscar',
            ]);

            $csv = $this->cumplimientoService->exportarRteCsv($filtros);

            $fechaArchivo = date('Ymd_His');
            $fileName = "reporte_ive_rte_{$fechaArchivo}.csv";

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                'Cache-Control' => 'no-store, no-cache',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al exportar reporte de cumplimiento', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al generar exportación: ' . $e->getMessage(),
            ], 500);
        }
    }
}
