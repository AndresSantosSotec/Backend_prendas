<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClienteBorrador;
use Illuminate\Support\Facades\Auth;

class ClienteBorradorController extends Controller
{
    private const MAX_BORRADORES = 6;

    /** GET /clientes/borradores */
    public function index()
    {
        $borradores = ClienteBorrador::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $borradores]);
    }

    /** POST /clientes/borradores */
    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'nullable|string|max:100',
            'datos'  => 'required|array',
        ]);

        $userId = Auth::id();

        // Límite de 6 borradores por usuario: eliminar el más antiguo si se supera
        $count = ClienteBorrador::where('user_id', $userId)->count();
        if ($count >= self::MAX_BORRADORES) {
            ClienteBorrador::where('user_id', $userId)
                ->orderBy('updated_at', 'asc')
                ->first()
                ?->delete();
        }

        $titulo = $request->input('titulo') ?: $this->generarTitulo($request->input('datos'));

        $borrador = ClienteBorrador::create([
            'user_id' => $userId,
            'titulo'  => $titulo,
            'datos'   => $request->input('datos'),
        ]);

        return response()->json(['success' => true, 'data' => $borrador], 201);
    }

    /** PUT /clientes/borradores/{id} */
    public function update(Request $request, $id)
    {
        $borrador = ClienteBorrador::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'titulo' => 'nullable|string|max:100',
            'datos'  => 'required|array',
        ]);

        $titulo = $request->input('titulo') ?: $this->generarTitulo($request->input('datos'));

        $borrador->update([
            'titulo' => $titulo,
            'datos'  => $request->input('datos'),
        ]);

        return response()->json(['success' => true, 'data' => $borrador]);
    }

    /** DELETE /clientes/borradores/{id} */
    public function destroy($id)
    {
        $borrador = ClienteBorrador::where('user_id', Auth::id())->findOrFail($id);
        $borrador->delete();

        return response()->json(['success' => true, 'message' => 'Borrador eliminado']);
    }

    private function generarTitulo(array $datos): string
    {
        $nombres   = trim($datos['nombres']   ?? '');
        $apellidos = trim($datos['apellidos'] ?? '');
        $dpi       = trim($datos['dpi']       ?? '');

        if ($nombres || $apellidos) {
            return trim("$nombres $apellidos");
        }
        if ($dpi) {
            return "DPI: $dpi";
        }
        return 'Borrador sin nombre';
    }
}
