<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Local, additive migration with a private pre-change backup. No credentials
// appear in command arguments or output. Run from the repository root.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (app()->environment('production')) {
    throw new RuntimeException('Este ayudante está limitado al entorno local.');
}
if (DB::connection()->getDriverName() !== 'pgsql') {
    throw new RuntimeException('Este ayudante requiere PostgreSQL local.');
}
if (Schema::hasTable('production_records')) {
    echo "La estructura de producción ya existe. No se aplicaron cambios.\n";
    exit(0);
}
$config = DB::connection()->getConfig();
if (! in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('El destino debe ser PostgreSQL local.');
}
$binary = $argv[1] ?? 'C:/laragon/bin/postgresql/postgresql/bin/pg_dump.exe';
if (! is_file($binary)) {
    throw new RuntimeException('No se encontró pg_dump. Indica su ruta como primer argumento.');
}
$folder = storage_path('app/private/backups');
if (! is_dir($folder)) {
    mkdir($folder, 0700, true);
}
$backup = $folder.'/before-production-'.date('Ymd-His').'.dump';
$before = [];
foreach (['fincas', 'lotes', 'actividades', 'archivos_multimedia', 'users'] as $table) {
    $before[$table] = DB::table($table)->count();
}
$command = [$binary, '--format=custom', '--no-owner', '--no-acl', '--host='.$config['host'], '--port='.$config['port'], '--username='.$config['username'], '--dbname='.$config['database'], '--file='.$backup];
$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), array_merge(getenv(), ['PGPASSWORD' => $config['password']]));
if (! is_resource($process)) {
    throw new RuntimeException('No se pudo iniciar el respaldo.');
}
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($process) !== 0 || ! is_file($backup) || filesize($backup) < 100) {
    throw new RuntimeException('El respaldo falló. No se ejecutó la migración. '.$stderr);
}
echo 'Respaldo privado creado: '.basename($backup)."\n";
$result = Artisan::call('migrate', ['--path' => 'database/migrations/2026_10_28_000001_production_management.php', '--force' => true]);
echo Artisan::output();
if ($result !== 0) {
    exit($result);
}
foreach ($before as $table => $count) {
    if (DB::table($table)->count() !== $count) {
        throw new RuntimeException('Revisar recuento de '.$table.'; respaldo conservado.');
    }
}
echo 'Recuentos conservados: '.json_encode($before)."\n";
echo 'Cultivos históricos importados: '.DB::table('production_records')->where('tipo', 'cultivos')->count()."\n";
