<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MfaRecoveryCode extends Model
{
    protected $fillable = [
        'user_id',
        'code_hash',
        'used_at',
    ];
}