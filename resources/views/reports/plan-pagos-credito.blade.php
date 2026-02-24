<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan de Pagos - {{ $credito->numero_credito }}</title>
    <style>
        @page { margin: 0cm 0cm; }
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
            border-bottom: 3px solid #1e293b;
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
        .brand-title { font-size: 20px; font-weight: bold; color: #1e293b; padding-top: 15px; text-transform: uppercase; }
        .brand-subtitle { font-size: 12px; color: #64748b; }
        .document-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            color: #1e293b;
            margin-top: 10px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .info-grid { display: table; width: 100%; margin-bottom: 25px; background-color: #fff; }
        .info-col { display: table-cell; width: 50%; vertical-align: top; padding: 10px; }
        .info-item { margin-bottom: 8px; }
        .info-label { font-weight: bold; color: #64748b; font-size: 9px; text-transform: uppercase; display: block; }
        .info-value { color: #1e293b; font-weight: 500; font-size: 11px; }
        .amount-value { color: #1e293b; font-weight: bold; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background-color: #fff; }
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
        td { padding: 8px; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .status-badge { padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .status-paid { background-color: #dcfce7; color: #166534; }
        .status-pending { background-color: #f1f5f9; color: #475569; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; }
        .status-parcial { background-color: #fef3c7; color: #92400e; }
        .total-row td { background-color: #e2e8f0; font-weight: bold; color: #1e293b; border-top: 2px solid #cbd5e1; }
        .resumen-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .resumen-table th { background: #f1f5f9; padding: 8px; text-align: left; font-size: 10px; }
        .resumen-table td { padding: 8px; border: 1px solid #e2e8f0; }
        .resumen-table .amount { text-align: right; font-weight: bold; }
        .gastos-section { margin: 15px 0; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .gastos-section h3 { margin: 0 0 8px 0; font-size: 11px; color: #475569; }
        .gastos-grid { display: table; width: 100%; }
        .gasto-item { display: table-cell; width: 25%; padding: 5px; }
        .disclaimer { margin-top: 30px; font-size: 8px; color: #94a3b8; text-align: justify; font-style: italic; }
    </style>
</head>
<body>
    <header>
        <div class="brand-title">{{ $venta->sucursal->nombre ?? 'DIGIPRENDA' }}</div>
        <div class="brand-subtitle">{{ $venta->sucursal->direccion ?? 'Sistema de Gestión de Empeños' }}</div>
    </header>

    <footer>
        Página <span class="pagenum"></span> — Generado el {{ $fechaGeneracion ?? now()->format('d/m/Y H:i') }}
    </footer>

    <h2 class="document-title">Plan de Pagos — Venta a Crédito</h2>

    <div class="info-grid">
        <div class="info-col">
            <div class="info-item">
                <span class="info-label">No. Crédito</span>
                <span class="info-value">{{ $credito->numero_credito }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">No. Venta</span>
                <span class="info-value">{{ $venta->codigo_venta }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Cliente</span>
                <span class="info-value">{{ $venta->cliente->nombres ?? '' }} {{ $venta->cliente->apellidos ?? '' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">DPI / NIT</span>
                <span class="info-value">{{ $venta->cliente->dpi ?? $venta->cliente->nit ?? 'N/A' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Teléfono</span>
                <span class="info-value">{{ $venta->cliente->telefono ?? 'N/A' }}</span>
            </div>
        </div>
        <div class="info-col">
            <div class="info-item">
                <span class="info-label">Fecha crédito</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($credito->fecha_credito)->format('d/m/Y') }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Vencimiento</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($credito->fecha_vencimiento)->format('d/m/Y') }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Estado</span>
                <span class="info-value">{{ strtoupper($credito->estado) }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Vendedor</span>
                <span class="info-value">{{ $venta->vendedor->name ?? 'N/A' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Sucursal</span>
                <span class="info-value">{{ $venta->sucursal->nombre ?? 'N/A' }}</span>
            </div>
        </div>
    </div>

    <table class="resumen-table">
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="amount">Monto</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Monto venta</td>
                <td class="amount">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->monto_venta, 2) }}</td>
            </tr>
            <tr>
                <td>Enganche pagado</td>
                <td class="amount">-{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->enganche, 2) }}</td>
            </tr>
            <tr style="background: #f1f5f9;">
                <td><strong>Saldo a financiar</strong></td>
                <td class="amount"><strong>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->saldo_financiar, 2) }}</strong></td>
            </tr>
            <tr>
                <td>Interés total ({{ number_format($credito->tasa_interes, 2) }}% × {{ $credito->numero_cuotas }} cuotas)</td>
                <td class="amount">+{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->interes_total, 2) }}</td>
            </tr>
            @if(($credito->total_gastos ?? 0) > 0)
            <tr>
                <td>Gastos adicionales</td>
                <td class="amount">+{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->total_gastos, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td><strong>Total crédito</strong></td>
                <td class="amount"><strong>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->total_credito, 2) }}</strong></td>
            </tr>
            <tr>
                <td>Número de cuotas</td>
                <td class="amount">{{ $credito->numero_cuotas }}</td>
            </tr>
            <tr style="background: #f8fafc;">
                <td><strong>Cuota periódica</strong></td>
                <td class="amount"><strong>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->monto_cuota, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    @if(($credito->total_gastos ?? 0) > 0)
    <div class="gastos-section">
        <h3>Desglose de gastos adicionales</h3>
        <div class="gastos-grid">
            <div class="gasto-item"><span class="info-label">Seguro</span><br><span class="amount-value">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->gasto_seguro ?? 0, 2) }}</span></div>
            <div class="gasto-item"><span class="info-label">Estudio</span><br><span class="amount-value">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->gasto_estudio ?? 0, 2) }}</span></div>
            <div class="gasto-item"><span class="info-label">Apertura</span><br><span class="amount-value">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->gasto_apertura ?? 0, 2) }}</span></div>
            <div class="gasto-item"><span class="info-label">Otros</span><br><span class="amount-value">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->gasto_otros ?? 0, 2) }}</span></div>
        </div>
    </div>
    @endif

    <h3 style="margin-top: 20px; margin-bottom: 10px; font-size: 12px; color: #1e293b;">Plan de pagos detallado</h3>
    <table>
        <thead>
            <tr>
                <th class="text-center" width="6%">#</th>
                <th width="14%">Vencimiento</th>
                <th class="text-right">Capital</th>
                <th class="text-right">Interés</th>
                @if(($credito->total_gastos ?? 0) > 0)
                <th class="text-right">Gastos</th>
                @endif
                <th class="text-right">Cuota total</th>
                <th class="text-right">Saldo capital</th>
                <th class="text-center" width="14%">Estado</th>
            </tr>
        </thead>
        <tbody>
            @php
                $gastosPorCuota = ($credito->numero_cuotas ?? 0) > 0 && ($credito->total_gastos ?? 0) > 0
                    ? $credito->total_gastos / $credito->numero_cuotas : 0;
            @endphp
            @foreach($planPagos as $cuota)
                <tr>
                    <td class="text-center">{{ $cuota->numero_cuota }}</td>
                    <td>{{ \Carbon\Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') }}</td>
                    <td class="text-right">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($cuota->capital_proyectado, 2) }}</td>
                    <td class="text-right">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($cuota->interes_proyectado, 2) }}</td>
                    @if(($credito->total_gastos ?? 0) > 0)
                    <td class="text-right">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($gastosPorCuota, 2) }}</td>
                    @endif
                    <td class="text-right"><strong>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($cuota->monto_cuota_proyectado, 2) }}</strong></td>
                    <td class="text-right">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($cuota->saldo_capital_credito, 2) }}</td>
                    <td class="text-center">
                        @if($cuota->estado === 'pagada')
                            <span class="status-badge status-paid">Pagada</span>
                        @elseif($cuota->estado === 'pagada_parcial')
                            <span class="status-badge status-parcial">Parcial</span>
                        @elseif($cuota->estado === 'vencida' || $cuota->estado === 'en_mora')
                            <span class="status-badge status-overdue">Vencida</span>
                        @else
                            <span class="status-badge status-pending">Pendiente</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(!empty($credito->observaciones))
    <div style="margin-top: 15px; padding: 10px; background: #f8fafc; border-left: 3px solid #64748b;">
        <strong style="font-size: 10px;">Observaciones</strong>
        <p style="margin: 5px 0 0 0; font-size: 9px;">{{ $credito->observaciones }}</p>
    </div>
    @endif

    <div class="disclaimer">
        <strong>Notas:</strong> El pago de cada cuota debe realizarse en o antes de la fecha de vencimiento. Los pagos atrasados pueden generar intereses moratorios según la política de la empresa. Este documento es informativo; consulte el estado actual del crédito en la empresa. Conserve este documento para su control.
    </div>
</body>
</html>
