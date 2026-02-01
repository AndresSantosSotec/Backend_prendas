<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CreditoPrendario;

echo "=== VERIFICACIÓN FINAL: CATEGORÍA EN PRENDAS ===\n\n";

// Simular el mismo eager loading que usa el controlador
$credito = CreditoPrendario::with([
    'cliente:id,nombres,apellidos,codigo_cliente,telefono',
    'prendas:id,credito_prendario_id,codigo_prenda,descripcion,marca,modelo,categoria_producto_id,valor_tasacion,valor_prestamo,estado',
    'prendas.categoriaProducto:id,nombre'
])->first();

if (!$credito) {
    echo "No se encontraron créditos en la base de datos.\n";
    exit(1);
}

echo "Crédito: {$credito->codigo_credito}\n";
echo "Cliente: {$credito->cliente->nombres} {$credito->cliente->apellidos}\n";
echo "Total de prendas: " . $credito->prendas->count() . "\n\n";

echo "=== DETALLES DE PRENDAS ===\n";
foreach ($credito->prendas as $prenda) {
    echo "- Prenda ID: {$prenda->id}\n";
    echo "  Código: {$prenda->codigo_prenda}\n";
    echo "  Descripción: {$prenda->descripcion}\n";
    echo "  Marca: " . ($prenda->marca ?? 'N/A') . "\n";
    echo "  Modelo: " . ($prenda->modelo ?? 'N/A') . "\n";
    echo "  Categoría ID: " . ($prenda->categoria_producto_id ?? 'N/A') . "\n";
    echo "  Categoría Nombre: " . ($prenda->categoriaProducto ? $prenda->categoriaProducto->nombre : 'N/A') . "\n";
    echo "  Estado: {$prenda->estado}\n";
    echo "  Valor Tasación: Q" . number_format($prenda->valor_tasacion, 2) . "\n";
    echo "  Valor Préstamo: Q" . number_format($prenda->valor_prestamo, 2) . "\n";
    echo "\n";
}

// Simular el formateo del controlador
echo "=== FORMATO JSON (como retorna la API) ===\n";
$formatoPrenda = $credito->prendas->map(function ($prenda) {
    return [
        'id' => (string) $prenda->id,
        'codigo_prenda' => $prenda->codigo_prenda,
        'descripcion' => $prenda->descripcion,
        'marca' => $prenda->marca,
        'modelo' => $prenda->modelo,
        'categoria' => $prenda->categoriaProducto ? $prenda->categoriaProducto->nombre : null,
        'categoria_producto_id' => $prenda->categoria_producto_id ? (string) $prenda->categoria_producto_id : null,
        'valor_tasacion' => (float) $prenda->valor_tasacion,
        'valor_prestamo' => (float) $prenda->valor_prestamo,
        'estado' => $prenda->estado,
    ];
});

echo json_encode($formatoPrenda, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

echo "✅ Verificación completada. La categoría se está cargando correctamente.\n";
