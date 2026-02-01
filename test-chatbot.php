<?php

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;

echo "🧪 Probando endpoint del chatbot...\n\n";

try {
    $client = new Client([
        'base_uri' => 'http://localhost:8000',
        'timeout' => 30,
    ]);

    // Obtener token de prueba (asume que tienes un usuario admin)
    echo "1️⃣ Autenticando...\n";
    $loginResponse = $client->post('/api/login', [
        'json' => [
            'email' => 'admin@admin.com',
            'password' => 'password'
        ]
    ]);

    $loginData = json_decode($loginResponse->getBody(), true);

    if (!isset($loginData['token'])) {
        echo "❌ Error: No se pudo obtener token\n";
        exit(1);
    }

    $token = $loginData['token'];
    echo "✅ Token obtenido\n\n";

    // Probar endpoint del chatbot
    echo "2️⃣ Consultando chatbot...\n";
    $chatbotResponse = $client->post('/api/v1/chatbot/consultar', [
        'headers' => [
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'json' => [
            'mensaje' => '¿Cómo crear un empeño?',
            'contexto_sistema' => 'creditos: Gestión de créditos prendarios
clientes: Gestión de clientes
caja: Control de caja diaria'
        ]
    ]);

    $chatbotData = json_decode($chatbotResponse->getBody(), true);

    echo "✅ Status: " . $chatbotResponse->getStatusCode() . "\n";
    echo "📊 Respuesta:\n";
    echo str_repeat('─', 60) . "\n";
    print_r($chatbotData);
    echo str_repeat('─', 60) . "\n";

    if (isset($chatbotData['success']) && $chatbotData['success']) {
        echo "\n✅ Chatbot funcionando correctamente!\n";
        echo "\n💬 Respuesta de la IA:\n";
        echo $chatbotData['respuesta'] ?? 'Sin respuesta';
        echo "\n\n";
    } else {
        echo "\n❌ Error en la respuesta del chatbot\n";
    }

} catch (\GuzzleHttp\Exception\ClientException $e) {
    echo "❌ Error del cliente: " . $e->getResponse()->getStatusCode() . "\n";
    echo $e->getResponse()->getBody() . "\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
