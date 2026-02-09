<?php

require __DIR__.'/vendor/autoload.php';

// 🔐 Cargar variables de entorno desde .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "🧪 Probando conexión con GROQ API...\n\n";

// ✅ Obtener API key desde variable de entorno
$apiKey = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY');

if (empty($apiKey) || $apiKey === 'your_groq_api_key_here') {
    echo "❌ ERROR: GROQ_API_KEY no configurada en el archivo .env\n";
    echo "💡 Configura tu API key en el archivo .env:\n";
    echo "   GROQ_API_KEY=tu_api_key_aqui\n\n";
    exit(1);
}

echo "🔑 API Key cargada desde variable de entorno\n\n";

try {
    $client = new \GuzzleHttp\Client();

    $response = $client->post('https://api.groq.com/openai/v1/chat/completions', [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer ' . $apiKey, // ✅ Usar variable de entorno
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'model' => 'llama-3.1-8b-instant',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Eres un asistente del sistema de empeños Digiprenda. Responde en español, sé conciso.'
                ],
                [
                    'role' => 'user',
                    'content' => '¿Cómo crear un crédito prendario?'
                ]
            ],
            'temperature' => 0.2,
            'max_tokens' => 300
        ]
    ]);

    $statusCode = $response->getStatusCode();
    echo "✅ Status: $statusCode\n\n";

    if ($statusCode === 200) {
        $data = json_decode($response->getBody(), true);
        echo "✅ Respuesta de GROQ:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo $data['choices'][0]['message']['content'] . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        echo "📊 Tokens usados: " . $data['usage']['total_tokens'] . "\n";
        echo "⏱️  Modelo: " . $data['model'] . "\n";
        echo "🚀 Velocidad: ~500 tokens/segundo\n";
    } else {
        echo "❌ Error: " . $response->getBody() . "\n";
    }
} catch (\GuzzleHttp\Exception\RequestException $e) {
    echo "❌ Error de red: " . $e->getMessage() . "\n";
    if ($e->hasResponse()) {
        echo "Respuesta: " . $e->getResponse()->getBody() . "\n";
    }
} catch (Exception $e) {
    echo "❌ Excepción: " . $e->getMessage() . "\n";
}

echo "\n✅ Test completado\n";



