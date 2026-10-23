<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$creditos = DB::select("SELECT id, numero_credito, sucursal_id, monto_aprobado, created_at FROM avanza_copia.creditos_prendarios ORDER BY id ASC LIMIT 5");
foreach ($creditos as $c) {
    echo json_encode($c) . "\n";
}
