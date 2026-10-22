<?php

namespace App\Providers;

use App\Models\DispositivoIot;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ingesta IoT: se cuenta por API key si el sensor la envía y por IP si no.
        RateLimiter::for('iot', function (Request $request) {
            $porMinuto = (int) config('iot.rate_limit_per_minute', 120);
            $key = (string) $request->header(DispositivoIot::KEY_HEADER, '');

            return Limit::perMinute($porMinuto)->by(
                $key !== '' ? 'iot-key:'.hash('sha256', $key) : 'iot-ip:'.$request->ip()
            );
        });
    }
}
