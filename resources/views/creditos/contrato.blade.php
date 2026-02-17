<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato de Crédito - {{ $credito->codigo_credito ?? $credito->numero_credito }}</title>
    <style>
        @page {
            margin: 0cm 0cm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #333;
            line-height: 1.6;
            margin-top: 3cm;
            margin-bottom: 2cm;
            margin-left: 2cm;
            margin-right: 2cm;
            text-align: justify;
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
            color: #1e293b;
            margin-top: 10px;
            margin-bottom: 25px;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .section-title {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            font-size: 11px;
            padding: 5px 10px;
            margin-top: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #2563eb;
            text-transform: uppercase;
        }
        .info-table {
            width: 100%;
            margin-bottom: 15px;
        }
        .info-table td {
            vertical-align: top;
            padding: 4px;
        }
        .label {
            font-weight: bold;
            color: #64748b;
            font-size: 9px;
            width: 30%;
        }
        .value {
            color: #1e293b;
            font-weight: 500;
        }
        .strong-value {
            font-weight: bold;
            color: #000;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            border: 1px solid #e2e8f0;
        }
        table.data-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8px;
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        table.data-table td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9px;
        }

        .clause-item {
            margin-bottom: 12px;
            line-height: 1.5;
        }
        .clause-number {
            font-weight: bold;
            color: #2563eb;
            margin-right: 5px;
        }

        .signatures {
            margin-top: 60px;
            width: 100%;
        }
        .signature-box {
            width: 40%;
            display: inline-block;
            vertical-align: top;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin: 0 auto 10px auto;
            width: 80%;
        }
        .signature-name {
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }
        .signature-role {
            font-size: 9px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <header>
        <div class="brand-title">{{ $sucursal->nombre ?? 'DigiPrenda' }}</div>
        <div class="brand-subtitle">{{ $sucursal->direccion ?? 'Contrato de Servicios Prendarios' }}</div>
    </header>

    <footer>
        Página <span class="pagenum"></span> - Contrato No. {{ $credito->codigo_credito }}
    </footer>

    <h2 class="document-title">CONTRATO DE CRÉDITO PRENDARIO</h2>

    <p style="margin-bottom: 20px;">
        En la ciudad de <strong>{{ $sucursal->ciudad ?? 'Guatemala' }}</strong>, el día <strong>{{ $fechaContrato }}</strong>, comparecen por una parte:
    </p>

    <!-- Partes -->
    <table class="info-table">
        <tr>
            <td width="50%">
                <div class="section-title" style="margin-top: 0;">EL PRESTAMISTA</div>
                <table width="100%">
                    <tr><td class="label">Razón Social:</td><td class="value strong-value">{{ $sucursal->nombre }}</td></tr>
                    @if($sucursal->nit)
                    <tr><td class="label">NIT:</td><td class="value">{{ $sucursal->nit }}</td></tr>
                    @endif
                    <tr><td class="label">Dirección:</td><td class="value">{{ $sucursal->direccion }}</td></tr>
                    <tr><td class="label">Teléfono:</td><td class="value">{{ $sucursal->telefono }}</td></tr>
                </table>
            </td>
            <td width="50%">
                <div class="section-title" style="margin-top: 0;">EL PRESTATARIO (CLIENTE)</div>
                <table width="100%">
                    <tr><td class="label">Nombre:</td><td class="value strong-value">{{ $cliente->nombres }} {{ $cliente->apellidos }}</td></tr>
                    <tr><td class="label">DPI/ID:</td><td class="value">{{ $cliente->dpi ?? 'N/A' }}</td></tr>
                    <tr><td class="label">Teléfono:</td><td class="value">{{ $cliente->telefono }}</td></tr>
                    <tr><td class="label">Dirección:</td><td class="value">{{ $cliente->direccion ?? 'Ciudad' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Condiciones -->
    <div class="section-title">CONDICIONES DEL CRÉDITO</div>
    <table class="info-table">
        <tr>
            <td width="33%">
                <span class="label">Crédito No.</span><br>
                <span class="value strong-value">{{ $credito->codigo_credito ?? $credito->numero_credito }}</span>
            </td>
            <td width="33%">
                <span class="label">Monto del Préstamo</span><br>
                <span class="value strong-value" style="color: #2563eb; font-size: 11px;">Q {{ number_format($credito->monto_aprobado, 2) }}</span>
            </td>
            <td width="33%">
                <span class="label">Fecha Vencimiento</span><br>
                <div class="value">{{ \Carbon\Carbon::parse($credito->fecha_vencimiento)->format('d/m/Y') }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Tasa de Interés</span><br>
                <span class="value">{{ number_format($credito->tasa_interes, 2) }}% {{ $credito->tipo_interes }}</span>
            </td>
            <td>
                <span class="label">Plazo Estimado</span><br>
                <span class="value">{{ $credito->numero_cuotas }} cuotas</span>
            </td>
            <td>
                <span class="label">Dias de Gracia</span><br>
                <span class="value">{{ $credito->dias_gracia ?? 0 }} días</span>
            </td>
        </tr>
    </table>

    <!-- Prendas -->
    @if($prendas && count($prendas) > 0)
    <div class="section-title">DESCRIPCIÓN DE LA PRENDA (GARANTÍA)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th width="40%">Descripción</th>
                <th width="20%">Marca/Modelo</th>
                <th width="20%">Serie</th>
                <th width="20%" style="text-align: right;">Avalúo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($prendas as $prenda)
            <tr>
                <td>{{ $prenda->descripcion_general ?? $prenda->descripcion }}</td>
                <td>{{ $prenda->marca ?? '-' }} {{ $prenda->modelo ?? '' }}</td>
                <td>{{ $prenda->serie ?? $prenda->numero_serie ?? '-' }}</td>
                <td style="text-align: right;">Q {{ number_format($prenda->valor_tasacion ?? 0, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Cláusulas -->
    <div class="section-title">CLÁUSULAS DEL CONTRATO</div>
    <div class="clauses">
        <div class="clause-item">
            <span class="clause-number">PRIMERA:</span> EL PRESTATARIO reconoce haber recibido a su entera satisfacción la suma detallada en la sección de condiciones, obligándose a devolverla en el plazo y forma estipulados.
        </div>
        <div class="clause-item">
            <span class="clause-number">SEGUNDA:</span> En garantía del pago del capital, intereses y demás accesorios legales, EL PRESTATARIO entrega y constituye prenda sobre los bienes muebles descritos anteriormente, declarando que son de su exclusiva propiedad y libre disposición.
        </div>
        <div class="clause-item">
            <span class="clause-number">TERCERA:</span> La falta de pago puntual de cualquiera de las cuotas pactadas facultará a EL PRESTAMISTA para dar por vencido el plazo total y exigir el pago inmediato del saldo adeudado, o proceder a la ejecución y venta de la prenda conforme a la ley.
        </div>
        <div class="clause-item">
            <span class="clause-number">CUARTA:</span> EL PRESTAMISTA se obliga a conservar la prenda con la debida diligencia y a restituirla una vez cancelada la totalidad de la deuda.
        </div>
        <div class="clause-item">
            <span class="clause-number">QUINTA:</span> Ambas partes aceptan el contenido íntegro del presente contrato y se someten a la jurisdicción de los tribunales de esta ciudad para cualquier controversia.
        </div>
    </div>

    <!-- Firmas -->
    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-name">{{ $sucursal->nombre }}</div>
            <div class="signature-role">POR EL PRESTAMISTA</div>
        </div>
        <div style="width: 15%; display: inline-block;"></div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-name">{{ $cliente->nombres }} {{ $cliente->apellidos }}</div>
            <div class="signature-role">EL PRESTATARIO</div>
        </div>
    </div>
</body>
</html>
