<?php

namespace App\Models;

use App\Models\Concerns\PreservesHistory;
use Illuminate\Database\Eloquent\Model;

class AccessRequest extends Model
{
    use PreservesHistory;

    protected $hidden = ['invitation_secret', 'invitation_version'];

    protected function casts(): array
    {
        return ['invitation_secret' => 'encrypted', 'invitation_sent_at' => 'datetime', 'invitation_attempted_at' => 'datetime'];
    }

    public function setEmailAttribute(string $email): void
    {
        $this->attributes['email'] = mb_strtolower(trim($email));
    }

    protected $fillable = [
        'firstname', 'lastname', 'id_type', 'id_number',
        'landname', 'country', 'department', 'city', 'email', 'status',
    ];
}
