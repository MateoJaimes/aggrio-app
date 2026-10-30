<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessRequest;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Services\TrustedDeviceService;
use App\Services\WebSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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
        return DB::transaction(fn () => $this->performLogin($request), 3);
    }

    private function performLogin(Request $request): JsonResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
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

        $user = User::where('email', $request->email)->lockForUpdate()->first();

        // 1. Verificar credenciales básicas
        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 300);

            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        if (! $user->isActive()) {
            return response()->json(['success' => false, 'code' => 'account_suspended', 'message' => 'Tu cuenta está suspendida. Contacta al equipo de Aggrio para revisar tu acceso.'], 403);
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

        if ($user->must_change_password && $user->temporary_password_expires_at?->isPast()) {
            return response()->json(['success' => false, 'message' => 'Tu contraseña temporal caducó. Utiliza «Olvidé mi contraseña» para crear una nueva.'], 403);
        }

        $deviceName = $request->routeIs('web.auth.*') ? 'web-panel' : ($request->device_name ?? 'api-client');
        if (! $request->routeIs('web.auth.*') && $deviceName === 'web-panel') {
            $deviceName = 'api-web-panel';
        }

        // 3. Doble factor obligatorio para el rol Superadmin (RF003).
        if ($user->requiresTwoFactor()) {
            $devices = app(TrustedDeviceService::class);
            if ($device = $devices->recognize($request, $user)) {
                $response = response()->json(['success' => true, 'message' => 'Dispositivo reconocido.', 'data' => [
                    'requires_two_factor' => false, 'trusted_device' => true, 'user' => $user->toAuthPayload(), ...$this->tokens->issue($user, $deviceName),
                ]]);

                return $devices->remember($request, $user, $response, $device);
            }
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
        abort_if(in_array('web:session', $request->user()->currentAccessToken()->abilities ?? [], true), 403, 'La sesión web solo se renueva por cookie.');
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

    public function changePassword(Request $request): JsonResponse
    {
        if ($request->routeIs('web.auth.password')) {
            $cookie = app(WebSession::class)->authenticatedToken($request);
            abort_unless($cookie->tokenable_id === $request->user()->id && $this->tokens->sessionIdFor($cookie) === $this->tokens->sessionIdFor($request->user()->currentAccessToken()), 403, 'La sesión del navegador cambió. Vuelve a iniciar sesión.');
        } else {
            abort_if(in_array('web:session', $request->user()->currentAccessToken()->abilities ?? [], true), 403, 'Cambia tu contraseña desde la sesión web protegida.');
        }

        return DB::transaction(function () use ($request) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            $data = $request->validate(['current_password' => $user->must_change_password ? 'nullable|string' : 'required|string',
                'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]], [
                    'current_password.required' => 'Ingresa tu contraseña actual para realizar este cambio.',
                    'password.confirmed' => 'Las contraseñas nuevas no coinciden.',
                ]);
            // The temporary login already authenticated this session. Only subsequent
            // voluntary password changes require the current personal password again.
            if (! $user->must_change_password) {
                abort_unless(Hash::check($data['current_password'], $user->password), 422, 'La contraseña actual no es correcta.');
            }
            abort_if($user->must_change_password && $user->temporary_password_expires_at?->isPast(), 403, 'La contraseña temporal caducó. Restablece tu contraseña.');
            abort_if(Hash::check($data['password'], $user->password), 422, 'Elige una contraseña nueva diferente de la anterior.');
            $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false,
                'temporary_password_expires_at' => null, 'remember_token' => Str::random(60)])->save();
            $this->tokens->revokeAll($user);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            return response()->json(['success' => true, 'data' => ['requires_two_factor' => false,
                'user' => $user->toAuthPayload(), ...$this->tokens->issue($user, 'password-change')]]);
        });
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
