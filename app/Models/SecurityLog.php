<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityLog extends Model
{
    protected $fillable = [
        'user_id',
        'tipo_evento',
        'ip_address',
        'user_agent',
        'descripcion',
    ];

    public $timestamps = false;
    
    const CREATED_AT = 'created_at';

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
    return $this->belongsTo(User::class);
    }
}
