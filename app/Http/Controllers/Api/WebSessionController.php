<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ApiTokenService;
use App\Services\WebSession;
use Illuminate\Http\Request;

class WebSessionController extends Controller
{
    public function csrf(Request $r, WebSession $sessions)
    {
        $token = $sessions->authenticatedToken($r);

        return response()->json(['success' => true, 'data' => ['csrf_token' => $sessions->csrf($token)]]);
    }

    public function refresh(Request $r, WebSession $sessions, ApiTokenService $tokens)
    {
        $token = $sessions->authenticatedToken($r);

        return response()->json(['success' => true, 'data' => ['user' => $token->tokenable->toAuthPayload(), ...$tokens->rotate($token->tokenable, $token)]]);
    }

    public function logout(Request $r, WebSession $sessions, ApiTokenService $tokens)
    {
        $token = $sessions->token($r, false);
        if ($token && $token->tokenable) {
            if ($r->routeIs('web.auth.logout-all')) {
                $tokens->revokeAll($token->tokenable);
            } else {
                $tokens->revokeSession($token->tokenable, $tokens->sessionIdFor($token));
            }
        }

        return $sessions->clear(response()->json(['success' => true, 'message' => 'Sesión cerrada.']));
    }
}
