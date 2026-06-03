<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use PragmaRX\Google2FA\Google2FA;
use App\Models\User;
use App\Models\MfaRecoveryCode;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use App\Services\SecurityLogService;

class MfaController extends Controller
{
    /**
     * @OA\Post(
     *     path="/mfa/setup",
     *     summary="Iniciar configuración de MFA",
     *     tags={"MFA"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Secreto y QR generados"),
     *     @OA\Response(response=400, description="MFA ya está activado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    
    // Paso 1: Generar secreto y QR para activar MFA
    public function setup(Request $request)
    {
        $user = Auth::user();

        if ($user->mfa_enabled) {
            return response()->json(['message' => 'MFA ya está activado'], 400);
        }

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        // Guardamos el secreto temporalmente (sin activar aún)
        $user->mfa_secret = encrypt($secret);
        $user->save();

        // Generamos la URL para el QR
        $qrUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        // Generamos el QR en SVG
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrSvg = $writer->writeString($qrUrl);

        return response()->json([
            'message' => 'Escanea el QR con tu app de autenticación',
            'secret' => $secret,
            'qr_code' => base64_encode($qrSvg),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/mfa/confirm",
     *     summary="Confirmar y activar MFA",
     *     tags={"MFA"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", example="123456")
     *         )
     *     ),
     *     @OA\Response(response=200, description="MFA activado correctamente"),
     *     @OA\Response(response=422, description="Código inválido")
     * )
     */

    // Paso 2: Confirmar MFA con código TOTP
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();
        $user->refresh(); // Refresca el usuario desde la BD
        $google2fa = new Google2FA();

        
        $valid = $google2fa->verifyKey(decrypt($user->mfa_secret), $request->code);

        if (!$valid) {
            SecurityLogService::log('MFA_FAILED', $user->id, 'Código MFA inválido al activar', $request);
            return response()->json(['message' => 'Código inválido'], 422);
        }

        // Activamos MFA
        $user->mfa_enabled = true;
        $user->save();

        // Generamos códigos de recuperación
        $codes = $this->generateRecoveryCodes($user);
        SecurityLogService::log('MFA_ENABLED', $user->id, 'MFA activado correctamente', $request);

        return response()->json([
            'message' => 'MFA activado correctamente',
            'recovery_codes' => $codes,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/mfa/disable",
     *     summary="Desactivar MFA",
     *     tags={"MFA"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", example="123456")
     *         )
     *     ),
     *     @OA\Response(response=200, description="MFA desactivado correctamente"),
     *     @OA\Response(response=422, description="Código inválido")
     * )
     */

    // Desactivar MFA
    public function disable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();
        $google2fa = new Google2FA();

        $valid = $google2fa->verifyKey(decrypt($user->mfa_secret), $request->code);

        if (!$valid) {
            SecurityLogService::log('MFA_FAILED', $user->id, 'Código MFA inválido al desactivar', $request);
            return response()->json(['message' => 'Código inválido'], 422);
        }

        $user->mfa_enabled = false;
        $user->mfa_secret = null;
        $user->save();

        MfaRecoveryCode::where('user_id', $user->id)->delete();
        SecurityLogService::log('MFA_DISABLED', $user->id, 'MFA desactivado', $request);

        return response()->json(['message' => 'MFA desactivado correctamente']);
    }

    /**
     * @OA\Post(
     *     path="/mfa/verify-login",
     *     summary="Verificar código MFA durante el login",
     *     tags={"MFA"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"temporary_token","code"},
     *             @OA\Property(property="temporary_token", type="string", example="abc123..."),
     *             @OA\Property(property="code", type="string", example="123456")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login completado con éxito"),
     *     @OA\Response(response=401, description="Token o código inválido")
     * )
     */

    // Verificar código MFA en el login
    public function verifyLogin(Request $request)
    {
        $request->validate([
            'temporary_token' => 'required|string',
            'code' => 'required|string',
        ]);

        // Buscamos el usuario por el temporary_token
        $user = User::where('mfa_temp_token', $request->temporary_token)->first();

        if (!$user) {
            return response()->json(['message' => 'Token inválido'], 401);
        }

        $google2fa = new Google2FA();

        // Intentamos con código TOTP normal
        $valid = $google2fa->verifyKey(decrypt($user->mfa_secret), $request->code);

        // Si no es válido, intentamos con código de recuperación
        if (!$valid) {
            $recovery = MfaRecoveryCode::where('user_id', $user->id)
                ->whereNull('used_at')
                ->get()
                ->first(fn($r) => Hash::check($request->code, $r->code_hash));

            if ($recovery) {
                $recovery->used_at = now();
                $recovery->save();
                $valid = true;
            }
        }

        if (!$valid) {
            SecurityLogService::log('MFA_FAILED', $user->id, 'Código MFA inválido en login', $request);
            return response()->json(['message' => 'Código MFA inválido'], 401);
        }

        // Limpiamos el token temporal y generamos el definitivo
        $user->mfa_temp_token = null;
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;
        // Generamos el refresh token
        $refreshToken = \Illuminate\Support\Str::random(64);
        \App\Models\RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $refreshToken),
            'expires_at' => now()->addDays(30),
        ]);

        SecurityLogService::log('LOGIN_SUCCESS', $user->id, 'Login completado con MFA', $request);

        return response()->json([
            'message' => 'Login completado',
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/mfa/recovery-codes/regenerate",
     *     summary="Regenerar códigos de recuperación MFA",
     *     tags={"MFA"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Códigos regenerados"),
     *     @OA\Response(response=400, description="MFA no está activado")
     * )
     */

    // Regenerar códigos de recuperación
    public function regenerateRecoveryCodes(Request $request)
    {
        $user = Auth::user();

        if (!$user->mfa_enabled) {
            return response()->json(['message' => 'MFA no está activado'], 400);
        }

        MfaRecoveryCode::where('user_id', $user->id)->delete();
        $codes = $this->generateRecoveryCodes($user);

        return response()->json([
            'message' => 'Códigos de recuperación regenerados',
            'recovery_codes' => $codes,
        ]);
    }

    // Método privado para generar códigos de recuperación
    private function generateRecoveryCodes(User $user): array
    {
        MfaRecoveryCode::where('user_id', $user->id)->delete();

        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $code = Str::upper(Str::random(4)) . '-' . Str::upper(Str::random(4));
            MfaRecoveryCode::create([
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
            ]);
            $codes[] = $code;
        }

        return $codes;
    }

    // Funciones para MFA obligatorio en administradores
    public function setupRequired(Request $request)
{
    $request->validate([
        'temporary_token' => 'required|string',
    ]);

    $user = User::where('mfa_temp_token', $request->temporary_token)->first();

    if (!$user) {
        return response()->json(['message' => 'Token inválido'], 401);
    }

    if ($user->mfa_enabled) {
        return response()->json(['message' => 'MFA ya está activado'], 400);
    }

    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();

    $user->mfa_secret = encrypt($secret);
    $user->save();

    $qrUrl = $google2fa->getQRCodeUrl(
        config('app.name'),
        $user->email,
        $secret
    );

    $renderer = new ImageRenderer(
        new RendererStyle(200),
        new SvgImageBackEnd()
    );
    $writer = new Writer($renderer);
    $qrSvg = $writer->writeString($qrUrl);

    return response()->json([
        'message' => 'Escanea el QR para activar el MFA obligatorio',
        'secret' => $secret,
        'qr_code' => base64_encode($qrSvg),
        'temporary_token' => $request->temporary_token,
    ]);
}
    // Función para confirmar MFA obligatorio en administradores
    public function confirmRequired(Request $request)
{
    $request->validate([
        'temporary_token' => 'required|string',
        'code' => 'required|string|size:6',
    ]);

    $user = User::where('mfa_temp_token', $request->temporary_token)->first();

    if (!$user) {
        return response()->json(['message' => 'Token inválido'], 401);
    }

    $google2fa = new Google2FA();
    $valid = $google2fa->verifyKey(decrypt($user->mfa_secret), $request->code);

    if (!$valid) {
        return response()->json(['message' => 'Código inválido'], 422);
    }

    $user->mfa_enabled = true;
    $user->mfa_temp_token = null;
    $user->save();

    $codes = $this->generateRecoveryCodes($user);

    $token = $user->createToken('auth_token')->plainTextToken;
    $refreshToken = \Illuminate\Support\Str::random(64);
    \App\Models\RefreshToken::create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $refreshToken),
        'expires_at' => now()->addDays(30),
    ]);

    SecurityLogService::log('MFA_ENABLED', $user->id, 'MFA activado obligatoriamente para administrador', $request);

    return response()->json([
        'message' => 'MFA activado. Acceso concedido.',
        'access_token' => $token,
        'refresh_token' => $refreshToken,
        'token_type' => 'Bearer',
        'recovery_codes' => $codes,
    ]);
}
}