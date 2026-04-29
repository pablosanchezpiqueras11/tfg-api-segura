<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InformeMedicoController;

// Primer punto de entrada (Endpoint)
Route::get('/saludo', function () {
    return response()->json([
        'mensaje' => 'Hola Mundo, mi API del TFG está viva',
        'autor' => 'Pablo',
        'estado' => 'Entorno configurado correctamente',
        'fecha' => date('Y-m-d H:i:s')
    ]);
});

// Módulo de Informes Médicos (Vulnerable)
Route::get('/informes', [InformeMedicoController::class, 'index']);
Route::post('/informes', [InformeMedicoController::class, 'store']);
Route::get('/informes/{id}', [InformeMedicoController::class, 'show']);
Route::delete('/informes/{id}', [InformeMedicoController::class, 'destroy']);