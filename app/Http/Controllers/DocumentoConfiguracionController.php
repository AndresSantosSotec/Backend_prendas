<?php

namespace App\Http\Controllers;

use App\Models\TbDoc;
use App\Models\ConfiguracionSistema;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class DocumentoConfiguracionController extends Controller
{
    /**
     * Listar todos los documentos configurados y datos institucionales de la empresa
     * GET /api/v1/configuracion/documentos
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $sucursalId = $request->query('sucursal_id');

        $query = TbDoc::with('sucursal:id,nombre,codigo');
        if ($sucursalId) {
            $query->where('sucursal_id', $sucursalId);
        }
        $documentos = $query->orderBy('tipo_documento')->get();

        // Si la tabla está vacía, correr seeder al vuelo
        if ($documentos->isEmpty()) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'TbDocSeeder']);
            $documentos = TbDoc::with('sucursal:id,nombre,codigo')->orderBy('tipo_documento')->get();
        }

        // Datos generales de la empresa desde configuraciones_sistema
        $empresa = [
            'nombre' => ConfiguracionSistema::obtener('empresa_nombre', env('APP_NAME', 'Microsystem Plus')),
            'nit' => ConfiguracionSistema::obtener('empresa_nit', 'C/F'),
            'direccion' => ConfiguracionSistema::obtener('empresa_direccion', 'Guatemala'),
            'telefono' => ConfiguracionSistema::obtener('empresa_telefono', 'PBX: 2200-0000'),
            'email' => ConfiguracionSistema::obtener('empresa_email', 'contacto@ejemplo.com'),
            'logo_url' => ConfiguracionSistema::obtener('empresa_logo_url', null),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'documentos' => $documentos,
                'empresa' => $empresa,
                'variantes_disponibles' => [
                    'estandar' => 'Estándar Corporativo',
                    'cemadec' => 'Formato CEMADEC (Perito Contador + Representante Legal)',
                    'ticket_80mm' => 'Ticket Térmico (80mm / POS)',
                    'carta_compacto' => 'Carta Compacto',
                    'moderno' => 'Diseño Moderno',
                ]
            ]
        ]);
    }

    /**
     * Crear una nueva configuración de plantilla para documento
     * POST /api/v1/configuracion/documentos
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !in_array($user->rol, ['superadmin', 'administrador'])) {
            return response()->json(['error' => 'No tienes permisos para crear plantillas de documentos.'], 403);
        }

        $request->validate([
            'tipo_documento' => 'required|string|max:100',
            'nombre_documento' => 'required|string|max:150',
            'organizacion_code' => 'nullable|string|max:50',
            'sucursal_id' => 'nullable|exists:sucursales,id',
            'plantilla_variante' => 'nullable|string|max:100',
            'vista_pdf' => 'nullable|string|max:150',
            'titulo_personalizado' => 'nullable|string|max:255',
            'subtitulo_personalizado' => 'nullable|string|max:255',
            'encabezado_texto' => 'nullable|string',
            'pie_pagina_texto' => 'nullable|string',
            'firmante_1_nombre' => 'nullable|string|max:150',
            'firmante_1_titulo' => 'nullable|string|max:150',
            'firmante_2_nombre' => 'nullable|string|max:150',
            'firmante_2_titulo' => 'nullable|string|max:150',
            'perito_contador_nombre' => 'nullable|string|max:150',
            'perito_contador_registro' => 'nullable|string|max:100',
            'mostrar_logo' => 'nullable|boolean',
            'mostrar_firmas' => 'nullable|boolean',
            'activo' => 'nullable|boolean',
        ]);

        $data = $request->all();
        if (empty($data['organizacion_code'])) {
            $data['organizacion_code'] = env('ORGANIZATION_CODE', '01');
        }

        $doc = TbDoc::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Plantilla de documento creada exitosamente',
            'data' => $doc
        ], 201);
    }

    /**
     * Actualizar configuración de una plantilla de documento
     * PUT /api/v1/configuracion/documentos/{id}
     */
    public function updateDocumento(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !in_array($user->rol, ['superadmin', 'administrador'])) {
            return response()->json(['error' => 'No tienes permisos para modificar plantillas de documentos.'], 403);
        }

        $doc = TbDoc::findOrFail($id);

        $request->validate([
            'nombre_documento' => 'nullable|string|max:150',
            'plantilla_variante' => 'nullable|string|max:100',
            'vista_pdf' => 'nullable|string|max:150',
            'titulo_personalizado' => 'nullable|string|max:255',
            'subtitulo_personalizado' => 'nullable|string|max:255',
            'encabezado_texto' => 'nullable|string',
            'pie_pagina_texto' => 'nullable|string',
            'firmante_1_nombre' => 'nullable|string|max:150',
            'firmante_1_titulo' => 'nullable|string|max:150',
            'firmante_2_nombre' => 'nullable|string|max:150',
            'firmante_2_titulo' => 'nullable|string|max:150',
            'perito_contador_nombre' => 'nullable|string|max:150',
            'perito_contador_registro' => 'nullable|string|max:100',
            'mostrar_logo' => 'nullable|boolean',
            'mostrar_firmas' => 'nullable|boolean',
            'activo' => 'nullable|boolean',
        ]);

        $doc->update($request->only([
            'nombre_documento',
            'plantilla_variante',
            'vista_pdf',
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
            'activo',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Plantilla de documento actualizada correctamente',
            'data' => $doc
        ]);
    }

    /**
     * Subir imagen del Logo de la Organización / Empresa
     * POST /api/v1/configuracion/logo
     */
    public function subirLogo(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !in_array($user->rol, ['superadmin', 'administrador'])) {
            return response()->json(['error' => 'No tienes permisos para modificar el logo.'], 403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120', // Máx 5MB
            'documento_id' => 'nullable|exists:tb_docs,id',
        ]);

        if (!$request->file('logo')->isValid()) {
            return response()->json(['error' => 'Archivo de imagen no válido'], 400);
        }

        try {
            $file = $request->file('logo');
            $fileName = 'logo_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('public/logos', $fileName);

            $publicUrl = asset('storage/logos/' . $fileName);

            // Si se mandó un documento_id, asociar a ese documento específico
            if ($request->filled('documento_id')) {
                $doc = TbDoc::findOrFail($request->documento_id);
                $doc->logo_url = $publicUrl;
                $doc->save();
            } else {
                // Guardar como logo global de la empresa en configuraciones_sistema
                ConfiguracionSistema::establecer('empresa_logo_url', $publicUrl, 'string');
            }

            return response()->json([
                'success' => true,
                'message' => 'Logo subido exitosamente',
                'logo_url' => $publicUrl
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al guardar la imagen del logo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar datos generales de la empresa (Nombre, NIT, Dirección, Teléfono, Email)
     * POST /api/v1/configuracion/empresa
     */
    public function updateEmpresa(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !in_array($user->rol, ['superadmin', 'administrador'])) {
            return response()->json(['error' => 'No tienes permisos para modificar datos de la empresa.'], 403);
        }

        $request->validate([
            'nombre' => 'required|string|max:255',
            'nit' => 'nullable|string|max:50',
            'direccion' => 'nullable|string|max:500',
            'telefono' => 'nullable|string|max:100',
            'email' => 'nullable|string|email|max:150',
        ]);

        ConfiguracionSistema::establecer('empresa_nombre', $request->nombre, 'string');
        if ($request->has('nit')) ConfiguracionSistema::establecer('empresa_nit', $request->nit, 'string');
        if ($request->has('direccion')) ConfiguracionSistema::establecer('empresa_direccion', $request->direccion, 'string');
        if ($request->has('telefono')) ConfiguracionSistema::establecer('empresa_telefono', $request->telefono, 'string');
        if ($request->has('email')) ConfiguracionSistema::establecer('empresa_email', $request->email, 'string');

        return response()->json([
            'success' => true,
            'message' => 'Datos institucionales de la empresa actualizados correctamente'
        ]);
    }
}
