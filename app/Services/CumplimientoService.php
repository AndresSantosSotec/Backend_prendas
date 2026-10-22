<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CreditoPrendario;
use App\Models\MovimientoCaja;
use App\Models\Recibo;
use App\Models\Sucursal;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CumplimientoService
{
    /**
     * Umbral base por defecto para el Registro de Transacciones en Efectivo (RTE)
     * En Guatemala, según resoluciones de la IVE, operaciones individuales o múltiples
     * en efectivo superiores o iguales a Q10,000 / USD 10,000 (o Q50,000 según tipo de sujeto obligado).
     */
    public const UMBRAL_DEFAULT_GTQ = 10000.00;

    /**
     * Resumen de KPIs ejecutivos para el módulo de cumplimiento
     */
    public function getResumenKpis(array $filtros = []): array
    {
        $fechaInicio = !empty($filtros['fecha_inicio'])
            ? Carbon::parse($filtros['fecha_inicio'])->startOfDay()
            : Carbon::now()->startOfMonth();

        $fechaFin = !empty($filtros['fecha_fin'])
            ? Carbon::parse($filtros['fecha_fin'])->endOfDay()
            : Carbon::now()->endOfDay();

        $sucursalId = !empty($filtros['sucursal_id']) ? (int) $filtros['sucursal_id'] : null;
        $umbral = !empty($filtros['umbral_efectivo']) ? (float) $filtros['umbral_efectivo'] : self::UMBRAL_DEFAULT_GTQ;

        // 1. Obtener listado de transacciones RTE
        $transaccionesRte = $this->obtenerColeccionRte($fechaInicio, $fechaFin, $sucursalId, $umbral);

        // 2. Obtener todas las transacciones en efectivo del periodo (para base comparativa)
        $todasEfectivo = $this->obtenerColeccionRte($fechaInicio, $fechaFin, $sucursalId, 0.0);

        // 3. Alertas de monitoreo (fraccionamiento y concentración)
        $alertas = $this->getAlertasMonitoreo([
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString(),
            'sucursal_id' => $sucursalId,
            'umbral_efectivo' => $umbral,
        ]);

        // 4. Auditoría KYC expedientes clientes
        $kycStats = $this->getKycStats($fechaInicio, $fechaFin, $sucursalId);

        $montoTotalEfectivo = (float) $todasEfectivo->sum('monto_efectivo');
        $montoTotalRte = (float) $transaccionesRte->sum('monto_efectivo');

        return [
            'periodo' => [
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
                'dias' => $fechaInicio->diffInDays($fechaFin) + 1,
            ],
            'umbral_utilizado' => $umbral,
            'total_operaciones_evaluadas' => $todasEfectivo->count(),
            'total_monto_efectivo' => $montoTotalEfectivo,
            'total_operaciones_rte' => $transaccionesRte->count(),
            'total_monto_rte' => $montoTotalRte,
            'porcentaje_monto_rte' => $montoTotalEfectivo > 0 ? round(($montoTotalRte / $montoTotalEfectivo) * 100, 2) : 0,
            'total_alertas_activas' => count($alertas),
            'alertas_por_nivel' => [
                'alta' => collect($alertas)->where('nivel_riesgo', 'ALTO')->count(),
                'media' => collect($alertas)->where('nivel_riesgo', 'MEDIO')->count(),
                'baja' => collect($alertas)->where('nivel_riesgo', 'BAJO')->count(),
            ],
            'clientes_evaluados' => $kycStats['total_clientes'],
            'clientes_expediente_incompleto' => $kycStats['incompletos'],
            'porcentaje_cumplimiento_kyc' => $kycStats['porcentaje_completos'],
        ];
    }

    /**
     * Reporte RTE: Registro de Transacciones en Efectivo >= Umbral
     */
    public function getReporteRTE(array $filtros = []): array
    {
        $fechaInicio = !empty($filtros['fecha_inicio'])
            ? Carbon::parse($filtros['fecha_inicio'])->startOfDay()
            : Carbon::now()->startOfMonth();

        $fechaFin = !empty($filtros['fecha_fin'])
            ? Carbon::parse($filtros['fecha_fin'])->endOfDay()
            : Carbon::now()->endOfDay();

        $sucursalId = !empty($filtros['sucursal_id']) ? (int) $filtros['sucursal_id'] : null;
        $umbral = !empty($filtros['umbral_efectivo']) ? (float) $filtros['umbral_efectivo'] : self::UMBRAL_DEFAULT_GTQ;
        $tipoFiltro = !empty($filtros['tipo_operacion']) ? $filtros['tipo_operacion'] : null;
        $busqueda = !empty($filtros['buscar']) ? trim(mb_strtolower($filtros['buscar'])) : null;

        $coleccion = $this->obtenerColeccionRte($fechaInicio, $fechaFin, $sucursalId, $umbral);

        // Filtrar por tipo si aplica
        if ($tipoFiltro && $tipoFiltro !== 'todos') {
            $coleccion = $coleccion->filter(fn ($item) => $item['tipo_operacion'] === $tipoFiltro);
        }

        // Filtrar por búsqueda si aplica
        if ($busqueda) {
            $coleccion = $coleccion->filter(function ($item) use ($busqueda) {
                return str_contains(mb_strtolower($item['cliente_nombre'] ?? ''), $busqueda)
                    || str_contains(mb_strtolower($item['cliente_dpi'] ?? ''), $busqueda)
                    || str_contains(mb_strtolower($item['cliente_nit'] ?? ''), $busqueda)
                    || str_contains(mb_strtolower($item['codigo_transaccion'] ?? ''), $busqueda);
            });
        }

        // Ordenar por fecha descendente
        $transacciones = $coleccion->sortByDesc('fecha_hora')->values();

        return [
            'filtros_aplicados' => [
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
                'sucursal_id' => $sucursalId,
                'umbral_efectivo' => $umbral,
                'tipo_operacion' => $tipoFiltro ?? 'todos',
            ],
            'total_registros' => $transacciones->count(),
            'total_monto' => (float) $transacciones->sum('monto_efectivo'),
            'transacciones' => $transacciones->all(),
        ];
    }

    /**
     * Detección de Alertas de Monitoreo / Transacciones Inusuales (RTS e Indicios IVE)
     */
    public function getAlertasMonitoreo(array $filtros = []): array
    {
        $fechaInicio = !empty($filtros['fecha_inicio'])
            ? Carbon::parse($filtros['fecha_inicio'])->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $fechaFin = !empty($filtros['fecha_fin'])
            ? Carbon::parse($filtros['fecha_fin'])->endOfDay()
            : Carbon::now()->endOfDay();

        $sucursalId = !empty($filtros['sucursal_id']) ? (int) $filtros['sucursal_id'] : null;
        $umbral = !empty($filtros['umbral_efectivo']) ? (float) $filtros['umbral_efectivo'] : self::UMBRAL_DEFAULT_GTQ;

        // Obtenemos todas las transacciones sin umbral mínimo para evaluar fraccionamiento
        $todas = $this->obtenerColeccionRte($fechaInicio, $fechaFin, $sucursalId, 0.0);

        $alertas = [];

        // ALERTA 1: FRACCIONAMIENTO (Pitufeo / Structuring)
        // Múltiples operaciones en efectivo individuales (< umbral) realizadas por el mismo cliente
        // en el periodo analizado que suman >= umbral
        $agrupadoCliente = $todas->groupBy('cliente_id');

        foreach ($agrupadoCliente as $clienteId => $items) {
            if (!$clienteId || $items->count() < 2) {
                continue;
            }

            $montoAcumulado = (float) $items->sum('monto_efectivo');
            $operacionesMenores = $items->filter(fn ($it) => $it['monto_efectivo'] < $umbral);

            // Si tiene al menos 2 operaciones menores al umbral pero su total supera el umbral
            if ($operacionesMenores->count() >= 2 && $montoAcumulado >= $umbral) {
                $primerItem = $items->first();
                $alertas[] = [
                    'id' => 'ALERTA-FRACC-' . $clienteId . '-' . $fechaInicio->format('Ymd'),
                    'tipo_alerta' => 'FRACCIONAMIENTO_EFECTIVO',
                    'titulo' => 'Posible Fraccionamiento de Efectivo (Pitufeo)',
                    'nivel_riesgo' => $montoAcumulado >= ($umbral * 2) ? 'ALTO' : 'MEDIO',
                    'cliente_id' => $clienteId,
                    'cliente_nombre' => $primerItem['cliente_nombre'] ?? 'Cliente sin nombre',
                    'cliente_dpi' => $primerItem['cliente_dpi'] ?? 'Sin DPI',
                    'cliente_nit' => $primerItem['cliente_nit'] ?? 'CF',
                    'monto_acumulado' => $montoAcumulado,
                    'cantidad_operaciones' => $items->count(),
                    'sucursal_nombre' => $primerItem['sucursal_nombre'] ?? 'General',
                    'descripcion' => sprintf(
                        'El cliente realizó %d transacciones en efectivo que individualmente no superan el umbral (Q%s) pero acumuladas suman Q%s en el periodo evaluado.',
                        $items->count(),
                        number_format($umbral, 2),
                        number_format($montoAcumulado, 2)
                    ),
                    'operaciones_detalle' => $items->take(5)->map(fn ($it) => [
                        'codigo' => $it['codigo_transaccion'],
                        'tipo' => $it['tipo_label'],
                        'monto' => $it['monto_efectivo'],
                        'fecha' => $it['fecha_hora'],
                    ])->values()->all(),
                ];
            }
        }

        // ALERTA 2: CONCENTRACIÓN ATÍPICA DE EMPEÑOS / COMPRAS
        // Clientes con 4 o más empeños o ventas en el periodo con monto relevante
        foreach ($agrupadoCliente as $clienteId => $items) {
            if (!$clienteId || $items->count() < 4) {
                continue;
            }

            $montoAcumulado = (float) $items->sum('monto_efectivo');
            $primerItem = $items->first();

            // Evitar duplicar si ya está en fraccionamiento con riesgo alto
            $yaRegistrada = collect($alertas)->contains('id', 'ALERTA-FRACC-' . $clienteId . '-' . $fechaInicio->format('Ymd'));
            if (!$yaRegistrada) {
                $alertas[] = [
                    'id' => 'ALERTA-CONC-' . $clienteId . '-' . $fechaInicio->format('Ymd'),
                    'tipo_alerta' => 'ALTA_FRECUENCIA_OPERACIONES',
                    'titulo' => 'Alta Frecuencia Atípica de Operaciones',
                    'nivel_riesgo' => 'MEDIO',
                    'cliente_id' => $clienteId,
                    'cliente_nombre' => $primerItem['cliente_nombre'] ?? 'Cliente sin nombre',
                    'cliente_dpi' => $primerItem['cliente_dpi'] ?? 'Sin DPI',
                    'cliente_nit' => $primerItem['cliente_nit'] ?? 'CF',
                    'monto_acumulado' => $montoAcumulado,
                    'cantidad_operaciones' => $items->count(),
                    'sucursal_nombre' => $primerItem['sucursal_nombre'] ?? 'General',
                    'descripcion' => sprintf(
                        'El cliente acumula %d operaciones en el periodo por un total de Q%s.',
                        $items->count(),
                        number_format($montoAcumulado, 2)
                    ),
                    'operaciones_detalle' => $items->take(5)->map(fn ($it) => [
                        'codigo' => $it['codigo_transaccion'],
                        'tipo' => $it['tipo_label'],
                        'monto' => $it['monto_efectivo'],
                        'fecha' => $it['fecha_hora'],
                    ])->values()->all(),
                ];
            }
        }

        // ALERTA 3: OPERACIÓN SIGNIFICATIVA SIN DPI O DOCUMENTACIÓN OBLIGATORIA
        $operacionesSinDpi = $todas->filter(function ($it) {
            $monto = (float) ($it['monto_efectivo'] ?? 0);
            $dpi = trim($it['cliente_dpi'] ?? '');
            return $monto >= 5000.00 && (empty($dpi) || strlen($dpi) < 13);
        });

        foreach ($operacionesSinDpi->take(15) as $op) {
            $alertas[] = [
                'id' => 'ALERTA-NODPI-' . $op['id'],
                'tipo_alerta' => 'OPERACION_SIN_DPI_COMPLETO',
                'titulo' => 'Transacción Significativa sin DPI Válido',
                'nivel_riesgo' => 'ALTO',
                'cliente_id' => $op['cliente_id'],
                'cliente_nombre' => $op['cliente_nombre'],
                'cliente_dpi' => $op['cliente_dpi'] ?: 'NO REGISTRADO',
                'cliente_nit' => $op['cliente_nit'] ?: 'CF',
                'monto_acumulado' => (float) $op['monto_efectivo'],
                'cantidad_operaciones' => 1,
                'sucursal_nombre' => $op['sucursal_nombre'],
                'descripcion' => sprintf(
                    'Transacción %s (%s) por Q%s realizada sin DPI completo de 13 dígitos registrado en el expediente.',
                    $op['codigo_transaccion'],
                    $op['tipo_label'],
                    number_format($op['monto_efectivo'], 2)
                ),
                'operaciones_detalle' => [[
                    'codigo' => $op['codigo_transaccion'],
                    'tipo' => $op['tipo_label'],
                    'monto' => $op['monto_efectivo'],
                    'fecha' => $op['fecha_hora'],
                ]],
            ];
        }

        return $alertas;
    }

    /**
     * Reporte KYC / Perfil y Estado de Expedientes de Clientes (IVE-BA)
     */
    public function getReporteKycClientes(array $filtros = []): array
    {
        $estadoExpediente = !empty($filtros['estado_expediente']) ? $filtros['estado_expediente'] : 'todos';
        $busqueda = !empty($filtros['buscar']) ? trim(mb_strtolower($filtros['buscar'])) : null;
        $sucursalId = !empty($filtros['sucursal_id']) ? (int) $filtros['sucursal_id'] : null;

        $query = Cliente::query()->where('eliminado', false);

        if ($busqueda) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombres', 'like', "%{$busqueda}%")
                    ->orWhere('apellidos', 'like', "%{$busqueda}%")
                    ->orWhere('dpi', 'like', "%{$busqueda}%")
                    ->orWhere('nit', 'like', "%{$busqueda}%")
                    ->orWhere('codigo_cliente', 'like', "%{$busqueda}%");
            });
        }

        $clientes = $query->orderBy('created_at', 'desc')->limit(200)->get();

        $evaluados = $clientes->map(function ($cliente) {
            $dpiValido = !empty($cliente->dpi) && strlen(preg_replace('/\D/', '', (string) $cliente->dpi)) >= 13;
            $tieneNit = !empty($cliente->nit);
            $tieneTelefono = !empty($cliente->telefono);
            $tieneDireccion = !empty($cliente->direccion);
            $tieneProfesion = !empty($cliente->profesion);
            $tieneFechaNac = !empty($cliente->fecha_nacimiento);

            $faltantes = [];
            if (!$dpiValido) $faltantes[] = 'DPI (13 dígitos)';
            if (!$tieneNit) $faltantes[] = 'NIT';
            if (!$tieneTelefono) $faltantes[] = 'Teléfono';
            if (!$tieneDireccion) $faltantes[] = 'Dirección completa';
            if (!$tieneProfesion) $faltantes[] = 'Profesión / Ocupación';
            if (!$tieneFechaNac) $faltantes[] = 'Fecha de nacimiento';

            $puntos = 6 - count($faltantes);
            if ($puntos === 6) {
                $estado = 'COMPLETO';
                $riesgoKyc = 'BAJO';
            } elseif ($puntos >= 4) {
                $estado = 'PARCIAL';
                $riesgoKyc = 'MEDIO';
            } else {
                $estado = 'INCOMPLETO';
                $riesgoKyc = 'ALTO';
            }

            return [
                'id' => $cliente->id,
                'codigo_cliente' => $cliente->codigo_cliente,
                'nombre_completo' => $cliente->nombre_completo,
                'dpi' => $cliente->dpi,
                'dpi_valido' => $dpiValido,
                'nit' => $cliente->nit,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion,
                'municipio' => $cliente->municipio,
                'profesion' => $cliente->profesion,
                'fecha_nacimiento' => $cliente->fecha_nacimiento?->format('Y-m-d'),
                'estado_expediente' => $estado,
                'nivel_riesgo' => $riesgoKyc,
                'porcentaje_completitud' => round(($puntos / 6) * 100),
                'campos_faltantes' => $faltantes,
                'fecha_registro' => $cliente->created_at?->format('Y-m-d H:i'),
            ];
        });

        if ($estadoExpediente !== 'todos') {
            $evaluados = $evaluados->filter(fn ($c) => mb_strtolower($c['estado_expediente']) === mb_strtolower($estadoExpediente))->values();
        }

        return [
            'total_clientes' => $evaluados->count(),
            'completos' => $evaluados->where('estado_expediente', 'COMPLETO')->count(),
            'parciales' => $evaluados->where('estado_expediente', 'PARCIAL')->count(),
            'incompletos' => $evaluados->where('estado_expediente', 'INCOMPLETO')->count(),
            'clientes' => $evaluados->all(),
        ];
    }

    /**
     * Consolidado Estadístico Mensual para presentación ante la IVE
     */
    public function getConsolidadoMensual(array $filtros = []): array
    {
        $año = !empty($filtros['anio']) ? (int) $filtros['anio'] : (int) date('Y');
        $sucursalId = !empty($filtros['sucursal_id']) ? (int) $filtros['sucursal_id'] : null;
        $umbral = !empty($filtros['umbral_efectivo']) ? (float) $filtros['umbral_efectivo'] : self::UMBRAL_DEFAULT_GTQ;

        $fechaInicio = Carbon::create($año, 1, 1)->startOfDay();
        $fechaFin = Carbon::create($año, 12, 31)->endOfDay();

        $todas = $this->obtenerColeccionRte($fechaInicio, $fechaFin, $sucursalId, 0.0);

        // Agrupar por mes (1 a 12)
        $mesesNombres = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];

        $matriz = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $opsMes = $todas->filter(function ($item) use ($mes) {
                $fecha = Carbon::parse($item['fecha_hora']);
                return (int) $fecha->month === $mes;
            });

            $opsRte = $opsMes->filter(fn ($it) => $it['monto_efectivo'] >= $umbral);
            $clientesUnicos = $opsMes->pluck('cliente_id')->filter()->unique()->count();

            $matriz[] = [
                'mes_numero' => $mes,
                'mes_nombre' => $mesNombres[$mes],
                'anio' => $año,
                'total_operaciones' => $opsMes->count(),
                'monto_total_efectivo' => (float) $opsMes->sum('monto_efectivo'),
                'operaciones_rte' => $opsRte->count(),
                'monto_rte' => (float) $opsRte->sum('monto_efectivo'),
                'clientes_unicos' => $clientesUnicos,
                'promedio_por_operacion' => $opsMes->count() > 0 ? round($opsMes->sum('monto_efectivo') / $opsMes->count(), 2) : 0,
            ];
        }

        return [
            'anio' => $año,
            'umbral_rte' => $umbral,
            'totales_anuales' => [
                'total_operaciones' => collect($matriz)->sum('total_operaciones'),
                'monto_total_efectivo' => collect($matriz)->sum('monto_total_efectivo'),
                'operaciones_rte' => collect($matriz)->sum('operaciones_rte'),
                'monto_rte' => collect($matriz)->sum('monto_rte'),
            ],
            'meses' => $matriz,
        ];
    }

    /**
     * Exportación de RTE a formato CSV según estándar de columnas IVE
     */
    public function exportarRteCsv(array $filtros = []): string
    {
        $reporte = $this->getReporteRTE($filtros);
        $items = $reporte['transacciones'] ?? [];

        $output = fopen('php://temp', 'r+');

        // BOM UTF-8 para compatibilidad perfecta con Excel
        fputs($output, "\xEF\xBB\xBF");

        // Encabezados requeridos por IVE
        fputcsv($output, [
            'ID Transacción',
            'Fecha y Hora',
            'Tipo de Operación',
            'Código Comprobante',
            'Monto Efectivo (GTQ)',
            'Código Cliente',
            'Nombre Completo',
            'DPI / Documento Identificación',
            'NIT',
            'Dirección',
            'Profesión / Ocupación',
            'Teléfono',
            'Sucursal',
            'Cajero / Responsable',
        ]);

        foreach ($items as $row) {
            fputcsv($output, [
                $row['id'],
                $row['fecha_hora'],
                $row['tipo_label'],
                $row['codigo_transaccion'],
                number_format($row['monto_efectivo'], 2, '.', ''),
                $row['cliente_codigo'] ?? '',
                $row['cliente_nombre'] ?? '',
                $row['cliente_dpi'] ?? '',
                $row['cliente_nit'] ?? '',
                $row['cliente_direccion'] ?? '',
                $row['cliente_profesion'] ?? '',
                $row['cliente_telefono'] ?? '',
                $row['sucursal_nombre'] ?? '',
                $row['usuario_nombre'] ?? '',
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * Consulta y unificación de operaciones en efectivo desde las diferentes entidades del sistema
     */
    protected function obtenerColeccionRte(Carbon $fechaInicio, Carbon $fechaFin, ?int $sucursalId, float $montoMinimo): Collection
    {
        $items = collect();

        // 1. DESEMBOLSOS DE CRÉDITO PRENDARIO (Salida de efectivo hacia el cliente)
        $creditosQuery = CreditoPrendario::with(['cliente', 'sucursal', 'cajero'])
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->where('monto_prestado', '>=', $montoMinimo);

        if ($sucursalId) {
            $creditosQuery->where('sucursal_id', $sucursalId);
        }

        foreach ($creditosQuery->get() as $credito) {
            $items->push([
                'id' => 'CRED-' . $credito->id,
                'tipo_operacion' => 'credito_desembolso',
                'tipo_label' => 'Desembolso Crédito Prendario',
                'codigo_transaccion' => $credito->numero_credito,
                'fecha_hora' => $credito->fecha_desembolso ? $credito->fecha_desembolso->toDateTimeString() : $credito->created_at->toDateTimeString(),
                'monto_efectivo' => (float) $credito->monto_prestado,
                'moneda' => 'GTQ',
                'cliente_id' => $credito->cliente_id,
                'cliente_codigo' => $credito->cliente?->codigo_cliente,
                'cliente_nombre' => $credito->cliente?->nombre_completo ?? 'N/A',
                'cliente_dpi' => $credito->cliente?->dpi ?? '',
                'cliente_nit' => $credito->cliente?->nit ?? '',
                'cliente_direccion' => $credito->cliente?->direccion ?? '',
                'cliente_profesion' => $credito->cliente?->profesion ?? '',
                'cliente_telefono' => $credito->cliente?->telefono ?? '',
                'sucursal_id' => $credito->sucursal_id,
                'sucursal_nombre' => $credito->sucursal?->nombre ?? 'Principal',
                'usuario_nombre' => $credito->cajero?->name ?? 'Sistema',
            ]);
        }

        // 2. COMPRAS DIRECTAS DE PRENDAS (Pago en efectivo al cliente)
        $comprasQuery = Compra::with(['cliente', 'sucursal', 'usuario'])
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->where('monto_pagado', '>=', $montoMinimo);

        if ($sucursalId) {
            $comprasQuery->where('sucursal_id', $sucursalId);
        }

        foreach ($comprasQuery->get() as $compra) {
            $items->push([
                'id' => 'COMP-' . $compra->id,
                'tipo_operacion' => 'compra_directa',
                'tipo_label' => 'Compra Directa de Prenda',
                'codigo_transaccion' => $compra->codigo_compra,
                'fecha_hora' => $compra->created_at->toDateTimeString(),
                'monto_efectivo' => (float) $compra->monto_pagado,
                'moneda' => 'GTQ',
                'cliente_id' => $compra->cliente_id,
                'cliente_codigo' => $compra->cliente?->codigo_cliente,
                'cliente_nombre' => $compra->cliente_nombre ?: ($compra->cliente?->nombre_completo ?? 'N/A'),
                'cliente_dpi' => $compra->cliente_dpi ?: ($compra->cliente?->dpi ?? ''),
                'cliente_nit' => $compra->cliente?->nit ?? '',
                'cliente_direccion' => $compra->cliente?->direccion ?? '',
                'cliente_profesion' => $compra->cliente?->profesion ?? '',
                'cliente_telefono' => $compra->cliente?->telefono ?? '',
                'sucursal_id' => $compra->sucursal_id,
                'sucursal_nombre' => $compra->sucursal?->nombre ?? 'Principal',
                'usuario_nombre' => $compra->usuario?->name ?? 'Sistema',
            ]);
        }

        // 3. RECIBOS DE PAGO / ABONO DE CRÉDITOS (Entrada de efectivo desde el cliente)
        $recibosQuery = Recibo::with(['cliente', 'sucursal', 'credito'])
            ->whereBetween('fecha', [$fechaInicio->toDateString(), $fechaFin->toDateString()])
            ->where('estado', '!=', 'anulado')
            ->where('monto', '>=', $montoMinimo);

        if ($sucursalId) {
            $recibosQuery->where('sucursal_id', $sucursalId);
        }

        foreach ($recibosQuery->get() as $recibo) {
            $items->push([
                'id' => 'REC-' . $recibo->id,
                'tipo_operacion' => 'pago_recibo',
                'tipo_label' => 'Recibo de Pago / Abono',
                'codigo_transaccion' => $recibo->numero_recibo,
                'fecha_hora' => $recibo->fecha->toDateString() . ' ' . $recibo->created_at->format('H:i:s'),
                'monto_efectivo' => (float) $recibo->monto,
                'moneda' => 'GTQ',
                'cliente_id' => $recibo->cliente_id,
                'cliente_codigo' => $recibo->cliente?->codigo_cliente,
                'cliente_nombre' => $recibo->cliente?->nombre_completo ?? 'N/A',
                'cliente_dpi' => $recibo->cliente?->dpi ?? '',
                'cliente_nit' => $recibo->cliente?->nit ?? '',
                'cliente_direccion' => $recibo->cliente?->direccion ?? '',
                'cliente_profesion' => $recibo->cliente?->profesion ?? '',
                'cliente_telefono' => $recibo->cliente?->telefono ?? '',
                'sucursal_id' => $recibo->sucursal_id,
                'sucursal_nombre' => $recibo->sucursal?->nombre ?? 'Principal',
                'usuario_nombre' => 'Cajero',
            ]);
        }

        // 4. VENTAS DE MERCADERÍA / PRENDAS (Entrada en efectivo)
        $ventasQuery = Venta::with(['cliente', 'sucursal'])
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->where('metodo_pago', 'efectivo')
            ->where('total', '>=', $montoMinimo);

        if ($sucursalId) {
            $ventasQuery->where('sucursal_id', $sucursalId);
        }

        foreach ($ventasQuery->get() as $venta) {
            $items->push([
                'id' => 'VENT-' . $venta->id,
                'tipo_operacion' => 'venta_mercaderia',
                'tipo_label' => 'Venta en Efectivo',
                'codigo_transaccion' => $venta->codigo_venta,
                'fecha_hora' => $venta->created_at->toDateTimeString(),
                'monto_efectivo' => (float) $venta->total,
                'moneda' => 'GTQ',
                'cliente_id' => $venta->cliente_id,
                'cliente_codigo' => $venta->cliente?->codigo_cliente,
                'cliente_nombre' => $venta->cliente_nombre ?: ($venta->cliente?->nombre_completo ?? 'Público General'),
                'cliente_dpi' => $venta->cliente?->dpi ?? '',
                'cliente_nit' => $venta->cliente_nit ?: 'CF',
                'cliente_direccion' => $venta->cliente?->direccion ?? '',
                'cliente_profesion' => $venta->cliente?->profesion ?? '',
                'cliente_telefono' => $venta->cliente_telefono ?: ($venta->cliente?->telefono ?? ''),
                'sucursal_id' => $venta->sucursal_id,
                'sucursal_nombre' => $venta->sucursal?->nombre ?? 'Principal',
                'usuario_nombre' => 'Vendedor',
            ]);
        }

        return $items;
    }

    /**
     * Resumen de estadísticas KYC sobre clientes activos
     */
    protected function getKycStats(Carbon $fechaInicio, Carbon $fechaFin, ?int $sucursalId): array
    {
        $clientes = Cliente::query()->where('eliminado', false)->get();
        $total = $clientes->count();

        if ($total === 0) {
            return [
                'total_clientes' => 0,
                'completos' => 0,
                'incompletos' => 0,
                'porcentaje_completos' => 100,
            ];
        }

        $completos = 0;
        $incompletos = 0;

        foreach ($clientes as $cliente) {
            $dpiValido = !empty($cliente->dpi) && strlen(preg_replace('/\D/', '', (string) $cliente->dpi)) >= 13;
            $tieneNit = !empty($cliente->nit);
            $tieneDir = !empty($cliente->direccion);
            $tieneTel = !empty($cliente->telefono);
            $tieneProf = !empty($cliente->profesion);

            if ($dpiValido && $tieneDir && $tieneTel && ($tieneNit || $tieneProf)) {
                $completos++;
            } else {
                $incompletos++;
            }
        }

        return [
            'total_clientes' => $total,
            'completos' => $completos,
            'incompletos' => $incompletos,
            'porcentaje_completos' => round(($completos / $total) * 100, 1),
        ];
    }
}
