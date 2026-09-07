<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $asiento->numero_comprobante }} - {{ $tituloDocumento ?? 'Partida Contable' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            line-height: 1.35;
            color: #111;
            padding: 24px;
        }
        .header {
            width: 100%;
            margin-bottom: 14px;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }
        .logo-cell {
            width: 90px;
            text-align: left;
        }
        .logo-img {
            max-width: 80px;
            max-height: 60px;
            object-fit: contain;
        }
        .title-cell {
            text-align: center;
        }
        .title-cell h1 {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .title-cell h2 {
            font-size: 11px;
            font-weight: normal;
            color: #333;
            margin-bottom: 2px;
        }
        .title-cell .empresa-sub {
            font-size: 9px;
            color: #555;
        }
        .info-box {
            width: 100%;
            border: 1px solid #333;
            background-color: #fafafa;
            margin-bottom: 14px;
            padding: 8px 10px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            font-size: 9px;
            padding: 2px 4px;
            vertical-align: top;
            border: none;
        }
        .info-table strong {
            color: #000;
        }
        .glosa-box {
            width: 100%;
            border: 1px solid #666;
            background-color: #f5f5f5;
            padding: 6px 10px;
            margin-bottom: 14px;
            font-size: 9px;
        }
        .glosa-box strong {
            display: inline-block;
            margin-bottom: 2px;
        }
        .movimientos-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .movimientos-table th,
        .movimientos-table td {
            border: 1px solid #333;
            padding: 5px 6px;
            font-size: 9px;
        }
        .movimientos-table th {
            background-color: #eaeaea;
            font-weight: bold;
            text-transform: uppercase;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totales-row td {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 10px;
            border-top: 2px solid #000;
        }
        .cuadre-status {
            margin-bottom: 20px;
            padding: 6px;
            font-size: 9px;
            text-align: center;
            font-weight: bold;
            border: 1px dashed #333;
            background-color: #fdfdfd;
        }
        .signatures {
            margin-top: 35px;
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .signatures td {
            width: 45%;
            text-align: center;
            vertical-align: top;
            font-size: 9px;
            border: none;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 4px;
            padding-top: 4px;
        }
        .footer {
            margin-top: 25px;
            border-top: 1px dotted #888;
            padding-top: 5px;
            font-size: 8px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="header">
        <table class="header-table">
            <tr>
                @if(!empty($mostrarLogo) && !empty($empresa['logo_base64']))
                <td class="logo-cell">
                    <img src="{{ $empresa['logo_base64'] }}" class="logo-img" alt="Logo">
                </td>
                @endif
                <td class="title-cell">
                    <h1>{{ $empresa['nombre'] ?? 'MICROSYSTEM PLUS' }}</h1>
                    <h2>{{ $tituloDocumento ?? 'PÓLIZA DE DIARIO / PARTIDA CONTABLE' }}</h2>
                    <div class="empresa-sub">
                        NIT: {{ $empresa['nit'] ?? 'C/F' }} &bull; {{ $empresa['direccion'] ?? 'Guatemala' }} &bull; TEL: {{ $empresa['telefono'] ?? '' }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Metadatos de la Póliza / Partida --}}
    <div class="info-box">
        <table class="info-table">
            <tr>
                <td width="33%">
                    <strong>NO. COMPROBANTE:</strong><br>
                    <span style="font-size: 11px; font-weight: bold;">{{ $asiento->numero_comprobante }}</span>
                </td>
                <td width="33%">
                    <strong>FECHA CONTABLE:</strong><br>
                    <span>{{ \Carbon\Carbon::parse($asiento->fecha_contabilizacion)->format('d/m/Y') }}</span>
                </td>
                <td width="34%">
                    <strong>TIPO DE PÓLIZA:</strong><br>
                    <span>{{ $asiento->tipoPoliza->codigo ?? 'PD' }} - {{ $asiento->tipoPoliza->nombre ?? 'Diario' }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <strong>SUCURSAL:</strong><br>
                    <span>{{ $asiento->sucursal->nombre ?? 'Central / Todas' }}</span>
                </td>
                <td>
                    <strong>DOCUMENTO DE SOPORTE:</strong><br>
                    <span>{{ $asiento->numero_documento ?: 'S/D' }}</span>
                </td>
                <td>
                    <strong>ORIGEN / OPERACIÓN:</strong><br>
                    <span style="text-transform: uppercase;">{{ str_replace('_', ' ', $asiento->tipo_origen ?? 'manual') }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- Glosa o Concepto General --}}
    <div class="glosa-box">
        <strong>CONCEPTO / GLOSA:</strong><br>
        {{ $asiento->glosa ?: 'Sin descripción registrada' }}
    </div>

    {{-- Tabla de Cuentas y Movimientos --}}
    <table class="movimientos-table">
        <thead>
            <tr>
                <th width="8%" class="text-center">#</th>
                <th width="20%">CÓDIGO CUENTA</th>
                <th width="36%">NOMBRE DE CUENTA</th>
                <th width="18%" class="text-right">DEBE</th>
                <th width="18%" class="text-right">HABER</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sumDebe = 0;
                $sumHaber = 0;
            @endphp
            @foreach($asiento->movimientos as $index => $m)
                @php
                    $sumDebe += (float) $m->debe;
                    $sumHaber += (float) $m->haber;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td style="font-weight: bold;">{{ $m->cuentaContable->codigo_cuenta ?? $m->cuenta_contable_id }}</td>
                    <td>
                        {{ $m->cuentaContable->nombre_cuenta ?? '—' }}
                        @if(!empty($m->detalle) && $m->detalle !== $asiento->glosa)
                            <br><span style="font-size: 8px; color: #555;">{{ $m->detalle }}</span>
                        @endif
                    </td>
                    <td class="text-right">
                        {{ $m->debe > 0 ? 'Q ' . number_format((float)$m->debe, 2) : '—' }}
                    </td>
                    <td class="text-right">
                        {{ $m->haber > 0 ? 'Q ' . number_format((float)$m->haber, 2) : '—' }}
                    </td>
                </tr>
            @endforeach
            <tr class="totales-row">
                <td colspan="3" class="text-right">SUMAS IGUALES:</td>
                <td class="text-right">Q {{ number_format($sumDebe, 2) }}</td>
                <td class="text-right">Q {{ number_format($sumHaber, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Verificación de Cuadre --}}
    @php
        $esCuadrado = abs($sumDebe - $sumHaber) < 0.01 && $sumDebe > 0;
    @endphp
    <div class="cuadre-status">
        @if($esCuadrado)
            <span>✓ PARTIDA DOBLE CUADRADA EXACTA (SUMAS IGUALES: Q {{ number_format($sumDebe, 2) }})</span>
        @else
            <span style="color: #c00;">⚠ PARTIDA DESCUADRADA (DIFERENCIA: Q {{ number_format(abs($sumDebe - $sumHaber), 2) }})</span>
        @endif
    </div>

    {{-- Firmas Oficiales --}}
    @if(!empty($mostrarFirmas))
    <table class="signatures">
        <tr>
            <td>
                <div class="signature-line">
                    <strong>{{ $firmante1Nombre ?? ($asiento->usuario->name ?? 'PERITO CONTADOR') }}</strong><br>
                    <span>{{ $firmante1Titulo ?? 'CONTADOR GENERAL / ELABORÓ' }}</span>
                </div>
            </td>
            <td width="10%"></td>
            <td>
                <div class="signature-line">
                    <strong>{{ $firmante2Nombre ?? 'REPRESENTANTE LEGAL' }}</strong><br>
                    <span>{{ $firmante2Titulo ?? 'REVISÓ Y AUTORIZÓ' }}</span>
                </div>
            </td>
        </tr>
    </table>
    @endif

    <div class="footer">
        {{ $piePaginaTexto ?? 'Comprobante contable oficial de operaciones en partida doble.' }}<br>
        Generado por: {{ Auth::user()->name ?? 'Sistema' }} &bull; Fecha de impresión: {{ $fecha_generacion ?? date('d/m/Y H:i') }} &bull; Estado: {{ strtoupper($asiento->estado) }}
    </div>

</body>
</html>
