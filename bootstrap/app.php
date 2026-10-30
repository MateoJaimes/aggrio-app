<?php

use App\Http\Middleware\ApprovedAccount;
use App\Http\Middleware\IdempotentWrite;
use App\Http\Middleware\IdentifyIotDevice;
use App\Http\Middleware\ProtectWebSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // Para que puedan Consumirla
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        // Necesarios para separar el access token del refresh token (RF004).
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            // Identifica al sensor por su X-Device-Key en la ingesta IoT (RF002).
            'iot.device' => IdentifyIotDevice::class,
            'approved.account' => ApprovedAccount::class,
            'web.session' => ProtectWebSession::class,
            'idempotent.write' => IdempotentWrite::class,
        ]);
        if (env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: array_map('trim', explode(',', env('TRUSTED_PROXIES'))));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
