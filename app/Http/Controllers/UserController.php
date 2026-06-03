<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\SecurityLog;

class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/users",
     *     summary="Listar todos los usuarios",
     *     tags={"Usuarios (Admin)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Lista de usuarios"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=403, description="Sin permisos de administrador")
     * )
     */
    // Listar todos los usuarios (solo admin)
    public function index()
    {
        $users = User::with('roles')->get();
        return response()->json($users);
    }

    /**
     * @OA\Get(
     *     path="/users/{id}",
     *     summary="Ver un usuario específico",
     *     tags={"Usuarios (Admin)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Usuario encontrado"),
     *     @OA\Response(response=404, description="Usuario no encontrado"),
     *     @OA\Response(response=403, description="Sin permisos de administrador")
     * )
     */
    // Ver un usuario específico (solo admin)
    public function show($id)
    {
        $user = User::with('roles')->find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        return response()->json($user);
    }

    /**
     * @OA\Put(
     *     path="/users/{id}",
     *     summary="Actualizar un usuario",
     *     tags={"Usuarios (Admin)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Nuevo nombre"),
     *             @OA\Property(property="email", type="string", example="nuevo@email.com"),
     *             @OA\Property(property="role", type="string", example="manager")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Usuario actualizado"),
     *     @OA\Response(response=404, description="Usuario no encontrado"),
     *     @OA\Response(response=403, description="Sin permisos de administrador")
     * )
     */

    // Editar un usuario (solo admin)
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'role' => 'sometimes|string|in:admin,manager,user',
        ]);

        $user->update($request->only('name', 'email'));

        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
        }

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'data' => $user->load('roles')
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/users/{id}",
     *     summary="Eliminar un usuario",
     *     tags={"Usuarios (Admin)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Usuario eliminado"),
     *     @OA\Response(response=404, description="Usuario no encontrado"),
     *     @OA\Response(response=403, description="Sin permisos de administrador")
     * )
     */

    // Eliminar un usuario (solo admin)
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }

    /**
     * @OA\Patch(
     *     path="/users/{id}/lock",
     *     summary="Bloquear un usuario",
     *     tags={"Usuarios (Admin)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Usuario bloqueado"),
     *     @OA\Response(response=404, description="Usuario no encontrado"),
     *     @OA\Response(response=403, description="Sin permisos de administrador")
     * )
     */

    // Bloquear un usuario (solo admin)
    public function lock($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $user->update(['bloqueado_hasta' => Carbon::now()->addYears(10)]);

        return response()->json(['message' => 'Usuario bloqueado correctamente']);
    }

    /**
     * @OA\Patch(
     *     path="/users/{id}/unlock",
     *     summary="Desbloquear un usuario",
     *     tags={"Usuarios (Admin)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Usuario desbloqueado"),
     *     @OA\Response(response=404, description="Usuario no encontrado"),
     *     @OA\Response(response=403, description="Sin permisos de administrador")
     * )
     */

    // Desbloquear un usuario (solo admin)
    public function unlock($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $user->update([
            'bloqueado_hasta' => null,
            'intentos_fallidos' => 0
        ]);

        return response()->json(['message' => 'Usuario desbloqueado correctamente']);
    }

    /**
     * @OA\Get(
     *     path="/security-logs",
     *     summary="Ver logs de seguridad",
     *     tags={"Usuarios (Admin)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Lista de logs de seguridad paginada"),
     *     @OA\Response(response=403, description="Sin permisos de administrador")
     * )
     */

    public function securityLogs()
    {
    $logs = SecurityLog::with('user:id,name,email')
        ->orderBy('created_at', 'desc')
        ->paginate(20);

    return response()->json($logs);
    }

    /**
     * @OA\Get(
     *     path="/me",
     *     summary="Ver perfil del usuario autenticado",
     *     tags={"Autenticación"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Perfil del usuario"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function me(Request $request)
    {
        return response()->json($request->user()->load('roles'));
    }

    /**
     * @OA\Put(
     *     path="/me",
     *     summary="Editar perfil del usuario autenticado",
     *     tags={"Autenticación"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Nuevo nombre"),
     *             @OA\Property(property="email", type="string", example="nuevo@email.com")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Perfil actualizado"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=422, description="Datos incorrectos")
     * )
     */
    public function updateMe(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        $user->update($request->only('name', 'email'));

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'data' => $user->load('roles')
        ]);
    }

        /**
     * @OA\Post(
     *     path="/users",
     *     summary="Crear un nuevo usuario (Admin)",
     *     tags={"Usuarios (Admin)"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password"},
     *             @OA\Property(property="name", type="string", example="Nuevo Usuario"),
     *             @OA\Property(property="email", type="string", example="nuevo@email.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="role", type="string", example="user")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Usuario creado correctamente"),
     *     @OA\Response(response=403, description="Sin permisos de administrador"),
     *     @OA\Response(response=422, description="Datos incorrectos")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'sometimes|string|in:admin,manager,user',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
        ]);

        $user->assignRole($request->role ?? 'user');

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'data' => $user->load('roles')
        ], 201);
    }
}