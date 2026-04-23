<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pago - {{ $movimiento->numero_movimiento }}</title>
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
        /* ===== CABECERA ===== */
        .header {
            text-align: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .brand-title  { font-size: 18px; font-weight: bold; text-transform: uppercase; color: #1e293b; }
        .brand-sub    { font-size: 9px; color: #64748b; margin-top: 2px; }
        .doc-title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            color: #2563eb;
            margin: 10px 0 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        /* ===== BADGE DE TIPO ===== */
        .tipo-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
            margin-bottom: 10px;
        }
        /* ===== GRILLA DE INFO ===== */
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        .info-row   { display: table-row; }
        .info-cell  { display: table-cell; width: 50%; vertical-align: top; padding: 2px 6px 4px 0; }
        .label      { font-size: 8px; color: #64748b; text-transform: uppercase; font-weight: bold; display: block; }
        .value      { font-size: 10px; color: #1e293b; font-weight: 500; }
        .value-mono { font-family: 'Courier New', monospace; font-size: 11px; font-weight: bold; color: #1e40af; }
        /* ===== CAJA DE DESGLOSE ===== */
        .desglose-box {
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            overflow: hidden;
            margin: 10px 0;
        }
        .desglose-header {
            background: #f1f5f9;
            padding: 5px 10px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }
        .desglose-row {
            display: table;
            width: 100%;
            border-bottom: 1px solid #f1f5f9;
        }
        .desglose-row:last-child { border-bottom: none; }
        .dr-label { display: table-cell; padding: 4px 10px; font-size: 10px; color: #475569; width: 60%; }
        .dr-value { display: table-cell; padding: 4px 10px; font-size: 10px; text-align: right; font-weight: 500; }
        .dr-total .dr-label { font-weight: bold; color: #1e293b; font-size: 11px; }
        .dr-total .dr-value { font-weight: bold; color: #2563eb; font-size: 13px; }
        .dr-total { background: #eff6ff; border-top: 2px solid #2563eb !important; }
        .text-orange { color: #d97706; }
        .text-red    { color: #dc2626; }
        .text-green  { color: #16a34a; }
        .text-blue   { color: #2563eb; }
        /* ===== SALDO RESTANTE ===== */
        .saldo-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 8px 12px;
            margin: 10px 0;
        }
        .saldo-row { display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 3px; }
        .saldo-row:last-child { margin-bottom: 0; }
        /* ===== PIE ===== */
        .divider { border: none; border-top: 1px dashed #cbd5e1; margin: 10px 0; }
        .firma-area {
            display: table;
            width: 100%;
            margin-top: 20px;
        }
        .firma-cell {
            display: table-cell;
            width: 45%;
            text-align: center;
            padding-top: 5px;
        }
        .firma-line { border-top: 1px solid #334155; margin: 0 auto; width: 80%; }
        .firma-label { font-size: 8px; color: #64748b; margin-top: 3px; }
        .footer {
            margin-top: 12px;
            font-size: 7.5px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
        /* ===== ESTADO TAG ===== */
        .estado-pagado { color: #16a34a; font-weight: bold; }
    </style>
</head>
<body>

{{-- ENCABEZADO --}}
<div class="header">
    <div class="brand-title">{{ $sucursal->nombre ?? config('app.name', 'DigiPrenda') }}</div>
    @if(!empty($sucursal->direccion))
    <div class="brand-sub">{{ $sucursal->direccion }}</div>
    @endif
    @if(!empty($sucursal->telefono))
    <div class="brand-sub">Tel: {{ $sucursal->telefono }}</div>
    @endif
</div>

<div class="doc-title">Recibo de Pago</div>

{{-- BADGE TIPO DE OPERACIÓN --}}
<div style="text-align:center">
    <span class="tipo-badge">
        @switch($movimiento->tipo_movimiento)
            @case('pago')        Pago de Cuota @break
            @case('renovacion')  Renovación @break
            @case('adelanto')    Pago Adelantado @break
            @case('parcial')     Abono Parcial @break
            @case('liquidacion') Liquidación Total @break
            @case('pago_parcial') Abono Parcial @break
            @case('pago_total')  Liquidación Total @break
            @default             {{ ucfirst(str_replace('_',' ', $movimiento->tipo_movimiento)) }}
        @endswitch
    </span>
</div>

{{-- DATOS DEL RECIBO --}}
<div class="info-grid">
    <div class="info-row">
        <div class="info-cell">
            <span class="label">No. de Movimiento</span>
            <span class="value value-mono">{{ $movimiento->numero_movimiento }}</span>
        </div>
        <div class="info-cell">
            <span class="label">Fecha y Hora</span>
            <span class="value">{{ \Carbon\Carbon::parse($movimiento->fecha_registro ?? $movimiento->fecha_movimiento)->format('d/m/Y H:i:s') }}</span>
        </div>
    </div>
</div>

{{-- DATOS DEL CRÉDITO --}}
<div class="info-grid">
    <div class="info-row">
        <div class="info-cell">
            <span class="label">No. de Crédito</span>
            <span class="value value-mono">{{ $credito->numero_credito }}</span>
        </div>
        <div class="info-cell">
            <span class="label">Estado del Crédito</span>
            <span class="value estado-pagado">{{ ucfirst($credito->estado) }}</span>
        </div>
    </div>
</div>

{{-- DATOS DEL CLIENTE --}}
<div class="info-grid">
    <div class="info-row">
        <div class="info-cell">
            <span class="label">Cliente</span>
            <span class="value">{{ trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? '')) }}</span>
        </div>
        <div class="info-cell">
            <span class="label">DPI / Identificación</span>
            <span class="value">{{ $cliente->dpi ?? $cliente->cui ?? 'N/A' }}</span>
        </div>
    </div>
    @if(!empty($cliente->telefono))
    <div class="info-row">
        <div class="info-cell">
            <span class="label">Teléfono</span>
            <span class="value">{{ $cliente->telefono }}</span>
        </div>
        <div class="info-cell"></div>
    </div>
    @endif
</div>

{{-- PRENDAS --}}
@if($prendas && $prendas->count() > 0)
<div style="margin-bottom: 10px;">
    <span class="label" style="margin-bottom: 4px; display:block;">Prenda(s) Empeñada(s)</span>
    @foreach($prendas as $prenda)
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; padding:5px 8px; margin-bottom:4px; font-size:9px;">
        <strong>{{ $prenda->descripcion ?? $prenda->descripcion_general ?? 'Sin descripción' }}</strong>
        @if(!empty($prenda->marca)) &bull; {{ $prenda->marca }} @endif
        @if(!empty($prenda->modelo)) {{ $prenda->modelo }} @endif
        @if(!empty($prenda->codigo_prenda))
        <br><span style="color:#64748b;">Cód: {{ $prenda->codigo_prenda }}</span>
        @endif
    </div>
    @endforeach
</div>
@endif

<hr class="divider">

{{-- DESGLOSE DEL PAGO --}}
<div class="desglose-box">
    <div class="desglose-header">Desglose del Pago</div>

    @if($movimiento->capital > 0)
    <div class="desglose-row">
        <span class="dr-label">Capital Pagado</span>
        <span class="dr-value">Q {{ number_format($movimiento->capital, 2, '.', ',') }}</span>
    </div>
    @endif

    @if($movimiento->interes > 0)
    <div class="desglose-row">
        <span class="dr-label">Interés Pagado</span>
        <span class="dr-value text-orange">Q {{ number_format($movimiento->interes, 2, '.', ',') }}</span>
    </div>
    @endif

    @if($movimiento->mora > 0)
    <div class="desglose-row">
        <span class="dr-label">Mora Pagada</span>
        <span class="dr-value text-red">Q {{ number_format($movimiento->mora, 2, '.', ',') }}</span>
    </div>
    @endif

    @if(isset($movimiento->otros_cargos) && $movimiento->otros_cargos > 0)
    <div class="desglose-row">
        <span class="dr-label">Otros Cargos</span>
        <span class="dr-value">Q {{ number_format($movimiento->otros_cargos, 2, '.', ',') }}</span>
    </div>
    @endif

    <div class="desglose-row dr-total">
        <span class="dr-label">TOTAL PAGADO</span>
        <span class="dr-value">Q {{ number_format($movimiento->monto_total, 2, '.', ',') }}</span>
    </div>
</div>

{{-- MÉTODO DE PAGO --}}
<div class="info-grid" style="margin-bottom: 6px;">
    <div class="info-row">
        <div class="info-cell">
            <span class="label">Método de Pago</span>
            <span class="value">{{ ucfirst($movimiento->forma_pago ?? 'Efectivo') }}</span>
        </div>
        @if(!empty($movimiento->numero_cuota))
        <div class="info-cell">
            <span class="label">Cuota No.</span>
            <span class="value"># {{ $movimiento->numero_cuota }}</span>
        </div>
        @endif
    </div>
    @if(!empty($movimiento->observaciones))
    <div class="info-row">
        <div class="info-cell" style="width:100%">
            <span class="label">Observaciones</span>
            <span class="value">{{ $movimiento->observaciones }}</span>
        </div>
    </div>
    @endif
</div>

{{-- SALDO RESTANTE --}}
<div class="saldo-box">
    <div class="saldo-row">
        <span style="color:#64748b;">Capital Pendiente Después del Pago:</span>
        <span style="font-weight:bold; color: {{ $movimiento->saldo_capital > 0 ? '#dc2626' : '#16a34a' }}">
            Q {{ number_format($movimiento->saldo_capital ?? 0, 2, '.', ',') }}
        </span>
    </div>
    @if($movimiento->saldo_capital <= 0.01)
    <div class="saldo-row" style="justify-content: center; margin-top: 4px;">
        <span style="color:#16a34a; font-weight:bold; font-size:11px;">✓ CRÉDITO LIQUIDADO COMPLETAMENTE</span>
    </div>
    @endif
</div>

{{-- ATENDIDO POR --}}
@if(!empty($movimiento->usuario))
<div style="font-size: 9px; color: #64748b; margin-bottom: 6px;">
    Atendido por: <strong>{{ $movimiento->usuario->nombre ?? $movimiento->usuario->name ?? 'Sistema' }}</strong>
</div>
@endif

<hr class="divider">

{{-- ÁREA DE FIRMA --}}
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

{{-- PIE --}}
<div class="footer">
    <p>Este documento es un comprobante oficial de pago. Consérvelo para sus registros.</p>
    <p>{{ config('app.name', 'DigiPrenda') }} &bull; {{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }} &bull; {{ $sucursal->nombre ?? '' }}</p>
</div>

</body>
</html>
