<?php
// Disposable preview account only; never connects to PostgreSQL.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => storage_path('app/private/ui-preview.sqlite'), 'database.connections.sqlite.url' => null]);
$admin = App\Models\User::where('email', 'admin@aggrio.test')->firstOrFail();
if (!$admin->two_factor_secret) {
    $admin->two_factor_secret = app(App\Services\TwoFactorService::class)->generateSecret();
    $admin->two_factor_confirmed_at = now();
    $admin->save();
}
echo app(PragmaRX\Google2FAQRCode\Google2FA::class)->getCurrentOtp($admin->two_factor_secret).PHP_EOL;
