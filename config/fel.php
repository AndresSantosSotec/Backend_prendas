<?php

return [
    'enabled' => env('TEKRA_ENABLED', false),
    'user' => env('TEKRA_USER', 'tekra_api'),
    'password' => env('TEKRA_PASSWORD', '123456789'),
    'client' => env('TEKRA_CLIENT', '2121010001'),
    'contract' => env('TEKRA_CONTRACT', '2122010001'),
    'soap_url' => env('TEKRA_SOAP_URL', 'http://apicertificacion.desa.tekra.com.gt:8080/certificacion/servicio.php'),
    'rest_url' => env('TEKRA_REST_URL', 'https://apiseguimiento.tekra.com.gt'),
    'emisor' => [
        'nit' => env('TEKRA_EMISOR_NIT', '107346834'),
        'nombre' => env('TEKRA_EMISOR_NOMBRE', 'TEKRA SOCIEDAD ANONIMA'),
        'nombre_comercial' => env('TEKRA_EMISOR_NOMBRE_COMERCIAL', 'TEKRA SOCIEDAD ANONIMA'),
        'direccion' => env('TEKRA_EMISOR_DIRECCION', '19 CALLE 18-48'),
        'municipio' => env('TEKRA_EMISOR_MUNICIPIO', 'GUATEMALA'),
        'departamento' => env('TEKRA_EMISOR_DEPARTAMENTO', 'GUATEMALA'),
        'codigo_postal' => env('TEKRA_EMISOR_CODIGO_POSTAL', '01010'),
        'afiliacion_iva' => env('TEKRA_EMISOR_AFILIACION_IVA', 'GEN'),
    ]
];
