<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: letter;
            margin: 20mm 2.95mm 10mm 2.95mm;
        }
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
        }
        .pdf-header {
            position: fixed;
            top: 0;
            left: -2.95mm;
            right: -2.95mm;
            height: 18mm;
            background: #203347;
        }
        .pdf-header img {
            position: absolute;
            top: 2.5mm;
            left: 2.95mm;
            height: 13mm;
        }

        .pdf-footer {
            position: fixed;
            bottom: 0;
            left: -2.95mm;
            right: -2.95mm;
            height: 10mm;
            background: #203347;
        }
        .pdf-page-number {
            position: absolute;
            top: 3.2mm;
            right: 8mm;
            color: #fff;
            font: 10pt "Times New Roman", Times, serif;
            white-space: nowrap;
        }
        .pdf-page-number:after {
            content: "Página " counter(page);
        }
        .header-clearance {
            height: 20mm;
        }
        .page + .page {
            page-break-before: always;
        }
        .form-row {
            width: 210mm;
            table-layout: fixed;
            border-collapse: collapse;
            border-spacing: 0;
            margin: 0 auto;
            padding: 0;
        }
        .form-row {
            page-break-inside: avoid;
        }
        .feic-cell-style-1 {
            width:200mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Times New Roman, Times, serif;
            font-size:12.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#ffffff;
            background-color:#19345b;
        }
        .feic-cell-style-2 {
            width:200mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:10.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#ffffff;
            background-color:#009ccb;
        }
        .feic-cell-style-3 {
            width:70mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-4 {
            width:13.929mm;
            height:5.0mm;
            border: 0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:normal;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-5 {
            width:46.429mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-6 {
            width:64.642mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:normal;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-7 {
            width:48.75mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-8 {
            width:200mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:10.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
            background-color:#afafaf;
        }
        .feic-cell-style-9 {
            width:32.5mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-10 {
            width:32.5mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-11 {
            width:55.714mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-12 {
            width:139.286mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-13 {
            width:41.786mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-14 {
            width:34.357mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }


        .feic-cell-style-15 {
            width:41.786mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-16 {
            width:34.357mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }

        .feic-cell-style-17 {
            width:45.5mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-18 {
            width:65mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-19 {
            width:65mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-20 {
            width:200mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-21 {
            width:200mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-22 {
            width:59.429mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-23 {
            width:16.713mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-24 {
            width:59.429mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-25 {
            width:16.713mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-26 {
            width:135.571mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-27 {
            width:181.071mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:10.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
            background-color:#afafaf;
        }
        .feic-cell-style-28 {
            width:13.929mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:10.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
            background-color:#afafaf;
        }
        .feic-cell-style-29 {
            width:200mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:10.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
            background-color:#ffc62b;
        }
        .feic-cell-style-30 {
            width:32.5mm;
            height:8.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-31 {
            width:41.786mm;
            height:8.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-32 {
            width:34.357mm;
            height:8.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-33 {
            width:50.143mm;
            height:8.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-34 {
            width:36.214mm;
            height:8.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-35 {
            width:32.5mm;
            height:25.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-36 {
            width:41.786mm;
            height:25.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-37 {
            width:34.357mm;
            height:25.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-38 {
            width:50.143mm;
            height:25.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-39 {
            width:36.214mm;
            height:25.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-40 {
            width:200mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:10.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
            background-color:#009ccb;
        }
        .feic-cell-style-41 {
            width:200mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:10.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
            background-color:#9d8148;
        }

        .feic-cell-style-42 {
            width:102.142mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
            background-color:#9d8148;
        }
        .feic-cell-style-43 {
            width:111.429mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-44 {
            width:83.571mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-45 {
            width:83.571mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:right;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-46 {
            width:37.143mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-47 {
            width:157.857mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }

        .feic-cell-style-48 {
            width:159.714mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }

        .feic-cell-style-49 {
            width:130mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-50 {
            width:102.142mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:10.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
            background-color:#9d8148;
        }
        .feic-cell-style-51 {
            width:81.25mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-52 {
            width:74.286mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:left;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }
        .feic-cell-style-53 {
            width:37.143mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:9.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
        }

        .feic-cell-style-54 {
            width:200mm;
            height:5.0mm;
            border:0.2mm solid #000;
            padding:0 1mm;
            text-align:center;
            vertical-align:middle;
            font-family:Arial, Helvetica, sans-serif;
            font-size:10.0pt;
            font-weight:bold;
            font-style:normal;
            line-height:4.5mm;
            color:#000000;
            background-color:#ffffff;
        }
    </style>

@php
    use App\Support\NumeroALetrasHelper;
    use Carbon\Carbon;

    //ACTUACIÓN DEL TITULAR 1
    $titularPropio = $cliente->actua_en_nombre_propio ? 'No' : 'Si';
    $ActuaEnCalidad = $cliente->actua_en_calidad_de ?? '--';

    //Lugar y Fecha
    $sucursal = $cliente->sucursalRelacion;
    $paisSucursal = $sucursal?->pais ?? '--';
    $departamentoSucursal = $sucursal?->departamento ?? '--';
    $municipioSucursal = $sucursal?->municipio ?? '--';
    $fechaEmision = $cliente->fecha_emision ? Carbon::parse($cliente->fecha_emision)->format('d/m/Y') : '--';

    //Datos del titular/Cliente
    $titulaNombre = $cliente->nombres ?? $cliente->nombre ?? '--';
    $titulaApellido = $cliente->apellidos ?? $cliente->apellido ?? '--';
    $dividir = function ($cadena) {
    $limpia = Str::squish($cadena ?? '');
    return array_pad($limpia !== '' ? explode(' ', $limpia, 3) : [], 3, '--');
    };
    [$primerNombre, $segundoNombre, $otrosNombres] = $dividir($cliente->nombres ?? $cliente->nombre);
    [$primerApellido, $segundoApellido, $apellidoCasada] = $dividir($cliente->apellidos ?? $cliente->apellido);

    $docID = $cliente->tipo_documento ?? 'DPI';
    $NoDoc = $cliente->dpi ?? '--';
    $paisPasaporte = $cliente->pais_pasaporte ?? '--';
    $nit = $cliente->nit ?? '--';
    $sexo = $cliente->genero ?? '--';
    $fechaNacimiento = $cliente->fecha_nacimiento ? Carbon::parse($cliente->fecha_nacimiento)->format('d/m/Y') : '--';
    $esFemenino = !in_array(strtolower($cliente->genero ?? 'femenino'), ['masculino', 'm', 'hombre', 'varon']);
    $nacionalidad = $esFemenino ? 'Guatemalteca' : 'Guatemalteco';

    $paisNacimiento = $cliente->pais_nacimiento ?? '--';
    $departamentoNacimiento = $cliente->departamento_nacimiento ?? '--';
    $municipioNacimiento = $cliente->municipio_nacimiento ?? '--';
    $condicionMigratoria = $cliente->condicion_migratoria ?? '--';
    $especifique = $cliente->especifique ?? '--';

    $estadoCivil = $cliente->estado_civil ?? '--';
    $profesion = $cliente->profesion ?? '--';
    $correo = $cliente->email ?? '--';
    $residencia = $cliente->direccion ?? '--';

    //Datos de la residencia
    $direccionResidencia = $cliente->direccion ?? '--';
    $paisResidencia = $cliente->pais_residencia ?? 'Guatemala';
    $departamentoResidencia = $cliente->departamento ?? '--';
    $municipioResidencia = $cliente->municipio ?? '--';
    $zonaResidencia = $cliente->zona_residencia ?? '--';

    $telefonoPrincipal = $cliente->telefono ?? '--';
    $telefonoSecundario = $cliente->telefono_secundario ?? '';
    $telefonos = $telefonoPrincipal . ($telefonoSecundario ? ' / ' . $telefonoSecundario : '');

    //ES CPE
    $esCPE = $cliente->es_cpe ? 'Sí' : 'No';

    //Datos del PEP
    $esPep = $cliente->pep !== null ? 'Sí' : 'No';
    $entidadPep = $cliente->pep ? $cliente->pep->entidad : '--';
    $puestoPep = $cliente->pep ? $cliente->pep->puesto : '--';
    $paisPep = $cliente->pep ? $cliente->pep->paisEntidad : '--';
    $origenRiquezaPep = $cliente->pep ? $cliente->pep->origen : '--';
    $especifiquePep = $cliente->pep ? $cliente->pep->especifique : '--';

    $parentescoPep = $cliente->complementosPep()->where('tipo', 'parentesco')
    ->exists() ? 'Sí' : 'No';
    $asociadoPep = $cliente->complementosPep()->where('tipo', 'asociado')
    ->exists() ? 'Sí' : 'No';

    

    $saltodePagina = 0;

    $totalcreditos = $cliente?->creditosPrendarios()
    ->with('prendas')
    ->orderBy('fecha_solicitud')
    ->get() ?? collect();

    $cantidadCreditos = $totalcreditos->count();
@endphp

</head>
<body>
    <header class="pdf-header">
        @include('pdf.partials.logo', ['height' => '55px'])
        <h1 style="margin: 45px 0 0 510px; font-size: 12px; color: #ffffff;">Formulario Electrónico de Información del Cliente</h1>
    </header>
    <footer class="pdf-footer">
        <div class="pdf-page-number"></div>
    </footer>

    <main>
    <section class="page">
        <div class="header-clearance"></div>

    <table class="form-row">
        <tbody>
            <tr><td class="feic-cell-style-1">TITULARES</td></tr>
        </tbody>
    </table>
    <table class="form-row">
        <tbody>
            <tr><td class="feic-cell-style-1">TITULAR 1</td></tr>
        </tbody>
    </table>

    <table class="form-row">
        <tbody>
                <tr><td class="feic-cell-style-2">TIPO DE ACTUACIÓN DEL TITULAR 1</td></tr>
        </tbody>
    </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-3">El titular actua en nombre propio:</td>
                    <td class="feic-cell-style-4">{{ $titularPropio }}</td>
                    <td class="feic-cell-style-5">Calidad con la que actua:</td>
                    <td class="feic-cell-style-6">{{ $ActuaEnCalidad }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-2">LUGAR Y FECHA</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-7">Pais</td>
                    <td class="feic-cell-style-7">Departamento</td>
                    <td class="feic-cell-style-7">Municipio</td>
                    <td class="feic-cell-style-7">Fecha</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-7">{{ $paisSucursal }}</td>
                    <td class="feic-cell-style-7">{{ $departamentoSucursal }}</td>
                    <td class="feic-cell-style-7">{{ $municipioSucursal }}</td>
                    <td class="feic-cell-style-7">{{ $fechaEmision }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-2">DATOS PERSONALES DEL TITULAR 1</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-8">Información Personal Del titular</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-9">Primer nombre</td>
                    <td class="feic-cell-style-9">Segundo nombre</td>
                    <td class="feic-cell-style-9">Otros nombres</td>
                    <td class="feic-cell-style-9">Primer apellido</td>
                    <td class="feic-cell-style-9">Segundo apellido</td>
                    <td class="feic-cell-style-9">Apellido casado/a</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-10">{{ $primerNombre }}</td>
                    <td class="feic-cell-style-10">{{ $segundoNombre }}</td>
                    <td class="feic-cell-style-10">{{ $otrosNombres }}</td>
                    <td class="feic-cell-style-10">{{ $primerApellido }}</td>
                    <td class="feic-cell-style-10">{{ $segundoApellido }}</td>
                    <td class="feic-cell-style-10">{{ $apellidoCasada }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-9">Doc. ID</td>
                    <td class="feic-cell-style-9">Numero ID</td>
                    <td class="feic-cell-style-9">Pais (Pasaporte)</td>
                    <td class="feic-cell-style-9">NIT</td>
                    <td class="feic-cell-style-9">Sexo</td>
                    <td class="feic-cell-style-9">Fecha nacimiento</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-10">{{ $docID }}</td>
                    <td class="feic-cell-style-10">{{ $NoDoc }}</td>
                    <td class="feic-cell-style-10">{{ $paisPasaporte }}</td>
                    <td class="feic-cell-style-10">{{ $nit }}</td>
                    <td class="feic-cell-style-10">{{ $sexo }}</td>
                    <td class="feic-cell-style-10">{{ $fechaNacimiento }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-11">Nacionalidades</td>
                    <td class="feic-cell-style-12">{{ $nacionalidad }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-9">Pais nacimiento</td>
                    <td class="feic-cell-style-13">Departamento nacimiento</td>
                    <td class="feic-cell-style-14">Municipio nacimiento</td>
                    <td class="feic-cell-style-13">Condicion migratoria</td>
                    <td class="feic-cell-style-5">Especifique</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-10">{{ $paisNacimiento }}</td>
                    <td class="feic-cell-style-15">{{ $departamentoNacimiento }}</td>
                    <td class="feic-cell-style-16">{{ $municipioNacimiento }}</td>
                    <td class="feic-cell-style-15">{{ $condicionMigratoria }}</td>
                    <td class="feic-cell-style-17">{{ $especifique }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-18">Estado civil</td>
                    <td class="feic-cell-style-18">Profesion u oficio</td>
                    <td class="feic-cell-style-18">Correo electronico</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-19">{{ $estadoCivil }}</td>
                    <td class="feic-cell-style-19">{{ $profesion }}</td>
                    <td class="feic-cell-style-19">{{ $correo }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-20">Direccion de residencia completa del titular (calle o avenida, numero de casa, colonia, sector, lote, manzana, otros)</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-21">{{ $direccionResidencia }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-22">Pais</td>
                    <td class="feic-cell-style-22">Departamento</td>
                    <td class="feic-cell-style-22">Municipio</td>
                    <td class="feic-cell-style-23">Zona</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-24">{{ $paisResidencia }}</td>
                    <td class="feic-cell-style-24">{{ $departamentoResidencia }}</td>
                    <td class="feic-cell-style-24">{{ $municipioResidencia }}</td>
                    <td class="feic-cell-style-25">{{ $zonaResidencia }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-22">Telefonos</td>
                    <td class="feic-cell-style-26">{{ $telefonos }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-27">¿El titular es PEP?</td>
                    <td class="feic-cell-style-28">{{ $esPep }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row">
            <tbody>
                <tr>
                    <td class="feic-cell-style-29"> DATO PEP 1</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-30">Entidad</td>
                    <td class="feic-cell-style-31">Puesto que desempeña</td>
                    <td class="feic-cell-style-32">Pais entidad</td>
                    <td class="feic-cell-style-33">Origen o procedencia riqueza</td>
                    <td class="feic-cell-style-34">Especifique</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-35">{{ $entidadPep }}</td>
                    <td class="feic-cell-style-36">{{ $puestoPep }}</td>
                    <td class="feic-cell-style-37">{{ $paisPep }}</td>
                    <td class="feic-cell-style-38">{{ $origenRiquezaPep }}</td>
                    <td class="feic-cell-style-39">{{ $especifiquePep }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-27">¿El titular tiene parentesco con una PEP?</td>
                    <td class="feic-cell-style-28">{{ $parentescoPep }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-27">¿El titular es asociado cercano a una PEP?</td>
                    <td class="feic-cell-style-28">{{ $asociadoPep }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-40">OTRA INFORMACION</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-27">¿El titular es CPE?</td>
                    <td class="feic-cell-style-28">{{ $esCPE }}</td>
                </tr>
            </tbody>
        </table>
        </section>
        <!-- <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-2">INFORMACION ECONOMICA DEL TITULAR 1</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-41">INFORMACION ECONOMICA - INICIAL</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-5">Fecha</td>
                    <td class="feic-cell-style-17"></td>
                    <td class="feic-cell-style-42"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-20">Actividad economica RTU</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-21"></td>
                </tr>
            </tbody>
        </table>
        </section>
        <section class="page">
            <div class="header-clearance"></div>
        
        <table class="form-row" role="presentation">
        
            <tbody>
        
                <tr>
        
                    <td class="feic-cell-style-29">MONTOS DE INGRESOS</td>
        
                </tr>
        
            </tbody>
        
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43">Moneda</td>
                    <td class="feic-cell-style-44">Monto</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43"></td>
                    <td class="feic-cell-style-45"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-41">FUENTE DE INGRESOS</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-29">NEGOCIOS PROPIOS</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-46">Nombre Comercial</td>
                    <td class="feic-cell-style-47"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-29">RELACIONES DE DEPENDENCIA</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-16">Nombre de Empleador</td>
                    <td class="feic-cell-style-48"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-29">  OTROS INGRESOS</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-14">Descripcion de otra fuente de ingreso</td>
                    <td class="feic-cell-style-48"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-14">Descripcion de otra fuente de ingreso</td>
                    <td class="feic-cell-style-48"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-14">Descripcion de otra fuente de ingreso</td>
                    <td class="feic-cell-style-48"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-29">  MONTOS DE EGRESOS</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43">Moneda</td>
                    <td class="feic-cell-style-44">Monto</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43"></td>
                    <td class="feic-cell-style-45"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-18">Proposito de la relacion comercial</td>
                    <td class="feic-cell-style-49"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-41">INFORMACION ECONOMICA - ACTUALIZACION 1</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-5">Fecha</td>
                    <td class="feic-cell-style-17"></td>
                    <td class="feic-cell-style-50"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-20">Actividad economica RTU</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-21"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-29">  MONTOS DE INGRESOS</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43">Moneda</td>
                    <td class="feic-cell-style-44">Monto</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43"></td>
                    <td class="feic-cell-style-45"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-41">FUENTES DE INGRESOS</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-29">NEGOSIOS PROPIOS</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-16">Nombre Comersial</td>
                    <td class="feic-cell-style-48"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-29">RELACIONES DE DEPENDENCIA</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-16">Nombre del Empleador</td>
                    <td class="feic-cell-style-48"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-29">OTROS INGRESOS</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-14">Descripcion de otra fuente de ingreso</td>
                    <td class="feic-cell-style-48"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-14">Descripcion de otra fuente de ingreso</td>
                    <td class="feic-cell-style-48"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-14">Descripcion de otra fuente de ingreso</td>
                    <td class="feic-cell-style-48"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-29">MONTOS DE EGRESOS</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43">Moneda</td>
                    <td class="feic-cell-style-44">Monto</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43"></td>
                    <td class="feic-cell-style-45"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-18">Proposito de la relacion comercial</td>
                    <td class="feic-cell-style-49"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-41">INFORMACION ECONOMICA - ACTUALIZACION 2</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-5">Fecha</td>
                    <td class="feic-cell-style-17"></td>
                    <td class="feic-cell-style-50"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-20">Actividad economica RTU</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-21"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-29">  MONTOS DE INGRESOS</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43">Moneda</td>
                    <td class="feic-cell-style-44">Monto</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43"></td>
                    <td class="feic-cell-style-45"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-41">FUENTES DE INGRESOS</td>
                </tr>
            </tbody>
        </table>
        </section>
        <section class="page">
            <div class="header-clearance"></div>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-29">OTROS INGRESOS</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-14">Descripcion de otra fuente de ingreso</td>
                    <td class="feic-cell-style-48"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-14">Descripcion de otra fuente de ingreso</td>
                    <td class="feic-cell-style-48"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-29">MONTOS DE EGRESOS</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43">Moneda</td>
                    <td class="feic-cell-style-44">Monto</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43"></td>
                    <td class="feic-cell-style-45"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-18">Proposito de la relacion comercial</td>
                    <td class="feic-cell-style-49"></td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-1">PRODUCTOS Y/O SERVICIOS</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-1">PRODUCTO Y/O SERVICIO 1</td>
                </tr>
            </tbody>
        </table> -->
        @if($cantidadCreditos > 0)
        <section class="page">
            <div class="header-clearance"></div>
        @foreach($totalcreditos as $credito)
        @php
        $listaPrendas = collect($prendas ?? $credito->prendas ?? []);
        $prenda = $listaPrendas->first();
        //PRODUCTO Y/O SERVICIO
        $fechaAdquisicion = $credito?->fecha_solicitud ? 
        Carbon::parse($credito->fecha_solicitud)->format('d/m/Y'): '--';
        $tipoProducto = 'Crédito Prendiario';
        $nombreProducto = $prenda?->descripcion ?? '';
        $identificadorProducto = $prenda?->codigo_prenda ?? '';    
        $valorProducto = $credito?->monto_aprobado ?? '--';
        $tipoMoneda = 'Moneda';
        $utilizaFondoPropio = 'Fondo Propio';
        $terceroFondos = $credito?->tercero_fondo ?? '--';
        $firmantes = $credito?->firmantes ?? 'Sin firmantes asociados al producto / servicio';
        @endphp
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-2">INFORMACIÓN GENERAL DEL PRODUCTO Y/O SERVICIO</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-7">Pais</td>
                    <td class="feic-cell-style-7">Departamento</td>
                    <td class="feic-cell-style-7">Municipio</td>
                    <td class="feic-cell-style-7">Fecha de adquisicion</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-7">{{ $paisSucursal }}</td>
                    <td class="feic-cell-style-7">{{ $departamentoSucursal }}</td>
                    <td class="feic-cell-style-7">{{ $municipioSucursal }}</td>
                    <td class="feic-cell-style-7">{{ $fechaAdquisicion }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-7">Tipo producto / servicio</td>
                    <td class="feic-cell-style-51">Nombre y / o descripcion del producto / servicio</td>
                    <td class="feic-cell-style-19">Identificador producto / servicio</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-7">{{ $tipoProducto }}</td>
                    <td class="feic-cell-style-51">{{ $nombreProducto }}</td>
                    <td class="feic-cell-style-19">{{ $identificadorProducto }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-21">A nombre de quien se contrata el producto / servicio</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-21">{{ $titulaNombre }} {{ $titulaApellido }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43">Valor del producto / servicio</td>
                    <td class="feic-cell-style-44">{{ $tipoMoneda }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-43">Q.{{ $valorProducto }}</td>
                    <td class="feic-cell-style-44">Quetzal</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-52">Utiliza fondos propios o de un tercero para contratar el producto o servicio?</td>
                    <td class="feic-cell-style-53">{{ $utilizaFondoPropio }}</td>
                    <td class="feic-cell-style-5">Terceros que proveen los fondos</td>
                    <td class="feic-cell-style-53">{{ $terceroFondos }}</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-41">FIRMANTES</td>
                </tr>
            </tbody>
        </table>
        <table class="form-row" role="presentation">
            <tbody>
                <tr>
                    <td class="feic-cell-style-54">{{ $firmantes }}</td>
                </tr>
            </tbody>
        </table>
        
        @if($saltodePagina >1)
        <div style="page-break-before: always;"></div>
        <div class="header-clearance"></div>
        @php
        $saltodePagina = 0;
        @endphp
        @else
        @php
        $saltodePagina++;
        @endphp
        @endif
        @endforeach
        </section>
        @endif
    </main>
</body>
</html>