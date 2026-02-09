<?php

namespace App\Http\Controllers;

use App\Models\CodigoPrereservado;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class CodigoPrereservadoController extends Controller
{
    /**
     * Reservar o recuperar códigos únicos para el wizard de crédito
     *
     * Este endpoint genera códigos únicos que se mantienen durante toda la sesión
     * del wizard, asegurando que el código de barras sea el mismo en el recibo,
     * contrato y cuando se crea el crédito finalmente.
     */
    public function reservar(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string|max:100',
            'cliente_id' => 'nullable|string|max:50', // Puede ser UUID o ID entero
            'sucursal_id' => 'nullable|integer', // ID de sucursal/agencia
        ]);

        try {
            $usuarioId = Auth::id();
            $sessionToken = $request->session_token;
            $clienteId = $request->cliente_id;
            $sucursalId = $request->sucursal_id ?? 1;

            Log::info('Reservando códigos', [
                'session_token' => $sessionToken,
                'usuario_id' => $usuarioId,
                'cliente_id' => $clienteId,
                'sucursal_id' => $sucursalId
            ]);

            $reserva = CodigoPrereservado::reservarCodigos(
                sessionToken: $sessionToken,
                usuarioId: $usuarioId,
                clienteId: $clienteId,
                sucursalId: $sucursalId,
                horasExpiracion: 24 // Los códigos expiran en 24 horas si no se usan
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $reserva->id,
                    'codigo_credito' => $reserva->codigo_credito,
                    'codigo_prenda' => $reserva->codigo_prenda,
                    'session_token' => $reserva->session_token,
                    'fecha_expiracion' => $reserva->fecha_expiracion->toIso8601String(),
                    'estado' => $reserva->estado,
                ],
                'message' => 'Códigos reservados exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al reservar códigos: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al reservar códigos',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Obtener códigos reservados por session token
     */
    public function obtener(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string|max:100',
        ]);

        try {
            $reserva = CodigoPrereservado::buscarPorSessionToken($request->session_token);

            if (!$reserva) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró una reserva activa para este token',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $reserva->id,
                    'codigo_credito' => $reserva->codigo_credito,
                    'codigo_prenda' => $reserva->codigo_prenda,
                    'session_token' => $reserva->session_token,
                    'fecha_expiracion' => $reserva->fecha_expiracion->toIso8601String(),
                    'estado' => $reserva->estado,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener códigos',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Liberar códigos reservados (si el usuario cancela el proceso)
     */
    public function liberar(Request $request): JsonResponse
    {
        $request->validate([
            'session_token' => 'required|string|max:100',
        ]);

        try {
            $reserva = CodigoPrereservado::buscarPorSessionToken($request->session_token);

            if ($reserva) {
                $reserva->update(['estado' => 'expirado']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Códigos liberados exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al liberar códigos',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Generar token de sesión único para el wizard
     */
    public function generarToken(): JsonResponse
    {
        $token = Str::uuid()->toString() . '-' . time();

        return response()->json([
            'success' => true,
            'data' => [
                'session_token' => $token,
            ]
        ]);
    }
}
