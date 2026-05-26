<?php

namespace App\Http\Controllers;

use App\Models\InformeMedico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InformeMedicoController extends Controller
{   
    /**
     * @OA\Get(
     *     path="/informes",
     *     summary="Listar informes médicos",
     *     tags={"Informes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="titulo",
     *         in="query",
     *         description="Filtrar por título",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="paciente_id",
     *         in="query",
     *         description="Filtrar por paciente (solo admin y manager)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número de página",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Lista de informes paginada"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    // Listar informes según el rol
    public function index(Request $request)
        {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            $query = InformeMedico::query();
        } elseif ($user->hasRole('manager')) {
            $query = InformeMedico::where('medico_id', $user->id);
        } else {
            $query = InformeMedico::where('paciente_id', $user->id);
        }

        // Filtro por título
        if ($request->has('titulo')) {
            $query->where('titulo', 'like', '%' . $request->titulo . '%');
        }

        // Filtro por paciente (solo admin y manager)
        if ($request->has('paciente_id') && $user->hasRole(['admin', 'manager'])) {
            $query->where('paciente_id', $request->paciente_id);
        }

        // Filtro por médico (solo admin)
        if ($request->has('medico_id') && $user->hasRole('admin')) {
            $query->where('medico_id', $request->medico_id);
        }

        // Paginación
        $informes = $query->paginate(5);

        return response()->json($informes);
    }

    /**
     * @OA\Post(
     *     path="/informes",
     *     summary="Crear un nuevo informe médico",
     *     tags={"Informes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"titulo","diagnostico","paciente_id","medico_id"},
     *                 @OA\Property(property="titulo", type="string", example="Informe de revisión"),
     *                 @OA\Property(property="diagnostico", type="string", example="Paciente en buen estado"),
     *                 @OA\Property(property="paciente_id", type="integer", example=1),
     *                 @OA\Property(property="medico_id", type="integer", example=2),
     *                 @OA\Property(property="archivo", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Informe creado con éxito"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=422, description="Datos incorrectos")
     * )
     */

    // Crear un informe
    public function store(Request $request)
    {
        $request->validate([
            'titulo'      => 'required|string|max:255',
            'diagnostico' => 'required|string',
            'paciente_id' => 'required|integer|exists:users,id',
            'medico_id'   => 'required|integer|exists:users,id',
            'archivo'     => 'nullable|file|mimes:pdf,doc,docx|max:2048',
        ]);

        $tituloSeguro      = strip_tags($request->titulo);
        $diagnosticoSeguro = strip_tags($request->diagnostico);

        $rutaArchivo = null;
        if ($request->hasFile('archivo')) {
            $rutaArchivo = $request->file('archivo')->store('informes', 'public');
        }

        $informe = InformeMedico::create([
            'titulo'       => $tituloSeguro,
            'diagnostico'  => $diagnosticoSeguro,
            'paciente_id'  => $request->paciente_id,
            'medico_id'    => $request->medico_id,
            'ruta_archivo' => $rutaArchivo,
        ]);

        return response()->json([
            'message' => 'Informe creado con éxito',
            'data'    => $informe
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/informes/{id}",
     *     summary="Ver un informe médico",
     *     tags={"Informes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Informe encontrado"),
     *     @OA\Response(response=403, description="Acceso no autorizado"),
     *     @OA\Response(response=404, description="Informe no encontrado")
     * )
     */

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

        // Añadimos la URL completa del archivo si existe
        $data = $informe->toArray();
        if ($informe->ruta_archivo) {
            $data['archivo_url'] = asset('storage/' . $informe->ruta_archivo);
        }

        return response()->json($data);
    }

    /**
     * @OA\Put(
     *     path="/informes/{id}",
     *     summary="Actualizar un informe médico",
     *     tags={"Informes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="titulo", type="string", example="Informe actualizado"),
     *             @OA\Property(property="diagnostico", type="string", example="Nuevo diagnóstico"),
     *             @OA\Property(property="paciente_id", type="integer", example=1),
     *             @OA\Property(property="medico_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Informe actualizado"),
     *     @OA\Response(response=403, description="Acceso no autorizado"),
     *     @OA\Response(response=404, description="Informe no encontrado")
     * )
     */
    
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

    /**
     * @OA\Delete(
     *     path="/informes/{id}",
     *     summary="Eliminar un informe médico",
     *     tags={"Informes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Informe eliminado"),
     *     @OA\Response(response=403, description="Solo administradores pueden eliminar"),
     *     @OA\Response(response=404, description="Informe no encontrado")
     * )
     */

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