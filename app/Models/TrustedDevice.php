<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TrustedDevice extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['secret_hash', 'credential_hash', 'agent_hash', 'user_agent'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
