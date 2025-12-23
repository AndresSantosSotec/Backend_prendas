<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AuthSecurityService
{
    /**
     * Número máximo de intentos fallidos antes del bloqueo
     */
    const MAX_FAILED_ATTEMPTS = 5;
    
    /**
     * Minutos de bloqueo después de exceder intentos
     */
    const LOCKOUT_MINUTES = 15;
    
    /**
     * Minutos para resetear el contador de intentos
     */
    const ATTEMPT_RESET_MINUTES = 30;
    
    /**
     * Requisitos mínimos de contraseña
     */
    const PASSWORD_MIN_LENGTH = 8;
    const PASSWORD_REQUIRE_UPPERCASE = true;
    const PASSWORD_REQUIRE_LOWERCASE = true;
    const PASSWORD_REQUIRE_NUMBER = true;
    const PASSWORD_REQUIRE_SPECIAL = true;

    /**
     * Verificar si una IP está bloqueada por rate limiting
     */
    public function isIpBlocked(string $ip): bool
    {
        $key = "login_attempts_ip:{$ip}";
        $attempts = Cache::get($key, 0);
        
        return $attempts >= self::MAX_FAILED_ATTEMPTS * 2; // IP tiene límite más alto
    }

    /**
     * Verificar si un usuario está bloqueado
     */
    public function isUserLocked(User $user): bool
    {
        if (!$user->locked_until) {
            return false;
        }
        
        if (Carbon::parse($user->locked_until)->isPast()) {
            // El bloqueo expiró, limpiar
            $user->update([
                'locked_until' => null,
                'failed_login_attempts' => 0
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Obtener tiempo restante de bloqueo en minutos
     */
    public function getLockoutRemainingMinutes(User $user): int
    {
        if (!$user->locked_until) {
            return 0;
        }
        
        return max(0, Carbon::now()->diffInMinutes(Carbon::parse($user->locked_until), false));
    }

    /**
     * Registrar intento de login fallido
     */
    public function recordFailedAttempt(User $user, string $ip): void
    {
        // Incrementar intentos del usuario
        $user->increment('failed_login_attempts');
        $user->update(['last_failed_login_at' => now()]);
        
        // Incrementar intentos de la IP
        $ipKey = "login_attempts_ip:{$ip}";
        $ipAttempts = Cache::get($ipKey, 0) + 1;
        Cache::put($ipKey, $ipAttempts, now()->addMinutes(self::ATTEMPT_RESET_MINUTES));
        
        // Bloquear usuario si excede el límite
        if ($user->failed_login_attempts >= self::MAX_FAILED_ATTEMPTS) {
            $user->update([
                'locked_until' => now()->addMinutes(self::LOCKOUT_MINUTES)
            ]);
            
            Log::warning('Usuario bloqueado por múltiples intentos fallidos', [
                'user_id' => $user->id,
                'username' => $user->username,
                'ip' => $ip,
                'attempts' => $user->failed_login_attempts
            ]);
        }
        
        // Log del intento fallido
        Log::info('Intento de login fallido', [
            'user_id' => $user->id,
            'username' => $user->username,
            'ip' => $ip,
            'attempt_number' => $user->failed_login_attempts
        ]);
    }

    /**
     * Registrar intento fallido para usuario desconocido (por IP)
     */
    public function recordFailedAttemptForUnknownUser(string $username, string $ip): void
    {
        $ipKey = "login_attempts_ip:{$ip}";
        $ipAttempts = Cache::get($ipKey, 0) + 1;
        Cache::put($ipKey, $ipAttempts, now()->addMinutes(self::ATTEMPT_RESET_MINUTES));
        
        Log::info('Intento de login fallido - usuario no encontrado', [
            'username_attempted' => $username,
            'ip' => $ip,
            'ip_attempts' => $ipAttempts
        ]);
    }

    /**
     * Limpiar intentos fallidos después de login exitoso
     */
    public function clearFailedAttempts(User $user, string $ip): void
    {
        $user->update([
            'failed_login_attempts' => 0,
            'last_failed_login_at' => null,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $ip
        ]);
        
        // Limpiar intentos de IP
        $ipKey = "login_attempts_ip:{$ip}";
        Cache::forget($ipKey);
        
        Log::info('Login exitoso', [
            'user_id' => $user->id,
            'username' => $user->username,
            'ip' => $ip
        ]);
    }

    /**
     * Validar fortaleza de contraseña
     */
    public function validatePasswordStrength(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $errors[] = 'La contraseña debe tener al menos ' . self::PASSWORD_MIN_LENGTH . ' caracteres';
        }
        
        if (self::PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una letra mayúscula';
        }
        
        if (self::PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una letra minúscula';
        }
        
        if (self::PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos un número';
        }
        
        if (self::PASSWORD_REQUIRE_SPECIAL && !preg_match('/[!@#$%^&*(),.?":{}|<>_\-+=\[\]\\\\\/]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos un carácter especial (!@#$%^&*...)';
        }
        
        // Verificar contraseñas comunes
        $commonPasswords = ['password', '123456', '12345678', 'qwerty', 'admin', 'letmein', 'welcome'];
        if (in_array(strtolower($password), $commonPasswords)) {
            $errors[] = 'Esta contraseña es demasiado común y no es segura';
        }
        
        return $errors;
    }

    /**
     * Verificar si la contraseña necesita ser cambiada
     */
    public function needsPasswordChange(User $user): bool
    {
        // Si está marcado para cambio forzado
        if ($user->force_password_change) {
            return true;
        }
        
        // Si la contraseña tiene más de 90 días (opcional)
        if ($user->password_changed_at) {
            $daysSinceChange = Carbon::parse($user->password_changed_at)->diffInDays(now());
            // return $daysSinceChange > 90; // Descomentar para política de 90 días
        }
        
        return false;
    }

    /**
     * Revocar todos los tokens de un usuario (para logout en todos los dispositivos)
     */
    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
        
        Log::info('Todos los tokens revocados para usuario', [
            'user_id' => $user->id,
            'username' => $user->username
        ]);
    }

    /**
     * Generar reglas de validación de contraseña para Laravel
     */
    public static function getPasswordValidationRules(): array
    {
        $rules = ['required', 'string', 'min:' . self::PASSWORD_MIN_LENGTH];
        
        if (self::PASSWORD_REQUIRE_UPPERCASE) {
            $rules[] = 'regex:/[A-Z]/';
        }
        if (self::PASSWORD_REQUIRE_LOWERCASE) {
            $rules[] = 'regex:/[a-z]/';
        }
        if (self::PASSWORD_REQUIRE_NUMBER) {
            $rules[] = 'regex:/[0-9]/';
        }
        if (self::PASSWORD_REQUIRE_SPECIAL) {
            $rules[] = 'regex:/[!@#$%^&*(),.?":{}|<>_\-+=\[\]\\\\\/]/';
        }
        
        return $rules;
    }

    /**
     * Mensajes de error personalizados para validación de contraseña
     */
    public static function getPasswordValidationMessages(): array
    {
        return [
            'password.min' => 'La contraseña debe tener al menos ' . self::PASSWORD_MIN_LENGTH . ' caracteres',
            'password.regex' => 'La contraseña debe contener mayúsculas, minúsculas, números y caracteres especiales',
        ];
    }
}

