<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Primer punto de entrada (Endpoint)
Route::get('/saludo', function () {
    return response()->json([
        'mensaje' => 'Hola Mundo, mi API del TFG está viva',
        'autor' => 'Pablo',
        'estado' => 'Entorno configurado correctamente',
        'fecha' => date('Y-m-d H:i:s')
    ]);
});

use App\Http\Controllers\InformeMedicoController;
Route::get('/informes', [InformeMedicoController::class, 'index']);
Route::post('/informes', [InformeMedicoController::class, 'store']);