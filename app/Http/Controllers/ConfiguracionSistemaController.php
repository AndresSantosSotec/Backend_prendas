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
        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
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

        if (!$user || !in_array($user->rol, ['superadmin', 'administrador'])) {
            return response()->json(['error' => 'No tienes permisos para modificar configuraciones del sistema.'], 403);
        }

        $request->validate([
            'configuraciones'         => 'required|array|min:1',
            'configuraciones.*.clave' => 'required|string',
            'configuraciones.*.valor' => 'present',
        ]);

        $actualizadas = [];

        foreach ($request->configuraciones as $item) {
            $config = ConfiguracionSistema::where('clave', $item['clave'])->first();

            if (!$config) {
                continue; // Solo actualizamos claves existentes, no creamos nuevas vía API
            }

            if (!$config->editable_por_usuario) {
                continue; // Respetar restricción de edición
            }

            // Normalizar valor booleano
            $valor = $item['valor'];
            if ($config->tipo === 'boolean') {
                $valor = filter_var($valor, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
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

        if (!$user || !in_array($user->rol, ['superadmin', 'administrador'])) {
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
