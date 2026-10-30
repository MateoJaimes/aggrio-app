<?php

// Dedicated loopback preview with a disposable SQLite database and separate
// cookies. Never points at the user's PostgreSQL database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => storage_path('app/private/ui-preview.sqlite'), 'database.connections.sqlite.url' => null,
    'web_session.origins' => ['http://127.0.0.1:4201'], 'app.frontend_url' => 'http://127.0.0.1:4201', 'mail.default' => 'array', 'invitations.console_enabled' => false]);
$app->bind(App\Services\WebSession::class, fn () => new class extends App\Services\WebSession { public function cookieName(): string { return 'aggrio_preview_refresh'; } });
$app->bind(App\Services\TrustedDeviceService::class, fn () => new class extends App\Services\TrustedDeviceService { public function cookieName(): string { return 'aggrio_preview_device'; } });
$app->handleRequest(Illuminate\Http\Request::capture());
