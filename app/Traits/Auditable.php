<?php

namespace App\Traits;

use App\Services\AuditoriaService;
use Illuminate\Support\Facades\Auth;

/**
 * Trait Auditable
 *
 * Agrega auditoría automática a los modelos de Eloquent.
 * Registra automáticamente las operaciones de crear, actualizar y eliminar.
 *
 * Uso:
 * class MiModelo extends Model
 * {
 *     use Auditable;
 *
 *     // Opcional: definir nombre del módulo para auditoría
 *     protected string $auditoriaModulo = 'mi_modulo';
 *
 *     // Opcional: campos a ignorar en la auditoría
 *     protected array $auditoriaIgnorar = ['remember_token', 'updated_at'];
 * }
 */
trait Auditable
{
    /**
     * Array para rastrear qué modelos tienen auditoría deshabilitada
     * Clave: nombre de la clase, Valor: boolean
     */
    private static array $auditarDeshabilitadoPorClase = [];

    /**
     * Boot del trait
     */
    public static function bootAuditable(): void
    {
        // Registrar cuando se crea un modelo
        static::created(function ($model) {
            if (!$model->debeAuditar()) {
                return;
            }

            $model->registrarAuditoria('crear', null, $model->getAuditData());
        });

        // Registrar cuando se actualiza un modelo
        static::updated(function ($model) {
            if (!$model->debeAuditar()) {
                return;
            }

            $original = $model->getOriginal();
            $cambios = $model->getChanges();

            // Filtrar campos ignorados
            $camposIgnorar = $model->getCamposIgnorar();
            $cambios = array_diff_key($cambios, array_flip($camposIgnorar));

            // Solo auditar si hay cambios reales
            if (empty($cambios)) {
                return;
            }

            // Obtener solo los valores originales de los campos que cambiaron
            $datosAnteriores = array_intersect_key($original, $cambios);

            $model->registrarAuditoria('actualizar', $datosAnteriores, $cambios);
        });

        // Registrar cuando se elimina un modelo
        static::deleted(function ($model) {
            if (!$model->debeAuditar()) {
                return;
            }

            $model->registrarAuditoria('eliminar', $model->getAuditData(), null);
        });

        // Si el modelo usa SoftDeletes, registrar restauración
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                if (!$model->debeAuditar()) {
                    return;
                }

                $model->registrarAuditoria('restaurar', null, $model->getAuditData());
            });
        }
    }

    /**
     * Verificar si se debe auditar
     */
    protected function debeAuditar(): bool
    {
        // Verificar si la auditoría está deshabilitada para esta clase
        $clase = static::class;
        if (isset(self::$auditarDeshabilitadoPorClase[$clase]) && self::$auditarDeshabilitadoPorClase[$clase]) {
            return false;
        }

        // No auditar si no hay usuario autenticado (operaciones de sistema/seeder)
        if (!Auth::check()) {
            return property_exists($this, 'auditarSinUsuario') && $this->auditarSinUsuario;
        }

        return true;
    }

    /**
     * Obtener campos a ignorar en la auditoría
     */
    protected function getCamposIgnorar(): array
    {
        $default = ['remember_token', 'updated_at', 'password', 'api_token'];

        if (property_exists($this, 'auditoriaIgnorar')) {
            return array_merge($default, $this->auditoriaIgnorar);
        }

        return $default;
    }

    /**
     * Obtener datos para auditoría (filtrados)
     */
    protected function getAuditData(): array
    {
        $data = $this->toArray();
        $camposIgnorar = $this->getCamposIgnorar();

        return array_diff_key($data, array_flip($camposIgnorar));
    }

    /**
     * Obtener nombre del módulo para auditoría
     */
    protected function getAuditoriaModulo(): string
    {
        if (property_exists($this, 'auditoriaModulo')) {
            return $this->auditoriaModulo;
        }

        // Generar nombre del módulo basado en el nombre de la clase
        $className = class_basename($this);
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }

    /**
     * Obtener descripción de la auditoría
     */
    protected function getAuditoriaDescripcion(string $accion): string
    {
        $tabla = $this->getTable();
        $id = $this->getKey();

        $descripciones = [
            'crear' => "Registro creado en {$tabla}",
            'actualizar' => "Registro #{$id} actualizado en {$tabla}",
            'eliminar' => "Registro #{$id} eliminado de {$tabla}",
            'restaurar' => "Registro #{$id} restaurado en {$tabla}",
        ];

        // Si el modelo tiene un método personalizado para descripción
        if (method_exists($this, 'getAuditoriaDescripcionPersonalizada')) {
            return $this->getAuditoriaDescripcionPersonalizada($accion);
        }

        return $descripciones[$accion] ?? "Acción {$accion} en {$tabla}";
    }

    /**
     * Registrar auditoría
     */
    protected function registrarAuditoria(string $accion, ?array $datosAnteriores, ?array $datosNuevos): void
    {
        AuditoriaService::log(
            modulo: $this->getAuditoriaModulo(),
            accion: $accion,
            tabla: $this->getTable(),
            registroId: (string) $this->getKey(),
            datosAnteriores: $datosAnteriores,
            datosNuevos: $datosNuevos,
            descripcion: $this->getAuditoriaDescripcion($accion)
        );
    }

    /**
     * Ejecutar operación sin auditoría
     *
     * Uso:
     * MiModelo::sinAuditoria(function() {
     *     MiModelo::create([...]);
     * });
     */
    public static function sinAuditoria(callable $callback)
    {
        $clase = static::class;
        self::$auditarDeshabilitadoPorClase[$clase] = true;

        try {
            return $callback();
        } finally {
            self::$auditarDeshabilitadoPorClase[$clase] = false;
        }
    }
}
