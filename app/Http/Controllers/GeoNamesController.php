<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GeoNamesController extends Controller
{
    private $username = 'psantosg1';
    private $baseUrl = 'https://secure.geonames.org';
    private $guatemalaGeonameId = 3595528; // ID de Guatemala en GeoNames

    /**
     * Obtiene todos los departamentos de Guatemala
     */
    public function obtenerDepartamentos(): JsonResponse
    {
        try {
            // Cache por 24 horas (los departamentos no cambian frecuentemente)
            $departamentos = Cache::remember('geonames_guatemala_departamentos', 86400, function () {
                $response = Http::get("{$this->baseUrl}/childrenJSON", [
                    'geonameId' => $this->guatemalaGeonameId,
                    'username' => $this->username
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['geonames'] ?? [];
                }

                return [];
            });

            return response()->json([
                'success' => true,
                'data' => $departamentos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener departamentos',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los municipios de un departamento
     */
    public function obtenerMunicipios(int $geonameId): JsonResponse
    {
        try {
            // Cache por 24 horas
            $municipios = Cache::remember("geonames_municipios_{$geonameId}", 86400, function () use ($geonameId) {
                $response = Http::get("{$this->baseUrl}/childrenJSON", [
                    'geonameId' => $geonameId,
                    'username' => $this->username
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['geonames'] ?? [];
                }

                return [];
            });

            return response()->json([
                'success' => true,
                'data' => $municipios
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener municipios',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene toda la información de Guatemala (departamentos y municipios)
     * Útil para cargar todo de una vez
     */
    public function obtenerGuatemalaCompleto(): JsonResponse
    {
        try {
            $guatemala = Cache::remember('geonames_guatemala_completo', 86400, function () {
                // Obtener departamentos
                $departamentosResponse = Http::get("{$this->baseUrl}/childrenJSON", [
                    'geonameId' => $this->guatemalaGeonameId,
                    'username' => $this->username
                ]);

                if (!$departamentosResponse->successful()) {
                    return null;
                }

                $departamentos = $departamentosResponse->json()['geonames'] ?? [];

                // Para cada departamento, obtener sus municipios
                foreach ($departamentos as &$departamento) {
                    $municipiosResponse = Http::get("{$this->baseUrl}/childrenJSON", [
                        'geonameId' => $departamento['geonameId'],
                        'username' => $this->username
                    ]);

                    if ($municipiosResponse->successful()) {
                        $departamento['municipios'] = $municipiosResponse->json()['geonames'] ?? [];
                    } else {
                        $departamento['municipios'] = [];
                    }
                }

                return [
                    'pais' => [
                        'geonameId' => $this->guatemalaGeonameId,
                        'countryName' => 'Guatemala',
                        'countryCode' => 'GT'
                    ],
                    'departamentos' => $departamentos
                ];
            });

            if (!$guatemala) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudieron obtener los datos de Guatemala'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $guatemala
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener datos de Guatemala',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

