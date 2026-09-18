<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TwoFactorChallenge extends Model
{
    /**
     * Máximo de intentos fallidos antes de invalidar el reto.
     */
    public const MAX_ATTEMPTS = 5;

    protected $fillable = [
        'user_id',
        'token',
        'device_name',
        'attempts',
        'expires_at',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Crea un reto para el usuario y devuelve el token en claro,
     * que es lo único que viaja al cliente. En base de datos se guarda hasheado.
     *
     * @return array{0: self, 1: string}
     */
    public static function issueFor(User $user, ?string $deviceName = null): array
    {
        // Un usuario sólo puede tener un reto vivo a la vez.
        static::where('user_id', $user->id)->delete();

        $plainToken = Str::random(64);

        $challenge = static::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'device_name' => $deviceName,
            'expires_at' => now()->addMinutes((int) config('sanctum.two_factor_challenge_ttl', 5)),
        ]);

        return [$challenge, $plainToken];
    }

    /**
     * Busca un reto vigente a partir del token en claro enviado por el cliente.
     */
    public static function findValid(string $plainToken): ?self
    {
        return static::with('user')
            ->where('token', hash('sha256', $plainToken))
            ->where('expires_at', '>', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->first();
    }

    public function registerFailedAttempt(): void
    {
        $this->increment('attempts');
    }

    public function hasExhaustedAttempts(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * Elimina los retos vencidos o agotados. Lo invoca el scheduler.
     */
    public static function pruneStale(): int
    {
        return static::where('expires_at', '<', now())
            ->orWhere('attempts', '>=', self::MAX_ATTEMPTS)
            ->delete();
    }
}
