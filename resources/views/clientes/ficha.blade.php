<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Cliente - {{ $cliente->nombre_completo }}</title>
    <style>
        @page {
            margin: 0cm 0cm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #444;
            line-height: 1.5;
            margin-top: 3cm;
            margin-bottom: 2cm;
            margin-left: 2cm;
            margin-right: 2cm;
        }
        header {
            position: fixed;
            top: 0cm;
            left: 0cm;
            right: 0cm;
            height: 2.5cm;
            background-color: #f8f9fa;
            color: #333;
            text-align: center;
            line-height: 30px;
            border-bottom: 3px solid #2563eb;
        }
        footer {
            position: fixed;
            bottom: 0cm;
            left: 0cm;
            right: 0cm;
            height: 1.5cm;
            background-color: #f8f9fa;
            color: #666;
            text-align: center;
            line-height: 1.5cm;
            border-top: 1px solid #ddd;
            font-size: 9px;
        }
        .brand-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e293b;
            padding-top: 15px;
            text-transform: uppercase;
        }
        .brand-subtitle {
            font-size: 12px;
            color: #64748b;
        }
        .document-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            color: #2563eb;
            margin-top: 10px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            background-color: #fff;
            border-radius: 8px;
        }
        .info-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 10px;
        }
        .info-item {
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            color: #64748b;
            font-size: 9px;
            text-transform: uppercase;
            display: block;
        }
        .info-value {
            color: #1e293b;
            font-weight: 500;
            font-size: 11px;
        }
        .amount-value {
            color: #2563eb;
            font-weight: bold;
            font-size: 12px;
        }
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }
        .stat-box {
            display: table-cell;
            width: 25%;
            padding: 15px;
            text-align: center;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }
        .stat-label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 16px;
            font-weight: bold;
            color: #2563eb;
        }
        .stat-value.red { color: #dc2626; }
        .stat-value.green { color: #16a34a; }
        .stat-value.purple { color: #9333ea; }
        .score-box {
            background-color: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .score-box.excellent { background-color: #dcfce7; border: 2px solid #16a34a; }
        .score-box.regular { background-color: #fef3c7; border: 2px solid #eab308; }
        .score-box.risky { background-color: #fee2e2; border: 2px solid #dc2626; }
        .score-title {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .score-value {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        .score-value.excellent { color: #16a34a; }
        .score-value.regular { color: #eab308; }
        .score-value.risky { color: #dc2626; }
        .score-points {
            font-size: 12px;
            color: #64748b;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #1e293b;
            margin-top: 25px;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            padding: 10px 8px;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: middle;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .status-badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-activo { background-color: #dcfce7; color: #166534; }
        .status-desembolsado { background-color: #dbeafe; color: #1e40af; }
        .status-liquidado { background-color: #e0e7ff; color: #4338ca; }
        .status-vencido { background-color: #fee2e2; color: #991b1b; }
        .status-en_mora { background-color: #fed7aa; color: #9a3412; }
        .disclaimer {
            margin-top: 30px;
            font-size: 8px;
            color: #94a3b8;
            text-align: justify;
            line-height: 1.4;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <header>
        <div class="brand-title">Microsystem Plus</div>
        <div class="brand-subtitle">Sistema de Créditos Prendarios</div>
    </header>

    <footer>
        Generado el {{ $fechaGeneracion }} | Documento confidencial - Para uso interno exclusivamente
    </footer>

    <div class="document-title">Ficha de Cliente</div>

    <!-- Información del Cliente -->
    <div class="info-grid">
        <div class="info-col">
            <div class="info-item">
                <span class="info-label">Código de Cliente</span>
                <span class="info-value">{{ $cliente->codigo_cliente ?? '—' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Nombre Completo</span>
                <span class="info-value">{{ $cliente->nombre_completo }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">DPI</span>
                <span class="info-value">{{ $cliente->dpi ?? '—' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Teléfono</span>
                <span class="info-value">{{ $cliente->telefono ?? '—' }}</span>
            </div>
        </div>
        <div class="info-col">
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value">{{ $cliente->email ?? '—' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Dirección</span>
                <span class="info-value">{{ $cliente->direccion ?? '—' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Municipio</span>
                <span class="info-value">{{ $cliente->municipio ?? '—' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Estado</span>
                <span class="info-value">{{ ucfirst($cliente->estado) }}</span>
            </div>
        </div>
    </div>

    <!-- Estadísticas Financieras -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-label">Monto Total Prestado</div>
            <div class="stat-value">Q {{ number_format($stats['monto_total_prestado'], 2) }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Saldo Pendiente</div>
            <div class="stat-value red">Q {{ number_format($stats['saldo_actual_total'], 2) }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Puntualidad</div>
            <div class="stat-value purple">
                {{ $comportamiento['tasa_puntualidad'] !== null ? round($comportamiento['tasa_puntualidad']) . '%' : '—' }}
            </div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Créditos Activos</div>
            <div class="stat-value green">{{ $stats['activos'] }}</div>
        </div>
    </div>

    <!-- Score de Riesgo -->
    <div class="score-box {{ strtolower($comportamiento['nivel']) === 'excelente' ? 'excellent' : (strtolower($comportamiento['nivel']) === 'regular' ? 'regular' : 'risky') }}">
        <div class="score-title">Calificación de Riesgo</div>
        <div class="score-value {{ strtolower($comportamiento['nivel']) === 'excelente' ? 'excellent' : (strtolower($comportamiento['nivel']) === 'regular' ? 'regular' : 'risky') }}">
            {{ $comportamiento['comportamiento'] }}
        </div>
        <div class="score-points">Score: {{ round($comportamiento['puntos']) }}/100</div>
        <div style="margin-top: 10px; font-size: 9px; color: #64748b;">
            {{ $comportamiento['pagos_puntuales'] }} de {{ $comportamiento['pagos_total'] }} pagos realizados a tiempo o adelantados
        </div>
    </div>

    <!-- Resumen de Créditos -->
    <div class="section-title">Resumen de Créditos</div>
    <div style="margin-bottom: 20px;">
        <div style="display: inline-block; margin-right: 20px;">
            <span style="font-weight: bold; color: #64748b;">Total Historial:</span> {{ $stats['total_historial'] }}
        </div>
        <div style="display: inline-block; margin-right: 20px;">
            <span style="font-weight: bold; color: #16a34a;">Liquidados:</span> {{ $stats['liquidados'] }}
        </div>
        <div style="display: inline-block;">
            <span style="font-weight: bold; color: #dc2626;">Vencidos:</span> {{ $stats['vencidos'] }}
        </div>
    </div>

    <!-- Créditos Activos -->
    @if($creditos_activos->count() > 0)
    <div class="section-title">Créditos Activos</div>
    <table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Fecha</th>
                <th class="text-right">Monto Aprobado</th>
                <th class="text-right">Capital Pendiente</th>
                <th class="text-right">Saldo Total</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($creditos_activos as $credito)
            <tr>
                <td>{{ $credito->codigo_credito ?? $credito->numero_credito }}</td>
                <td>{{ $credito->fecha_solicitud ? $credito->fecha_solicitud->format('d/m/Y') : '—' }}</td>
                <td class="text-right">Q {{ number_format($credito->monto_aprobado, 2) }}</td>
                <td class="text-right">Q {{ number_format($credito->capital_pendiente, 2) }}</td>
                <td class="text-right">
                    Q {{ number_format(
                        $credito->capital_pendiente +
                        ($credito->interes_generado - $credito->interes_pagado) +
                        ($credito->mora_generada - $credito->mora_pagada),
                        2
                    ) }}
                </td>
                <td class="text-center">
                    <span class="status-badge status-{{ str_replace(' ', '_', strtolower($credito->estado)) }}">
                        {{ ucfirst($credito->estado) }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Créditos Liquidados (últimos 5) -->
    @if($creditos_liquidados->count() > 0)
    <div class="section-title" style="margin-top: 30px;">Últimos Créditos Liquidados</div>
    <table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Fecha Solicitud</th>
                <th class="text-right">Monto</th>
                <th>Prendas</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($creditos_liquidados as $credito)
            <tr>
                <td>{{ $credito->codigo_credito ?? $credito->numero_credito }}</td>
                <td>{{ $credito->fecha_solicitud ? $credito->fecha_solicitud->format('d/m/Y') : '—' }}</td>
                <td class="text-right">Q {{ number_format($credito->monto_aprobado, 2) }}</td>
                <td>{{ $credito->prendas->count() }} prenda(s)</td>
                <td class="text-center">
                    <span class="status-badge status-liquidado">Liquidado</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="disclaimer">
        <strong>AVISO LEGAL:</strong> Este documento contiene información confidencial del cliente.
        Los datos presentados reflejan el estado financiero y comportamiento de pago al {{ $fechaGeneracion }}.
        El score de riesgo es calculado automáticamente basándose en el historial de pagos, créditos vencidos y mora activa.
        Esta ficha es de uso interno y no debe ser compartida sin autorización. La puntualidad se calcula considerando
        los pagos realizados a tiempo o adelantados respecto a la fecha de vencimiento.
    </div>
</body>
</html>
