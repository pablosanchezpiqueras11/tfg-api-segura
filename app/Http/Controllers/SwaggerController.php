<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="TFG API Segura - Gestión de Informes Médicos",
 *     version="1.0.0",
 *     description="API REST segura con autenticación JWT, MFA, roles y permisos, rate limiting y auditoría de seguridad.",
 *     @OA\Contact(
 *         email="pablo@tfg.com",
 *         name="Pablo Sánchez"
 *     )
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Server(
 *     url="http://localhost/api",
 *     description="Servidor local de desarrollo"
 * )
 */
class SwaggerController extends Controller
{
}