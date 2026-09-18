<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessRequest;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthApiController extends Controller
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    /**
     * Login (paso 1).
     *
     * Valida las credenciales y:
     *   - si el rol exige doble factor (RF003), NO entrega tokens: devuelve un
     *     challenge_token temporal con el que el cliente llamará a /login/2fa/verify;
     *   - en caso contrario entrega directamente el par access + refresh (RF004).
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:60',   // Nombre de la app que consume
        ]);

        $throttleKey = 'login:'.Str::lower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Demasiados intentos fallidos. Intenta de nuevo en '
                    .RateLimiter::availableIn($throttleKey).' segundos.',
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        // 1. Verificar credenciales básicas
        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 300);

            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        // 2. Los productores necesitan una solicitud de acceso aprobada.
        //    El superadmin no pasa por ese flujo, así que queda exento.
        if (! $user->isSuperAdmin()) {
            $solicitud = AccessRequest::where('email', $request->email)->first();

            if (! $solicitud || $solicitud->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tu acceso no ha sido aprobado o ha sido revocado.',
                ], 403); // Error 403: Prohibido
            }
        }

        RateLimiter::clear($throttleKey);

        $deviceName = $request->device_name ?? 'api-client';

        // 3. Doble factor obligatorio para el rol Superadmin (RF003).
        if ($user->requiresTwoFactor()) {
            [, $challengeToken] = TwoFactorChallenge::issueFor($user, $deviceName);

            return response()->json([
                'success' => true,
                'message' => $user->hasConfirmedTwoFactor()
                    ? 'Ingresa el código de tu aplicación autenticadora.'
                    : 'Debes configurar la autenticación en dos pasos para continuar.',
                'data' => [
                    'requires_two_factor' => true,
                    // Cuando es false, el cliente debe llevar al usuario al alta
                    // del TOTP (/2fa/setup) usando este mismo challenge_token.
                    'two_factor_enrolled' => $user->hasConfirmedTwoFactor(),
                    'challenge_token' => $challengeToken,
                    'expires_in' => (int) config('sanctum.two_factor_challenge_ttl', 5) * 60,
                ],
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'requires_two_factor' => false,
                'user' => $user->toAuthPayload(),
                ...$this->tokens->issue($user, $deviceName),
            ],
        ], 200);
    }

    /**
     * Renueva el par de tokens a partir del refresh token (RF004).
     *
     * Ruta protegida con la ability "token:refresh", así que sólo el refresh
     * token puede llamarla; el access token normal no sirve aquí.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Sesión renovada',
            'data' => [
                'user' => $user->toAuthPayload(),
                ...$this->tokens->rotate($user, $request->user()->currentAccessToken()),
            ],
        ], 200);
    }

    /**
     * Me - Devuelve datos del usuario autenticado
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $request->user()->toAuthPayload(),
            ],
        ], 200);
    }

    /**
     * Logout - Revoca el par de tokens de la sesión actual
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->tokens->revokeSession(
            $user,
            $this->tokens->sessionIdFor($user->currentAccessToken()),
        );

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente',
        ], 200);
    }

    /**
     * Logout All - Revoca TODOS los tokens del usuario
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $this->tokens->revokeAll($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Todas las sesiones han sido cerradas',
        ], 200);
    }
}
