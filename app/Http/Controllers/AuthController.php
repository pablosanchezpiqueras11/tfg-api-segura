<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon; // Necesaria para gestionar los tiempos de bloqueo
use App\Services\SecurityLogService; // Servicio para registrar eventos de seguridad

class AuthController extends Controller
{
    // Registro de usuario(primero algo funcional, mas adelante se añadirá validación y roles)
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
        $user->assignRole('user'); // Asignamos rol por defecto
        // Emitimos el token de Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado con éxito',
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }
    
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        // 1. VERIFICAR BLOQUEO
        if ($user->bloqueado_hasta && Carbon::parse($user->bloqueado_hasta)->isFuture()) {
            $minutosRestantes = Carbon::now()->diffInMinutes($user->bloqueado_hasta);
            return response()->json([
                'message' => "Cuenta bloqueada. Inténtalo en $minutosRestantes minutos."
            ], 403);
        }

        // 2. VERIFICAR CONTRASEÑA
        if (!Hash::check($request->password, $user->password)) {
            $user->increment('intentos_fallidos');

            if ($user->intentos_fallidos >= 3) {
                $user->update(['bloqueado_hasta' => Carbon::now()->addMinutes(5)]);
                SecurityLogService::log('USER_LOCKED', $user->id, 'Cuenta bloqueada por múltiples intentos fallidos', $request);
                return response()->json(['message' => 'Cuenta bloqueada por 5 minutos debido a múltiples fallos.'], 403);
            }
            SecurityLogService::log('LOGIN_FAILED', $user->id, 'Intento de login fallido', $request);
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        // 3. LOGIN EXITOSO
        $user->tokens()->delete();
        $user->update([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ]);

        // 4. VERIFICAR MFA
        if ($user->mfa_enabled) {
            $tempToken = \Illuminate\Support\Str::random(64);
            $user->update(['mfa_temp_token' => $tempToken]);

            return response()->json([
                'message' => 'MFA requerido',
                'mfa_required' => true,
                'temporary_token' => $tempToken,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        SecurityLogService::log('LOGIN_SUCCESS', $user->id, 'Login correcto', $request);

        return response()->json([
            'message' => 'Login correcto',
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request)
{
   // Verificamos que el usuario tenga un token antes de intentar borrarlo
    if ($request->user()) {
        SecurityLogService::log('LOGOUT', $request->user()->id, 'Sesión cerrada', $request);
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'message' => 'Sesión cerrada con éxito.'
        ], 200);
    }

    return response()->json([
        'message' => 'No se pudo encontrar una sesión activa.'
    ], 401);
}
}
