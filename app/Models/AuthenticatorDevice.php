<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuthenticatorDevice extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['secret', 'session_hash'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'confirmed_at' => 'datetime', 'expires_at' => 'datetime', 'security_version' => 'integer'];
    }
}
