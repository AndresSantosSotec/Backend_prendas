<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Probar el render de la vista con datos vacíos
    $html = view('reportes.ventas', [
        'ventas'       => [],
        'estadisticas' => [
            'total_ventas'     => 0,
            'total_ingresos'   => 0,
            'total_descuentos' => 0,
            'ticket_promedio'  => 0,
            'ventas_contado'   => 0,
            'ventas_credito'   => 0,
            'ventas_apartado'  => 0,
        ],
        'totales'      => [
            'costo'        => 0,
            'precio_lista' => 0,
            'descuentos'   => 0,
            'venta'        => 0,
            'utilidad'     => 0,
            'margen'       => 0,
        ],
        'fecha_desde'  => '2026-07-01',
        'fecha_hasta'  => '2026-07-31',
        'generado_por' => 'Test',
        'generado_en'  => date('d/m/Y H:i'),
    ])->render();
    echo "Vista OK: " . strlen($html) . " bytes\n";

    // Probar PDF
    $pdf = Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('A4', 'landscape');
    $output = $pdf->output();
    echo "PDF OK: " . strlen($output) . " bytes\n";

    // Probar con ventas reales
    $ventas = App\Models\Venta::with(['cliente', 'vendedor', 'sucursal', 'detalles.prenda', 'detalles.compra', 'prenda'])
        ->withoutTrashed()
        ->whereDate('created_at', '>=', '2026-07-01')
        ->whereDate('created_at', '<=', '2026-07-31')
        ->orderBy('created_at', 'desc')
        ->get();
    echo "Query OK: " . $ventas->count() . " ventas\n";

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File:  " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Class: " . get_class($e) . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}
