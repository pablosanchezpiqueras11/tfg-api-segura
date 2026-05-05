<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InformeMedicoController;
use App\Http\Controllers\AuthController;


// Primer punto de entrada (Endpoint)
Route::get('/saludo', function () {
    return response()->json([
        'mensaje' => 'Hola Mundo, mi API del TFG está viva',
        'autor' => 'Pablo',
        'estado' => 'Entorno configurado correctamente',
        'fecha' => date('Y-m-d H:i:s')
    ]);
});

// Rutas que requieren estar logueado (Protegidas)
Route::middleware('auth:sanctum')->group(function () {
    // Módulo de Informes Médicos (Vulnerable)
    Route::get('/informes', [InformeMedicoController::class, 'index']);
    Route::post('/informes', [InformeMedicoController::class, 'store']);
    Route::get('/informes/{id}', [InformeMedicoController::class, 'show']);
    Route::delete('/informes/{id}', [InformeMedicoController::class, 'destroy']);
    Route::post('/logout', [AuthController::class, 'logout']);
    // También se puede añadir una ruta para ver el perfil del usuario actual
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
// Rutas públicas de autenticación(de momento, sin protección)

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
