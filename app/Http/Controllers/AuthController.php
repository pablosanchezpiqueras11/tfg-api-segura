<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon; // Necesaria para gestionar los tiempos de bloqueo
use App\Services\SecurityLogService; // Servicio para registrar eventos de seguridad
use App\Models\RefreshToken;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/register",
     *     summary="Registrar un nuevo usuario",
     *     tags={"Autenticación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password"},
     *             @OA\Property(property="name", type="string", example="Pablo Sánchez"),
     *             @OA\Property(property="email", type="string", example="pablo@ejemplo.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Usuario registrado con éxito"),
     *     @OA\Response(response=422, description="Datos de validación incorrectos")
     * )
     */

    // Registro de usuario
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
            'password' => Hash::make($request->password), // Siempre encriptada
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
    
    /**
     * @OA\Post(
     *     path="/login",
     *     summary="Iniciar sesión",
     *     tags={"Autenticación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", example="admin@test.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login correcto"),
     *     @OA\Response(response=401, description="Credenciales incorrectas"),
     *     @OA\Response(response=403, description="Cuenta bloqueada"),
     *     @OA\Response(response=429, description="Demasiadas peticiones")
     * )
     */

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
        if ($user->bloqueado_hasta && Carbon::parse($user->bloqueado_hasta)->isFuture()) { //isFuture() verifica si el bloqueo aún está activo
            $minutosRestantes = Carbon::now()->diffInMinutes($user->bloqueado_hasta);
            return response()->json([
                'message' => "Cuenta bloqueada. Inténtalo en $minutosRestantes minutos."
            ], 403);
        }

        // 2. VERIFICAR CONTRASEÑA
        if (!Hash::check($request->password, $user->password)) { //Hash::check compara la contraseña recibida con el hash bcrypt guardado
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
        $user->tokens()->delete(); //borramos tokens anteriores para evitar múltiples sesiones
        $user->update([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ]);

        // 4. VERIFICAR MFA
        if ($user->hasRole('admin') && !$user->mfa_enabled) {
            $tempToken = \Illuminate\Support\Str::random(64);
            $user->update(['mfa_temp_token' => $tempToken]);

            return response()->json([
                'message' => 'Los administradores deben activar el MFA antes de acceder.',
                'mfa_setup_required' => true,
                'temporary_token' => $tempToken,
            ]);
        }

        if ($user->mfa_enabled) {
            $tempToken = \Illuminate\Support\Str::random(64);
            $user->update(['mfa_temp_token' => $tempToken]);

            return response()->json([
                'message' => 'MFA requerido',
                'mfa_required' => true,
                'temporary_token' => $tempToken,
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken; //El plainTextToken es el token en claro, que solo se entrega al cliente en el momento de crearlo
        // Generamos el refresh token
    $refreshToken = \Illuminate\Support\Str::random(64);
    RefreshToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $refreshToken),
        'expires_at' => now()->addDays(30),
    ]);

    SecurityLogService::log('LOGIN_SUCCESS', $user->id, 'Login correcto', $request);

    return response()->json([
        'message' => 'Login correcto',
        'access_token' => $token,
        'refresh_token' => $refreshToken,
        'token_type' => 'Bearer',
    ]);
    }

     /**
     * @OA\Post(
     *     path="/logout",
     *     summary="Cerrar sesión",
     *     tags={"Autenticación"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Sesión cerrada con éxito"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
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
    /**
     * @OA\Post(
     *     path="/token/refresh",
     *     summary="Renovar token de acceso",
     *     tags={"Autenticación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *             @OA\Property(property="refresh_token", type="string", example="abc123...")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Token renovado correctamente"),
     *     @OA\Response(response=401, description="Refresh token inválido o caducado")
     * )
     */
    public function refresh(Request $request)
{
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $tokenHash = hash('sha256', $request->refresh_token);
        $refreshToken = RefreshToken::where('token_hash', $tokenHash)->first();

        if (!$refreshToken || !$refreshToken->isValid()) {
            return response()->json(['message' => 'Refresh token inválido o caducado'], 401);
        }

        $user = $refreshToken->user;

        // Revocamos el refresh token anterior
        $refreshToken->update(['revoked_at' => now()]);

        // Revocamos tokens de acceso anteriores
        $user->tokens()->delete();

        // Generamos nuevos tokens
        $newAccessToken = $user->createToken('auth_token')->plainTextToken;
        $newRefreshToken = \Illuminate\Support\Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $newRefreshToken),
            'expires_at' => now()->addDays(30),
        ]);

        SecurityLogService::log('TOKEN_REFRESHED', $user->id, 'Token de acceso renovado', $request);

        return response()->json([
            'message' => 'Token renovado correctamente',
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
        ]);
}

    /**
     * @OA\Post(
     *     path="/token/revoke",
     *     summary="Revocar refresh token",
     *     tags={"Autenticación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *             @OA\Property(property="refresh_token", type="string", example="abc123...")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Token revocado correctamente"),
     *     @OA\Response(response=401, description="Refresh token inválido")
     * )
     */
    public function revoke(Request $request)
{
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $tokenHash = hash('sha256', $request->refresh_token);
        $refreshToken = RefreshToken::where('token_hash', $tokenHash)->first();

        if (!$refreshToken || !$refreshToken->isValid()) {
            return response()->json(['message' => 'Refresh token inválido o caducado'], 401);
        }

        $refreshToken->update(['revoked_at' => now()]);

        return response()->json(['message' => 'Refresh token revocado correctamente']);
}

    /**
     * @OA\Post(
     *     path="/change-password",
     *     summary="Cambiar contraseña",
     *     tags={"Autenticación"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password","new_password"},
     *             @OA\Property(property="current_password", type="string", example="password123"),
     *             @OA\Property(property="new_password", type="string", example="nuevaPassword123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Contraseña cambiada correctamente"),
     *     @OA\Response(response=401, description="Contraseña actual incorrecta"),
     *     @OA\Response(response=422, description="Datos incorrectos")
     * )
     */
    public function changePassword(Request $request)
{
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|different:current_password',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta'], 401);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        // Revocamos todos los tokens para forzar nuevo login
        $user->tokens()->delete();

        SecurityLogService::log('PASSWORD_CHANGED', $user->id, 'Contraseña cambiada correctamente', $request);

        return response()->json(['message' => 'Contraseña cambiada correctamente. Por seguridad, inicia sesión de nuevo.']);
}
}
