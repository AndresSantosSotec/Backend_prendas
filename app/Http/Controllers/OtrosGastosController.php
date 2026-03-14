<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OtroGastoTipo;
use App\Models\OtroGastoMovimiento;
use App\Models\CajaAperturaCierre;
use App\Models\MovimientoCaja;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OtrosGastosController extends Controller
{
    // =========================================================
    //  CATÁLOGO DE TIPOS
    // =========================================================

    /** GET /otros-gastos/tipos */
    public function indexTipos(Request $request)
    {
        $query = OtroGastoTipo::query();

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('buscar')) {
            $query->where(function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->buscar . '%')
                  ->orWhere('grupo', 'like', '%' . $request->buscar . '%')
                  ->orWhere('nomenclatura', 'like', '%' . $request->buscar . '%');
            });
        }
        if ($request->boolean('activos', true)) {
            $query->where('activo', true);
        }
        if ($request->boolean('all', false)) {
            return response()->json(['success' => true, 'data' => $query->orderBy('nombre')->get()]);
        }

        $perPage = (int) $request->get('per_page', 15);
        $data = $query->orderBy('nombre')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'total'        => $data->total(),
                'per_page'     => $data->perPage(),
            ],
        ]);
    }

    /** POST /otros-gastos/tipos */
    public function storeTipo(Request $request)
    {
        $validated = $request->validate([
            'nombre'      => 'required|string|max:150',
            'tipo'        => 'required|in:ingreso,egreso',
            'grupo'       => 'nullable|string|max:100',
            'nomenclatura'=> 'nullable|string|max:100',
            'tipo_linea'  => 'nullable|in:bien,servicio',
            'descripcion' => 'nullable|string',
            'activo'      => 'boolean',
        ]);

        $tipo = OtroGastoTipo::create($validated);

        return response()->json(['success' => true, 'data' => $tipo, 'message' => 'Tipo creado correctamente'], 201);
    }

    /** PUT /otros-gastos/tipos/{id} */
    public function updateTipo(Request $request, $id)
    {
        $tipo = OtroGastoTipo::findOrFail($id);

        $validated = $request->validate([
            'nombre'      => 'required|string|max:150',
            'tipo'        => 'required|in:ingreso,egreso',
            'grupo'       => 'nullable|string|max:100',
            'nomenclatura'=> 'nullable|string|max:100',
            'tipo_linea'  => 'nullable|in:bien,servicio',
            'descripcion' => 'nullable|string',
            'activo'      => 'boolean',
        ]);

        $tipo->update($validated);

        return response()->json(['success' => true, 'data' => $tipo, 'message' => 'Tipo actualizado correctamente']);
    }

    /** DELETE /otros-gastos/tipos/{id} */
    public function destroyTipo($id)
    {
        $tipo = OtroGastoTipo::findOrFail($id);

        // Soft-delete usando activo flag (tienen SoftDeletes también)
        $tipo->update(['activo' => false]);
        $tipo->delete();

        return response()->json(['success' => true, 'message' => 'Tipo eliminado correctamente']);
    }

    // =========================================================
    //  MOVIMIENTOS (registrar ingresos / egresos)
    // =========================================================

    /** GET /otros-gastos/movimientos */
    public function indexMovimientos(Request $request)
    {
        $user = Auth::user();
        $isAdmin = in_array($user->rol, ['administrador', 'superadmin']);

        $query = OtroGastoMovimiento::with(['tipo_gasto', 'user', 'sucursal'])
            ->where('estado', '!=', 'anulado');

        // Filtros
        if (!$isAdmin) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha', '<=', $request->fecha_fin);
        }
        if ($request->filled('mes') && $request->filled('anio')) {
            $query->whereMonth('fecha', $request->mes)->whereYear('fecha', $request->anio);
        }
        if ($request->filled('otro_gasto_tipo_id')) {
            $query->where('otro_gasto_tipo_id', $request->otro_gasto_tipo_id);
        }

        if ($request->boolean('all', false)) {
            return response()->json(['success' => true, 'data' => $query->orderBy('fecha', 'desc')->get()]);
        }

        $perPage = (int) $request->get('per_page', 20);
        $data = $query->orderBy('fecha', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        // Resumen totales
        $totales = OtroGastoMovimiento::where('estado', 'aplicado')
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
            ->when($request->filled('mes') && $request->filled('anio'), fn($q) =>
                $q->whereMonth('fecha', $request->mes)->whereYear('fecha', $request->anio))
            ->selectRaw("
                SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as total_ingresos,
                SUM(CASE WHEN tipo = 'egreso'  THEN monto ELSE 0 END) as total_egresos,
                COUNT(*) as total_registros
            ")
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => [
                'current_page'   => $data->currentPage(),
                'last_page'      => $data->lastPage(),
                'total'          => $data->total(),
                'per_page'       => $data->perPage(),
            ],
            'totales' => $totales,
        ]);
    }

    /** POST /otros-gastos/movimientos */
    public function storeMovimiento(Request $request)
    {
        $validated = $request->validate([
            'otro_gasto_tipo_id' => 'required|exists:otro_gasto_tipos,id',
            'monto'              => 'required|numeric|min:0.01',
            'concepto'           => 'required|string|max:255',
            'descripcion'        => 'nullable|string',
            'fecha'              => 'nullable|date',
            'numero_recibo'      => 'nullable|string|max:80',
            'forma_pago'         => 'nullable|in:efectivo,transferencia,cheque,otro',
        ]);

        $user   = Auth::user();
        $tipo   = OtroGastoTipo::findOrFail($validated['otro_gasto_tipo_id']);
        $fecha  = Carbon::parse($validated['fecha'] ?? now())->toDateString();

        DB::beginTransaction();
        try {
            // Buscar caja abierta del usuario para registrar el movimiento en ella
            $cajaAbierta = CajaAperturaCierre::where('user_id', $user->id)
                ->where('estado', 'abierta')
                ->first();

            $movimientoCajaId = null;

            if ($cajaAbierta) {
                // Crear movimiento en caja (incremento si es ingreso, decremento si es egreso)
                $mov = MovimientoCaja::create([
                    'caja_id'             => $cajaAbierta->id,
                    'tipo'                => $tipo->tipo === 'ingreso' ? 'incremento' : 'decremento',
                    'monto'               => $validated['monto'],
                    'concepto'            => 'Otro gasto: ' . ($validated['concepto'] ?: $tipo->nombre),
                    'detalles_movimiento' => json_encode([
                        'origen'          => 'otros_gastos',
                        'tipo_gasto'      => $tipo->nombre,
                        'forma_pago'      => $validated['forma_pago'] ?? 'efectivo',
                        'numero_recibo'   => $validated['numero_recibo'] ?? null,
                    ]),
                    'user_id'             => $user->id,
                    'estado'              => 'aplicado',
                ]);
                $movimientoCajaId = $mov->id;
            }

            // Obtener sucursal activa del usuario (desde el request o del perfil del usuario)
            $sucursalId = $request->header('X-Sucursal-Id') ?? ($user->sucursal_id ?? null);

            $registro = OtroGastoMovimiento::create([
                'user_id'            => $user->id,
                'sucursal_id'        => $sucursalId,
                'otro_gasto_tipo_id' => $tipo->id,
                'caja_id'            => $cajaAbierta?->id,
                'movimiento_caja_id' => $movimientoCajaId,
                'fecha'              => $fecha,
                'tipo'               => $tipo->tipo,
                'monto'              => $validated['monto'],
                'concepto'           => $validated['concepto'],
                'descripcion'        => $validated['descripcion'] ?? null,
                'numero_recibo'      => $validated['numero_recibo'] ?? null,
                'forma_pago'         => $validated['forma_pago'] ?? 'efectivo',
                'estado'             => 'aplicado',
            ]);

            DB::commit();

            $registro->load(['tipo_gasto', 'user']);

            return response()->json([
                'success'        => true,
                'data'           => $registro,
                'aplico_en_caja' => $cajaAbierta !== null,
                'message'        => $cajaAbierta
                    ? 'Movimiento registrado y aplicado a la caja abierta'
                    : 'Movimiento registrado (sin caja abierta, no se aplicó a caja)',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error registrando otro gasto: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al registrar el movimiento'], 500);
        }
    }

    /** POST /otros-gastos/movimientos/{id}/anular */
    public function anularMovimiento(Request $request, $id)
    {
        $request->validate(['motivo' => 'required|string|max:255']);

        $registro = OtroGastoMovimiento::findOrFail($id);
        $user     = Auth::user();

        // Solo el dueño o admin/superadmin
        if ($registro->user_id !== $user->id && !in_array($user->rol, ['administrador', 'superadmin'])) {
            return response()->json(['success' => false, 'message' => 'Sin permisos'], 403);
        }

        if ($registro->estado === 'anulado') {
            return response()->json(['success' => false, 'message' => 'El movimiento ya está anulado'], 400);
        }

        DB::beginTransaction();
        try {
            $registro->update([
                'estado'          => 'anulado',
                'anulado_motivo'  => $request->motivo,
            ]);

            // Anular el movimiento de caja correspondiente si existe
            if ($registro->movimiento_caja_id) {
                $movCaja = MovimientoCaja::find($registro->movimiento_caja_id);
                if ($movCaja) {
                    // Crear movimiento inverso para revertir el efecto
                    MovimientoCaja::create([
                        'caja_id'             => $movCaja->caja_id,
                        'tipo'                => $movCaja->tipo === 'incremento' ? 'decremento' : 'incremento',
                        'monto'               => $movCaja->monto,
                        'concepto'            => 'ANULACIÓN - ' . $movCaja->concepto,
                        'detalles_movimiento' => json_encode(['origen' => 'anulacion_otro_gasto', 'motivo' => $request->motivo]),
                        'user_id'             => $user->id,
                        'estado'              => 'aplicado',
                    ]);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Movimiento anulado correctamente']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error anulando otro gasto: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al anular'], 500);
        }
    }

    /** GET /otros-gastos/resumen-mes */
    public function resumenMes(Request $request)
    {
        $user   = Auth::user();
        $mes    = (int) $request->get('mes', now()->month);
        $anio   = (int) $request->get('anio', now()->year);
        $isAdmin = in_array($user->rol, ['administrador', 'superadmin']);

        $query = OtroGastoMovimiento::where('estado', 'aplicado')
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id));

        $totales = $query->clone()->selectRaw("
            SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as total_ingresos,
            SUM(CASE WHEN tipo = 'egreso'  THEN monto ELSE 0 END) as total_egresos,
            COUNT(*) as total_movimientos
        ")->first();

        $porTipo = $query->clone()
            ->join('otro_gasto_tipos', 'otro_gasto_tipos.id', '=', 'otro_gasto_movimientos.otro_gasto_tipo_id')
            ->selectRaw("otro_gasto_tipos.nombre, otro_gasto_tipos.tipo, SUM(monto) as subtotal, COUNT(*) as cantidad")
            ->groupBy('otro_gasto_tipos.id', 'otro_gasto_tipos.nombre', 'otro_gasto_tipos.tipo')
            ->orderBy('subtotal', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'mes'     => $mes,
            'anio'    => $anio,
            'totales' => $totales,
            'por_tipo'=> $porTipo,
        ]);
    }
}
