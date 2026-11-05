<?php

/**
 * Script independiente para verificar usuarios y permisos de Macro Financiera S.A.
 * Ejecución directa:
 *   ea-php83 verify_macro_users.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "\n================================================================\n";
echo "🔍 VERIFICACIÓN DE USUARIOS: MACRO FINANCIERA S.A.\n";
echo "================================================================\n\n";

$emails = [
    'brayanajpacaja547@gmail.com' => 'Gerente General (administrador)',
    'adielajpacaja@gmail.com'     => 'Contador General (contador)',
    'svictoriavp@gmail.com'        => 'Secretaria y Cajera (cajero)',
    'Vasqueztoto2018@gmail.com'    => 'Asesor de Créditos Prendarios (cajero/créditos)',
    'visiondefuego@hotmail.com'    => 'Asesor de Venta de Incobrables (vendedor)',
];

$todoOk = true;

foreach ($emails as $email => $cargoEsperado) {
    $user = User::with(['permissions', 'sucursal'])->where('email', $email)->first();

    if (!$user) {
        echo "❌ [NO EXISTE] {$email} ({$cargoEsperado})\n";
        echo "   -> Ejecute: ea-php83 artisan db:seed --class=MacroFinancieraUsuariosSeeder --force\n\n";
        $todoOk = false;
        continue;
    }

    $total = $user->permissions->count();
    $sucursal = $user->sucursal?->nombre ?? 'Sin sucursal';

    echo "✅ [{$user->rol}] {$user->name}\n";
    echo "   Email:    {$user->email}\n";
    echo "   Username: {$user->username}\n";
    echo "   Cargo:    {$cargoEsperado}\n";
    echo "   Sucursal: {$sucursal}\n";
    echo "   Permisos: {$total} asignados\n";

    // Módulos asignados agrupados
    $modulos = $user->permissions->groupBy('modulo')->map->count();
    $modulosStr = [];
    foreach ($modulos as $mod => $cant) {
        $modulosStr[] = "{$mod} ({$cant})";
    }
    echo "   Módulos:  " . implode(', ', $modulosStr) . "\n\n";
}

echo "================================================================\n";
if ($todoOk) {
    echo "🎉 TODOS LOS USUARIOS TIENEN SUS PERMISOS OPERATIVOS.\n";
} else {
    echo "⚠️ Hay usuarios pendientes por sembrar.\n";
}
echo "================================================================\n\n";

