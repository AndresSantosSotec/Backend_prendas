<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CreditoPrendario;
use App\Models\Prenda;

echo "\n=== VERIFICANDO CRÉDITOS ===\n\n";

$creditos = CreditoPrendario::whereIn('numero_credito', ['CR-26017641', 'CR-26016759'])->get();

foreach ($creditos as $c) {
    echo "Crédito: {$c->numero_credito}\n";
    echo "  Estado: {$c->estado}\n";
    echo "  Fecha vencimiento: {$c->fecha_vencimiento}\n";
    echo "  Monto aprobado: {$c->monto_aprobado}\n";
    echo "  Capital pendiente: {$c->capital_pendiente}\n";

    // Ver prendas asociadas
    $prendas = Prenda::where('credito_prendario_id', $c->id)->get();
    echo "  Prendas asociadas: {$prendas->count()}\n";
    foreach ($prendas as $p) {
        echo "    - {$p->codigo_prenda}: estado={$p->estado}\n";
    }
    echo "\n";
}

echo "\n=== SOLUCIÓN SUGERIDA ===\n";
echo "Si quieres marcar las prendas como 'en_venta', tienes 2 opciones:\n\n";
echo "1. Cambiar el estado del crédito a 'vencido':\n";
echo "   UPDATE creditos_prendarios SET estado='vencido' WHERE numero_credito IN ('CR-26017641', 'CR-26016759');\n\n";
echo "2. Desvincular la prenda del crédito (NO recomendado):\n";
echo "   UPDATE prendas SET credito_prendario_id=NULL WHERE credito_prendario_id IN (...);\n\n";

