<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Emisión y rotación de tokens de sesión. RF004.
 *
 * Cada login entrega DOS tokens:
 *   - access  : vida corta, es el que firma todas las peticiones normales.
 *   - refresh : vida larga, sólo sirve para pedir un par nuevo en /api/auth/refresh.
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
        return $this->createPair($user, $this->sanitizeDeviceName($deviceName), Str::random(16));
    }

    /**
     * Cambia un refresh token válido por un par nuevo. El par anterior se revoca
     * (rotación), de forma que un refresh token robado sólo sirve una vez.
     *
     * @return array<string, mixed>
     */
    public function rotate(User $user, PersonalAccessToken $refreshToken): array
    {
        [$deviceName, $sessionId] = $this->parseName($refreshToken->name);

        $this->revokeSession($user, $sessionId);

        return $this->createPair($user, $deviceName, Str::random(16));
    }

    /**
     * Revoca el access y el refresh de una sesión concreta (logout de un dispositivo).
     */
    public function revokeSession(User $user, string $sessionId): void
    {
        $user->tokens()
            ->where('name', 'like', '%'.self::SEPARATOR.$sessionId.self::SEPARATOR.'%')
            ->delete();
    }

    /**
     * Revoca todas las sesiones del usuario en todos sus dispositivos.
     */
    public function revokeAll(User $user): void
    {
        $user->tokens()->delete();
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
    private function createPair(User $user, string $deviceName, string $sessionId): array
    {
        $accessExpiresAt = Carbon::now()->addMinutes($this->accessTtlMinutes());
        $refreshExpiresAt = Carbon::now()->addMinutes($this->refreshTtlMinutes());

        $access = $user->createToken(
            $this->buildName($deviceName, $sessionId, 'access'),
            [self::ACCESS_ABILITY],
            $accessExpiresAt,
        );

        $refresh = $user->createToken(
            $this->buildName($deviceName, $sessionId, 'refresh'),
            [self::REFRESH_ABILITY],
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

            'expires_in' => $this->accessTtlMinutes() * 60,
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
