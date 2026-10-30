<?php

namespace App\Services;

use App\Models\AuthenticatorDevice;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Emisión y rotación de tokens de sesión. RF004.
 *
 * Cada login entrega DOS tokens:
 *   - access  : vida corta, es el que firma todas las peticiones normales.
 *   - refresh : vida larga, renueva el par por el transporte nativo o la cookie web.
 *
 * Ambos comparten un "session id" incrustado en el nombre del token, de modo que
 * cerrar sesión o rotar el par afecta exactamente al dispositivo correspondiente
 * y no a las demás sesiones del usuario.
 *
 * No se usa `sanctum.expiration` porque ese ajuste es global y caducaría también
 * al refresh token; la expiración se aplica token por token vía `expires_at`.
 */
class ApiTokenService
{
    public const ACCESS_ABILITY = 'api:access';

    public const REFRESH_ABILITY = 'token:refresh';

    private const SEPARATOR = '::';

    /**
     * Emite un par de tokens nuevo para una sesión nueva.
     *
     * @return array<string, mixed>
     */
    public function issue(User $user, ?string $deviceName = null): array
    {
        return DB::transaction(function () use ($user, $deviceName) {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            abort_unless($locked->isActive(), 403, 'Tu cuenta está suspendida.');
            abort_unless(hash_equals((string) $locked->getRawOriginal('password'), (string) $user->getRawOriginal('password')), 401, 'La contraseña cambió. Vuelve a iniciar sesión.');

            return $this->createPair($locked, $this->sanitizeDeviceName($deviceName), Str::random(16), request()->routeIs('web.auth.*'));
        }, 3);
    }

    /**
     * Cambia un refresh token válido por un par nuevo. El par anterior se revoca
     * (rotación), de forma que un refresh token robado sólo sirve una vez.
     *
     * @return array<string, mixed>
     */
    public function rotate(User $user, PersonalAccessToken $refreshToken): array
    {
        return DB::transaction(function () use ($user, $refreshToken) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            abort_unless($user->isActive(), 403, 'Tu cuenta está suspendida.');
            $locked = PersonalAccessToken::whereKey($refreshToken->id)->lockForUpdate()->first();
            abort_unless($locked && (! $locked->expires_at || $locked->expires_at->isFuture()), 401, 'La sesión ya fue renovada o caducó.');
            [$deviceName, $sessionId] = $this->parseName($locked->name);
            $this->revokeSession($user, $sessionId);

            $web = in_array('web:session', $locked->abilities ?? [], true);

            return $this->createPair($user, $deviceName, $web ? $sessionId : Str::random(16), $web);
        }, 3);
    }

    /**
     * Revoca el access y el refresh de una sesión concreta (logout de un dispositivo).
     */
    public function revokeSession(User $user, string $sessionId): void
    {
        DB::transaction(function () use ($user, $sessionId) {
            // Serialize logout with refresh so rotation cannot recreate a closed session.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->tokens()
                ->where('name', 'like', '%'.self::SEPARATOR.$sessionId.self::SEPARATOR.'%')
                ->delete();
        }, 3);
    }

    /**
     * Revoca todas las sesiones del usuario en todos sus dispositivos.
     */
    public function revokeAll(User $user): void
    {
        DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->tokens()->delete();
            TrustedDevice::where('user_id', $user->id)->delete();
            $user->twoFactorChallenges()->delete();
            AuthenticatorDevice::where('user_id', $user->id)->whereNull('confirmed_at')->delete();
        }, 3);
    }

    /**
     * Extrae el session id del token con el que viene firmada la petición actual.
     */
    public function sessionIdFor(PersonalAccessToken $token): string
    {
        return $this->parseName($token->name)[1];
    }

    public function accessTtlMinutes(): int
    {
        return (int) config('sanctum.access_token_ttl', 60);
    }

    public function refreshTtlMinutes(): int
    {
        return (int) config('sanctum.refresh_token_ttl', 10080);
    }

    /**
     * @return array<string, mixed>
     */
    private function createPair(User $user, string $deviceName, string $sessionId, bool $web = false): array
    {
        $accessMinutes = $web ? max(1, min(60, (int) config('web_session.access_minutes', 15))) : $this->accessTtlMinutes();
        $accessExpiresAt = Carbon::now()->addMinutes($accessMinutes);
        $refreshExpiresAt = Carbon::now()->addMinutes($this->refreshTtlMinutes());

        $access = $user->createToken(
            $this->buildName($deviceName, $sessionId, 'access'),
            $web ? [self::ACCESS_ABILITY, 'web:session'] : [self::ACCESS_ABILITY],
            $accessExpiresAt,
        );

        $refresh = $user->createToken(
            $this->buildName($deviceName, $sessionId, 'refresh'),
            $web ? [self::REFRESH_ABILITY, 'web:session'] : [self::REFRESH_ABILITY],
            $refreshExpiresAt,
        );

        return [
            'access_token' => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'token_type' => 'Bearer',

            // DEPRECADO: nombres que usaba la respuesta de /api/login antes del
            // RF004. Se mantienen para que la app Flutter siga funcionando sin
            // cambios; eliminar cuando el móvil consuma access_token/refresh_token.
            'token' => $access->plainTextToken,
            'type' => 'Bearer',

            'expires_in' => $accessMinutes * 60,
            'expires_at' => $accessExpiresAt->toIso8601String(),
            'refresh_expires_in' => $this->refreshTtlMinutes() * 60,
            'refresh_expires_at' => $refreshExpiresAt->toIso8601String(),
        ];
    }

    private function buildName(string $deviceName, string $sessionId, string $kind): string
    {
        return implode(self::SEPARATOR, [$deviceName, $sessionId, $kind]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseName(string $name): array
    {
        $parts = explode(self::SEPARATOR, $name);

        // Tokens antiguos (emitidos antes del RF004) no llevan session id;
        // se les asigna uno derivado del nombre para que logout siga funcionando.
        if (count($parts) < 3) {
            return [$name, sha1($name)];
        }

        return [$parts[0], $parts[1]];
    }

    private function sanitizeDeviceName(?string $deviceName): string
    {
        $clean = trim(str_replace(self::SEPARATOR, '-', (string) $deviceName));

        return $clean !== '' ? Str::limit($clean, 60, '') : 'api-client';
    }
}
