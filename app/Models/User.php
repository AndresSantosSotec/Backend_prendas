<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\CajaAperturaCierre;
use App\Traits\Auditable;

/**
 * @method bool hasPermission(string $modulo, string $accion)
 * @method bool hasRole(array|string $roles)
 * @method bool hasModuleAccess(string $modulo)
 * @property string $rol
 * @property int|null $sucursal_id
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, Auditable;

    protected string $auditoriaModulo = 'usuarios';
    protected array $auditoriaIgnorar = ['password', 'remember_token', 'api_token', 'updated_at'];
    public static bool $auditarDeshabilitado = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'foto_url',
        'password',
        'rol',
        'activo',
        'sucursal_id', // Added this line
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
        // SuperAdmin y Administrador tienen todos los permisos
        if (in_array($this->rol, ['superadmin', 'administrador'])) {
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
        // SuperAdmin y Administrador tienen acceso a todo
        if (in_array($this->rol, ['superadmin', 'administrador'])) {
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
        // SuperAdmin y Administrador tienen todos los permisos
        if (in_array($this->rol, ['superadmin', 'administrador'])) {
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

    /**
     * Verificar si el usuario tiene uno de los roles especificados
     */
    public function hasRole(array|string $roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        // Normalizar roles (admin -> administrador, etc.)
        $normalizedRoles = array_map(function ($role) {
            return match(strtolower($role)) {
                'admin' => 'administrador',
                'gerente', 'manager' => 'gerente',
                'cajero', 'cashier' => 'cajero',
                'vendedor', 'seller' => 'vendedor',
                default => strtolower($role)
            };
        }, $roles);

        return in_array($this->rol, $normalizedRoles);
    }

    /**
     * Sucursal a la que pertenece el usuario
     */
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Cajas de apertura/cierre del usuario
     */
    public function cajasApertura()
    {
        return $this->hasMany(CajaAperturaCierre::class);
    }
}
