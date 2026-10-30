<?php

namespace App\Services;

use App\Models\AuthenticatorDevice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;

/**
 * Doble factor TOTP (Google Authenticator / Authy / 1Password). RF003.
 *
 * El secreto y los códigos de recuperación pertenecen a la cuenta del usuario,
 * de modo que el panel Angular puede completar el alta sin exponerlos de nuevo.
 */
class TwoFactorService
{
    /**
     * Cantidad de códigos de recuperación que se entregan al activar el 2FA.
     */
    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Genera un secreto TOTP nuevo (Base32).
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * URI otpauth:// para quien prefiera escribir el código a mano.
     */
    public function otpauthUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl($this->issuer(), $user->email, $secret);
    }

    /**
     * QR listo para pintar en un <img src="..."> del frontend (SVG en data URI).
     */
    public function qrCodeDataUri(User $user, string $secret, ?string $label = null): string
    {
        return $this->google2fa->getQRCodeInline($this->issuer(), $user->email.($label ? ' · '.$label : ''), $secret);
    }

    /**
     * Valida un código de 6 dígitos contra el secreto del usuario.
     *
     * La ventana tolera desfases de reloj entre el servidor y el teléfono:
     * 1 = se aceptan el periodo anterior, el actual y el siguiente (±30 s).
     */
    public function verifyCode(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        if ($this->verifySecret($user->two_factor_secret, $code)) {
            return true;
        }
        foreach (AuthenticatorDevice::where('user_id', $user->id)->whereNotNull('confirmed_at')->get() as $device) {
            if ($this->verifySecret($device->secret, $code)) {
                return true;
            }
        }

        return false;
    }

    public function verifySecret(string $secret, string $code): bool
    {
        return preg_match('/^[0-9]{6}$/D', $code) === 1 && (bool) $this->google2fa->verifyKey($secret, $code, (int) config('sanctum.two_factor_window', 1));
    }

    /**
     * Genera un juego nuevo de códigos, guarda su hash en el usuario y devuelve
     * los códigos en claro, que es la única vez que pueden mostrarse.
     *
     * Se guardan hasheados (no cifrados): un código de recuperación no necesita
     * poder leerse, sólo compararse.
     *
     * @return list<string>
     */
    public function issueRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(
                static fn (string $code): string => Hash::make($code),
                $codes,
            ),
        ])->save();

        return $codes;
    }

    /**
     * Formato fácil de guardar y de introducir manualmente.
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn (): string => Str::random(10).'-'.Str::random(10))
            ->all();
    }

    /**
     * Consume un código de recuperación: si coincide, lo borra de la lista
     * para que no pueda reutilizarse.
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $stored = $user->two_factor_recovery_codes ?? [];

        $remaining = [];
        $isValid = false;

        foreach ($stored as $hashedCode) {
            if (! $isValid && Hash::check($code, $hashedCode)) {
                $isValid = true;

                continue;
            }

            $remaining[] = $hashedCode;
        }

        if (! $isValid) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();

        return true;
    }

    /**
     * Nombre que aparece en la app autenticadora.
     */
    private function issuer(): string
    {
        return (string) config('app.name', 'Aggrio');
    }
}
