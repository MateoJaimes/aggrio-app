<?php

use App\Http\Controllers\Api\ProductionController;
use App\Models\User;
use App\Services\ProductionReport;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

// Read-only smoke check against the configured local database. Never creates
// users, sessions, financial records or evidences.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$checked = 0;
foreach (User::whereHas('fincas')->get() as $user) {
    $request = Request::create('/api/produccion/inicio');
    $request->setUserResolver(fn () => $user);
    $result = app(ProductionController::class)->bootstrap($request)->getData(true)['data'];
    if (! $user->isSuperAdmin()) {
        foreach ($result['fincas'] as $farm) {
            if ($farm['user_id'] !== $user->id) {
                throw new RuntimeException('Propiedad inconsistente.');
            }
        }
    }
    $report = app(ProductionReport::class)->build($request);
    if (round($report['ventas'] - $report['costos_produccion'], 2) !== $report['resultado']) {
        throw new RuntimeException('Resultado inconsistente.');
    }
    foreach (array_slice($result['records'], 0, 1) as $record) {
        $history = app(ProductionController::class)->history($request, 'registro', $record['id'])->getData(true)['data'];
        if (! str_starts_with($history['qr'], 'data:image/svg+xml;base64,')) {
            throw new RuntimeException('QR no válido.');
        }
    }
    $checked++;
}
echo "Comprobación de solo lectura completada para {$checked} titulares: inicio, propiedad, reportes y QR.\n";
