<?php

namespace App\Services;

use App\Models\AccessRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Cookie;

class WebSession
{
    public function cookieName(): string
    {
        return app()->environment('production') ? '__Host-aggrio_refresh' : 'aggrio_refresh';
    }

    public function token(Request $r, bool $fresh = true): ?PersonalAccessToken
    {
        try {
            $raw = $r->cookie($this->cookieName());
            if (! is_string($raw) || $raw === '') {
                return null;
            }
            $token = PersonalAccessToken::findToken(Crypt::decryptString($raw));
            if (! $token || ! in_array('web:session', $token->abilities ?? [], true) || ! $token->can('token:refresh') || ($fresh && $token->expires_at?->isPast())) {
                return null;
            }

            return $token;
        } catch (\Throwable) {
            return null;
        }
    }

    public function csrf(PersonalAccessToken $token): string
    {
        $session = app(ApiTokenService::class)->sessionIdFor($token);

        return hash_hmac('sha256', 'web-csrf|'.$token->tokenable_id.'|'.$session, (string) config('app.key'));
    }

    public function authenticatedToken(Request $r): PersonalAccessToken
    {
        $token = $this->token($r);
        abort_unless($token && $token->tokenable, 401, 'Tu sesión terminó. Vuelve a iniciar sesión.');
        $user = $token->tokenable;
        if (! $user->isActive()) {
            abort(response()->json(['success' => false, 'code' => 'account_suspended', 'message' => 'Tu cuenta está suspendida. Contacta al equipo de Aggrio.'], 403));
        }
        abort_unless($user->isSuperAdmin() || AccessRequest::where('email', $user->email)->where('status', 'approved')->exists(), 403, 'Tu acceso no está aprobado.');

        return $token;
    }

    public function attach(JsonResponse $response, array $data): JsonResponse
    {
        $plain = $data['refresh_token'];
        $token = PersonalAccessToken::findToken($plain);
        abort_unless($token && in_array('web:session', $token->abilities ?? [], true), 500, 'No se pudo crear la sesión web.');
        $previous = $this->token(request(), false);
        if ($previous && $previous->id !== $token->id && $previous->tokenable) {
            app(ApiTokenService::class)->revokeSession($previous->tokenable, app(ApiTokenService::class)->sessionIdFor($previous));
        }
        $data['csrf_token'] = $this->csrf($token);
        unset($data['refresh_token'],$data['token'],$data['type']);
        $body = $response->getData(true);
        $body['data'] = $data;
        $response->setData($body);
        $response->headers->setCookie(Cookie::create($this->cookieName(), Crypt::encryptString($plain), $token->expires_at, '/', null, app()->environment('production') || request()->isSecure(), true, false, Cookie::SAMESITE_STRICT));

        return $response;
    }

    public function clear(JsonResponse $response): JsonResponse
    {
        $response->headers->setCookie(Cookie::create($this->cookieName(), ' ', 1, '/', null, app()->environment('production') || request()->isSecure(), true, false, Cookie::SAMESITE_STRICT));

        return $response;
    }
}
