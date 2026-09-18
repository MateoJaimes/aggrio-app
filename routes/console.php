<?php

use App\Models\TwoFactorChallenge;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Limpieza de credenciales caducadas (RF003 / RF004).
// Requiere que el scheduler esté corriendo: php artisan schedule:work
Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::call(fn () => TwoFactorChallenge::pruneStale())
    ->hourly()
    ->name('prune-two-factor-challenges');
