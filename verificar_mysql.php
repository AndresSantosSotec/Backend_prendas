<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=== VERIFICACIÓN DE CONFIGURACIÓN MYSQL ===\n\n";

try {
    // Obtener max_allowed_packet
    $result = DB::select("SHOW VARIABLES LIKE 'max_allowed_packet'");
    $maxPacket = $result[0]->Value ?? 'N/A';
    $maxPacketMB = round($maxPacket / 1024 / 1024, 2);

    echo "📦 max_allowed_packet actual: " . number_format($maxPacket) . " bytes (" . $maxPacketMB . " MB)\n";

    // Obtener wait_timeout
    $result = DB::select("SHOW VARIABLES LIKE 'wait_timeout'");
    $waitTimeout = $result[0]->Value ?? 'N/A';
    echo "⏱️  wait_timeout: " . $waitTimeout . " segundos\n";

    // Obtener versión de MySQL
    $result = DB::select("SELECT VERSION() as version");
    $version = $result[0]->version ?? 'N/A';
    echo "🔢 Versión MySQL: " . $version . "\n";

    echo "\n=== RECOMENDACIONES ===\n\n";

    if ($maxPacket < 64 * 1024 * 1024) {
        echo "⚠️  PROBLEMA: max_allowed_packet es muy bajo!\n";
        echo "   Las imágenes base64 pueden ser muy grandes.\n\n";

        echo "💡 SOLUCIÓN 1 (TEMPORAL):\n";
        echo "   Ejecutar en MySQL:\n";
        echo "   SET GLOBAL max_allowed_packet = 67108864;  -- 64MB\n\n";

        echo "💡 SOLUCIÓN 2 (PERMANENTE):\n";
        echo "   Agregar en my.ini o my.cnf:\n";
        echo "   [mysqld]\n";
        echo "   max_allowed_packet = 64M\n\n";

        echo "💡 SOLUCIÓN 3 (MEJOR - EVITAR IMÁGENES BASE64):\n";
        echo "   Guardar imágenes como archivos en storage/ en lugar de base64.\n\n";
    } else {
        echo "✅ max_allowed_packet es adecuado.\n\n";
    }

    echo "📁 Ubicación del archivo de configuración MySQL:\n";
    $result = DB::select("SHOW VARIABLES LIKE 'datadir'");
    $datadir = $result[0]->Value ?? 'N/A';
    echo "   " . $datadir . "\n";
    echo "   (Buscar my.ini o my.cnf en el directorio padre)\n\n";

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}
