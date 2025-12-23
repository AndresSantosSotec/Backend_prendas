<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'modulo',
        'accion',
        'descripcion',
    ];

    /**
     * Los usuarios que tienen este permiso
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions');
    }

    /**
     * Permisos por módulo con sus acciones
     */
    public static array $permisosPorModulo = [
        'dashboard' => ['ver'],
        'clientes' => ['ver', 'crear', 'editar', 'eliminar'],
        'sucursales' => ['ver', 'crear', 'editar', 'eliminar'],
        'simulador' => ['usar', 'imprimir', 'guardar'],
        'creditos' => ['ver', 'crear', 'renovar', 'cancelar', 'pasar_venta'],
        'prendas' => ['ver', 'editar', 'cambiar_estado', 'vender'],
        'ventas' => ['ver', 'tasar', 'vender', 'apartar', 'crear_plan_pago', 'modificar_precio', 'aplicar_descuento'],
        'caja' => ['abrir', 'cerrar', 'ver_movimientos'],
        'cobros' => ['realizar', 'ver', 'imprimir_recibo'],
        'historial' => ['ver'],
        'reportes' => ['generar', 'exportar'],
        'usuarios' => ['ver', 'crear', 'editar', 'eliminar', 'asignar_permisos'],
    ];

    /**
     * Permisos por rol por defecto
     */
    public static array $permisosPorRol = [
        'administrador' => '*', // Todos los permisos
        'cajero' => [
            'dashboard' => ['ver'],
            'clientes' => ['ver', 'crear'],
            'creditos' => ['ver', 'crear'],
            'caja' => ['abrir', 'cerrar', 'ver_movimientos'],
            'cobros' => ['realizar', 'ver', 'imprimir_recibo'],
            'prendas' => ['ver'],
            'historial' => ['ver'],
        ],
        'tasador' => [
            'dashboard' => ['ver'],
            'clientes' => ['ver'],
            'simulador' => ['usar', 'imprimir', 'guardar'],
            'prendas' => ['ver'],
            'ventas' => ['ver', 'tasar'],
            'historial' => ['ver'],
        ],
        'vendedor' => [
            'dashboard' => ['ver'],
            'clientes' => ['ver', 'crear'],
            'ventas' => ['ver', 'vender', 'apartar', 'crear_plan_pago', 'aplicar_descuento'],
            'prendas' => ['ver'],
            'historial' => ['ver'],
        ],
        'supervisor' => [
            'dashboard' => ['ver'],
            'clientes' => ['ver'],
            'creditos' => ['ver', 'renovar'],
            'prendas' => ['ver', 'cambiar_estado'],
            'ventas' => ['ver', 'modificar_precio', 'aplicar_descuento'],
            'reportes' => ['generar', 'exportar'],
            'caja' => ['ver_movimientos'],
            'historial' => ['ver'],
        ],
    ];
}

