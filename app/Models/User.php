<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; //Agregado para la API :D

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Un usuario puede tener muchas fincas
    public function fincas()
    {
        return $this->hasMany(Finca::class);
    }

    /**
     * Determina si este usuario es el dueño del sistema (Superadmin)
     */
    public function isSuperAdmin(): bool
    {
        // Puedes definirlo por el ID (ej. el primer usuario creado) 
        // o por tu correo electrónico.
        return $this->id === 1; 
        
        // Alternativa: return $this->email === 'tu_correo@gmail.com';
    }
}