<?php

namespace App\Services;

use App\Models\ConfiguracionSistema;
use Illuminate\Support\Facades\Log;

class LogoResolverService
{
    /**
     * Resuelve el logo en formato Data URI (Base64) para DomPDF y previsualización web.
     * Prioridad de resolución jerárquica:
     * 1. Parámetro explícito ($custom: Base64, URL o nombre de archivo)
     * 2. Configuración en BD (empresa_logo_url o empresa_logo en configuraciones_sistema)
     * 3. Variable de entorno APP_LOGO u ORGANIZATION_LOGO
     * 4. Variable de entorno ORGANIZATION_SLUG (ej: avanza -> avanza_logo.png)
     * 5. Logo genérico estándar (storage/logos/logo.png o resources/logos/logo.png)
     * 6. Retorna null (permite renderizado de texto tipográfico limpio sin forzar marcas ajenas)
     */
    public function resolveBase64(?string $custom = null): ?string
    {
        // 1. Si ya viene un data URI en base64 explícito
        if (!empty($custom) && str_starts_with($custom, 'data:image')) {
            return $custom;
        }

        // 2. Si se pasó una ruta, nombre de archivo o URL específico
        if (!empty($custom)) {
            $base64 = $this->fileToBase64($custom);
            if ($base64 !== null) {
                return $base64;
            }
            if (filter_var($custom, FILTER_VALIDATE_URL)) {
                return $custom;
            }
        }

        // 3. Revisar configuración activa en Base de Datos (configuraciones_sistema)
        $dbLogo = ConfiguracionSistema::obtener('empresa_logo_url') 
            ?: ConfiguracionSistema::obtener('empresa_logo') 
            ?: ConfiguracionSistema::obtener('logo_activo');
        if (!empty($dbLogo)) {
            if (str_starts_with($dbLogo, 'data:image')) {
                return $dbLogo;
            }
            $base64 = $this->fileToBase64($dbLogo);
            if ($base64 !== null) {
                return $base64;
            }
            if (filter_var($dbLogo, FILTER_VALIDATE_URL)) {
                return $dbLogo;
            }
        }

        // 4. Revisar variable de entorno APP_LOGO u ORGANIZATION_LOGO
        $envLogo = env('APP_LOGO') ?: env('ORGANIZATION_LOGO');
        if (!empty($envLogo)) {
            $base64 = $this->fileToBase64($envLogo);
            if ($base64 !== null) {
                return $base64;
            }
        }

        // 5. Revisar ORGANIZATION_SLUG o EMPRESA_NOMBRE / APP_NAME (ej: prendamas o predamas)
        $slug = strtolower(trim((string) (env('ORGANIZATION_SLUG') ?: env('EMPRESA_NOMBRE') ?: env('APP_NAME') ?: '')));
        if (!empty($slug)) {
            $candidates = [
                "{$slug}_logo.png",
                "{$slug}.png",
                "{$slug}_logo.jpg",
                "{$slug}.jpg",
                "{$slug}.svg",
            ];

            if (str_contains($slug, 'predama') || str_contains($slug, 'prendama')) {
                array_unshift($candidates, 'prendamas_logo.png', 'prendamas.png', 'predamas_logo.png', 'predamas.png');
            }

            foreach ($candidates as $cand) {
                $base64 = $this->fileToBase64($cand);
                if ($base64 !== null) {
                    return $base64;
                }
            }
        }

        // 6. Revisar logo genérico o disponible del sistema si existe
        $genericCandidates = [
            'prendamas_logo.png',
            'storage/logos/prendamas_logo.png',
            'logos/prendamas_logo.png',
            'storage/logos/logo.png',
            'logos/logo.png',
            'logo.png',
        ];
        foreach ($genericCandidates as $cand) {
            $base64 = $this->fileToBase64($cand);
            if ($base64 !== null) {
                return $base64;
            }
        }

        // 7. Retorna null: la vista mostrará texto corporativo limpio sin asumir ninguna marca
        return null;
    }

    /**
     * Convierte una ruta o nombre de archivo de imagen a Data URI Base64.
     */
    public function fileToBase64(string $pathOrName): ?string
    {
        $clean = ltrim($pathOrName, '/\\');
        $fileName = basename($clean);

        $possiblePaths = [
            // Ruta exacta dada
            $pathOrName,
            public_path($clean),
            public_path('storage/' . $clean),
            public_path('storage/logos/' . $fileName),
            storage_path('app/public/' . $clean),
            storage_path('app/public/logos/' . $fileName),
            resource_path($clean),
            resource_path('logos/' . $fileName),
            resource_path('logos/' . $fileName . '.png'),
        ];

        foreach ($possiblePaths as $fullPath) {
            if (!empty($fullPath) && file_exists($fullPath) && is_file($fullPath)) {
                try {
                    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                    $mime = match ($ext) {
                        'png' => 'image/png',
                        'jpg', 'jpeg' => 'image/jpeg',
                        'svg' => 'image/svg+xml',
                        'webp' => 'image/webp',
                        'gif' => 'image/gif',
                        default => mime_content_type($fullPath) ?: 'image/png',
                    };
                    $content = file_get_contents($fullPath);
                    if ($content !== false && strlen($content) > 0) {
                        return 'data:' . $mime . ';base64,' . base64_encode($content);
                    }
                } catch (\Throwable $e) {
                    Log::warning("Error leyendo archivo de logo [{$fullPath}]: " . $e->getMessage());
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Obtiene el catálogo de logos existentes en el servidor (en resources/logos y storage/app/public/logos).
     * Esto permite a cada instalación elegir su logo desde la UI o CLI sin borrar archivos.
     */
    public function getLogosDisponibles(): array
    {
        $logos = [];
        $carpetas = [
            'sistema' => resource_path('logos'),
            'subidos' => storage_path('app/public/logos'),
        ];

        $extensionesValidas = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

        foreach ($carpetas as $origen => $dir) {
            if (is_dir($dir)) {
                $archivos = scandir($dir);
                foreach ($archivos as $archivo) {
                    if ($archivo === '.' || $archivo === '..') {
                        continue;
                    }
                    $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
                    if (in_array($ext, $extensionesValidas)) {
                        $nombreLimpio = ucwords(str_replace(['_', '-', '.' . $ext], ' ', $archivo));
                        $dataBase64 = $this->fileToBase64($origen === 'subidos' ? "storage/logos/{$archivo}" : "logos/{$archivo}");

                        $logos[] = [
                            'id' => $archivo,
                            'nombre' => trim($nombreLimpio),
                            'archivo' => $archivo,
                            'origen' => $origen,
                            'preview_base64' => $dataBase64,
                            'url' => $origen === 'subidos' ? asset("storage/logos/{$archivo}") : null,
                        ];
                    }
                }
            }
        }

        return $logos;
    }
}
