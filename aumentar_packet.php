<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=== AUMENTANDO max_allowed_packet ===\n\n";

try {
    // Aumentar a 64MB
    DB::statement("SET GLOBAL max_allowed_packet = 67108864");

    echo "✅ max_allowed_packet aumentado a 64MB exitosamente!\n\n";

    // Verificar
    $result = DB::select("SHOW VARIABLES LIKE 'max_allowed_packet'");
    $maxPacket = $result[0]->Value ?? 'N/A';
    $maxPacketMB = round($maxPacket / 1024 / 1024, 2);

    echo "📦 Nuevo valor: " . number_format($maxPacket) . " bytes (" . $maxPacketMB . " MB)\n\n";

    echo "⚠️  NOTA: Este cambio es TEMPORAL.\n";
    echo "   Se perderá cuando reinicies MySQL/XAMPP.\n\n";

    echo "💡 Para hacer el cambio PERMANENTE:\n";
    echo "   1. Abre: C:\\xampp\\mysql\\bin\\my.ini\n";
    echo "   2. Busca la sección [mysqld]\n";
    echo "   3. Agrega o modifica:\n";
    echo "      max_allowed_packet = 64M\n";
    echo "   4. Reinicia MySQL desde el panel de XAMPP\n\n";

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}
