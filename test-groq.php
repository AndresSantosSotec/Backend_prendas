<?php

require __DIR__.'/vendor/autoload.php';

echo "🧪 Probando conexión con GROQ API...\n\n";

try {
    $client = new \GuzzleHttp\Client();

    $response = $client->post('https://api.groq.com/openai/v1/chat/completions', [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Bearer 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',
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


