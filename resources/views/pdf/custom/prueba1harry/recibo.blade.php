<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de Cuenta y Plan de Pagos - {{ $credito->codigo_credito ?? $credito->numero_credito ?? 'S/N' }}</title>
    <style>
        @page {
            size: letter;
            margin: 1.8cm 1.6cm 1.8cm 1.6cm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            font-size: 10pt;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        /* ===== ESTILOS PÁGINA 1: ESTADO DE CUENTA PRENDAMAS ===== */
        .page-1-wrapper {
            width: 100%;
        }
        .header-top {
            width: 100%;
            margin-bottom: 8px;
        }
        .header-logo-cell {
            vertical-align: middle;
            text-align: left;
        }
        .header-title-cell {
            vertical-align: middle;
            text-align: right;
        }
        .titulo-estado-cuenta {
            font-size: 16pt;
            font-weight: bold;
            color: #0a2d52;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        /* Franja azul oscura */
        .banner-azul {
            width: 100%;
            background-color: #0a2d52;
            color: #ffffff;
            padding: 6px 12px;
            margin-bottom: 16px;
        }
        .banner-azul table {
            width: 100%;
            border-collapse: collapse;
        }
        .banner-azul td {
            font-size: 10.5pt;
            font-weight: bold;
            color: #ffffff;
            padding: 0;
            border: none;
            background: transparent;
        }

        /* Info dos columnas */
        .tabla-info-dos-cols {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .tabla-info-dos-cols td {
            vertical-align: top;
            border: none;
            padding: 0;
        }
        .info-col-left {
            width: 52%;
            padding-right: 15px !important;
        }
        .info-col-right {
            width: 48%;
            padding-left: 15px !important;
        }
        .info-label {
            font-weight: bold;
            color: #000;
            font-size: 9.5pt;
            text-transform: uppercase;
            margin-top: 6px;
            margin-bottom: 2px;
        }
        .info-valor {
            color: #222;
            font-size: 9.5pt;
            margin-bottom: 6px;
        }
        .info-valor-destacado {
            font-weight: bold;
            color: #0a2d52;
            font-size: 10.5pt;
        }
        .horarios-list {
            margin: 3px 0 0 0;
            padding-left: 12px;
            font-size: 9pt;
            color: #333;
        }
        .horarios-list li {
            margin-bottom: 2px;
        }

        /* Tabla principal de Prenda y Resumen Financiero */
        .tabla-resumen-prenda {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #0a2d52;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        .tabla-resumen-prenda th {
            background-color: #0a2d52;
            color: #ffffff;
            font-weight: bold;
            font-size: 10pt;
            padding: 7px 10px;
            text-transform: uppercase;
            border: 1px solid #0a2d52;
        }
        .tabla-resumen-prenda td {
            border: 1px solid #0a2d52;
            padding: 6px 10px;
            font-size: 9.5pt;
            color: #111;
        }
        .celda-prenda-detalle {
            vertical-align: top;
            line-height: 1.45;
            background-color: #ffffff;
        }
        .subtitulo-desglose {
            background-color: #0a2d52 !important;
            color: #ffffff !important;
            font-weight: bold;
            text-align: center;
            font-size: 9pt !important;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 5px 8px !important;
        }
        .fila-retiro {
            background-color: #f1f5f9;
        }

        /* Nota y firma */
        .nota-garantia {
            font-size: 9pt;
            color: #222;
            line-height: 1.4;
            margin-top: 15px;
            margin-bottom: 30px;
        }
        .recibo-frase {
            text-align: center;
            font-size: 10pt;
            color: #111;
            margin-bottom: 50px;
        }
        .firma-area {
            text-align: center;
        }
        .linea-firma-p1 {
            width: 320px;
            margin: 0 auto;
            border-top: 1.5px solid #000;
            padding-top: 5px;
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
        }

        /* Salto de página */
        .page-break {
            page-break-after: always;
            clear: both;
        }

        /* ===== ESTILOS PÁGINA 2: PLAN DE PAGOS NORMAL ===== */
        .page-2-header {
            width: 100%;
            border-bottom: 2px solid #0a2d52;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .p2-brand-title {
            font-size: 16pt;
            font-weight: bold;
            color: #0a2d52;
            text-transform: uppercase;
        }
        .p2-brand-subtitle {
            font-size: 9pt;
            color: #555;
        }
        .p2-doc-title {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            color: #0a2d52;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 10px 0 14px 0;
        }
        .p2-info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9pt;
        }
        .p2-info-grid td {
            vertical-align: top;
            padding: 3px 6px;
            border: none;
        }
        .p2-label {
            font-weight: bold;
            color: #555;
            font-size: 8.5pt;
            text-transform: uppercase;
            display: block;
        }
        .p2-val {
            font-weight: bold;
            color: #111;
            font-size: 9.5pt;
        }

        /* Tabla de cuotas */
        .tabla-cuotas {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 8.5pt;
        }
        .tabla-cuotas th {
            background-color: #0a2d52;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 4px;
            border: 1px solid #0a2d52;
            font-size: 8pt;
        }
        .tabla-cuotas td {
            padding: 5px 4px;
            border: 1px solid #cbd5e1;
            color: #222;
        }
        .tabla-cuotas tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .total-row td {
            background-color: #e2e8f0;
            font-weight: bold;
            color: #0a2d52;
            border-top: 2px solid #0a2d52;
            font-size: 9pt;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid { background-color: #dcfce7; color: #166534; }
        .status-pending { background-color: #f1f5f9; color: #475569; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; }

        .p2-disclaimer {
            margin-top: 15px;
            font-size: 7.5pt;
            color: #64748b;
            line-height: 1.35;
        }
    </style>
</head>
<body>

@php
    use Carbon\Carbon;
    Carbon::setLocale('es');

    // 1. Datos de Empresa y Sucursal
    $nombreEmpresa = $empresa['nombre'] ?? 'PRENDAMÁS+';
    $razonSocial = $empresa['razon_social'] ?? 'GRUPO JUMERC, SOCIEDAD ANÓNIMA';
    $direccionSucursal = !empty($sucursal->direccion) ? $sucursal->direccion : '15 Avenida 4-60 zona 3 Quetzaltenango.';
    $telefonoWhatsapp = '3996-6178';
    $telefonoFijo = !empty($sucursal->telefono) ? $sucursal->telefono : '7934-0485';

    // 2. Datos del Crédito
    $numeroContrato = $credito->codigo_credito ?? $credito->numero_credito ?? $credito->numero_contrato ?? '0001';
    
    // Fechas
    $fechaEmisionStr = now()->format('d/m/Y');
    
    $fechaInicioObj = !empty($credito->fecha_desembolso) 
        ? Carbon::parse($credito->fecha_desembolso) 
        : (!empty($credito->created_at) ? Carbon::parse($credito->created_at) : Carbon::now());
    $fechaInicioFormatted = $fechaInicioObj->isoFormat('DD [de] MMMM YYYY');

    $plazoDias = (int) ($credito->plazo_dias ?? 30);
    $fechaFinObj = !empty($credito->fecha_vencimiento) 
        ? Carbon::parse($credito->fecha_vencimiento) 
        : $fechaInicioObj->copy()->addDays($plazoDias);
    $fechaFinFormatted = $fechaFinObj->isoFormat('DD [de] MMMM YYYY');

    // Días de gracia
    $diasGracia = (int) ($credito->dias_gracia ?? 4);
    if ($diasGracia <= 0) {
        $diasGracia = 4; // Default corporativo Prendamas
    }
    $fechaGraciaObj = $fechaFinObj->copy()->addDays($diasGracia);
    $fechaGraciaFormatted = $fechaGraciaObj->isoFormat('D [de] MMMM, YYYY');

    // 3. Cliente
    $clienteNombre = $cliente->nombre_completo ?? trim(($cliente->nombres ?? $cliente->nombre ?? '') . ' ' . ($cliente->apellidos ?? $cliente->apellido ?? ''));
    if (empty($clienteNombre)) {
        $clienteNombre = 'Manuel Francisco García Robles';
    }

    // 4. Prendas y Avalúo
    $listaPrendas = collect($prendas ?? $credito->prendas ?? []);
    
    // Monto Principal
    $montoPrincipal = (float) ($credito->monto_prestamo ?? $credito->monto_aprobado ?? 1750.00);

    // Valor del Avalúo (Suma de las prendas o duplicado de préstamo como referencia)
    $valorAvaluo = $listaPrendas->sum(function($p) {
        return (float) ($p->valor_tasacion ?? $p->monto_avaluo ?? $p->valor_estimado_cliente ?? 0);
    });
    if ($valorAvaluo <= 0) {
        $valorAvaluo = 3500.00;
    }

    // Descripción formateada de la prenda con viñetas (•)
    if ($listaPrendas->isNotEmpty()) {
        $partesDesc = [];
        foreach ($listaPrendas as $p) {
            $descItem = [];
            $tituloPrenda = $p->descripcion ?? $p->descripcion_general ?? 'Bien mueble prendario';
            $descItem[] = '<strong>' . e($tituloPrenda) . '•</strong>';

            if (!empty($p->marca)) {
                $descItem[] = 'Marca: ' . e($p->marca) . '•';
            }
            if (!empty($p->modelo)) {
                $descItem[] = 'Modelo: ' . e($p->modelo) . '•';
            }
            if (!empty($p->color)) {
                $descItem[] = 'Color: ' . e($p->color) . '•';
            }
            if (!empty($p->peso_neto) || !empty($p->peso_bruto)) {
                $descItem[] = 'Material/Peso: ' . ($p->peso_neto ?? $p->peso_bruto) . 'g•';
            }
            if (!empty($p->kilataje)) {
                $descItem[] = 'Kilataje: ' . $p->kilataje . 'K•';
            }
            if (!empty($p->peso_piedra)) {
                $descItem[] = 'Piedra: ' . $p->peso_piedra . 'g•';
            }
            if (!empty($p->serie)) {
                $descItem[] = 'Número de serie: ' . e($p->serie) . '•';
            }
            if (!empty($p->condicion) || !empty($p->estado)) {
                $descItem[] = 'Estado: ' . e($p->condicion ?? $p->estado) . '•';
            }
            if (!empty($p->observaciones)) {
                $descItem[] = 'Accesorios: ' . e($p->observaciones) . '•';
            }

            $partesDesc[] = implode(' ', $descItem);
        }
        $prendaTextoHtml = implode('<br><br>', $partesDesc);
    } else {
        $prendaTextoHtml = '<strong>Reloj de pulsera para hombre•</strong><br>'
            . 'Marca: Omega• Modelo: Sea master•<br>'
            . 'Material: Acero inoxidable• Accesorios:<br>'
            . 'Incluye estuche y certificado de<br>'
            . 'autenticidad• Número de serie:<br>'
            . '76543210• Estado: Excelente';
    }

    // 5. Cálculos Financieros
    $tipoPlan = strtoupper($credito->tipo_interes ?? $credito->frecuencia_pago ?? 'MENSUAL');

    $coleccionCuotas = collect($planPagos ?? []);
    $primerCuota = $coleccionCuotas->first();

    $interesesAPagar = 0;
    if ($primerCuota) {
        $interesesAPagar = (float) (
            $primerCuota->interes_proyectado 
            ?? $primerCuota->interes_pendiente 
            ?? $primerCuota->interes 
            ?? $primerCuota->cuota_interes 
            ?? 0
        );
    }
    
    // Si no viene en la primera cuota, calcular según la tasa
    if ($interesesAPagar <= 0) {
        $tasa = (float) ($credito->tasa_interes ?? 13.44);
        $interesesAPagar = round(($montoPrincipal * $tasa) / 100, 2);
        if ($interesesAPagar <= 0) {
            $interesesAPagar = 265.00;
        }
    }

    // Retiro de garantía = Principal + Intereses
    $retiroGarantia = round($montoPrincipal + $interesesAPagar, 2);
@endphp

    <!-- ============================================================= -->
    <!-- PÁGINA 1: ESTADO DE CUENTA PERSONALIZADO PRENDAMAS           -->
    <!-- ============================================================= -->
    <div class="page-1-wrapper">
        
        {{-- ENCABEZADO CON LOGO Y TÍTULO ESTADO DE CUENTA --}}
        <table class="header-top">
            <tr>
                <td class="header-logo-cell" width="55%">
                    @include('pdf.partials.logo', ['height' => '65px'])
                </td>
                <td class="header-title-cell" width="45%">
                    <h1 class="titulo-estado-cuenta">ESTADO DE CUENTA</h1>
                </td>
            </tr>
        </table>

        {{-- FRANJA AZUL: FECHA DE EMISIÓN Y RESUMEN DEL DÍA --}}
        <div class="banner-azul">
            <table>
                <tr>
                    <td align="left" width="50%">
                        FECHA DE EMISIÓN: {{ $fechaEmisionStr }}
                    </td>
                    <td align="right" width="50%">
                        <th width="32%" style="text-align: left;">RESUMEN DEL DÍA</th>
                    </td>
                </tr>
            </table>
        </div>

        {{-- DETALLE EN DOS COLUMNAS --}}
        <table class="tabla-info-dos-cols">
            <tr>
                {{-- Columna izquierda: Datos del crédito y cliente --}}
                <td class="info-col-left">
                    <div class="info-label">NUMERO DE CONTRATO: {{ $numeroContrato }}</div>

                    <div class="info-label" style="margin-top: 8px;">NOMBRE DEL CLIENTE:</div>
                    <div class="info-valor">{{ $clienteNombre }}</div>

                    <div class="info-label">FECHA DE INICIO DE PRÉSTAMO:</div>
                    <div class="info-valor">{{ $fechaInicioFormatted }}</div>

                    <div class="info-label">FECHA FINALIZACIÓN DE PRÉSTAMO:</div>
                    <div class="info-valor">{{ $fechaFinFormatted }}</div>

                    <div class="info-label">DÍAS DE GRACIA:</div>
                    <div class="info-valor">{{ $diasGracia }} días (hasta el {{ $fechaGraciaFormatted }})</div>
                </td>

                {{-- Columna derecha: Datos de la agencia y horarios --}}
                <td class="info-col-right">
                    <div class="info-valor-destacado">{{ $nombreEmpresa }}</div>

                    <div class="info-label" style="margin-top: 6px;">DIRECCIÓN:</div>
                    <div class="info-valor">{{ $direccionSucursal }}</div>

                    <div class="info-label">TELÉFONO:</div>
                    <div class="info-valor">WHATSAPP: {{ $telefonoWhatsapp }} &nbsp;&nbsp; TEL: {{ $telefonoFijo }}</div>

                    <div class="info-label">HORARIOS DE ATENCIÓN:</div>
                    <ul class="horarios-list">
                        <li>Lunes a viernes: 8:00 AM – 5:00 PM</li>
                        <li><strong>SÁBADO: CERRADO</strong></li>
                        <li>Domingo: 9:00 AM – 12:30 PM</li>
                    </ul>
                </td>
            </tr>
        </table>

        {{-- TABLA PRINCIPAL: PRENDA Y RESUMEN FINANCIERO --}}
        <table class="tabla-resumen-prenda">
            <thead>
                <tr>
                    <th width="48%" style="text-align: left;">DESCRIPCIÓN DE LA PRENDA</th>
                    <th width="32%" style="text-align: left;">VALOR DEL AVALÚO</th>
                    <th width="20%" style="text-align: right;">Q{{ number_format($valorAvaluo, 2) }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    {{-- Columna izquierda ocupa toda la altura --}}
                    <td rowspan="5" class="celda-prenda-detalle">
                        {!! $prendaTextoHtml !!}
                    </td>

                    {{-- Fila 1: Monto del préstamo --}}
                    <td style="font-weight: bold; text-transform: uppercase; font-size: 9pt;">
                        MONTO DE PRÉSTAMO (PRINCIPAL)
                    </td>
                    <td style="text-align: right; font-weight: bold; font-size: 10pt;">
                        Q{{ number_format($montoPrincipal, 2) }}
                    </td>
                </tr>

                {{-- Fila 2: Subheader azul --}}
                <tr>
                    <td colspan="2" class="subtitulo-desglose">
                        DESGLOSE DE CARGOS Y PAGOS A LA FECHA
                    </td>
                </tr>

                {{-- Fila 3: Tipo de Plan --}}
                <tr>
                    <td style="font-weight: bold; text-transform: uppercase;">
                        TIPO DE PLAN:
                    </td>
                    <td style="text-align: right; font-weight: bold;">
                        {{ $tipoPlan }}
                    </td>
                </tr>

                {{-- Fila 4: Intereses a Pagar --}}
                <tr>
                    <td style="font-weight: bold; text-transform: uppercase;">
                        INTERESES A PAGAR:
                    </td>
                    <td style="text-align: right; font-weight: bold; font-size: 10pt;">
                        Q{{ number_format($interesesAPagar, 2) }}
                    </td>
                </tr>

                {{-- Fila 5: Retiro de Garantía --}}
                <tr class="fila-retiro">
                    <td style="font-weight: bold; text-transform: uppercase; font-size: 9.5pt;">
                        RETIRO DE GARANTÍA:
                    </td>
                    <td style="text-align: right; font-weight: bold; font-size: 10.5pt; color: #0a2d52;">
                        Q{{ number_format($retiroGarantia, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- NOTA DE GARANTÍA --}}
        <div class="nota-garantia">
            <strong>NOTA:</strong> Para evitar perdida de su garantía, por favor realice su pago en la fecha indicada o dentro de los días de gracia.
        </div>

        {{-- FRASE DE RECIBO Y FIRMA DEL CLIENTE --}}
        <div class="recibo-frase">
            <div style="height: 25px;"></div>
            Recibo mi prenda conforme a lo anteriormente establecido
        </div>

        <div class="firma-area">
            <div style="height: 25px;"></div>
               f. _____________________________________________
                <div style="margin-top: 5px;">{{ $clienteNombre }}</div>
            </div>
        </div>

    </div>

    <!-- ============================================================= -->
    <!-- SALTO DE PÁGINA PARA EL PLAN DE PAGOS ESTÁNDAR               -->
    <!-- ============================================================= -->
    <div class="page-break"></div>

    <!-- ============================================================= -->
    <!-- PÁGINA 2 EN ADELANTE: PLAN DE PAGOS NORMAL                    -->
    <!-- ============================================================= -->
    <div class="page-2-header">
        <table width="100%">
            <tr>
                <td width="30%" align="left" style="vertical-align: middle;">
                    @include('pdf.partials.logo', ['height' => '50px'])
                </td>
                <td width="70%" align="right" style="vertical-align: middle;">
                    <div class="p2-brand-title">{{ $nombreEmpresa }}</div>
                    <div class="p2-brand-subtitle">{{ $direccionSucursal }} | Tel: {{ $telefonoFijo }}</div>
                </td>
            </tr>
        </table>
    </div>

    <h2 class="p2-doc-title">Plan de Pagos y Amortización</h2>

    <table class="p2-info-grid">
        <tr>
            <td width="25%">
                <span class="p2-label">Crédito No.</span>
                <span class="p2-val">{{ $numeroContrato }}</span>
            </td>
            <td width="35%">
                <span class="p2-label">Cliente</span>
                <span class="p2-val">{{ $clienteNombre }}</span>
            </td>
            <td width="20%">
                <span class="p2-label">Monto Aprobado</span>
                <span class="p2-val" style="color: #0a2d52;">Q {{ number_format($montoPrincipal, 2) }}</span>
            </td>
            <td width="20%">
                <span class="p2-label">Tasa de Interés</span>
                <span class="p2-val">{{ number_format($credito->tasa_interes ?? 13.44, 2) }}%</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="p2-label">Frecuencia</span>
                <span class="p2-val">{{ $tipoPlan }}</span>
            </td>
            <td>
                <span class="p2-label">Documento DPI</span>
                <span class="p2-val">{{ $cliente->dpi ?? $cliente->cui ?? $cliente->numero_documento ?? 'CF' }}</span>
            </td>
            <td>
                <span class="p2-label">Plazo Total</span>
                <span class="p2-val">{{ $coleccionCuotas->count() > 0 ? $coleccionCuotas->count() . ' cuotas' : $plazoDias . ' días' }}</span>
            </td>
            <td>
                <span class="p2-label">Fecha Desembolso</span>
                <span class="p2-val">{{ $fechaInicioObj->format('d/m/Y') }}</span>
            </td>
        </tr>
    </table>

    <table class="tabla-cuotas">
        <thead>
            <tr>
                <th width="6%" class="text-center">No.</th>
                <th width="14%">Vencimiento</th>
                <th width="12%" class="text-right">Capital</th>
                <th width="12%" class="text-right">Interés</th>
                <th width="11%" class="text-right">Mora</th>
                <th width="11%" class="text-right">Otros</th>
                <th width="13%" class="text-right">Total</th>
                <th width="13%" class="text-right">Saldo</th>
                <th width="8%" class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalCapital = 0;
                $totalInteres = 0;
                $totalMora = 0;
                $totalOtros = 0;
                $totalGeneral = 0;
                $saldoPendiente = $montoPrincipal;
            @endphp
            @forelse($coleccionCuotas as $cuota)
                @php
                    $capital = (float) ($cuota->capital_proyectado ?? $cuota->capital_pendiente ?? $cuota->capital ?? 0);
                    $interes = (float) ($cuota->interes_proyectado ?? $cuota->interes_pendiente ?? $cuota->interes ?? $cuota->cuota_interes ?? 0);
                    $mora = (float) ($cuota->mora_proyectada ?? $cuota->mora_pendiente ?? $cuota->mora ?? $cuota->cuota_mora ?? 0);
                    $otros = (float) (
                        $cuota->otros_cargos_proyectados
                        ?? $cuota->otros_cargos_pendientes
                        ?? $cuota->otros_proyectados
                        ?? $cuota->gastos_proyectado
                        ?? $cuota->gastos
                        ?? 0
                    );

                    $totalCuota = round($capital + $interes + $mora + $otros, 2);
                    if ($totalCuota <= 0) {
                        $totalCuota = (float) ($cuota->monto_cuota_proyectado ?? $cuota->cuota_total ?? $cuota->cuota ?? 0);
                    }

                    $totalCapital += $capital;
                    $totalInteres += $interes;
                    $totalMora += $mora;
                    $totalOtros += $otros;
                    $totalGeneral += $totalCuota;

                    $saldoDespuesCuota = max(0, round($saldoPendiente - $capital, 2));

                    $estadoClass = match($cuota->estado ?? 'pendiente') {
                        'pagada' => 'status-paid',
                        'vencida' => 'status-overdue',
                        default => 'status-pending'
                    };
                @endphp
                <tr>
                    <td class="text-center">{{ $cuota->numero_cuota ?? $loop->iteration }}</td>
                    <td>{{ !empty($cuota->fecha_vencimiento) ? Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') : '-' }}</td>
                    <td class="text-right">Q {{ number_format($capital, 2) }}</td>
                    <td class="text-right">Q {{ number_format($interes, 2) }}</td>
                    <td class="text-right">Q {{ number_format($mora, 2) }}</td>
                    <td class="text-right">Q {{ number_format($otros, 2) }}</td>
                    <td class="text-right" style="font-weight: bold; color: #0a2d52;">Q {{ number_format($totalCuota, 2) }}</td>
                    <td class="text-right" style="font-weight: bold;">Q {{ number_format($saldoDespuesCuota, 2) }}</td>
                    <td class="text-center">
                        <span class="status-badge {{ $estadoClass }}">{{ $cuota->estado ?? 'pendiente' }}</span>
                    </td>
                </tr>
                @php
                    $saldoPendiente = $saldoDespuesCuota;
                @endphp
            @empty
                <tr>
                    <td class="text-center">1</td>
                    <td>{{ $fechaFinObj->format('d/m/Y') }}</td>
                    <td class="text-right">Q {{ number_format($montoPrincipal, 2) }}</td>
                    <td class="text-right">Q {{ number_format($interesesAPagar, 2) }}</td>
                    <td class="text-right">Q 0.00</td>
                    <td class="text-right">Q 0.00</td>
                    <td class="text-right" style="font-weight: bold; color: #0a2d52;">Q {{ number_format($montoPrincipal + $interesesAPagar, 2) }}</td>
                    <td class="text-right" style="font-weight: bold;">Q 0.00</td>
                    <td class="text-center">
                        <span class="status-badge status-pending">pendiente</span>
                    </td>
                </tr>
                @php
                    $totalCapital = $montoPrincipal;
                    $totalInteres = $interesesAPagar;
                    $totalGeneral = $montoPrincipal + $interesesAPagar;
                @endphp
            @endforelse
            <tr class="total-row">
                <td colspan="2" class="text-right">TOTALES:</td>
                <td class="text-right">Q {{ number_format($totalCapital, 2) }}</td>
                <td class="text-right">Q {{ number_format($totalInteres, 2) }}</td>
                <td class="text-right">Q {{ number_format($totalMora, 2) }}</td>
                <td class="text-right">Q {{ number_format($totalOtros, 2) }}</td>
                <td class="text-right">Q {{ number_format($totalGeneral, 2) }}</td>
                <td class="text-right" style="color: #166534;">Q 0.00</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="p2-disclaimer">
        <strong>Condiciones del Plan de Pagos:</strong>
        <ul style="margin: 3px 0 0 16px; padding: 0;">
            <li><strong>Tasa de Interés:</strong> {{ number_format($credito->tasa_interes ?? 13.44, 2) }}% calculada sobre el saldo o monto convenido según el tipo de plan {{ $tipoPlan }}.</li>
            <li><strong>Período de Gracia:</strong> Cuenta con {{ $diasGracia }} días hábiles posteriores a la fecha de vencimiento antes de incurrir en recargos moratorios.</li>
            <li><strong>Cancelación y Retiro:</strong> Al cancelar el saldo total más intereses devengados, la garantía le será entregada en la misma agencia donde formalizó su crédito.</li>
        </ul>
    </div>

</body>
</html>
