<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InformeMedicoController;
use App\Http\Controllers\AuthController;


// Ruta de prueba
Route::get('/saludo', function () {
    return response()->json([
        'mensaje' => 'Hola Mundo, mi API del TFG está viva',
        'autor' => 'Pablo',
        'estado' => 'Entorno configurado correctamente',
        'fecha' => date('Y-m-d H:i:s')
    ]);
});

// Rutas públicas de autenticación (sin rate limiting)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::get('/buscar-informes', [InformeMedicoController::class, 'buscar']);

// Rutas de informes médicos
Route::get('/informes', [InformeMedicoController::class, 'index']);
Route::post('/informes', [InformeMedicoController::class, 'store']);
Route::get('/informes/{id}', [InformeMedicoController::class, 'show']);
Route::put('/informes/{id}', [InformeMedicoController::class, 'update']);
Route::delete('/informes/{id}', [InformeMedicoController::class, 'destroy']);

  