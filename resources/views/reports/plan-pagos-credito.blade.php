<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Plan de Pagos - Crédito {{ $credito->numero_credito }}</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 10px; color: #000; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; border: 2px solid #000; padding: 15px; background: #f5f5f5; }
        .header h1 { margin: 0 0 10px 0; color: #000; font-size: 20px; font-weight: bold; }
        .header p { margin: 3px 0; color: #000; font-size: 10px; }

        .info-section { display: table; width: 100%; margin-bottom: 15px; }
        .info-col { display: table-cell; width: 50%; padding: 10px; border: 1px solid #ddd; vertical-align: top; }
        .info-label { font-weight: bold; color: #333; display: inline-block; width: 120px; }
        .info-value { color: #000; }

        .resumen-financiero { width: 100%; margin: 15px 0; border-collapse: collapse; }
        .resumen-financiero th { background: #2c3e50; color: white; padding: 8px; text-align: left; font-size: 11px; border: 1px solid #000; }
        .resumen-financiero td { padding: 8px; border: 1px solid #ddd; background: #f9f9f9; }
        .resumen-financiero .amount { text-align: right; font-weight: bold; font-size: 11px; }

        .table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 9px; }
        .table thead { background: #34495e; color: white; }
        .table th { padding: 8px 4px; text-align: center; border: 1px solid #000; font-size: 9px; }
        .table td { border: 1px solid #ddd; padding: 6px 4px; text-align: center; }
        .table tbody tr:nth-child(even) { background: #f9f9f9; }
        .table tbody tr:nth-child(odd) { background: #ffffff; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .badge { padding: 3px 8px; border: 1px solid #000; font-size: 8px; display: inline-block; font-weight: bold; border-radius: 3px; }
        .badge-vigente { background: #d4edda; color: #155724; }
        .badge-liquidado { background: #fff3cd; color: #856404; }

        .gastos-section { margin: 15px 0; padding: 10px; background: #fff8dc; border: 1px solid #daa520; }
        .gastos-section h3 { margin: 0 0 8px 0; font-size: 12px; color: #b8860b; }
        .gastos-grid { display: table; width: 100%; }
        .gasto-item { display: table-cell; width: 25%; padding: 5px; }
        .gasto-label { font-size: 9px; color: #666; }
        .gasto-value { font-weight: bold; font-size: 10px; color: #000; }

        .footer { margin-top: 30px; text-align: center; color: #666; font-size: 8px; border-top: 1px solid #ddd; padding-top: 10px; }

        .highlight-total { background: #e8f5e9 !important; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PLAN DE PAGOS - VENTA A CRÉDITO</h1>
        <p><strong>{{ $venta->sucursal->nombre ?? 'DIGIPRENDA' }}</strong></p>
        <p>{{ $venta->sucursal->direccion ?? '' }}</p>
        <p>Teléfono: {{ $venta->sucursal->telefono ?? '' }}</p>
    </div>

    <!-- Información del Crédito -->
    <div class="info-section">
        <div class="info-col">
            <p><span class="info-label">No. Crédito:</span> <span class="info-value">{{ $credito->numero_credito }}</span></p>
            <p><span class="info-label">No. Venta:</span> <span class="info-value">{{ $venta->codigo_venta }}</span></p>
            <p><span class="info-label">Cliente:</span> <span class="info-value">{{ $venta->cliente->nombres ?? '' }} {{ $venta->cliente->apellidos ?? '' }}</span></p>
            <p><span class="info-label">DPI/NIT:</span> <span class="info-value">{{ $venta->cliente->dpi ?? $venta->cliente->nit ?? 'N/A' }}</span></p>
            <p><span class="info-label">Teléfono:</span> <span class="info-value">{{ $venta->cliente->telefono ?? 'N/A' }}</span></p>
        </div>
        <div class="info-col">
            <p><span class="info-label">Fecha Crédito:</span> <span class="info-value">{{ \Carbon\Carbon::parse($credito->fecha_credito)->format('d/m/Y') }}</span></p>
            <p><span class="info-label">Fecha Vencimiento:</span> <span class="info-value">{{ \Carbon\Carbon::parse($credito->fecha_vencimiento)->format('d/m/Y') }}</span></p>
            <p><span class="info-label">Estado:</span> <span class="badge badge-{{ strtolower($credito->estado) }}">{{ strtoupper($credito->estado) }}</span></p>
            <p><span class="info-label">Vendedor:</span> <span class="info-value">{{ $venta->vendedor->name ?? 'N/A' }}</span></p>
            <p><span class="info-label">Sucursal:</span> <span class="info-value">{{ $venta->sucursal->nombre ?? 'N/A' }}</span></p>
        </div>
    </div>

    <!-- Resumen Financiero -->
    <table class="resumen-financiero">
        <thead>
            <tr>
                <th>CONCEPTO</th>
                <th>MONTO</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Monto Venta</td>
                <td class="amount">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->monto_venta, 2) }}</td>
            </tr>
            <tr>
                <td>Enganche Pagado</td>
                <td class="amount">-{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->enganche, 2) }}</td>
            </tr>
            <tr style="background: #e3f2fd;">
                <td><strong>Saldo a Financiar</strong></td>
                <td class="amount"><strong>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->saldo_financiar, 2) }}</strong></td>
            </tr>
            <tr>
                <td>Interés Total ({{ number_format($credito->tasa_interes, 2) }}% mensual x {{ $credito->numero_cuotas }} cuotas)</td>
                <td class="amount">+{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->interes_total, 2) }}</td>
            </tr>
            @if($credito->total_gastos > 0)
            <tr>
                <td>Gastos Adicionales</td>
                <td class="amount">+{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->total_gastos, 2) }}</td>
            </tr>
            @endif
            <tr class="highlight-total">
                <td><strong>TOTAL CRÉDITO</strong></td>
                <td class="amount"><strong>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->total_credito, 2) }}</strong></td>
            </tr>
            <tr>
                <td>Número de Cuotas</td>
                <td class="amount">{{ $credito->numero_cuotas }}</td>
            </tr>
            <tr style="background: #e8f5e9;">
                <td><strong>Cuota Mensual</strong></td>
                <td class="amount"><strong>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->monto_cuota, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <!-- Desglose de Gastos Adicionales -->
    @if($credito->total_gastos > 0)
    <div class="gastos-section">
        <h3>Desglose de Gastos Adicionales</h3>
        <div class="gastos-grid">
            <div class="gasto-item">
                <div class="gasto-label">Seguro</div>
                <div class="gasto-value">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->gasto_seguro, 2) }}</div>
            </div>
            <div class="gasto-item">
                <div class="gasto-label">Estudio</div>
                <div class="gasto-value">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->gasto_estudio, 2) }}</div>
            </div>
            <div class="gasto-item">
                <div class="gasto-label">Apertura</div>
                <div class="gasto-value">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->gasto_apertura, 2) }}</div>
            </div>
            <div class="gasto-item">
                <div class="gasto-label">Otros</div>
                <div class="gasto-value">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($credito->gasto_otros, 2) }}</div>
            </div>
        </div>
    </div>
    @endif

    <!-- Plan de Pagos Detallado -->
    <h3 style="margin-top: 20px; margin-bottom: 10px; font-size: 14px; color: #2c3e50;">Plan de Pagos Detallado</h3>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Fecha<br>Vencimiento</th>
                <th>Capital</th>
                <th>Interés</th>
                @if($credito->total_gastos > 0)
                <th>Gastos</th>
                @endif
                <th>Cuota<br>Total</th>
                <th>Saldo<br>Capital</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @php
                $gastosPorCuota = $credito->numero_cuotas > 0 ? $credito->total_gastos / $credito->numero_cuotas : 0;
            @endphp
            @foreach($planPagos as $cuota)
                <tr>
                    <td>{{ $cuota->numero_cuota }}</td>
                    <td>{{ \Carbon\Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') }}</td>
                    <td class="text-right">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($cuota->capital_proyectado, 2) }}</td>
                    <td class="text-right">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($cuota->interes_proyectado, 2) }}</td>
                    @if($credito->total_gastos > 0)
                    <td class="text-right">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($gastosPorCuota, 2) }}</td>
                    @endif
                    <td class="text-right"><strong>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($cuota->monto_cuota_proyectado, 2) }}</strong></td>
                    <td class="text-right">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($cuota->saldo_capital_credito, 2) }}</td>
                    <td>
                        @if($cuota->estado === 'pagada')
                            <span class="badge" style="background: #d4edda; color: #155724;">PAGADA</span>
                        @elseif($cuota->estado === 'vencida')
                            <span class="badge" style="background: #f8d7da; color: #721c24;">VENCIDA</span>
                        @else
                            <span class="badge" style="background: #fff3cd; color: #856404;">PENDIENTE</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Observaciones -->
    @if($credito->observaciones)
    <div style="margin-top: 15px; padding: 10px; background: #f5f5f5; border-left: 3px solid #2c3e50;">
        <strong style="font-size: 10px;">Observaciones:</strong>
        <p style="margin: 5px 0 0 0; font-size: 9px;">{{ $credito->observaciones }}</p>
    </div>
    @endif

    <!-- Notas -->
    <div style="margin-top: 20px; padding: 10px; border: 1px dashed #999; background: #fafafa;">
        <p style="margin: 0; font-size: 8px; color: #666;"><strong>NOTAS IMPORTANTES:</strong></p>
        <ul style="margin: 5px 0 0 15px; padding: 0; font-size: 8px; color: #666;">
            <li>El pago de cada cuota debe realizarse en o antes de la fecha de vencimiento indicada.</li>
            <li>Los pagos atrasados pueden generar intereses moratorios según política de la empresa.</li>
            <li>Este documento es un plan de pagos proyectado. Consulte el estado real en la empresa.</li>
            <li>Conserve este documento para su control y referencia.</li>
        </ul>
    </div>

    <div class="footer">
        <p>Documento generado el {{ now()->format('d/m/Y H:i') }}</p>
        <p>Este es un documento informativo | DIGIPRENDA - Sistema de Gestión de Empeños</p>
    </div>
</body>
</html>
