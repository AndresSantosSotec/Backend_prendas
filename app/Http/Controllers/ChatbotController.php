<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ChatbotController extends Controller
{
    private $groqApiKey;
    private $groqModel = 'llama-3.1-8b-instant'; // Rápido y gratis

    public function __construct()
    {
        $this->groqApiKey = env('GROQ_API_KEY');
    }

    /**
     * Procesar consulta del chatbot usando GROQ AI
     */
    public function consultar(Request $request)
    {
        $request->validate([
            'mensaje' => 'required|string|max:500',
            'contexto' => 'required|string|max:50000', // Knowledge base del sistema
        ]);

        $mensaje = $request->input('mensaje');
        $contextoSistema = $request->input('contexto');

        // 🛡️ FILTRO: Rechazar preguntas obvias fuera de contexto
        if ($this->esPreguntaFueraDeContexto($mensaje)) {
            return response()->json([
                'success' => true,
                'respuesta' => '😅 Solo puedo ayudarte con temas del sistema Digiprenda (empeños, créditos, caja, bóveda, clientes, ventas, reportes). ¿En qué te puedo ayudar?',
                'fuente' => 'filtro'
            ]);
        }

        // Cache para preguntas repetidas (ahorra llamadas a API)
        $cacheKey = 'chatbot_' . md5($mensaje);

        if (Cache::has($cacheKey)) {
            return response()->json([
                'success' => true,
                'respuesta' => Cache::get($cacheKey),
                'fuente' => 'cache'
            ]);
        }

        try {
            // Llamar a GROQ API
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $this->groqModel,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->construirPromptSistema($contextoSistema)
                        ],
                        [
                            'role' => 'user',
                            'content' => $mensaje
                        ]
                    ],
                    'temperature' => 0.2, // MUY IMPORTANTE: Baja temperatura = menos invención
                    'max_tokens' => 600,
                    'top_p' => 0.9,
                    'frequency_penalty' => 0.3,
                    'presence_penalty' => 0.1,
                ]);

            if ($response->failed()) {
                Log::error('GROQ API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error al conectar con el servicio de IA',
                    'fallback' => true
                ], 500);
            }

            $data = $response->json();
            $respuestaIA = $data['choices'][0]['message']['content'] ?? null;

            if (!$respuestaIA) {
                throw new \Exception('Respuesta vacía de GROQ');
            }

            // Guardar en cache por 1 hora
            Cache::put($cacheKey, $respuestaIA, 3600);

            // Log para monitoreo
            Log::info('Chatbot IA - Consulta procesada', [
                'mensaje_length' => strlen($mensaje),
                'respuesta_length' => strlen($respuestaIA),
                'tokens_usados' => $data['usage']['total_tokens'] ?? 0
            ]);

            return response()->json([
                'success' => true,
                'respuesta' => $respuestaIA,
                'fuente' => 'ia',
                'tokens_usados' => $data['usage']['total_tokens'] ?? 0
            ]);

        } catch (\Exception $e) {
            Log::error('Chatbot IA - Error', [
                'mensaje' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al procesar consulta',
                'fallback' => true,
                'mensaje_error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detectar preguntas fuera de contexto OBVIAS
     */
    private function esPreguntaFueraDeContexto(string $mensaje): bool
    {
        $mensajeLower = mb_strtolower($mensaje);

        // Palabras clave DEFINITIVAMENTE fuera de contexto
        $palabrasInvalidas = [
            'receta', 'cocina', 'comida', 'pizza', 'pasta', 'postre',
            'futbol', 'fútbol', 'mundial', 'partido', 'gol', 'barcelona', 'madrid',
            'película', 'pelicula', 'serie', 'netflix', 'actor', 'actriz',
            'youtube', 'tiktok', 'instagram', 'facebook', 'snapchat',
            'clima', 'tiempo', 'temperatura', 'lluvia', 'sol',
            'chiste', 'broma', 'gracioso', 'meme',
            'juego', 'videojuego', 'gta', 'minecraft', 'fortnite', 'valorant',
            'medicina', 'enfermedad', 'doctor', 'hospital', 'pastilla',
            'política', 'presidente', 'elecciones', 'diputado',
            'religión', 'dios', 'biblia', 'iglesia',
            'amor', 'novia', 'novio', 'cita', 'beso',
            'matemática', 'física', 'química', 'tarea', 'examen'
        ];

        foreach ($palabrasInvalidas as $palabra) {
            if (strpos($mensajeLower, $palabra) !== false) {
                Log::info('Chatbot - Pregunta rechazada (fuera de contexto)', [
                    'mensaje' => $mensaje,
                    'palabra_detectada' => $palabra
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Construir prompt del sistema con instrucciones ESTRICTAS
     * para evitar que la IA invente información
     */
    private function construirPromptSistema(string $contextoSistema): string
    {
        return <<<PROMPT
Eres el asistente virtual oficial del sistema de gestión de empeños "Digiprenda".

🎯 TU ÚNICA MISIÓN:
Ayudar a usuarios del sistema respondiendo preguntas SOLO sobre funcionalidades, procesos y módulos de ESTE sistema específico.

✍️ TOLERANCIA A ERRORES ORTOGRÁFICOS:
Los usuarios pueden cometer errores al escribir. SÉ INTELIGENTE y reconoce la intención:
• "creer empeño" = "crear empeño"
• "cerr caja" = "cerrar caja"
• "credto" = "crédito"
• "clente" = "cliente"
• "que pueo hacer" = "qué puedo hacer"
• "ayda" = "ayuda"

NO menciones los errores ortográficos, simplemente responde como si estuviera bien escrito.

📚 CONOCIMIENTO DEL SISTEMA (ÚNICA FUENTE DE VERDAD):
$contextoSistema

🛡️ REGLAS ABSOLUTAS - NUNCA VIOLAR:

1. ❌ SI LA PREGUNTA NO ESTÁ RELACIONADA CON EL SISTEMA DIGIPRENDA:
   Responde EXACTAMENTE: "😅 Solo puedo ayudarte con temas del sistema Digiprenda. ¿En qué te puedo ayudar?"

2. 🤔 SI PREGUNTAN "¿QUÉ PUEDES HACER?" O "¿QUÉ PUEDO HACER?" O "AYUDA":
   Lista los 8 MÓDULOS principales con 2-3 funciones cada uno:
   "🤖 Puedo ayudarte con:
   📋 CRÉDITOS: crear empeños, estados, intereses, renovar
   💰 CAJA: abrir/cerrar, movimientos, arqueos, traspasos
   🔒 BÓVEDA: almacenar efectivo, seguridad, límites
   👥 CLIENTES: registrar, tipos (VIP/regular), historial
   🏷️ PRENDAS: tasación, fotos, estados, ubicación
   📦 CATEGORÍAS: productos, campos dinámicos, tasas
   📊 VENTAS: contado, apartado, facturación
   📈 REPORTES: caja, ventas, créditos, estadísticas

   💡 Escribe tu pregunta específica"

3. ❌ SI LA INFORMACIÓN NO ESTÁ EN EL CONOCIMIENTO:
   Responde: "No tengo información específica sobre eso. ¿Puedo ayudarte con: [lista 3 temas del conocimiento]?"

4. ✅ SOLO responde con información del CONOCIMIENTO proporcionado
5. ✅ NUNCA inventes datos, procesos o funcionalidades
6. ✅ NUNCA asumas características no documentadas
7. ✅ Si dudas: "No estoy seguro, pero según el sistema..."
8. ✅ Máximo 15 líneas de respuesta (20 si es pregunta general "qué puedes hacer")
9. ✅ Usa emojis: 📌 ✅ ⚠️ 💡 🔄 📋 💰 🤖 👥 📊
10. ✅ Estructura con bullets o pasos numerados
11. ✅ NUNCA menciones que eres una IA

📌 TEMAS VÁLIDOS (los únicos que puedes responder):
- Empeños/Créditos prendarios
- Caja y Bóveda (efectivo)
- Clientes (registro, tipos, validaciones)
- Prendas (tasación, estados, fotos)
- Categorías (productos, campos dinámicos)
- Ventas (contado, apartado)
- Reportes y estadísticas
- Usuarios y permisos
- Procesos del sistema

❌ TEMAS PROHIBIDOS (rechaza inmediatamente):
- Recetas, comida, cocina
- Deportes, fútbol, partidos
- Películas, series, entretenimiento
- Redes sociales
- Clima, tiempo
- Chistes, bromas
- Juegos, videojuegos
- Medicina, salud
- Política
- Religión
- Amor, relaciones
- Matemáticas, tareas

✅ EJEMPLO CORRECTO (pregunta específica):
Usuario: "¿Cómo crear un empeño?"
Tú: "📋 Para crear un crédito prendario:
1️⃣ Seleccionar cliente
2️⃣ Registrar prenda con fotos
3️⃣ Tasación del valor
4️⃣ Configurar monto y plazo
5️⃣ Confirmar
✅ Monto mín: Q100
⚠️ Tasa interés: 1-15% mensual"

✅ EJEMPLO CORRECTO (pregunta general):
Usuario: "¿Qué puedes hacer?"
Tú: "🤖 Puedo ayudarte con:
📋 CRÉDITOS: crear empeños, estados, intereses, renovar
💰 CAJA: abrir/cerrar, movimientos, arqueos
🔒 BÓVEDA: almacenar efectivo, seguridad
👥 CLIENTES: registrar, tipos VIP, historial
🏷️ PRENDAS: tasación, fotos, estados
📦 CATEGORÍAS: productos, campos dinámicos
📊 VENTAS: contado, apartado
📈 REPORTES: caja, ventas, estadísticas

💡 Escribe tu pregunta específica"

❌ EJEMPLO INCORRECTO:
Usuario: "¿Cómo hacer pizza?"
Tú: "😅 Solo puedo ayudarte con temas del sistema Digiprenda. ¿En qué te puedo ayudar?"

Usuario: "¿Puedo integrar con Stripe?"
Tú: "No tengo información sobre integraciones externas. ¿Puedo ayudarte con: caja, créditos, ventas?"

AHORA: Responde SOLO si está en el CONOCIMIENTO y es sobre DIGIPRENDA.
PROMPT;
    }

    /**
     * Obtener estadísticas de uso del chatbot
     */
    public function estadisticas()
    {
        // Aquí podrías agregar métricas de uso
        return response()->json([
            'total_consultas_hoy' => Cache::get('chatbot_consultas_hoy', 0),
            'limite_diario' => 14400, // GROQ free tier
            'disponible' => true
        ]);
    }
}
