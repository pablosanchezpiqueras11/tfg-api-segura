<?php

namespace App\Http\Controllers;

use App\Models\InformeMedico;
use Illuminate\Http\Request;

class InformeMedicoController extends Controller
{
    public function index()
    {
        // De momento, devolvemos TODOS los informes (Versión Vulnerable)
        return response()->json(InformeMedico::all());
    }

    public function store(Request $request)
    {
    // Creamos el informe con lo que llegue por la petición
    $informe = InformeMedico::create([
        'titulo' => $request->titulo,
        'diagnostico' => $request->diagnostico,
        'paciente_id' => $request->paciente_id,
        'medico_id' => $request->medico_id,
    ]);

    return response()->json([
        'mensaje' => 'Informe creado con éxito (Versión Vulnerable)',
        'data' => $informe
    ], 201);
    }

    // Obtener un informe específico
    public function show($id)
    {
        $informe = InformeMedico::find($id);

        if (!$informe) {
            return response()->json(['mensaje' => 'Informe no encontrado'], 404);
        }

        return response()->json($informe);
    }

    // Borrar un informe
    public function destroy($id)
    {
        $informe = InformeMedico::find($id);

        if (!$informe) {
            return response()->json(['mensaje' => 'Informe no encontrado'], 404);
        }

        $informe->delete();

        return response()->json(['mensaje' => 'Informe eliminado correctamente (Versión Vulnerable)']);
    }
}
