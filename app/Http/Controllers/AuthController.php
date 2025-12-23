<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthSecurityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected AuthSecurityService $securityService;

    public function __construct(AuthSecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Login de usuario con protecciones de seguridad
     */
    public function login(Request $request): JsonResponse
    {
        $ip = $request->ip();
        
        // Verificar si la IP está bloqueada
        if ($this->securityService->isIpBlocked($ip)) {
            Log::warning('Intento de login desde IP bloqueada', ['ip' => $ip]);
            return response()->json([
                'success' => false,
                'message' => 'Demasiados intentos fallidos. Intente más tarde.',
                'retry_after' => AuthSecurityService::LOCKOUT_MINUTES
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('username', 'password');
        
        // Buscar por username o email
        $user = User::where('username', $credentials['username'])
            ->orWhere('email', $credentials['username'])
            ->first();

        // Usuario no encontrado
        if (!$user) {
            $this->securityService->recordFailedAttemptForUnknownUser($credentials['username'], $ip);
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas'
            ], 401);
        }

        // Verificar si el usuario está bloqueado
        if ($this->securityService->isUserLocked($user)) {
            $remainingMinutes = $this->securityService->getLockoutRemainingMinutes($user);
            return response()->json([
                'success' => false,
                'message' => "Cuenta bloqueada temporalmente. Intente en {$remainingMinutes} minutos.",
                'locked' => true,
                'retry_after' => $remainingMinutes
            ], 423); // 423 Locked
        }

        // Verificar contraseña
        if (!Hash::check($credentials['password'], $user->password)) {
            $this->securityService->recordFailedAttempt($user, $ip);
            
            $attemptsLeft = AuthSecurityService::MAX_FAILED_ATTEMPTS - $user->fresh()->failed_login_attempts;
            $message = 'Credenciales inválidas';
            
            if ($attemptsLeft > 0 && $attemptsLeft <= 3) {
                $message .= ". {$attemptsLeft} intentos restantes.";
            }
            
            return response()->json([
                'success' => false,
                'message' => $message,
                'attempts_remaining' => max(0, $attemptsLeft)
            ], 401);
        }

        // Verificar si el usuario está activo
        if (!$user->activo) {
            Log::warning('Intento de login de usuario inactivo', [
                'user_id' => $user->id,
                'username' => $user->username,
                'ip' => $ip
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Usuario inactivo. Contacta al administrador.'
            ], 403);
        }

        // Login exitoso - limpiar intentos fallidos
        $this->securityService->clearFailedAttempts($user, $ip);

        // Crear token de autenticación (Sanctum) con nombre identificador
        $tokenName = 'auth-token-' . now()->timestamp;
        $token = $user->createToken($tokenName, ['*'], now()->addHours(24))->plainTextToken;

        $response = [
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'user' => $this->formatUser($user),
                'token' => $token,
                'expires_in' => 86400 // 24 horas en segundos
            ]
        ];

        // Verificar si necesita cambiar contraseña
        if ($this->securityService->needsPasswordChange($user)) {
            $response['data']['require_password_change'] = true;
            $response['message'] = 'Login exitoso. Se requiere cambio de contraseña.';
        }

        return response()->json($response);
    }

    /**
     * Obtener usuario autenticado
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatUser($user)
        ]);
    }

    /**
     * Logout - revocar token actual
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        
        Log::info('Logout de usuario', [
            'user_id' => $user->id,
            'username' => $user->username,
            'ip' => $request->ip()
        ]);

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    /**
     * Logout de todos los dispositivos
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $this->securityService->revokeAllTokens($user);

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada en todos los dispositivos'
        ]);
    }

    /**
     * Cambiar contraseña del usuario actual
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => array_merge(
                AuthSecurityService::getPasswordValidationRules(),
                ['different:current_password']
            ),
            'new_password_confirmation' => 'required|same:new_password',
        ], array_merge(
            AuthSecurityService::getPasswordValidationMessages(),
            [
                'new_password.different' => 'La nueva contraseña debe ser diferente a la actual',
                'new_password_confirmation.same' => 'Las contraseñas no coinciden'
            ]
        ));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verificar contraseña actual
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña actual es incorrecta'
            ], 401);
        }

        // Validar fortaleza de la nueva contraseña
        $passwordErrors = $this->securityService->validatePasswordStrength($request->new_password);
        if (!empty($passwordErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña no cumple los requisitos de seguridad',
                'errors' => ['new_password' => $passwordErrors]
            ], 422);
        }

        // Actualizar contraseña
        $user->update([
            'password' => Hash::make($request->new_password),
            'password_changed_at' => now(),
            'force_password_change' => false
        ]);

        // Revocar todos los otros tokens (seguridad)
        $currentTokenId = $request->user()->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        Log::info('Contraseña cambiada exitosamente', [
            'user_id' => $user->id,
            'username' => $user->username,
            'ip' => $request->ip()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada exitosamente'
        ]);
    }

    /**
     * Refrescar token
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revocar token actual
        $request->user()->currentAccessToken()->delete();
        
        // Crear nuevo token
        $tokenName = 'auth-token-' . now()->timestamp;
        $token = $user->createToken($tokenName, ['*'], now()->addHours(24))->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'expires_in' => 86400
            ]
        ]);
    }

    /**
     * Formatear usuario para respuesta (sin datos sensibles)
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'nombre' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'rol' => $user->rol,
            'activo' => $user->activo,
            'permisos' => $user->getFormattedPermissions(),
            'last_login_at' => $user->last_login_at?->toISOString(),
        ];
    }
}
