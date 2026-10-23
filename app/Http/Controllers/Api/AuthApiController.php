<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessRequest;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

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
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->toAuthPayload(),
                'profile' => $this->profilePayload(
                    $user,
                    AccessRequest::where('email', $user->email)->first(),
                ),
            ],
        ], 200);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $accessRequest = AccessRequest::where('email', $user->email)->first();

        if ($user->isSuperAdmin() || ! $accessRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Este perfil no está asociado a un productor aprobado.',
            ], 403);
        }

        $data = $request->validate([
            'firstname' => ['required', 'string', 'max:40', 'regex:/^[A-Za-zÀ-ÿ\\s]{3,40}$/u'],
            'lastname' => ['required', 'string', 'max:40', 'regex:/^[A-Za-zÀ-ÿ\\s]{3,40}$/u'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('access_requests', 'email')->ignore($accessRequest?->id),
            ],
            'id_type' => ['required', Rule::in(['CC', 'TI', 'Pasaporte'])],
            'id_number' => [
                'required',
                'string',
                'regex:/^[0-9A-Za-z]{5,20}$/',
                Rule::unique('access_requests', 'id_number')->ignore($accessRequest->id),
            ],
            'country' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'city' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($user, $data, $accessRequest): void {
            $user->update([
                'name' => trim("{$data['firstname']} {$data['lastname']}"),
                'email' => $data['email'],
            ]);
            $accessRequest->update($data);
        });

        return response()->json([
            'success' => true,
            'message' => 'Datos de perfil actualizados correctamente.',
            'data' => [
                'user' => $user->fresh()->toAuthPayload(),
                'profile' => $this->profilePayload($user->fresh(), $accessRequest->fresh()),
            ],
        ], 200);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
            'remember_token' => Str::random(60),
        ])->save();
        $this->tokens->revokeAll($user);

        return response()->json([
            'success' => true,
            'message' => 'Tu contraseña fue actualizada. Inicia sesión nuevamente.',
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

    /**
     * Datos de contacto e identificación que pertenecen a la solicitud
     * aprobada del productor, no al payload genérico de sesión.
     *
     * @return array<string, string|null>
     */
    private function profilePayload(User $user, ?AccessRequest $accessRequest): array
    {
        $name = preg_split('/\\s+/', trim($user->name), 2) ?: [];

        return [
            'firstname' => $accessRequest?->firstname ?? ($name[0] ?? ''),
            'lastname' => $accessRequest?->lastname ?? ($name[1] ?? ''),
            'email' => $user->email,
            'id_type' => $accessRequest?->id_type,
            'id_number' => $accessRequest?->id_number,
            'country' => $accessRequest?->country,
            'department' => $accessRequest?->department,
            'city' => $accessRequest?->city,
        ];
    }
}
