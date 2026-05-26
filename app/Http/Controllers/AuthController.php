<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


class AuthController extends Controller
{
    // Registro sin validación fuerte ni roles
    public function register(Request $request)
    {
        // Validamos los datos básicos
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        // Creamos el usuario
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password), // ¡Importante! Siempre encriptada
        ]);
        
        // Emitimos el token de Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado con éxito',
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }
    
    // Login sin bloqueo ni rate limiting
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        // Mensaje que revela si el usuario existe o no (enumeración)
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 401);
        }
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Contraseña incorrecta'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login correcto',
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    // Logout sin verificación
    public function logout(Request $request)
    {
        return response()->json([
            'message' => 'Sesión cerrada con éxito.'
        ], 200);
    }
}
        