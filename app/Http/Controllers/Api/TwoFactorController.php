<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Doble factor TOTP obligatorio para el Superadmin. RF003.
 *
 * Las rutas /login/2fa/* son públicas pero sólo se pueden usar presentando un
 * challenge_token válido, que el login entrega únicamente tras validar la
 * contraseña. Ese token no autentica contra el resto de la API.
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly ApiTokenService $tokens,
    ) {}

    /**
     * Alta del TOTP: devuelve el QR para escanear. Sólo disponible mientras el
     * usuario no haya confirmado un segundo factor.
     */
    public function setup(Request $request): JsonResponse
    {
        return DB::transaction(fn () => $this->performSetup($request), 3);
    }

    private function performSetup(Request $request): JsonResponse
    {
        $request->validate(['challenge_token' => 'required|string']);

        $challenge = TwoFactorChallenge::findValid($request->challenge_token, true);

        if (! $challenge || (($challenge->device_name === 'web-panel') !== $request->routeIs('web.auth.*'))) {
            return $this->invalidChallenge();
        }

        $user = User::whereKey($challenge->user_id)->lockForUpdate()->firstOrFail();

        if ($user->hasConfirmedTwoFactor()) {
            return response()->json([
                'success' => false,
                'message' => 'Ya tienes configurada la autenticación en dos pasos.',
            ], 409);
        }

        // Secreto provisional: queda guardado sin confirmar hasta que el usuario
        // demuestre, con un código válido, que lo registró en su app.
        $secret = $this->twoFactor->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Escanea el código QR con tu aplicación autenticadora.',
            'data' => [
                'qr_code' => $this->twoFactor->qrCodeDataUri($user, $secret),
                'otpauth_uri' => $this->twoFactor->otpauthUri($user, $secret),
                'secret' => $secret, // para introducirlo a mano si el QR falla
            ],
        ], 200);
    }

    /**
     * Confirma el alta del TOTP y, si el código es correcto, completa el login
     * entregando los tokens y los códigos de recuperación (se muestran una vez).
     */
    public function confirm(Request $request): JsonResponse
    {
        return DB::transaction(fn () => $this->performConfirm($request), 3);
    }

    private function performConfirm(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => 'required|string',
            'code' => 'required|string',
            'remember_device' => 'nullable|boolean', 'trusted_device_name' => 'nullable|string|max:100',
        ]);

        $challenge = TwoFactorChallenge::findValid($request->challenge_token, true);

        if (! $challenge || (($challenge->device_name === 'web-panel') !== $request->routeIs('web.auth.*'))) {
            return $this->invalidChallenge();
        }

        $user = User::whereKey($challenge->user_id)->lockForUpdate()->firstOrFail();
        if ($user->hasConfirmedTwoFactor()) {
            return response()->json(['success' => false, 'message' => 'El segundo factor ya está confirmado. Inicia sesión de nuevo.'], 409);
        }

        if ($user->two_factor_secret === null) {
            return response()->json([
                'success' => false,
                'message' => 'Primero debes generar tu código QR.',
            ], 409);
        }

        if (! $this->twoFactor->verifyCode($user, $request->code)) {
            return $this->rejectCode($challenge);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $challenge->setRelation('user', $user);

        // Se guardan hasheados; en claro sólo viajan en esta respuesta.
        $recoveryCodes = $this->twoFactor->issueRecoveryCodes($user);

        return $this->completeLogin($challenge, [
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Login (paso 2): valida el código de 6 dígitos o un código de recuperación
     * y entrega el par de tokens.
     */
    public function verify(Request $request): JsonResponse
    {
        return DB::transaction(fn () => $this->performVerify($request), 3);
    }

    private function performVerify(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => 'required|string',
            'code' => 'required_without:recovery_code|nullable|string',
            'recovery_code' => 'required_without:code|nullable|string',
            'remember_device' => 'nullable|boolean', 'trusted_device_name' => 'nullable|string|max:100',
        ]);

        $challenge = TwoFactorChallenge::findValid($request->challenge_token, true);

        if (! $challenge || (($challenge->device_name === 'web-panel') !== $request->routeIs('web.auth.*'))) {
            return $this->invalidChallenge();
        }

        $user = User::whereKey($challenge->user_id)->lockForUpdate()->firstOrFail();
        $challenge->setRelation('user', $user);

        if (! $user->hasConfirmedTwoFactor()) {
            return response()->json([
                'success' => false,
                'message' => 'Aún no has configurado la autenticación en dos pasos.',
            ], 409);
        }

        $verified = $request->filled('recovery_code')
            ? $this->twoFactor->consumeRecoveryCode($user, $request->recovery_code)
            : $this->twoFactor->verifyCode($user, (string) $request->code);

        if (! $verified) {
            return $this->rejectCode($challenge);
        }

        return $this->completeLogin($challenge);
    }

    /**
     * Estado del 2FA del usuario autenticado.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'required' => $user->requiresTwoFactor(),
                'enabled' => $user->hasConfirmedTwoFactor(),
                'confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
                'recovery_codes_remaining' => count($user->two_factor_recovery_codes ?? []),
            ],
        ], 200);
    }

    /**
     * Regenera los códigos de recuperación. Exige la contraseña actual porque
     * invalida los códigos anteriores.
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        return app(SecuritySettingsController::class)->recovery($request);
    }

    /**
     * Consume el reto y entrega el par de tokens (RF004).
     *
     * @param  array<string, mixed>  $extra
     */
    private function completeLogin(TwoFactorChallenge $challenge, array $extra = []): JsonResponse
    {
        $user = $challenge->user;
        $deviceName = $challenge->device_name;

        // El reto es de un solo uso.
        $challenge->delete();

        $response = response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'requires_two_factor' => false,
                'user' => $user->toAuthPayload(),
                ...$this->tokens->issue($user, $deviceName),
                ...$extra,
            ],
        ], 200);
        if (request()->routeIs('web.auth.*') && request()->boolean('remember_device')) {
            return app(TrustedDeviceService::class)->remember(request(), $user, $response);
        }

        return $response;
    }

    private function rejectCode(TwoFactorChallenge $challenge): JsonResponse
    {
        $challenge->registerFailedAttempt();

        $remaining = max(0, TwoFactorChallenge::MAX_ATTEMPTS - $challenge->attempts);

        if ($remaining === 0) {
            $challenge->delete();

            return response()->json([
                'success' => false,
                'message' => 'Superaste el número de intentos. Vuelve a iniciar sesión.',
            ], 429);
        }

        return response()->json([
            'success' => false,
            'message' => 'El código no es válido.',
            'data' => ['attempts_remaining' => $remaining],
        ], 422);
    }

    private function invalidChallenge(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'La verificación expiró o no es válida. Vuelve a iniciar sesión.',
        ], 401);
    }
}
