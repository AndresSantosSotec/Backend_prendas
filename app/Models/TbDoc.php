<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use App\Models\ConfiguracionSistema;

class TbDoc extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tb_docs';

    protected $fillable = [
        'organizacion_code',
        'sucursal_id',
        'tipo_documento',
        'nombre_documento',
        'plantilla_variante',
        'vista_pdf',
        'logo_url',
        'titulo_personalizado',
        'subtitulo_personalizado',
        'encabezado_texto',
        'pie_pagina_texto',
        'firmante_1_nombre',
        'firmante_1_titulo',
        'firmante_2_nombre',
        'firmante_2_titulo',
        'perito_contador_nombre',
        'perito_contador_registro',
        'mostrar_logo',
        'mostrar_firmas',
        'configuracion_extra',
        'activo',
    ];

    protected $casts = [
        'mostrar_logo' => 'boolean',
        'mostrar_firmas' => 'boolean',
        'activo' => 'boolean',
        'configuracion_extra' => 'array',
    ];

    /**
     * Relación con Sucursal
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    /**
     * Scope para plantillas activas
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope por tipo de documento
     */
    public function scopePorTipo($query, string $tipoDocumento)
    {
        return $query->where('tipo_documento', $tipoDocumento);
    }

    /**
     * Obtener configuración de plantilla activa para un tipo de documento
     */
    public static function obtenerPlantilla(string $tipoDocumento, ?int $sucursalId = null, ?string $orgCode = null): self
    {
        $orgCode = $orgCode ?: env('ORGANIZATION_CODE', '01');

        // 1. Buscar por sucursal específica si se indica
        if ($sucursalId) {
            $doc = static::where('organizacion_code', $orgCode)
                ->where('sucursal_id', $sucursalId)
                ->where('tipo_documento', $tipoDocumento)
                ->where('activo', true)
                ->first();
            if ($doc) return $doc;
        }

        // 2. Buscar por organización general (sucursal null)
        $docGeneral = static::where('organizacion_code', $orgCode)
            ->whereNull('sucursal_id')
            ->where('tipo_documento', $tipoDocumento)
            ->where('activo', true)
            ->first();
        if ($docGeneral) return $docGeneral;

        // 3. Fallback inteligente: buscar la plantilla base activa registrada en BD
        $docBase = static::where('tipo_documento', $tipoDocumento)
            ->where('activo', true)
            ->orderBy('id', 'asc')
            ->first();
        if ($docBase) return $docBase;

        // 4. Fallback: plantilla genérica en memoria
        return new static([
            'organizacion_code' => $orgCode,
            'sucursal_id' => $sucursalId,
            'tipo_documento' => $tipoDocumento,
            'nombre_documento' => ucwords(str_replace('_', ' ', $tipoDocumento)),
            'plantilla_variante' => 'estandar',
            'mostrar_logo' => true,
            'mostrar_firmas' => true,
            'activo' => true,
        ]);
    }

    /**
     * Obtener el logo convertido en Data URI (Base64) para renderizado seguro en DomPDF
     */
    public function obtenerLogoBase64(): ?string
    {
        if (isset($this->mostrar_logo) && !$this->mostrar_logo) {
            return null;
        }

        $logoPathOrUrl = $this->logo_url ?: ConfiguracionSistema::obtener('empresa_logo_url');

        if (!$logoPathOrUrl) {
            return null;
        }

        try {
            // Si es un data URI ya codificado
            if (str_starts_with($logoPathOrUrl, 'data:image')) {
                return $logoPathOrUrl;
            }

            // Ruta de almacenamiento local
            $relativePath = str_replace('/storage/', '', parse_url($logoPathOrUrl, PHP_URL_PATH) ?? $logoPathOrUrl);
            
            if (Storage::disk('public')->exists($relativePath)) {
                $fileContent = Storage::disk('public')->get($relativePath);
                $mimeType = Storage::disk('public')->mimeType($relativePath) ?? 'image/png';
                return 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
            }

            $localPublicPath = public_path(ltrim(parse_url($logoPathOrUrl, PHP_URL_PATH) ?? $logoPathOrUrl, '/'));
            if (file_exists($localPublicPath)) {
                $fileContent = file_get_contents($localPublicPath);
                $mimeType = mime_content_type($localPublicPath) ?: 'image/png';
                return 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
            }

            return $logoPathOrUrl;
        } catch (\Exception $e) {
            return null;
        }
    }
}
