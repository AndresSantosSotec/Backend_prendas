<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionSistema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConfiguracionSistemaController extends Controller
{
    /**
     * Obtener todas las configuraciones del sistema (o filtradas por grupo).
     * GET /api/v1/configuraciones-sistema
     * GET /api/v1/configuraciones-sistema?grupo=caja
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $esAutorizado = $user && (
            in_array($user->rol, ['superadmin', 'administrador']) ||
            in_array($user->role ?? null, ['superadmin', 'administrador']) ||
            $user->hasPermission('planes_interes', 'editar') ||
            $user->hasPermission('planes_interes', 'ver') ||
            $user->hasPermission('contabilidad', 'configurar')
        );

        if (!$esAutorizado) {
            return response()->json(['error' => 'Acceso denegado. Se requieren permisos administrativos.'], 403);
        }

        $query = ConfiguracionSistema::query();

        if ($request->filled('grupo')) {
            $query->where('grupo', $request->grupo);
        }

        $configs = $query->orderBy('grupo')->orderBy('clave')->get();

        // Castear los valores en la respuesta
        $configs->each(function (ConfiguracionSistema $c) {
            $c->append('valor_casteado');
        });

        return response()->json([
            'success' => true,
            'data'    => $configs,
        ]);
    }

    /**
     * Obtener una configuración por clave.
     * GET /api/v1/configuraciones-sistema/{clave}
     */
    public function show(string $clave): JsonResponse
    {
        $config = ConfiguracionSistema::where('clave', $clave)->firstOrFail();
        $config->append('valor_casteado');

        return response()->json([
            'success' => true,
            'data'    => $config,
        ]);
    }

    /**
     * Actualizar una o varias configuraciones del sistema.
     * PUT /api/v1/configuraciones-sistema
     *
     * Body: { configuraciones: [ { clave, valor }, ... ] }
     */
    public function update(Request $request): JsonResponse
    {
        $user = Auth::user();
        $esAutorizado = $user && (
            in_array($user->rol, ['superadmin', 'administrador']) ||
            in_array($user->role ?? null, ['superadmin', 'administrador']) ||
            $user->hasPermission('planes_interes', 'editar') ||
            $user->hasPermission('contabilidad', 'configurar')
        );

        if (!$esAutorizado) {
            return response()->json(['error' => 'Acceso denegado. Se requieren permisos administrativos.'], 403);
        }

        $request->validate([
            'configuraciones'         => 'required|array|min:1',
            'configuraciones.*.clave' => 'required|string',
            'configuraciones.*.valor' => 'present',
        ]);

        $actualizadas = [];

        foreach ($request->configuraciones as $item) {
            $config = ConfiguracionSistema::where('clave', $item['clave'])->first();

            $valor = $item['valor'];

            if (!$config) {
                $clave = (string) $item['clave'];
                $claveNuevaPermitida = str_starts_with($clave, 'promocion_') || str_starts_with($clave, 'credito_');
                if (!$claveNuevaPermitida) {
                    continue;
                }

                $tipo = 'string';
                if (is_bool($valor) || in_array($valor, ['true', 'false', true, false], true)) {
                    $tipo = 'boolean';
                } elseif (is_numeric($valor)) {
                    $tipo = is_float($valor + 0) ? 'float' : 'integer';
                } elseif (is_array($valor)) {
                    $tipo = 'json';
                }

                $grupo = str_contains($item['clave'], 'promocion') ? 'promociones' : 'creditos';

                $config = new ConfiguracionSistema();
                $config->clave = $item['clave'];
                $config->tipo = $tipo;
                $config->grupo = $grupo;
                $config->editable_por_usuario = true;
                $config->descripcion = ucfirst(str_replace('_', ' ', $item['clave']));
            }

            if (!$config->editable_por_usuario) {
                continue; // Respetar restricción de edición
            }

            // Normalizar valor según tipo
            if ($config->tipo === 'boolean') {
                $valor = filter_var($valor, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            } elseif ($config->tipo === 'json' && is_array($valor)) {
                $valor = json_encode($valor);
            }

            $config->valor = (string) $valor;
            $config->save();
            $config->append('valor_casteado');
            $actualizadas[] = $config;
        }

        return response()->json([
            'success'     => true,
            'message'     => 'Configuraciones actualizadas correctamente.',
            'actualizadas' => $actualizadas,
        ]);
    }

    /**
     * Endpoint rápido para el estado de integración Caja-Bóveda.
     * GET /api/v1/configuraciones-sistema/cash-vault-integration
     */
    public function cashVaultIntegration(): JsonResponse
    {
        $activa = ConfiguracionSistema::integracionCajaBovedaActiva();

        return response()->json([
            'success'                        => true,
            'cash_vault_integration_enabled' => $activa,
        ]);
    }

    /**
     * Activar/Desactivar integración Caja-Bóveda.
     * POST /api/v1/configuraciones-sistema/cash-vault-integration/toggle
     */
    public function toggleCashVaultIntegration(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || ($user->rol !== 'superadmin' && ($user->role ?? null) !== 'superadmin')) {
            return response()->json(['error' => 'No tienes permisos para modificar esta configuración.'], 403);
        }

        $request->validate([
            'activo' => 'required|boolean',
        ]);

        ConfiguracionSistema::setIntegracionCajaBoveda((bool) $request->activo);

        return response()->json([
            'success'                        => true,
            'message'                        => 'Integración Caja-Bóveda ' . ($request->activo ? 'activada' : 'desactivada') . ' correctamente.',
            'cash_vault_integration_enabled' => (bool) $request->activo,
        ]);
    }
}
