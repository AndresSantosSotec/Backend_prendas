<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Recibo;
use App\Models\Cliente;
use App\Models\CajaAperturaCierre;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ReciboController extends Controller
{
    /**
     * Obtener lista de recibos con filtros
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Recibo::with(['cliente', 'usuario', 'sucursal']);

        // Filtros por permisos
        if (!in_array($user->rol, ['superadmin', 'administrador', 'gerente'])) {
            $query->where('sucursal_id', $user->sucursal_id);
        }

        // Filtros adicionales
        if ($request->tipo) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->cliente_id) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->fecha_inicio) {
            $query->whereDate('fecha', '>=', $request->fecha_inicio);
        }

        if ($request->fecha_fin) {
            $query->whereDate('fecha', '<=', $request->fecha_fin);
        }

        if ($request->estado) {
            $query->where('estado', $request->estado);
        }

        if ($request->sucursal_id) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        $recibos = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json($recibos);
    }

    /**
     * Crear nuevo recibo individual
     */
    public function store(Request $request)
    {
        $request->validate([
            'tipo' => 'required|in:ingreso,egreso',
            'fecha' => 'required|date',
            'cliente_id' => 'nullable|exists:clientes,id',
            'monto' => 'required|numeric|min:0.01',
            'concepto' => 'required|string|max:500',
            'desglose_denominaciones' => 'nullable|array',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();

        // Verificar caja abierta
        $cajaAbierta = CajaAperturaCierre::where('user_id', $user->id)
            ->where('estado', 'abierta')
            ->first();

        if (!$cajaAbierta) {
            return response()->json([
                'error' => 'Debes tener una caja abierta para emitir recibos'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Generar número de recibo
            $numeroRecibo = Recibo::generarNumeroRecibo($user->sucursal_id);

            $recibo = Recibo::create([
                'numero_recibo' => $numeroRecibo,
                'tipo' => $request->tipo,
                'fecha' => $request->fecha,
                'serie' => 'R',
                'cliente_id' => $request->cliente_id,
                'credito_id' => $request->credito_id,
                'caja_id' => $cajaAbierta->id,
                'monto' => $request->monto,
                'desglose_denominaciones' => $request->desglose_denominaciones,
                'concepto' => $request->concepto,
                'observaciones' => $request->observaciones,
                'user_id' => $user->id,
                'sucursal_id' => $user->sucursal_id,
                'estado' => 'emitido',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Recibo emitido correctamente',
                'recibo' => $recibo->load(['cliente', 'usuario', 'sucursal']),
                'numero_recibo' => $numeroRecibo
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Error al crear recibo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ver detalle de recibo
     */
    public function show($id)
    {
        $user = Auth::user();
        $recibo = Recibo::with(['cliente', 'usuario', 'sucursal', 'caja', 'credito'])
            ->findOrFail($id);

        // Verificar permisos
        if (!in_array($user->rol, ['superadmin', 'administrador', 'gerente']) &&
            $recibo->sucursal_id != $user->sucursal_id) {
            return response()->json(['error' => 'No tienes permisos para ver este recibo'], 403);
        }

        return response()->json(['recibo' => $recibo]);
    }

    /**
     * Anular recibo
     */
    public function anular(Request $request, $id)
    {
        $request->validate([
            'motivo' => 'required|string|max:500'
        ]);

        /** @var User $user */
        $user = Auth::user();
        $recibo = Recibo::findOrFail($id);

        // Verificar permisos
        if (!$user->hasPermission('recibos', 'anular') &&
            !in_array($user->rol, ['superadmin', 'administrador', 'gerente'])) {
            return response()->json(['error' => 'No tienes permisos para anular recibos'], 403);
        }

        if (!$recibo->puedeAnular()) {
            return response()->json(['error' => 'Este recibo no puede ser anulado'], 400);
        }

        if ($recibo->anular($user, $request->motivo)) {
            return response()->json([
                'message' => 'Recibo anulado correctamente',
                'recibo' => $recibo->load(['cliente', 'usuario', 'sucursal'])
            ]);
        }

        return response()->json(['error' => 'No se pudo anular el recibo'], 400);
    }

    /**
     * Buscar cliente para recibo
     */
    public function buscarCliente(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $query = $request->query;

        $clientes = Cliente::where(function($q) use ($query) {
            $q->where('nombre', 'LIKE', "%{$query}%")
              ->orWhere('apellidos', 'LIKE', "%{$query}%")
              ->orWhere('cui', 'LIKE', "%{$query}%")
              ->orWhere('nit', 'LIKE', "%{$query}%")
              ->orWhere('codigo', 'LIKE', "%{$query}%");
        })
        ->where('activo', true)
        ->limit(10)
        ->get(['id', 'codigo', 'nombre', 'apellidos', 'cui', 'nit', 'telefono']);

        return response()->json(['clientes' => $clientes]);
    }

    /**
     * Generar PDF del recibo
     */
    public function generarPDF($id)
    {
        $user = Auth::user();
        $recibo = Recibo::with(['cliente', 'usuario', 'sucursal', 'caja'])
            ->findOrFail($id);

        // Verificar permisos
        if (!in_array($user->rol, ['superadmin', 'administrador', 'gerente']) &&
            $recibo->sucursal_id != $user->sucursal_id) {
            return response()->json(['error' => 'No tienes permisos para ver este recibo'], 403);
        }

        $pdf = Pdf::loadView('pdf.recibo', [
            'recibo' => $recibo
        ]);

        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream("recibo-{$recibo->numero_recibo}.pdf");
    }

    /**
     * Reporte de recibos
     */
    public function reporte(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $query = Recibo::with(['cliente', 'usuario', 'sucursal'])
            ->enRango($request->fecha_inicio, $request->fecha_fin)
            ->emitidos();

        // Filtrar por sucursal si no es admin
        if (!in_array($user->rol, ['superadmin', 'administrador'])) {
            $query->where('sucursal_id', $user->sucursal_id);
        } elseif ($request->sucursal_id) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        if ($request->tipo) {
            $query->where('tipo', $request->tipo);
        }

        $recibos = $query->orderBy('fecha', 'desc')->orderBy('numero_recibo', 'desc')->get();

        // Estadísticas
        $totales = [
            'total_recibos' => $recibos->count(),
            'total_ingresos' => $recibos->where('tipo', 'ingreso')->sum('monto'),
            'total_egresos' => $recibos->where('tipo', 'egreso')->sum('monto'),
            'balance' => $recibos->where('tipo', 'ingreso')->sum('monto') - $recibos->where('tipo', 'egreso')->sum('monto'),
        ];

        return response()->json([
            'recibos' => $recibos,
            'totales' => $totales,
            'periodo' => [
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
            ]
        ]);
    }

    /**
     * Obtener siguiente número de recibo (preview)
     */
    public function siguienteNumero()
    {
        $user = Auth::user();
        $numero = Recibo::generarNumeroRecibo($user->sucursal_id);

        return response()->json(['numero_recibo' => $numero]);
    }
}
