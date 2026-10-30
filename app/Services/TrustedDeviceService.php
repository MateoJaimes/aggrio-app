<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class TrustedDeviceService
{
    public function cookieName(): string
    {
        return app()->environment('production') ? '__Host-aggrio_device' : 'aggrio_device';
    }

    private function fingerprint(User $user): string
    {
        return hash_hmac('sha256', $user->getRawOriginal('password').'|'.$user->two_factor_version, (string) config('app.key'));
    }

    public function current(Request $request, User $user): ?TrustedDevice
    {
        try {
            $cookie = $request->cookie($this->cookieName());
            if (! is_string($cookie) || ! $cookie) {
                return null;
            }
            $parts = explode('.', Crypt::decryptString($cookie), 2);
            if (count($parts) !== 2 || ! Str::isUuid($parts[0]) || strlen($parts[1]) !== 64) {
                return null;
            }
            [$id, $secret] = $parts;
            $device = TrustedDevice::where('user_id', $user->id)->whereKey($id)->where('expires_at', '>', now())->first();
            if (! $device || ! hash_equals($device->secret_hash, hash('sha256', $secret)) || ! hash_equals($device->credential_hash, $this->fingerprint($user))
                || ! hash_equals($device->agent_hash, hash('sha256', (string) $request->userAgent()))) {
                return null;
            }

            return $device;
        } catch (\Throwable) {
            return null;
        }
    }

    public function recognize(Request $request, User $user): ?TrustedDevice
    {
        // A caller-controlled device_name or native token must never bypass MFA.
        if (! $request->routeIs('web.auth.login') || ! $user->hasConfirmedTwoFactor()) {
            return null;
        }

        return $this->current($request, $user);
    }

    public function remember(Request $request, User $user, JsonResponse $response, ?TrustedDevice $device = null): JsonResponse
    {
        $device ??= $this->current($request, $user);
        $fresh = ! $device;
        $device ??= new TrustedDevice;
        $secret = Str::random(64);
        $agent = (string) $request->userAgent();
        $device->fill(['user_id' => $user->id, 'name' => $device->name ?? $request->input('trusted_device_name') ?: $this->deviceLabel($agent),
            'secret_hash' => hash('sha256', $secret), 'credential_hash' => $this->fingerprint($user), 'agent_hash' => hash('sha256', $agent),
            'user_agent' => mb_substr($agent, 0, 500), 'last_used_at' => now(),
            'expires_at' => $device->expires_at ?? now()->addDays(30)]);
        $device->save();
        if ($fresh) {
            $this->event($request, $user, 'browser_remembered', $device->name);
        }
        $response->headers->setCookie(Cookie::create($this->cookieName(), Crypt::encryptString($device->id.'.'.$secret), $device->expires_at, '/', null,
            app()->environment('production') || $request->isSecure(), true, false, Cookie::SAMESITE_STRICT));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function deviceLabel(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge', str_contains($agent, 'Firefox/') => 'Firefox', str_contains($agent, 'Chrome/') => 'Chrome o Brave', str_contains($agent, 'Safari/') => 'Safari', default => 'Navegador'
        };
        $os = match (true) {
            str_contains($agent, 'Android') => 'Android', str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS', str_contains($agent, 'Windows') => 'Windows', str_contains($agent, 'Macintosh') => 'macOS', str_contains($agent, 'Linux') => 'Linux', default => 'este equipo'
        };

        return $browser.' en '.$os;
    }

    public function event(Request $request, User $user, string $action, ?string $device = null): void
    {
        DB::table('security_events')->insert(['user_id' => $user->id, 'action' => $action, 'device_name' => $device, 'ip' => $request->ip(), 'created_at' => now()]);
    }
}
