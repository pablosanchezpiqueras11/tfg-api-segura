<?php

namespace App\Services;

use App\Models\SecurityLog;
use Illuminate\Http\Request;

class SecurityLogService
{
    public static function log(string $eventType, ?int $userId, string $description, Request $request): void
    {
        SecurityLog::create([
            'user_id'     => $userId,
            'tipo_evento'  => $eventType,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'descripcion' => $description,
        ]);
    }
}