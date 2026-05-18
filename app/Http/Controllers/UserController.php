<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UserController extends Controller
{
    // Listar todos los usuarios (solo admin)
    public function index()
    {
        $users = User::with('roles')->get();
        return response()->json($users);
    }

    // Ver un usuario específico (solo admin)
    public function show($id)
    {
        $user = User::with('roles')->find($id);

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        return response()->json($user);
    }

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
}