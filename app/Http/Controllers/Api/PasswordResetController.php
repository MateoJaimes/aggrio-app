<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Recuperación de contraseña por correo (RF005).
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    /**
     * Envía el enlace de recuperación.
     *
     * Responde siempre lo mismo exista o no el correo, para no revelar qué
     * direcciones están registradas.
     */
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $genericResponse = response()->json([
            'success' => true,
            'message' => 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.',
        ], 200);

        $throttleKey = 'password-forgot:'.Str::lower($request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'Demasiadas solicitudes. Intenta de nuevo en '
                    .RateLimiter::availableIn($throttleKey).' segundos.',
            ], 429);
        }

        RateLimiter::hit($throttleKey, 900);

        Password::sendResetLink($request->only('email'));

        return $genericResponse;
    }

    /**
     * Aplica la nueva contraseña usando el token recibido por correo.
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Cambiar la contraseña cierra todas las sesiones abiertas.
                $this->tokens->revokeAll($user);
                $user->twoFactorChallenges()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PasswordReset) {
            return response()->json([
                'success' => false,
                'message' => match ($status) {
                    Password::InvalidToken => 'El enlace de recuperación es inválido o ya caducó.',
                    Password::InvalidUser => 'No encontramos una cuenta con ese correo.',
                    Password::Throttled => 'Espera un momento antes de volver a intentarlo.',
                    default => 'No fue posible restablecer la contraseña.',
                },
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.',
        ], 200);
    }
}
