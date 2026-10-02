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
     * Resuelve el origen óptimo para DomPDF:
     * 1. Prioriza la ruta física del archivo local en disco si existe (DomPDF lee archivos locales al 100% de confiabilidad sin depender de /tmp ni descodificación Base64 en memoria).
     * 2. Si no hay archivo físico local pero sí Base64 o URL remota, devuelve el Base64 o URL.
     */
    public function resolveForPdf(?string $custom = null): ?string
    {
        // 1. Intentar resolver la ruta física local en disco
        $localPath = $this->resolveFilePath($custom);
        if ($localPath !== null) {
            return $localPath;
        }

        // 2. Si ya es Base64 explícito
        if (!empty($custom) && str_starts_with($custom, 'data:image')) {
            return $custom;
        }

        // 3. Fallback a resolución Base64
        return $this->resolveBase64($custom);
    }

    /**
     * Resuelve la ruta física del logo en el servidor local si existe.
     * Retorna una ruta absoluta normalizada (ej: /home/.../public/logos/prendamas_logo.png)
     */
    public function resolveFilePath(?string $custom = null): ?string
    {
        // 1. Si se pasó una ruta o nombre de archivo específico
        if (!empty($custom) && !str_starts_with($custom, 'data:image') && !filter_var($custom, FILTER_VALIDATE_URL)) {
            $path = $this->findExistingFilePath($custom);
            if ($path !== null) {
                return $path;
            }
        }

        // 2. Revisar configuración en BD
        $dbLogo = ConfiguracionSistema::obtener('empresa_logo_url') 
            ?: ConfiguracionSistema::obtener('empresa_logo') 
            ?: ConfiguracionSistema::obtener('logo_activo');
        if (!empty($dbLogo) && !str_starts_with($dbLogo, 'data:image') && !filter_var($dbLogo, FILTER_VALIDATE_URL)) {
            $path = $this->findExistingFilePath($dbLogo);
            if ($path !== null) {
                return $path;
            }
        }

        // 3. Revisar variable de entorno APP_LOGO u ORGANIZATION_LOGO
        $envLogo = env('APP_LOGO') ?: env('ORGANIZATION_LOGO');
        if (!empty($envLogo)) {
            $path = $this->findExistingFilePath($envLogo);
            if ($path !== null) {
                return $path;
            }
        }

        // 4. Revisar slug o nombre de empresa
        $slug = strtolower(trim((string) (env('ORGANIZATION_SLUG') ?: env('EMPRESA_NOMBRE') ?: env('APP_NAME') ?: '')));
        if (!empty($slug)) {
            $candidates = [
                "{$slug}_logo.png",
                "{$slug}.png",
                "{$slug}_logo.jpg",
                "{$slug}.jpg",
            ];
            if (str_contains($slug, 'predama') || str_contains($slug, 'prendama')) {
                array_unshift($candidates, 'prendamas_logo.png', 'prendamas.png', 'predamas_logo.png', 'predamas.png');
            }
            foreach ($candidates as $cand) {
                $path = $this->findExistingFilePath($cand);
                if ($path !== null) {
                    return $path;
                }
            }
        }

        // 5. Revisar candidatos genéricos
        $genericCandidates = [
            'prendamas_logo.png',
            'logos/prendamas_logo.png',
            'storage/logos/prendamas_logo.png',
            'storage/logos/logo.png',
            'logos/logo.png',
            'logo.png',
        ];
        foreach ($genericCandidates as $cand) {
            $path = $this->findExistingFilePath($cand);
            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Localiza un archivo en los directorios públicos, recursos o almacenamiento.
     */
    public function findExistingFilePath(string $pathOrName): ?string
    {
        if (str_starts_with($pathOrName, 'data:image') || filter_var($pathOrName, FILTER_VALIDATE_URL)) {
            return null;
        }

        $clean = ltrim($pathOrName, '/\\');
        $fileName = basename($clean);

        $possiblePaths = [
            $pathOrName,
            public_path($clean),
            public_path('logos/' . $fileName),
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
                return str_replace('\\', '/', realpath($fullPath) ?: $fullPath);
            }
        }

        return null;
    }

    /**
     * Convierte una ruta o nombre de archivo de imagen a Data URI Base64.
     */
    public function fileToBase64(string $pathOrName): ?string
    {
        $filePath = $this->findExistingFilePath($pathOrName);
        if ($filePath !== null) {
            try {
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'svg' => 'image/svg+xml',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                    default => mime_content_type($filePath) ?: 'image/png',
                };
                $content = file_get_contents($filePath);
                if ($content !== false && strlen($content) > 0) {
                    return 'data:' . $mime . ';base64,' . base64_encode($content);
                }
            } catch (\Throwable $e) {
                Log::warning("Error leyendo archivo de logo [{$filePath}]: " . $e->getMessage());
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
