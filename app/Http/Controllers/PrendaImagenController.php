<?php

namespace App\Http\Controllers;

use App\Models\Prenda;
use App\Models\PrendaImagen;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PrendaImagenController extends Controller
{
    /**
     * Listar imágenes de una prenda
     */
    public function index(int $prendaId): JsonResponse
    {
        $prenda = Prenda::findOrFail($prendaId);

        $imagenes = $prenda->imagenesNormalizadas()->get()->map(function ($img) {
            return $this->formatImagen($img);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'prenda_id' => (string) $prendaId,
                'total' => $imagenes->count(),
                'imagenes' => $imagenes,
            ],
        ]);
    }

    /**
     * Subir nuevas imágenes a una prenda
     */
    public function store(Request $request, int $prendaId): JsonResponse
    {
        $prenda = Prenda::findOrFail($prendaId);

        $validator = Validator::make($request->all(), [
            'imagenes' => 'required|array|min:1',
            'imagenes.*.data' => 'required_without:imagenes.*.url|string', // Base64
            'imagenes.*.url' => 'required_without:imagenes.*.data|url', // URL externa
            'imagenes.*.tipo' => 'nullable|string|in:principal,frontal,trasera,lateral,detalle,defecto,marca,serie,general',
            'imagenes.*.etiqueta' => 'nullable|string|max:100',
            'imagenes.*.descripcion' => 'nullable|string|max:500',
            'imagenes.*.es_principal' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $imagenesCreadas = [];
        $ordenActual = $prenda->imagenesNormalizadas()->max('orden') ?? -1;

        foreach ($request->imagenes as $imagenData) {
            $ordenActual++;
            $tipoImagen = $imagenData['tipo'] ?? 'general';
            $esPrincipal = $imagenData['es_principal'] ?? false;

            // Si se marca como principal, quitar la marca de las demás
            if ($esPrincipal) {
                $prenda->imagenesNormalizadas()->update(['es_principal' => false]);
            }

            if (!empty($imagenData['data'])) {
                // Subir desde base64
                $imagen = PrendaImagen::crearDesdeBase64(
                    $prenda->id,
                    $imagenData['data'],
                    $tipoImagen,
                    Auth::id(),
                    $ordenActual
                );

                if (!empty($imagenData['etiqueta'])) {
                    $imagen->update(['etiqueta' => $imagenData['etiqueta']]);
                }
                if (!empty($imagenData['descripcion'])) {
                    $imagen->update(['descripcion' => $imagenData['descripcion']]);
                }
                if ($esPrincipal) {
                    $imagen->update(['es_principal' => true]);
                }

                $imagenesCreadas[] = $this->formatImagen($imagen->fresh());
            } elseif (!empty($imagenData['url'])) {
                // Registrar URL externa
                $imagen = PrendaImagen::create([
                    'prenda_id' => $prenda->id,
                    'nombre_archivo' => basename(parse_url($imagenData['url'], PHP_URL_PATH) ?: 'imagen_externa'),
                    'ruta_almacenamiento' => $imagenData['url'],
                    'url_publica' => $imagenData['url'],
                    'tipo_imagen' => $tipoImagen,
                    'etiqueta' => $imagenData['etiqueta'] ?? null,
                    'descripcion' => $imagenData['descripcion'] ?? null,
                    'es_principal' => $esPrincipal,
                    'orden' => $ordenActual,
                    'subida_por' => Auth::id(),
                ]);

                $imagenesCreadas[] = $this->formatImagen($imagen);
            }
        }

        // Sincronizar con el campo JSON de fotos para compatibilidad
        $this->sincronizarFotosJson($prenda);

        return response()->json([
            'success' => true,
            'message' => count($imagenesCreadas) . ' imagen(es) subida(s) correctamente',
            'data' => [
                'imagenes' => $imagenesCreadas,
            ],
        ], 201);
    }

    /**
     * Ver detalle de una imagen
     */
    public function show(int $prendaId, int $imagenId): JsonResponse
    {
        $imagen = PrendaImagen::where('prenda_id', $prendaId)
            ->findOrFail($imagenId);

        return response()->json([
            'success' => true,
            'data' => $this->formatImagen($imagen),
        ]);
    }

    /**
     * Actualizar metadatos de una imagen
     */
    public function update(Request $request, int $prendaId, int $imagenId): JsonResponse
    {
        $imagen = PrendaImagen::where('prenda_id', $prendaId)
            ->findOrFail($imagenId);

        $validator = Validator::make($request->all(), [
            'tipo' => 'nullable|string|in:principal,frontal,trasera,lateral,detalle,defecto,marca,serie,general',
            'etiqueta' => 'nullable|string|max:100',
            'descripcion' => 'nullable|string|max:500',
            'es_principal' => 'nullable|boolean',
            'orden' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Si se marca como principal, quitar de las demás
        if ($request->has('es_principal') && $request->es_principal) {
            PrendaImagen::where('prenda_id', $prendaId)
                ->where('id', '!=', $imagenId)
                ->update(['es_principal' => false]);
        }

        $imagen->update([
            'tipo_imagen' => $request->tipo ?? $imagen->tipo_imagen,
            'etiqueta' => $request->etiqueta ?? $imagen->etiqueta,
            'descripcion' => $request->descripcion ?? $imagen->descripcion,
            'es_principal' => $request->es_principal ?? $imagen->es_principal,
            'orden' => $request->orden ?? $imagen->orden,
        ]);

        // Sincronizar con JSON
        $this->sincronizarFotosJson($imagen->prenda);

        return response()->json([
            'success' => true,
            'message' => 'Imagen actualizada correctamente',
            'data' => $this->formatImagen($imagen->fresh()),
        ]);
    }

    /**
     * Eliminar una imagen
     */
    public function destroy(int $prendaId, int $imagenId): JsonResponse
    {
        $imagen = PrendaImagen::where('prenda_id', $prendaId)
            ->findOrFail($imagenId);

        $prenda = $imagen->prenda;

        // Si es la principal y hay otras, asignar otra como principal
        if ($imagen->es_principal) {
            $otraImagen = PrendaImagen::where('prenda_id', $prendaId)
                ->where('id', '!=', $imagenId)
                ->orderBy('orden')
                ->first();

            if ($otraImagen) {
                $otraImagen->update(['es_principal' => true]);
            }
        }

        // Eliminar archivo físico si existe
        if ($imagen->ruta_almacenamiento && !filter_var($imagen->ruta_almacenamiento, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($imagen->ruta_almacenamiento);
            if ($imagen->ruta_thumbnail) {
                Storage::disk('public')->delete($imagen->ruta_thumbnail);
            }
        }

        $imagen->delete();

        // Sincronizar JSON
        $this->sincronizarFotosJson($prenda);

        return response()->json([
            'success' => true,
            'message' => 'Imagen eliminada correctamente',
        ]);
    }

    /**
     * Reordenar imágenes
     */
    public function reordenar(Request $request, int $prendaId): JsonResponse
    {
        $prenda = Prenda::findOrFail($prendaId);

        $validator = Validator::make($request->all(), [
            'orden' => 'required|array',
            'orden.*.id' => 'required|integer|exists:prenda_imagenes,id',
            'orden.*.posicion' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->orden as $item) {
            PrendaImagen::where('id', $item['id'])
                ->where('prenda_id', $prendaId)
                ->update(['orden' => $item['posicion']]);
        }

        // Sincronizar JSON
        $this->sincronizarFotosJson($prenda);

        return response()->json([
            'success' => true,
            'message' => 'Imágenes reordenadas correctamente',
        ]);
    }

    /**
     * Establecer imagen principal
     */
    public function establecerPrincipal(int $prendaId, int $imagenId): JsonResponse
    {
        $imagen = PrendaImagen::where('prenda_id', $prendaId)
            ->findOrFail($imagenId);

        // Quitar principal de todas
        PrendaImagen::where('prenda_id', $prendaId)
            ->update(['es_principal' => false]);

        // Establecer esta como principal
        $imagen->update(['es_principal' => true, 'orden' => 0]);

        // Sincronizar JSON
        $this->sincronizarFotosJson($imagen->prenda);

        return response()->json([
            'success' => true,
            'message' => 'Imagen establecida como principal',
            'data' => $this->formatImagen($imagen->fresh()),
        ]);
    }

    /**
     * Buscar imágenes duplicadas por hash
     */
    public function buscarDuplicados(Request $request): JsonResponse
    {
        $duplicados = PrendaImagen::select('hash_contenido')
            ->whereNotNull('hash_contenido')
            ->groupBy('hash_contenido')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(function ($item) {
                $imagenes = PrendaImagen::where('hash_contenido', $item->hash_contenido)
                    ->with('prenda:id,codigo_prenda,descripcion')
                    ->get();

                return [
                    'hash' => $item->hash_contenido,
                    'cantidad' => $imagenes->count(),
                    'imagenes' => $imagenes->map(fn($img) => [
                        'id' => $img->id,
                        'prenda_id' => $img->prenda_id,
                        'prenda_codigo' => $img->prenda?->codigo_prenda,
                        'url' => $img->url,
                    ]),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'total_grupos_duplicados' => $duplicados->count(),
                'duplicados' => $duplicados,
            ],
        ]);
    }

    /**
     * Formatear imagen para respuesta
     */
    private function formatImagen(PrendaImagen $imagen): array
    {
        return [
            'id' => (string) $imagen->id,
            'prenda_id' => (string) $imagen->prenda_id,
            'url' => $imagen->url,
            'thumbnail_url' => $imagen->thumbnail_url,
            'nombre_archivo' => $imagen->nombre_archivo,
            'tipo' => $imagen->tipo_imagen,
            'etiqueta' => $imagen->etiqueta,
            'descripcion' => $imagen->descripcion,
            'es_principal' => $imagen->es_principal,
            'orden' => $imagen->orden,
            'dimensiones' => $imagen->dimensiones,
            'tamano' => $imagen->tamano_formateado,
            'mime_type' => $imagen->mime_type,
            'fecha_subida' => $imagen->created_at?->toISOString(),
        ];
    }

    /**
     * Sincronizar imágenes normalizadas con el campo JSON de fotos
     * para mantener compatibilidad con código existente
     */
    private function sincronizarFotosJson(Prenda $prenda): void
    {
        $fotos = $prenda->imagenesNormalizadas()
            ->orderBy('es_principal', 'desc')
            ->orderBy('orden')
            ->get()
            ->map(fn($img) => $img->url)
            ->toArray();

        $prenda->update(['fotos' => $fotos]);

        // También actualizar foto_principal si existe la columna
        $principal = $prenda->imagenPrincipal;
        if ($principal) {
            $prenda->update(['foto_principal' => $principal->url]);
        }
    }
}
