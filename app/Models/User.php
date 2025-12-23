<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'rol',
        'activo',
        'failed_login_attempts',
        'last_failed_login_at',
        'locked_until',
        'last_login_ip',
        'last_login_at',
        'password_changed_at',
        'force_password_change',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'failed_login_attempts' => 'integer',
            'last_failed_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'force_password_change' => 'boolean',
        ];
    }

    /**
     * Los permisos del usuario
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function hasPermission(string $modulo, string $accion): bool
    {
        // Administrador tiene todos los permisos
        if ($this->rol === 'administrador') {
            return true;
        }

        return $this->permissions()
            ->where('modulo', $modulo)
            ->where('accion', $accion)
            ->exists();
    }

    /**
     * Verificar si el usuario tiene acceso a un módulo (cualquier acción)
     */
    public function hasModuleAccess(string $modulo): bool
    {
        // Administrador tiene acceso a todo
        if ($this->rol === 'administrador') {
            return true;
        }

        return $this->permissions()
            ->where('modulo', $modulo)
            ->exists();
    }

    /**
     * Obtener todos los permisos del usuario formateados
     */
    public function getFormattedPermissions(): array
    {
        // Administrador tiene todos los permisos
        if ($this->rol === 'administrador') {
            $permisos = [];
            foreach (Permission::$permisosPorModulo as $modulo => $acciones) {
                $permisos[] = [
                    'modulo' => $modulo,
                    'acciones' => $acciones,
                ];
            }
            return $permisos;
        }

        $permisos = $this->permissions()->get()->groupBy('modulo');
        $resultado = [];

        foreach ($permisos as $modulo => $permisosModulo) {
            $resultado[] = [
                'modulo' => $modulo,
                'acciones' => $permisosModulo->pluck('accion')->toArray(),
            ];
        }

        return $resultado;
    }

    /**
     * Sincronizar permisos del usuario
     */
    public function syncPermissions(array $permisos): void
    {
        $permissionIds = [];

        foreach ($permisos as $permiso) {
            $modulo = $permiso['modulo'];
            $acciones = $permiso['acciones'] ?? [];

            foreach ($acciones as $accion) {
                $permission = Permission::where('modulo', $modulo)
                    ->where('accion', $accion)
                    ->first();

                if ($permission) {
                    $permissionIds[] = $permission->id;
                }
            }
        }

        $this->permissions()->sync($permissionIds);
    }

    /**
     * Asignar permisos por defecto según el rol
     */
    public function assignDefaultPermissions(): void
    {
        $rol = $this->rol;
        $permisosRol = Permission::$permisosPorRol[$rol] ?? [];

        // Si es administrador, asignar todos
        if ($permisosRol === '*') {
            $allPermissions = Permission::all()->pluck('id')->toArray();
            $this->permissions()->sync($allPermissions);
            return;
        }

        $permissionIds = [];
        foreach ($permisosRol as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                $permission = Permission::where('modulo', $modulo)
                    ->where('accion', $accion)
                    ->first();

                if ($permission) {
                    $permissionIds[] = $permission->id;
                }
            }
        }

        $this->permissions()->sync($permissionIds);
    }
}
