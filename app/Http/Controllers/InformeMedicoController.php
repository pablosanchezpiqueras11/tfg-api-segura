<?php

namespace App\Http\Controllers;

use App\Models\InformeMedico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InformeMedicoController extends Controller
{
    // Devuelve TODOS los informes sin autenticación ni control de acceso
    public function index()
    {
        return response()->json(InformeMedico::all());
    }

    // Crea un informe sin validación ni saneamiento (vulnerable a XSS e inyección)
    public function store(Request $request)
    {
        $informe = InformeMedico::create([
            'titulo'      => $request->titulo,
            'diagnostico' => $request->diagnostico,
            'paciente_id' => $request->paciente_id,
            'medico_id'   => $request->medico_id,
        ]);
        return response()->json([
            'mensaje' => 'Informe creado',
            'data'    => $informe
        ], 201);
    }

    // Devuelve cualquier informe por ID sin verificar si pertenece al usuario (IDOR)
    public function show($id)
    {
        $informe = InformeMedico::find($id);

        if (!$informe) {
            return response()->json(['mensaje' => 'Informe no encontrado'], 404);
        }

        return response()->json($informe);
    }

    // Actualiza cualquier informe sin verificar permisos
    public function update(Request $request, $id)
    {
        $informe = InformeMedico::find($id);

        if (!$informe) {
            return response()->json(['mensaje' => 'Informe no encontrado'], 404);
        }

        $informe->update([
            'titulo'      => $request->titulo,
            'diagnostico' => $request->diagnostico,
            'paciente_id' => $request->paciente_id,
            'medico_id'   => $request->medico_id,
        ]);

        return response()->json([
            'mensaje' => 'Informe actualizado',
            'data'    => $informe
        ], 200);
    }

    // Borra cualquier informe sin verificar permisos
    public function destroy($id)
    {
        $informe = InformeMedico::find($id);

        if (!$informe) {
            return response()->json(['mensaje' => 'Informe no encontrado'], 404);
        }

        $informe->delete();

        return response()->json(['mensaje' => 'Informe eliminado']);
    }

    // Búsqueda de informes
    // Patrón inseguro a propósito para la comparativa. Nunca usar en producción.
    public function buscar(Request $request)
    {
        $titulo = $request->query('titulo');
        $informes = DB::select("SELECT * FROM informes_medicos WHERE titulo = '$titulo'");
        return response()->json($informes);
    }
}