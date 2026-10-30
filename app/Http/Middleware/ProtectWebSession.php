<?php

namespace App\Http\Middleware;

use App\Services\WebSession;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProtectWebSession
{
    public function handle(Request $r, Closure $next)
    {
        abort_if(app()->environment('production') && ! $r->isSecure(), 400, 'La sesión web de producción requiere HTTPS.');
        $source = $r->header('Origin');
        if (! $source && $r->header('Referer')) {
            $parts = parse_url($r->header('Referer'));
            $source = isset($parts['scheme'],$parts['host']) ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '') : null;
        }
        $allowed = config('web_session.origins');
        if (app()->environment('local')) {
            $allowed = array_merge($allowed, ['http://localhost:4200', 'http://127.0.0.1:4200']);
        }
        abort_unless(is_string($source) && in_array($source, $allowed, true) && $r->header('X-Aggrio-Web') === '1', 403, 'El origen de la solicitud web no está permitido.');
        abort_if(! in_array($r->method(), ['GET', 'HEAD']) && ! $r->isJson(), 415, 'Envía la solicitud como JSON.');
        $sessions = app(WebSession::class);
        if ($r->routeIs('web.auth.refresh', 'web.auth.password', 'web.auth.logout', 'web.auth.logout-all')) {
            $cookie = $sessions->token($r, false);
            abort_if($cookie && ! hash_equals($sessions->csrf($cookie), (string) $r->header('X-CSRF-Token')), 419, 'La protección de la sesión caducó. Vuelve a iniciar sesión.');
            abort_if($r->routeIs('web.auth.password') && ! $cookie, 401, 'Tu sesión terminó. Vuelve a iniciar sesión.');
        }
        if ($r->routeIs('web.auth.login')) {
            $r->merge(['device_name' => 'web-panel']);
        }
        $response = $next($r);
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true)['data'] ?? [];
            if ($response->isSuccessful() && isset($data['refresh_token'])) {
                $response = $sessions->attach($response, $data);
            }
            if ($response->getStatusCode() === 401 || ($response->getData(true)['code'] ?? '') === 'account_suspended') {
                $response = $sessions->clear($response);
            }
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma','no-cache');
        }

        return $response;
    }
}
