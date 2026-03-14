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
        'refrendos' => ['ver', 'validar', 'calcular', 'procesar'],
        'prendas' => ['ver', 'editar', 'cambiar_estado', 'vender'],
        'ventas' => ['ver', 'tasar', 'vender', 'apartar', 'crear_plan_pago', 'modificar_precio', 'aplicar_descuento'],
        'caja' => ['abrir', 'cerrar', 'ver_movimientos'],
        'reportes' => ['generar', 'exportar'],
        'usuarios' => ['ver', 'crear', 'editar', 'eliminar', 'asignar_permisos'],
        'compras' => ['ver', 'crear', 'editar', 'eliminar'],
        'categorias_productos' => ['ver', 'crear', 'editar', 'eliminar', 'toggle_activa'],
        'gastos' => ['ver', 'crear', 'editar', 'eliminar', 'asignar_credito'],
        'auditoria' => ['ver', 'exportar'], // Solo para superadmin
        'boveda' => ['ver', 'transferir', 'ver_movimientos'],
        'migracion' => ['ver', 'importar', 'descargar_plantilla'],
        'contabilidad' => ['ver', 'configurar', 'asientos', 'reportes'],
        'cobros' => ['realizar', 'ver', 'imprimir_recibo'],
        'recibos' => ['ver', 'imprimir'],
        'historial' => ['ver'],
        'otros_gastos' => ['ver', 'crear', 'anular'],
        'cotizaciones' => ['ver', 'crear', 'editar', 'eliminar', 'convertir'],
        'planes_interes' => ['ver', 'crear', 'editar', 'eliminar'],
        'transferencias' => ['ver', 'crear', 'aprobar', 'anular'],
    ];

    /**
     * Permisos por rol por defecto
     */
    public static array $permisosPorRol = [
        'superadmin' => '*', // Todos los permisos, acceso a auditoría y cambio de sucursales
        'administrador' => '*', // Todos los permisos excepto auditoría
        'cajero' => [
            'dashboard' => ['ver'],
            'clientes' => ['ver', 'crear'],
            'creditos' => ['ver', 'crear'],
            'refrendos' => ['ver', 'validar', 'calcular', 'procesar'],
            'compras' => ['ver', 'crear'],
            'caja' => ['abrir', 'cerrar', 'ver_movimientos'],
            'prendas' => ['ver'],
            'gastos' => ['ver', 'asignar_credito'],
            'cobros' => ['realizar', 'ver', 'imprimir_recibo'],
            'recibos' => ['ver', 'imprimir'],
            'historial' => ['ver'],
            'otros_gastos' => ['ver', 'crear'],
            'planes_interes' => ['ver'],
        ],
        'tasador' => [
            'dashboard' => ['ver'],
            'clientes' => ['ver'],
            'simulador' => ['usar', 'imprimir', 'guardar'],
            'prendas' => ['ver'],
            'ventas' => ['ver', 'tasar'],
            'compras' => ['ver', 'crear'],
            'planes_interes' => ['ver'],
        ],
        'vendedor' => [
            'dashboard' => ['ver'],
            'clientes' => ['ver', 'crear'],
            'ventas' => ['ver', 'vender', 'apartar', 'crear_plan_pago', 'aplicar_descuento'],
            'compras' => ['ver'],
            'prendas' => ['ver'],
            'cotizaciones' => ['ver', 'crear', 'editar'],
        ],
        'supervisor' => [
            'dashboard' => ['ver'],
            'clientes' => ['ver'],
            'refrendos' => ['ver', 'validar', 'calcular', 'procesar'],
            'creditos' => ['ver', 'renovar'],
            'compras' => ['ver', 'crear', 'editar'],
            'prendas' => ['ver', 'cambiar_estado'],
            'ventas' => ['ver', 'modificar_precio', 'aplicar_descuento'],
            'reportes' => ['generar', 'exportar'],
            'caja' => ['ver_movimientos'],
            'categorias_productos' => ['ver', 'crear', 'editar'],
            'gastos' => ['ver', 'crear', 'editar', 'asignar_credito'],
            'cobros' => ['realizar', 'ver', 'imprimir_recibo'],
            'recibos' => ['ver', 'imprimir'],
            'historial' => ['ver'],
            'otros_gastos' => ['ver', 'crear', 'anular'],
            'cotizaciones' => ['ver', 'crear', 'editar', 'eliminar', 'convertir'],
            'planes_interes' => ['ver'],
            'transferencias' => ['ver'],
        ],
    ];
}

