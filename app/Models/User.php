<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Importación necesaria para los tokens

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable; 

    /**
     * Atributos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'intentos_fallidos',
        'bloqueado_hasta',
    ];

    /**
     * Atributos que deben ocultarse en las respuestas JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Configuración de tipos de datos (Casting).
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // Esto asegura que la contraseña siempre se guarde segura
            'bloqueado_hasta' => 'datetime', // Para gestionar el bloqueo temporal
        ];
    }
}