<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; //Agregado para la API :D

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // Un usuario puede tener muchas fincas
    public function fincas(): HasMany
    {
        return $this->hasMany(Finca::class);
    }

    public function twoFactorChallenges(): HasMany
    {
        return $this->hasMany(TwoFactorChallenge::class);
    }

    /**
     * Determina si este usuario es el dueño del sistema (Superadmin)
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::Superadmin;
    }

    /**
     * ¿El rol de este usuario está obligado a usar doble factor? (RF003)
     */
    public function requiresTwoFactor(): bool
    {
        return $this->role?->requiresTwoFactor() ?? false;
    }

    /**
     * ¿Ya terminó de configurar el TOTP (escaneó el QR y confirmó un código)?
     */
    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Forma en la que viaja el usuario en todas las respuestas de autenticación.
     *
     * @return array<string, mixed>
     */
    public function toAuthPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value,
            'is_superadmin' => $this->isSuperAdmin(),
            'two_factor_enabled' => $this->hasConfirmedTwoFactor(),
        ];
    }

    /**
     * El enlace de recuperación debe apuntar al frontend Angular, no a una
     * ruta web de Laravel (que no existe en este proyecto). RF005.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
