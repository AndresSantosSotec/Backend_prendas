<?php

namespace App\Http\Controllers;

use App\Services\CompraService;
use App\Services\ContabilidadAutomaticaService;
use App\Http\Resources\CompraResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class CompraController extends Controller
{
    protected $compraService;
    protected $contabilidadService;

    public function __construct(
        CompraService $compraService,
        ContabilidadAutomaticaService $contabilidadService
    ) {
        $this->compraService = $compraService;
        $this->contabilidadService = $contabilidadService;
    }

    /**
     * Listar compras con filtros y paginación
     */
    public function index(Request $request)
    {
        try {
            $filtros = $request->only([
                'sucursal_id',
                'estado',
                'cliente_id',
                'fecha_desde',
                'fecha_hasta',
                'search',
            ]);

            $perPage = $request->input('per_page', 15);

            $compras = $this->compraService->listarCompras($filtros)->paginate($perPage);

            return CompraResource::collection($compras);

        } catch (\Exception $e) {
            Log::error('Error al listar compras: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las compras',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Registrar una nueva compra directa
     */
    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'categoria_producto_id' => 'required|exists:categoria_productos,id',
            'descripcion' => 'required|string|max:500',
            'valor_tasacion' => 'nullable|numeric|min:0',
            'monto_pagado' => 'required|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
            'metodo_pago' => 'nullable|string|in:efectivo,transferencia,cheque,mixto',
            'observaciones' => 'nullable|string|max:1000',
            'condicion' => 'nullable|string|in:excelente,muy_buena,buena,regular,mala',
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'serie' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'campos_dinamicos' => 'nullable|array',
        ]);

        try {
            $compra = $this->compraService->procesarCompraDirecta($request->all());

            // Registrar asiento contable automático
            try {
                $this->contabilidadService->registrarAsiento('compra_directa', [
                    'sucursal_id' => $compra->sucursal_id,
                    'usuario_id' => Auth::id(),
                    'monto' => $compra->monto_pagado,
                    'compra_id' => $compra->id,
                    'numero_documento' => $compra->numero_compra,
                    'glosa' => "Compra directa #{$compra->numero_compra} - {$compra->descripcion}",
                    'fecha_documento' => $compra->fecha_compra,
                ]);
            } catch (\Exception $contError) {
                Log::warning('Error al registrar asiento contable para compra: ' . $contError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Compra registrada exitosamente. El producto ya está en el inventario.',
                'data' => new CompraResource($compra)
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al registrar compra directa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la compra',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 400);
        }
    }

    /**
     * Obtener detalle completo de una compra
     */
    public function show($id)
    {
        try {
            $compra = $this->compraService->obtenerDetalle($id);

            return response()->json([
                'success' => true,
                'data' => new CompraResource($compra)
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener detalle de compra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Compra no encontrada',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Cancelar una compra (si no ha sido vendida)
     */
    public function cancel($id, Request $request)
    {
        try {
            $compra = $this->compraService->obtenerDetalle($id);

            if ($compra->estado === 'vendida') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cancelar una compra cuya prenda ya fue vendida'
                ], 422);
            }

            $compra->update([
                'estado' => 'cancelada',
                'observaciones' => ($compra->observaciones ?? '') . "\n[CANCELADA] " . ($request->input('motivo') ?? 'Sin motivo especificado')
            ]);

            // Eliminar la prenda del inventario (soft delete) — como si nunca hubiera ingresado
            if ($compra->prenda) {
                $compra->prenda->delete();
                // Desasociar la prenda de la compra
                $compra->update(['prenda_id' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Compra cancelada exitosamente',
                'data' => new CompraResource($compra->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('Error al cancelar compra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la compra',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de compras
     */
    public function stats(Request $request)
    {
        try {
            $filtros = $request->only(['sucursal_id', 'fecha_desde', 'fecha_hasta']);

            $query = $this->compraService->listarCompras($filtros);

            $stats = [
                'total_compras' => $query->count(),
                'total_invertido' => $query->sum('monto_pagado'),
                'valor_inventario_actual' => $query->where('estado', 'activa')->sum('precio_venta_sugerido'),
                'compras_activas' => $query->where('estado', 'activa')->count(),
                'compras_vendidas' => $query->where('estado', 'vendida')->count(),
                'compras_canceladas' => $query->where('estado', 'cancelada')->count(),
                'margen_promedio' => round($query->avg(DB::raw('((precio_venta_sugerido - monto_pagado) / monto_pagado) * 100')), 2),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular estadísticas',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Actualizar una compra
     */
    public function update($id, Request $request)
    {
        try {
            $compra = $this->compraService->obtenerDetalle($id);

            if ($compra->estado === 'vendida') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar una compra cuya prenda ya fue vendida'
                ], 422);
            }

            $request->validate([
                'descripcion' => 'required|string|max:500',
                'marca' => 'nullable|string|max:100',
                'modelo' => 'nullable|string|max:100',
                'serie' => 'nullable|string|max:100',
                'color' => 'nullable|string|max:50',
                'condicion' => 'nullable|string|in:excelente,muy_buena,buena,regular,mala',
                'precio_venta_sugerido' => 'nullable|numeric|min:0',
                'observaciones' => 'nullable|string|max:1000',
            ]);

            $compra->update($request->only([
                'descripcion',
                'marca',
                'modelo',
                'serie',
                'color',
                'condicion',
                'precio_venta_sugerido',
                'observaciones'
            ]));

            // Actualizar también la prenda si existe
            if ($compra->prenda) {
                $compra->prenda->update([
                    'descripcion' => $request->descripcion,
                    'marca' => $request->marca,
                    'modelo' => $request->modelo,
                    'color' => $request->color,
                    'condicion' => $request->condicion,
                    'precio_venta' => $request->precio_venta_sugerido ?? $compra->prenda->precio_venta,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Compra actualizada exitosamente',
                'data' => new CompraResource($compra->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar compra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la compra',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Eliminar una compra (solo si no está vendida)
     */
    public function destroy($id)
    {
        try {
            $compra = $this->compraService->obtenerDetalle($id);

            if ($compra->estado === 'vendida') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar una compra cuya prenda ya fue vendida'
                ], 422);
            }

            // Eliminar la prenda asociada si existe
            if ($compra->prenda) {
                $compra->prenda->delete();
            }

            // Eliminar campos dinámicos
            $compra->camposDinamicos()->delete();

            // Eliminar la compra
            $compra->delete();

            return response()->json([
                'success' => true,
                'message' => 'Compra eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar compra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la compra',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Generar PDF del recibo de compra
     */
    public function generarReciboPDF($id)
    {
        try {
            $compra = $this->compraService->obtenerDetalle($id);

            $pdf = Pdf::loadView('pdf.recibo-compra', [
                'compra' => $compra,
                'fecha_actual' => now()->format('d/m/Y H:i')
            ]);

            return $pdf->download('recibo-compra-' . $compra->codigo_compra . '.pdf');

        } catch (\Exception $e) {
            Log::error('Error al generar PDF de compra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el recibo',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Generar PDF del contrato de compraventa de bien mueble
     */
    public function generarContratoPDF($id)
    {
        try {
            $compra = $this->compraService->obtenerDetalle($id);
            $cliente = $compra->cliente;
            $sucursal = $compra->sucursal;

            // Formatear fecha en español
            Carbon::setLocale('es');
            $fechaCompra = $compra->fecha_compra ? Carbon::parse($compra->fecha_compra) : now();
            $meses = [
                1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
                5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
                9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
            ];
            
            $diaNum = $fechaCompra->day;
            $diaLetras = ($diaNum === 1) ? 'primero' : $this->numeroALetras($diaNum);
            $mesLetras = $meses[$fechaCompra->month];
            $anioLetras = $this->numeroALetras($fechaCompra->year);

            // Edad del cliente
            $edad = '___';
            $edadLetras = '__________';
            if ($cliente && $cliente->fecha_nacimiento) {
                $fechaNac = Carbon::parse($cliente->fecha_nacimiento);
                $edad = $fechaNac->age;
                $edadLetras = $this->numeroALetras($edad);
            }

            // Estado civil y Profesión
            $estadoCivil = ($cliente && $cliente->estado_civil) ? $cliente->estado_civil : '__________';
            $profesion = ($cliente && $cliente->profesion) ? $cliente->profesion : '__________';

            // Documento del cliente (CUI/DPI)
            $dpi = $compra->cliente_documento ?? ($cliente->dpi ?? null);
            $dpiLetras = '______________________';
            $dpiFormateado = '_______ ______ _______';
            if ($dpi) {
                $cleanDpi = preg_replace('/[^0-9]/', '', $dpi);
                if (strlen($cleanDpi) === 13) {
                    $dpiLetras = $this->cuiEnLetras($cleanDpi);
                    $dpiFormateado = substr($cleanDpi, 0, 4) . ' ' . substr($cleanDpi, 4, 5) . ' ' . substr($cleanDpi, 9, 4);
                } else {
                    $dpiFormateado = $dpi;
                }
            }

            // Monto de compra en letras
            $monto = (float)$compra->monto_pagado;
            $enteros = (int)floor($monto);
            $centavos = (int)round(($monto - $enteros) * 100);
            $montoLetras = $this->numeroALetras($enteros);
            if ($centavos > 0) {
                $montoCompleto = strtoupper($montoLetras) . ' QUETZALES CON ' . str_pad($centavos, 2, '0', STR_PAD_LEFT) . '/100';
            } else {
                $montoCompleto = strtoupper($montoLetras) . ' QUETZALES EXACTOS';
            }

            // Identificación detallada (Serie, color, descripción, etc.)
            $identificacionPartes = [];
            if ($compra->serie) $identificacionPartes[] = "Serie: {$compra->serie}";
            if ($compra->color) $identificacionPartes[] = "Color: {$compra->color}";
            if ($compra->descripcion) $identificacionPartes[] = "Descripción: {$compra->descripcion}";
            
            // Campos dinámicos si existen
            if ($compra->camposDinamicos && $compra->camposDinamicos->count() > 0) {
                foreach ($compra->camposDinamicos as $campo) {
                    $identificacionPartes[] = "{$campo->campo_nombre}: {$campo->valor}";
                }
            }
            $identificacion = implode(', ', $identificacionPartes);

            // Estado físico
            $estadoFisico = ucfirst(str_replace('_', ' ', $compra->condicion ?? 'usado'));
            if ($compra->observaciones) {
                $estadoFisico .= " ({$compra->observaciones})";
            }

            $pdf = Pdf::loadView('pdf.contrato-compra', [
                'compra' => $compra,
                'cliente' => $cliente,
                'sucursal' => $sucursal,
                'dia' => $diaLetras,
                'mes' => $mesLetras,
                'anio' => $anioLetras,
                'edad' => $edad,
                'edadLetras' => $edadLetras,
                'estadoCivil' => $estadoCivil,
                'profesion' => $profesion,
                'dpiLetras' => $dpiLetras,
                'dpiFormateado' => $dpiFormateado,
                'montoCompleto' => $montoCompleto,
                'identificacion' => $identificacion,
                'estadoFisico' => $estadoFisico,
                'fecha_actual' => now()->format('d/m/Y H:i')
            ]);

            $pdf->setPaper('letter', 'portrait');

            return $pdf->download('contrato-compra-' . $compra->codigo_compra . '.pdf');

        } catch (\Exception $e) {
            Log::error('Error al generar contrato de compra PDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el contrato',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Convertir número a letras en español
     */
    private function numeroALetras($number)
    {
        if ($number == 0) return 'cero';
        
        $unidades = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $decenas = ['', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $especiales = [
            10 => 'diez', 11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce', 15 => 'quince',
            16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve',
            20 => 'veinte', 21 => 'veintiuno', 22 => 'veintidós', 23 => 'veintitrés', 24 => 'veinticuatro',
            25 => 'veinticinco', 26 => 'veintiséis', 27 => 'veintisiete', 28 => 'veintiocho', 29 => 'veintinueve'
        ];
        $centenas = [
            '', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
            'seiscientos', 'setecientos', 'ochocientos', 'novecientos'
        ];

        if ($number < 0) return 'menos ' . $this->numeroALetras(abs($number));

        $letras = '';

        if ($number >= 1000) {
            $miles = floor($number / 1000);
            $resto = $number % 1000;
            if ($miles == 1) {
                $letras .= 'mil ';
            } else {
                $letras .= $this->numeroALetras($miles) . ' mil ';
            }
            $number = $resto;
        }

        if ($number >= 100) {
            $cents = floor($number / 100);
            $resto = $number % 100;
            if ($cents == 1 && $resto == 0) {
                $letras .= 'cien ';
            } else {
                $letras .= $centenas[$cents] . ' ';
            }
            $number = $resto;
        }

        if ($number > 0) {
            if (isset($especiales[$number])) {
                $letras .= $especiales[$number];
            } else {
                $decs = floor($number / 10);
                $units = $number % 10;
                if ($decs > 0) {
                    $letras .= $decenas[$decs];
                    if ($units > 0) {
                        $letras .= ' y ' . $unidades[$units];
                    }
                } else {
                    $letras .= $unidades[$units];
                }
            }
        }

        return trim($letras);
    }

    /**
     * Convertir CUI (13 dígitos) a letras
     */
    private function cuiEnLetras($dpi)
    {
        $dpi = preg_replace('/[^0-9]/', '', $dpi);
        if (strlen($dpi) !== 13) {
            return $dpi;
        }
        
        $part1 = substr($dpi, 0, 4);
        $part2 = substr($dpi, 4, 5);
        $part3 = substr($dpi, 9, 4);
        
        $text1 = $this->numeroALetras((int)$part1);
        $text2 = $this->numeroALetras((int)$part2);
        
        $text3 = '';
        if (str_starts_with($part3, '0')) {
            $zeros = 0;
            while (isset($part3[$zeros]) && $part3[$zeros] === '0') {
                $zeros++;
            }
            $text3 .= str_repeat('cero ', $zeros);
            $remainder = (int)substr($part3, $zeros);
            if ($remainder > 0) {
                $text3 .= $this->numeroALetras($remainder);
            }
        } else {
            $text3 = $this->numeroALetras((int)$part3);
        }
        
        return trim($text1) . ' espacio ' . trim($text2) . ' espacio ' . trim($text3);
    }
}

