<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class PrendaImagen extends Model
{
    use SoftDeletes;

    protected $table = 'prenda_imagenes';

    protected $fillable = [
        'prenda_id',
        'nombre_archivo',
        'ruta_almacenamiento',
        'url_publica',
        'mime_type',
        'tamano_bytes',
        'ancho',
        'alto',
        'tipo_imagen',
        'etiqueta',
        'descripcion',
        'es_principal',
        'orden',
        'hash_contenido',
        'ruta_thumbnail',
        'subida_por',
        'fecha_captura',
    ];

    protected $casts = [
        'es_principal' => 'boolean',
        'orden' => 'integer',
        'tamano_bytes' => 'integer',
        'ancho' => 'integer',
        'alto' => 'integer',
        'fecha_captura' => 'datetime',
    ];

    /**
     * Tipos de imagen permitidos
     */
    const TIPOS_IMAGEN = [
        'principal' => 'Imagen Principal',
        'frontal' => 'Vista Frontal',
        'trasera' => 'Vista Trasera',
        'lateral' => 'Vista Lateral',
        'detalle' => 'Detalle',
        'defecto' => 'Defecto/Daño',
        'marca' => 'Marca/Logo',
        'serie' => 'Número de Serie',
        'general' => 'General',
    ];

    /**
     * Relación con la prenda
     */
    public function prenda(): BelongsTo
    {
        return $this->belongsTo(Prenda::class);
    }

    /**
     * Usuario que subió la imagen
     */
    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subida_por');
    }

    /**
     * Obtener URL pública de la imagen
     */
    public function getUrlAttribute(): string
    {
        // Si es una URL de datos base64, devolverla tal cual
        if ($this->url_publica && str_starts_with($this->url_publica, 'data:')) {
            return $this->url_publica;
        }

        // Si es una URL absoluta (http/https), devolverla tal cual
        if ($this->url_publica && preg_match('/^https?:\/\//', $this->url_publica)) {
            return $this->url_publica;
        }

        // Si tiene url_publica relativa, convertirla a absoluta usando asset()
        if ($this->url_publica) {
            // url_publica ya tiene formato /storage/...
            // Usamos url() para obtener la URL absoluta
            return url($this->url_publica);
        }

        // Fallback a Storage URL
        return url(Storage::url($this->ruta_almacenamiento));
    }

    /**
     * Obtener URL del thumbnail
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->ruta_thumbnail) {
            return url(Storage::url($this->ruta_thumbnail));
        }
        return $this->url;
    }

    /**
     * Tamaño formateado
     */
    public function getTamanoFormateadoAttribute(): string
    {
        $bytes = $this->tamano_bytes;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    /**
     * Dimensiones como string
     */
    public function getDimensionesAttribute(): ?string
    {
        if ($this->ancho && $this->alto) {
            return "{$this->ancho}x{$this->alto}";
        }
        return null;
    }

    /**
     * Scope para imágenes principales
     */
    public function scopePrincipal($query)
    {
        return $query->where('es_principal', true);
    }

    /**
     * Scope por tipo de imagen
     */
    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo_imagen', $tipo);
    }

    /**
     * Scope ordenado
     */
    public function scopeOrdenado($query)
    {
        return $query->orderBy('es_principal', 'desc')->orderBy('orden');
    }

    /**
     * Buscar imagen duplicada por hash
     */
    public static function buscarPorHash(string $hash): ?self
    {
        return self::where('hash_contenido', $hash)->first();
    }

    /**
     * Crear desde archivo base64
     */
    public static function crearDesdeBase64(
        int $prendaId,
        string $base64Data,
        string $tipoImagen = 'general',
        ?int $subidaPor = null,
        int $orden = 0
    ): self {
        // Extraer datos del base64
        $matches = [];
        preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches);
        $extension = $matches[1] ?? 'jpg';
        $mimeType = "image/{$extension}";

        // Decodificar contenido
        $contenido = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $base64Data));

        // Generar hash para detectar duplicados
        $hash = hash('sha256', $contenido);

        // Verificar si ya existe esta imagen
        $existente = self::buscarPorHash($hash);
        if ($existente && $existente->prenda_id === $prendaId) {
            return $existente;
        }

        // Generar nombre único
        $nombreArchivo = sprintf(
            'prenda_%d_%s_%s.%s',
            $prendaId,
            $tipoImagen,
            uniqid(),
            $extension
        );

        // Ruta de almacenamiento
        $carpeta = 'prendas/' . date('Y/m');
        $rutaCompleta = "{$carpeta}/{$nombreArchivo}";

        // Guardar archivo
        Storage::disk('public')->put($rutaCompleta, $contenido);

        // Obtener dimensiones
        $imagenInfo = @getimagesizefromstring($contenido);
        $ancho = $imagenInfo[0] ?? null;
        $alto = $imagenInfo[1] ?? null;

        return self::create([
            'prenda_id' => $prendaId,
            'nombre_archivo' => $nombreArchivo,
            'ruta_almacenamiento' => $rutaCompleta,
            'url_publica' => Storage::url($rutaCompleta),
            'mime_type' => $mimeType,
            'tamano_bytes' => strlen($contenido),
            'ancho' => $ancho,
            'alto' => $alto,
            'tipo_imagen' => $tipoImagen,
            'es_principal' => $orden === 0,
            'orden' => $orden,
            'hash_contenido' => $hash,
            'subida_por' => $subidaPor,
        ]);
    }
}
