<?php

namespace App\Http\Controllers;

use App\Models\InformeMedico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InformeMedicoController extends Controller
{
    // Listar informes según el rol
    public function index()
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            // Admin ve todos los informes
            $informes = InformeMedico::all();
        } elseif ($user->hasRole('manager')) {
            // Manager ve los informes donde es el médico
            $informes = InformeMedico::where('medico_id', $user->id)->get();
        } else {
            // Usuario normal solo ve sus propios informes como paciente
            $informes = InformeMedico::where('paciente_id', $user->id)->get();
        }

        return response()->json($informes);
    }

    // Crear un informe
    public function store(Request $request)
    {
        $request->validate([
            'titulo'      => 'required|string|max:255',
            'diagnostico' => 'required|string',
            'paciente_id' => 'required|integer|exists:users,id',
            'medico_id'   => 'required|integer|exists:users,id',
        ]);

        $tituloSeguro      = strip_tags($request->titulo);
        $diagnosticoSeguro = strip_tags($request->diagnostico);

        $informe = InformeMedico::create([
            'titulo'      => $tituloSeguro,
            'diagnostico' => $diagnosticoSeguro,
            'paciente_id' => $request->paciente_id,
            'medico_id'   => $request->medico_id,
        ]);

        return response()->json([
            'message' => 'Informe creado con éxito',
            'data'    => $informe
        ], 201);
    }

    // Ver un informe específico
    public function show($id)
    {
        $user = Auth::user();
        $informe = InformeMedico::find($id);

        if (!$informe) {
            return response()->json(['message' => 'Informe no encontrado'], 404);
        }

        // Control de acceso: evita IDOR
        if (!$user->hasRole('admin') &&
            $informe->paciente_id !== $user->id &&
            $informe->medico_id !== $user->id) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        return response()->json($informe);
    }

    // Actualizar un informe
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $informe = InformeMedico::find($id);

        if (!$informe) {
            return response()->json(['message' => 'Informe no encontrado'], 404);
        }

        // Solo admin o el médico que lo creó pueden editar
        if (!$user->hasRole('admin') && $informe->medico_id !== $user->id) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $request->validate([
            'titulo'      => 'required|string|max:255',
            'diagnostico' => 'required|string',
            'paciente_id' => 'required|integer|exists:users,id',
            'medico_id'   => 'required|integer|exists:users,id',
        ]);

        $informe->update([
            'titulo'      => strip_tags($request->titulo),
            'diagnostico' => strip_tags($request->diagnostico),
            'paciente_id' => $request->paciente_id,
            'medico_id'   => $request->medico_id,
        ]);

        return response()->json([
            'message' => 'Informe actualizado con éxito',
            'data'    => $informe
        ]);
    }

    // Eliminar un informe
    public function destroy($id)
    {
        $user = Auth::user();
        $informe = InformeMedico::find($id);

        if (!$informe) {
            return response()->json(['message' => 'Informe no encontrado'], 404);
        }

        // Solo admin puede eliminar
        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'Acceso no autorizado'], 403);
        }

        $informe->delete();

        return response()->json(['message' => 'Informe eliminado correctamente']);
    }
}