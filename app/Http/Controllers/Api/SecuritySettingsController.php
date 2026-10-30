<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthenticatorDevice;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\ApiTokenService;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;

class SecuritySettingsController extends Controller
{
    public function __construct(private TwoFactorService $factors, private TrustedDeviceService $devices, private ApiTokenService $tokens) {}

    private function admin(Request $r): User
    {
        abort_unless($r->user()->isSuperAdmin(), 403);

        return $r->user();
    }

    public function show(Request $r)
    {
        $user = $this->admin($r);
        $current = $this->devices->current($r, $user);
        $authenticators = AuthenticatorDevice::where('user_id', $user->id)->whereNotNull('confirmed_at')->orderBy('created_at')->get(['id', 'name', 'confirmed_at']);
        if ($user->hasConfirmedTwoFactor()) {
            $authenticators->prepend(['id' => 'principal', 'name' => $user->two_factor_label, 'confirmed_at' => $user->two_factor_confirmed_at]);
        }
        $trusted = TrustedDevice::where('user_id', $user->id)->where('expires_at', '>', now())->get()->map(fn ($d) => [
            'id' => $d->id, 'name' => $d->name, 'last_used_at' => $d->last_used_at, 'expires_at' => $d->expires_at, 'is_current' => $current?->id === $d->id]);

        return $this->ok(['authenticators' => $authenticators, 'trusted_devices' => $trusted, 'remember_days' => 30,
            'recovery_codes_remaining' => count($user->two_factor_recovery_codes ?? []),
            'events' => DB::table('security_events')->where('user_id', $user->id)->orderByDesc('id')->limit(30)->get()->map(function ($e) {
                $e->created_at = Carbon::parse($e->created_at, 'UTC')->toIso8601String();

                return $e;
            })]);
    }

    private function authorizeChange(Request $r, User $user): void
    {
        $r->validate(['password' => 'required|string|max:255', 'code' => 'nullable|string|max:10', 'recovery_code' => 'nullable|string|max:30']);
        $key = 'security-settings:'.$user->id.'|'.$r->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Demasiados intentos. Espera cinco minutos.');
        $validPassword = Hash::check($r->password, $user->password);
        $validFactor = $validPassword && $user->hasConfirmedTwoFactor() && ($r->filled('recovery_code')
            ? $this->factors->consumeRecoveryCode($user, $r->recovery_code) : $this->factors->verifyCode($user, (string) $r->code));
        if (! $validFactor) {
            RateLimiter::hit($key, 300);
            abort(422, 'Verifica la contraseña y el código de tu aplicación o de recuperación.');
        }
        RateLimiter::clear($key);
    }

    private function binding(Request $r): string
    {
        $token = $r->user()->currentAccessToken();

        return hash('sha256', $token instanceof PersonalAccessToken ? $this->tokens->sessionIdFor($token) : (string) $r->bearerToken());
    }

    public function start(Request $r)
    {
        $user = $this->admin($r);
        $r->validate(['name' => 'required|string|max:100', 'mode' => 'required|in:add,replace']);

        return DB::transaction(function () use ($r, $user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->authorizeChange($r, $locked);
            abort_if(AuthenticatorDevice::where('user_id', $user->id)->whereNotNull('confirmed_at')->count() >= 9 && $r->mode === 'add', 422, 'Puedes registrar hasta diez aplicaciones autenticadoras.');
            AuthenticatorDevice::where('user_id', $user->id)->whereNull('confirmed_at')->delete();
            $secret = $this->factors->generateSecret();
            $device = AuthenticatorDevice::create(['user_id' => $user->id, 'name' => $r->name, 'mode' => $r->mode, 'secret' => $secret,
                'session_hash' => $this->binding($r), 'security_version' => $locked->two_factor_version, 'expires_at' => now()->addMinutes(10)]);

            return $this->ok(['id' => $device->id, 'name' => $device->name, 'mode' => $device->mode, 'expires_at' => $device->expires_at,
                'qr_code' => $this->factors->qrCodeDataUri($user, $secret, $device->name), 'secret' => $secret]);
        });
    }

    public function confirm(Request $r, string $id)
    {
        $user = $this->admin($r);
        $r->validate(['code' => 'required|string|regex:/^[0-9]{6}$/']);

        return DB::transaction(function () use ($r, $user, $id) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $device = AuthenticatorDevice::where('user_id', $user->id)->whereKey($id)->whereNull('confirmed_at')->lockForUpdate()->firstOrFail();
            abort_unless($device->expires_at?->isFuture() && $device->security_version === (int) $locked->two_factor_version && hash_equals($device->session_hash, $this->binding($r)), 409, 'El registro expiró o la sesión cambió. Inicia de nuevo.');
            if (! $this->factors->verifySecret($device->secret, $r->code)) {
                $device->attempts++;
                if ($device->attempts >= 5) {
                    $device->delete();
                } else {
                    $device->save();
                }

                // Return a response so the failed-attempt counter commits.
                return response()->json(['success' => false, 'message' => 'Código incorrecto de la nueva aplicación.'], $device->attempts >= 5 ? 429 : 422);
            }
            if ($device->mode === 'replace') {
                $locked->forceFill(['two_factor_secret' => $device->secret, 'two_factor_confirmed_at' => now(), 'two_factor_label' => $device->name])->save();
                $device->delete();
            } else {
                $device->update(['confirmed_at' => now(), 'expires_at' => null]);
            }
            $this->changed($r, $locked, $device->mode === 'replace' ? 'authenticator_replaced' : 'authenticator_added', $device->name);

            return $this->ok(['confirmed' => true]);
        });
    }

    public function remove(Request $r, string $id)
    {
        $user = $this->admin($r);

        return DB::transaction(function () use ($r, $user, $id) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->authorizeChange($r, $locked);
            if ($id === 'principal') {
                $replacement = AuthenticatorDevice::where('user_id', $user->id)->whereNotNull('confirmed_at')->oldest()->first();
                abort_unless($replacement, 422, 'Debes conservar al menos una aplicación autenticadora. Añade y confirma otra primero.');
                $label = $locked->two_factor_label;
                $locked->forceFill(['two_factor_secret' => $replacement->secret, 'two_factor_label' => $replacement->name, 'two_factor_confirmed_at' => $replacement->confirmed_at])->save();
                $replacement->delete();
            } else {
                $device = AuthenticatorDevice::where('user_id', $user->id)->whereKey($id)->whereNotNull('confirmed_at')->firstOrFail();
                $label = $device->name;
                $device->delete();
            }
            $this->changed($r, $locked, 'authenticator_removed', $label);

            return $this->ok(null);
        });
    }

    public function forget(Request $r, string $id)
    {
        $user = $this->admin($r);
        $query = TrustedDevice::where('user_id', $user->id);
        if ($id !== 'all') {
            $query->whereKey($id)->firstOrFail();
        }
        $query->delete();
        $this->devices->event($r, $user, $id === 'all' ? 'all_browsers_forgotten' : 'browser_forgotten');

        return $this->ok(null);
    }

    public function recovery(Request $r)
    {
        $user = $this->admin($r);

        return DB::transaction(function () use ($r, $user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->authorizeChange($r, $locked);
            $codes = $this->factors->issueRecoveryCodes($locked);
            $this->devices->event($r, $locked, 'recovery_codes_regenerated');

            return $this->ok(['recovery_codes' => $codes]);
        });
    }

    private function changed(Request $r, User $user, string $action, string $label): void
    {
        $user->forceFill(['two_factor_version' => $user->two_factor_version + 1])->save();
        TrustedDevice::where('user_id', $user->id)->delete();
        $user->twoFactorChallenges()->delete();
        AuthenticatorDevice::where('user_id', $user->id)->whereNull('confirmed_at')->delete();
        $token = $r->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $session = $this->tokens->sessionIdFor($token);
            $user->tokens()->where('name', 'not like', '%::'.$session.'::%')->delete();
        }
        $this->devices->event($r, $user, $action, $label);
    }

    private function ok(mixed $data)
    {
        return response()->json(['success' => true, 'data' => $data])->header('Cache-Control', 'private, no-store');
    }
}
