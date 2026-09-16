<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    | OJO: este ajuste es global y pisa el "expires_at" de CUALQUIER token,
    | incluido el refresh token. Por eso se deja en null y la caducidad se
    | controla token por token desde App\Services\ApiTokenService (RF004).
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Expiración de los tokens de sesión (RF004)
    |--------------------------------------------------------------------------
    |
    | Minutos de vida de cada token emitido en el login. El access token es de
    | vida corta y se renueva con el refresh token desde /api/auth/refresh.
    |
    */

    'access_token_ttl' => (int) env('SANCTUM_ACCESS_TOKEN_TTL', 60),        // 1 hora
    'refresh_token_ttl' => (int) env('SANCTUM_REFRESH_TOKEN_TTL', 10080),   // 7 días

    /*
    |--------------------------------------------------------------------------
    | Doble factor (RF003)
    |--------------------------------------------------------------------------
    |
    | "challenge_ttl" son los minutos que dura el reto intermedio del login en
    | dos pasos. "window" es la tolerancia de desfase de reloj del TOTP, donde
    | cada unidad equivale a 30 segundos hacia atrás y hacia adelante.
    |
    */

    'two_factor_challenge_ttl' => (int) env('TWO_FACTOR_CHALLENGE_TTL', 5),
    'two_factor_window' => (int) env('TWO_FACTOR_WINDOW', 1),

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
