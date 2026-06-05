<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pago</title>
    <style>
        @page { margin: 0.8cm; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 10px;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #16a34a;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .brand-title  { font-size: 18px; font-weight: bold; text-transform: uppercase; color: #1e293b; }
        .brand-sub    { font-size: 9px; color: #64748b; margin-top: 2px; }
        .doc-title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            color: #16a34a;
            margin: 10px 0 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .tipo-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
            margin-bottom: 10px;
        }
        .info-grid  { display: table; width: 100%; margin-bottom: 10px; }
        .info-row   { display: table-row; }
        .info-cell  { display: table-cell; width: 50%; vertical-align: top; padding: 2px 6px 4px 0; }
        .label      { font-size: 8px; color: #64748b; text-transform: uppercase; font-weight: bold; display: block; }
        .value      { font-size: 10px; color: #1e293b; font-weight: 500; }
        .value-mono { font-family: 'Courier New', monospace; font-size: 11px; font-weight: bold; color: #15803d; }
        .desglose-box {
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            overflow: hidden;
            margin: 10px 0;
        }
        .desglose-header {
            background: #f0fdf4;
            padding: 5px 10px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #15803d;
            border-bottom: 1px solid #bbf7d0;
        }
        .desglose-row { display: table; width: 100%; border-bottom: 1px solid #f0fdf4; }
        .desglose-row:last-child { border-bottom: none; }
        .dr-label { display: table-cell; padding: 4px 10px; font-size: 10px; color: #475569; width: 60%; }
        .dr-value { display: table-cell; padding: 4px 10px; font-size: 10px; text-align: right; font-weight: 500; }
        .dr-total { background: #f0fdf4; border-top: 2px solid #16a34a !important; }
        .dr-total .dr-label { font-weight: bold; color: #1e293b; font-size: 11px; }
        .dr-total .dr-value { font-weight: bold; color: #16a34a; font-size: 13px; }
        .text-orange { color: #d97706; }
        .text-red    { color: #dc2626; }
        .saldo-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 8px 12px;
            margin: 10px 0;
        }
        .saldo-row { display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 3px; }
        .divider { border: none; border-top: 1px dashed #cbd5e1; margin: 10px 0; }
        .firma-area  { display: table; width: 100%; margin-top: 20px; }
        .firma-cell  { display: table-cell; width: 45%; text-align: center; padding-top: 5px; }
        .firma-line  { border-top: 1px solid #334155; margin: 0 auto; width: 80%; }
        .firma-label { font-size: 8px; color: #64748b; margin-top: 3px; }
        .footer {
            margin-top: 12px;
            font-size: 7.5px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
    </style>
</head>
<body>

<div class="header">
    <div class="brand-title">{{ $sucursal->nombre ?? config('app.name', 'Avanza') }}</div>
    @if(!empty($sucursal->direccion))
    <div class="brand-sub">{{ $sucursal->direccion }}</div>
    @endif
    @if(!empty($sucursal->telefono))
    <div class="brand-sub">Tel: {{ $sucursal->telefono }}</div>
    @endif
</div>

<div class="doc-title">Recibo de Pago — Venta a Crédito</div>

<div style="text-align:center">
    <span class="tipo-badge">
        @if(isset($esParcial) && $esParcial)
            Abono Parcial
        @elseif(isset($esLiquidacion) && $esLiquidacion)
            Liquidación Total
        @else
            Pago
        @endif
    </span>
</div>

{{-- DATOS DEL RECIBO --}}
<div class="info-grid">
    <div class="info-row">
        <div class="info-cell">
            <span class="label">No. de Venta</span>
            <span class="value value-mono">{{ $venta->codigo_venta ?? ('VTA-' . $venta->id) }}</span>
        </div>
        <div class="info-cell">
            <span class="label">Fecha y Hora</span>
            <span class="value">{{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }}</span>
        </div>
    </div>
</div>

{{-- DATOS DEL CRÉDITO/PLAN --}}
<div class="info-grid">
    <div class="info-row">
        <div class="info-cell">
            <span class="label">No. de Crédito</span>
            <span class="value value-mono">{{ $ventaCredito->numero_credito ?? ('CRED-' . $ventaCredito->id) }}</span>
        </div>
        <div class="info-cell">
            <span class="label">Cuota</span>
            <span class="value"># {{ $cuota->numero_cuota }} de {{ $ventaCredito->numero_cuotas ?? '—' }}</span>
        </div>
    </div>
</div>

{{-- CLIENTE --}}
<div class="info-grid">
    <div class="info-row">
        <div class="info-cell">
            <span class="label">Cliente</span>
            <span class="value">{{ $clienteNombre }}</span>
        </div>
        <div class="info-cell">
            <span class="label">DPI / NIT</span>
            <span class="value">{{ $clienteDoc ?? 'N/A' }}</span>
        </div>
    </div>
</div>

<hr class="divider">

{{-- DESGLOSE --}}
<div class="desglose-box">
    <div class="desglose-header">Desglose del Pago</div>

    @if($cuota->capital_pagado > 0)
    <div class="desglose-row">
        <span class="dr-label">Capital Pagado</span>
        <span class="dr-value">Q {{ number_format($cuota->capital_pagado, 2, '.', ',') }}</span>
    </div>
    @endif

    @if(($cuota->interes_pagado ?? 0) > 0)
    <div class="desglose-row">
        <span class="dr-label">Interés Pagado</span>
        <span class="dr-value text-orange">Q {{ number_format($cuota->interes_pagado, 2, '.', ',') }}</span>
    </div>
    @endif

    @if(($cuota->mora_pagada ?? 0) > 0)
    <div class="desglose-row">
        <span class="dr-label">Mora Pagada</span>
        <span class="dr-value text-red">Q {{ number_format($cuota->mora_pagada, 2, '.', ',') }}</span>
    </div>
    @endif

    @if(($cuota->otros_cargos_pagados ?? 0) > 0)
    <div class="desglose-row">
        <span class="dr-label">Otros Cargos</span>
        <span class="dr-value">Q {{ number_format($cuota->otros_cargos_pagados, 2, '.', ',') }}</span>
    </div>
    @endif

    <div class="desglose-row dr-total">
        <span class="dr-label">TOTAL PAGADO</span>
        <span class="dr-value">Q {{ number_format($cuota->monto_total_pagado, 2, '.', ',') }}</span>
    </div>
</div>

{{-- SALDO --}}
<div class="saldo-box">
    <div class="saldo-row">
        <span style="color:#64748b;">Saldo del Crédito Después del Pago:</span>
        <span style="font-weight:bold; color: {{ ($ventaCredito->saldo_actual ?? 0) > 0 ? '#dc2626' : '#16a34a' }}">
            Q {{ number_format($ventaCredito->saldo_actual ?? 0, 2, '.', ',') }}
        </span>
    </div>
    @if(($ventaCredito->saldo_actual ?? 0) <= 0.01)
    <div class="saldo-row" style="justify-content: center; margin-top: 4px;">
        <span style="color:#16a34a; font-weight:bold; font-size:11px;">✓ CRÉDITO LIQUIDADO COMPLETAMENTE</span>
    </div>
    @endif
    <div class="saldo-row">
        <span style="color:#64748b;">Cuotas Pagadas:</span>
        <span style="font-weight:bold;">{{ $ventaCredito->cuotas_pagadas ?? '—' }} de {{ $ventaCredito->numero_cuotas ?? '—' }}</span>
    </div>
</div>

<hr class="divider">

<div class="firma-area">
    <div class="firma-cell">
        <div class="firma-line"></div>
        <div class="firma-label">Firma del Cajero</div>
    </div>
    <div class="firma-cell" style="width: 10%;"></div>
    <div class="firma-cell">
        <div class="firma-line"></div>
        <div class="firma-label">Firma del Cliente</div>
    </div>
</div>

<div class="footer">
    <p>Este documento es un comprobante oficial de pago. Consérvelo para sus registros.</p>
    <p>{{ config('app.name', 'Avanza') }} &bull; {{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }} &bull; {{ $sucursal->nombre ?? '' }}</p>
</div>

</body>
</html>
