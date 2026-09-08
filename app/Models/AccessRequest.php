<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessRequest extends Model
{
    protected $fillable = [
        'firstname', 'lastname', 'id_type', 'id_number', 
        'landname', 'country', 'department', 'city', 'email', 'status'
    ];
}
