<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CreditoPrendario;

// Obtener un crédito con sus prendas
$credito = CreditoPrendario::with([
    'cliente:id,nombres,apellidos,dpi,codigo_cliente',
    'sucursal:id,nombre',
    'prendas:id,credito_prendario_id,codigo_prenda,descripcion,marca,modelo,categoria_producto_id,valor_tasacion,valor_prestamo,estado',
    'prendas.categoriaProducto:id,nombre',
])->first();

if (!$credito) {
    echo "No hay créditos en la base de datos\n";
    exit(1);
}

echo "=== CRÉDITO ===\n";
echo "Número: {$credito->numero_credito}\n";
echo "Cliente: {$credito->cliente->nombres} {$credito->cliente->apellidos}\n";
echo "Prendas cargadas: " . $credito->prendas->count() . "\n\n";

echo "=== PRENDAS ===\n";
foreach ($credito->prendas as $prenda) {
    echo "- ID: {$prenda->id}\n";
    echo "  Código: {$prenda->codigo_prenda}\n";
    echo "  Descripción: {$prenda->descripcion}\n";
    echo "  Marca: " . ($prenda->marca ?? 'N/A') . "\n";
    echo "  Modelo: " . ($prenda->modelo ?? 'N/A') . "\n";
    echo "  Categoría ID: {$prenda->categoria_producto_id}\n";
    echo "  Categoría: " . ($prenda->categoriaProducto ? $prenda->categoriaProducto->nombre : 'N/A') . "\n";
    echo "  Estado: {$prenda->estado}\n";
    echo "  Valor Tasación: Q{$prenda->valor_tasacion}\n";
    echo "  Valor Préstamo: Q{$prenda->valor_prestamo}\n\n";
}

// Formatear como en el controlador
$prendas_formateadas = $credito->prendas->map(function ($prenda) {
    return [
        'id' => (string) $prenda->id,
        'codigo_prenda' => $prenda->codigo_prenda,
        'descripcion' => $prenda->descripcion,
        'marca' => $prenda->marca,
        'modelo' => $prenda->modelo,
        'categoria' => $prenda->categoriaProducto ? $prenda->categoriaProducto->nombre : null,
        'categoria_producto_id' => $prenda->categoria_producto_id ? (string) $prenda->categoria_producto_id : null,
        'valor_tasacion' => $prenda->valor_tasacion ? (float) $prenda->valor_tasacion : null,
        'valor_prestamo' => $prenda->valor_prestamo ? (float) $prenda->valor_prestamo : null,
        'estado' => $prenda->estado,
    ];
})->values();

echo "=== PRENDAS FORMATEADAS (JSON) ===\n";
echo json_encode($prendas_formateadas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
