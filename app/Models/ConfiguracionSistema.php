<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionSistema extends Model
{
    protected $table = 'configuraciones_sistema';

    protected $fillable = [
        'clave',
        'valor',
        'tipo',
        'grupo',
        'descripcion',
        'editable_por_usuario',
    ];

    protected $casts = [
        'editable_por_usuario' => 'boolean',
    ];

    /**
     * Obtiene el valor casteado según el tipo declarado.
     */
    public function getValorCasteadoAttribute(): mixed
    {
        return match ($this->tipo) {
            'boolean' => filter_var($this->valor, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->valor,
            'float'   => (float) $this->valor,
            'json'    => json_decode($this->valor, true),
            default   => $this->valor,
        };
    }

    /**
     * Obtiene el valor de una clave de configuración.
     * Si no existe la clave, retorna $default.
     */
    public static function obtener(string $clave, mixed $default = null): mixed
    {
        $config = static::where('clave', $clave)->first();

        if (!$config) {
            return $default;
        }

        return $config->valor_casteado;
    }

    /**
     * Establece el valor de una clave de configuración.
     * Crea la entrada si no existe.
     */
    public static function establecer(string $clave, mixed $valor, string $tipo = 'string'): static
    {
        $valorString = is_bool($valor) ? ($valor ? 'true' : 'false') : (string) $valor;

        return static::updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valorString, 'tipo' => $tipo]
        );
    }

    /**
     * Verifica si la integración Caja-Bóveda está activa.
     */
    public static function integracionCajaBovedaActiva(): bool
    {
        return (bool) static::obtener('cash_vault_integration_enabled', false);
    }

    /**
     * Activa o desactiva la integración Caja-Bóveda.
     */
    public static function setIntegracionCajaBoveda(bool $activa): void
    {
        static::establecer('cash_vault_integration_enabled', $activa, 'boolean');
    }
}
