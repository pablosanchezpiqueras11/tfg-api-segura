<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InformeMedicoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MfaController;

// Ruta necesaria para manejar autenticación fallida
Route::get('/login', function () {
    return response()->json(['message' => 'No autenticado'], 401);
})->name('login');

// Ruta de prueba
Route::get('/saludo', function () {
    return response()->json([
        'mensaje' => 'Hola Mundo, mi API del TFG está viva',
        'autor' => 'Pablo',
        'estado' => 'Entorno configurado correctamente',
        'fecha' => date('Y-m-d H:i:s')
    ]);
});



// Rutas públicas de autenticación
// Limitamos a 5 intentos por minuto para evitar ataques de fuerza bruta
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/mfa/verify-login', [MfaController::class, 'verifyLogin']);
    Route::post('/token/refresh', [AuthController::class, 'refresh']);
    Route::post('/token/revoke', [AuthController::class, 'revoke']);
});

 // Verificar si el usuario necesita configurar MFA
    Route::post('/mfa/setup-required', [MfaController::class, 'setupRequired']);
    Route::post('/mfa/confirm-required', [MfaController::class, 'confirmRequired']);

// Rutas protegidas (requieren estar logueado)
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Rutas de informes médicos
    Route::get('/informes', [InformeMedicoController::class, 'index']);
    Route::post('/informes', [InformeMedicoController::class, 'store']);
    Route::get('/informes/{id}', [InformeMedicoController::class, 'show']);
    Route::put('/informes/{id}', [InformeMedicoController::class, 'update']);
    Route::delete('/informes/{id}', [InformeMedicoController::class, 'destroy']);

    // Rutas de MFA
    Route::post('/mfa/setup', [MfaController::class, 'setup']);
    Route::post('/mfa/confirm', [MfaController::class, 'confirm']);
    Route::post('/mfa/disable', [MfaController::class, 'disable']);
    Route::post('/mfa/recovery-codes/regenerate', [MfaController::class, 'regenerateRecoveryCodes']);

    // Cambio de contraseña
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // Perfil del usuario autenticado
    Route::get('/me', [UserController::class, 'me']);
    Route::put('/me', [UserController::class, 'updateMe']);
    
    // Rutas para usar códigos de recuperación MFA
    Route::post('/mfa/recovery-codes/use', [MfaController::class, 'useRecoveryCode']);

    // Rutas solo para administradores
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::patch('/users/{id}/lock', [UserController::class, 'lock']);
        Route::patch('/users/{id}/unlock', [UserController::class, 'unlock']);
        Route::get('/security-logs', [UserController::class, 'securityLogs']);
        Route::post('/users', [UserController::class, 'store']);
    });

    
});