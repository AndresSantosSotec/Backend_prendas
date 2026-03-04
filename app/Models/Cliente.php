<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class Cliente extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditoriaModulo = 'clientes';
    protected array $auditoriaIgnorar = ['fotografia', 'updated_at'];
    public static bool $auditarDeshabilitado = false;

    protected $fillable = [
        'codigo_cliente',
        'nombres',
        'apellidos',
        'dpi',
        'nit',
        'fecha_nacimiento',
        'genero',
        'telefono',
        'telefono_secundario',
        'email',
        'direccion',
        'municipio',
        'departamento_geoname_id',
        'municipio_geoname_id',
        'fotografia',
        'estado',
        'sucursal',
        'tipo_cliente',
        'notas',
        'eliminado',
        'eliminado_en',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'eliminado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'nombre_completo',
    ];

    /**
     * Obtener el nombre completo del cliente
     */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }

    /**
     * Obtener la URL de la fotografía
     */
    public function getFotografiaUrlAttribute(): ?string
    {
        if (!$this->fotografia) {
            return null;
        }

        // Si es una URL completa, retornarla
        if (filter_var($this->fotografia, FILTER_VALIDATE_URL)) {
            return $this->fotografia;
        }

        // Si es una ruta de archivo, retornar la URL pública
        if (str_starts_with($this->fotografia, 'storage/')) {
            return asset($this->fotografia);
        }

        // Si es base64, retornarlo como está
        if (str_starts_with($this->fotografia, 'data:image')) {
            return $this->fotografia;
        }

        return asset('storage/' . $this->fotografia);
    }

    /**
     * Boot del modelo - generar código automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        // Generar código de cliente al crear
        static::creating(function ($cliente) {
            if (empty($cliente->codigo_cliente)) {
                $cliente->codigo_cliente = static::generarCodigoCliente();
            }
        });
    }

    /**
     * Generar código único de cliente
     * Formato: CLI-YYYYMMDD-XXXXXX
     *
     * @return string
     */
    public static function generarCodigoCliente(): string
    {
        $fecha = date('Ymd'); // 20260102

        // Obtener el último ID + 1
        $ultimoCliente = static::withTrashed()->latest('id')->first();
        $siguienteId = $ultimoCliente ? $ultimoCliente->id + 1 : 1;

        $numero = str_pad($siguienteId, 6, '0', STR_PAD_LEFT); // 000001

        $codigo = "CLI-{$fecha}-{$numero}";

        // Verificar que no exista (por si acaso)
        $contador = 1;
        while (static::where('codigo_cliente', $codigo)->exists()) {
            $numero = str_pad($siguienteId + $contador, 6, '0', STR_PAD_LEFT);
            $codigo = "CLI-{$fecha}-{$numero}";
            $contador++;
        }

        return $codigo;
    }

    /**
     * Relaciones
     */
    public function creditosPrendarios()
    {
        return $this->hasMany(CreditoPrendario::class, 'cliente_id');
    }

    public function prendasCompradas()
    {
        return $this->hasMany(Prenda::class, 'comprador_id');
    }

    public function referencias()
    {
        return $this->hasMany(ClienteReferencia::class, 'cliente_id');
    }
}


