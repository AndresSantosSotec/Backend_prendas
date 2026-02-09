<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CreditoPrendario;
use Carbon\Carbon;

echo "\n=== ACTUALIZAR CÓDIGOS DE CRÉDITOS AL NUEVO FORMATO ===\n\n";
echo "¿Deseas actualizar TODOS los códigos antiguos (CR-XXXXXX) al nuevo formato (DDMMYYAACORRELATIVO)?\n";
echo "NOTA: Esta acción NO se puede deshacer. Se recomienda hacer backup de la BD primero.\n\n";
echo "Escribe 'SI' para continuar o cualquier otra cosa para cancelar: ";

$confirmacion = trim(fgets(STDIN));

if (strtoupper($confirmacion) !== 'SI') {
    echo "\n❌ Operación cancelada.\n\n";
    exit(0);
}

echo "\n⏳ Iniciando actualización...\n\n";

// Obtener todos los créditos con formato antiguo
$creditosAntiguos = CreditoPrendario::withTrashed()
    ->where('numero_credito', 'LIKE', 'CR-%')
    ->orderBy('created_at', 'asc')
    ->get();

echo "📊 Se encontraron {$creditosAntiguos->count()} créditos con formato antiguo.\n\n";

if ($creditosAntiguos->count() === 0) {
    echo "✅ No hay créditos que actualizar.\n\n";
    exit(0);
}

$actualizados = 0;
$errores = 0;

// Agrupar por fecha de creación y agencia
$creditosPorFechaAgencia = [];

foreach ($creditosAntiguos as $credito) {
    $fechaCreacion = Carbon::parse($credito->created_at);
    $fecha = $fechaCreacion->format('dmy');
    $agencia = str_pad($credito->sucursal_id ?? 1, 2, '0', STR_PAD_LEFT);
    $clave = $fecha . '-' . $agencia;

    if (!isset($creditosPorFechaAgencia[$clave])) {
        $creditosPorFechaAgencia[$clave] = [];
    }

    $creditosPorFechaAgencia[$clave][] = $credito;
}

// Actualizar cada grupo
foreach ($creditosPorFechaAgencia as $clave => $creditos) {
    list($fecha, $agencia) = explode('-', $clave);
    $correlativo = 1;

    foreach ($creditos as $credito) {
        try {
            $nuevoNumero = $fecha . $agencia . str_pad($correlativo, 6, '0', STR_PAD_LEFT);

            echo "  Actualizando: {$credito->numero_credito} → {$nuevoNumero}\n";

            $credito->numero_credito = $nuevoNumero;
            $credito->save();

            $actualizados++;
            $correlativo++;
        } catch (\Exception $e) {
            echo "  ❌ Error al actualizar crédito {$credito->id}: {$e->getMessage()}\n";
            $errores++;
        }
    }
}

echo "\n✅ Actualización completada.\n";
echo "   📊 Créditos actualizados: {$actualizados}\n";
echo "   ❌ Errores: {$errores}\n\n";

echo "Ejemplos de nuevos códigos:\n";
$ejemplos = CreditoPrendario::whereNotNull('numero_credito')
    ->where('numero_credito', 'NOT LIKE', 'CR-%')
    ->limit(5)
    ->get();

foreach ($ejemplos as $ej) {
    echo "  - {$ej->numero_credito} (Creado: {$ej->created_at->format('d/m/Y')})\n";
}

echo "\n";
