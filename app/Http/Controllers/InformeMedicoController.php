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
    // 1. VALIDACIÓN: Evitamos que entren datos vacíos o formatos incorrectos
    $request->validate([
        'titulo'      => 'required|string|max:255',
        'diagnostico' => 'required|string',
        'paciente_id' => 'required|integer',
        'medico_id'   => 'required|integer',
    ]);

    // 2. SANEAMIENTO: Desinfectamos los campos de texto antes de que toquen la BD
    // Esto elimina cualquier etiqueta <script>, <html> o similar
    $tituloSeguro      = strip_tags($request->titulo);
    $diagnosticoSeguro = strip_tags($request->diagnostico);

    // 3. CREACIÓN: Usamos las variables seguras para persistir el informe
    $informe = InformeMedico::create([
        'titulo'      => $tituloSeguro,
        'diagnostico' => $diagnosticoSeguro,
        'paciente_id' => $request->paciente_id,
        'medico_id'   => $request->medico_id,
    ]);

    // 4. RESPUESTA: Confirmamos que se ha creado con éxito
    return response()->json([
        'mensaje' => 'Informe creado con éxito (Protección XSS activada)',
        'data'    => $informe
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

    // Actualizar un informe existente
    public function update(Request $request, $id)
    {
        // 1. Buscamos el informe (si no existe, lanza un 404)
        $informe = InformeMedico::find($id);

        if (!$informe) {
            return response()->json(['mensaje' => 'Informe no encontrado'], 404);
        }

        // 2. VALIDACIÓN: Aseguramos que los datos nuevos sean correctos
        $request->validate([
            'titulo'      => 'required|string|max:255',
            'diagnostico' => 'required|string',
            'paciente_id' => 'required|integer',
            'medico_id'   => 'required|integer',
        ]);

        // 3. SANEAMIENTO: Limpiamos los datos antes de actualizar (Protección XSS)
        $tituloSeguro      = strip_tags($request->titulo);
        $diagnosticoSeguro = strip_tags($request->diagnostico);

        // 4. ACTUALIZACIÓN: Guardamos los cambios desinfectados
        $informe->update([
            'titulo'      => $tituloSeguro,
            'diagnostico' => $diagnosticoSeguro,
            'paciente_id' => $request->paciente_id,
            'medico_id'   => $request->medico_id,
        ]);

        return response()->json([
            'mensaje' => 'Informe actualizado con éxito (Protección XSS activada)',
            'data'    => $informe
        ], 200);
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
