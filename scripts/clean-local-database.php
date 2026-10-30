<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('local') || DB::connection()->getDriverName() !== 'pgsql') {
    throw new RuntimeException('La limpieza está limitada a PostgreSQL en entorno local.');
}
$config = DB::connection()->getConfig();
if (! in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('El destino debe ser local.');
}
$admins = DB::table('users')->where('role', 'superadmin')->get();
if ($admins->count() !== 1) {
    throw new RuntimeException('Se necesita exactamente un superadmin para identificar sin ambigüedad la cuenta que se conserva.');
}
$admin = (array) $admins->first();
$counts = [];
foreach (DB::select('SELECT tablename FROM pg_tables WHERE schemaname = current_schema() ORDER BY tablename') as $row) {
    if ($row->tablename !== 'migrations') {
        $counts[$row->tablename] = DB::table($row->tablename)->count();
    }
}
echo 'Superadmin que se conserva: ID '.$admin['id']."\n";
echo 'Recuentos antes de limpiar: '.json_encode($counts)."\n";
if (! in_array('--execute', $argv, true)) {
    echo "Solo inspección. La ejecución requiere --execute.\n";
    exit(0);
}

$binary = 'C:/laragon/bin/postgresql/postgresql/bin/pg_dump.exe';
if (! is_file($binary)) {
    throw new RuntimeException('No se encontró pg_dump. No se limpió la base.');
}
$folder = storage_path('app/private/backups');
if (! is_dir($folder)) {
    mkdir($folder, 0700, true);
}
$stamp = date('Ymd-His');
$backup = $folder.'/before-clean-'.$stamp.'.dump';
$process = proc_open([$binary, '--format=custom', '--no-owner', '--no-acl', '--host='.$config['host'], '--port='.$config['port'], '--username='.$config['username'], '--dbname='.$config['database'], '--file='.$backup],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), array_merge(getenv(), ['PGPASSWORD' => $config['password']]));
if (! is_resource($process)) {
    throw new RuntimeException('No se pudo iniciar el respaldo.');
}
fclose($pipes[0]);
stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($process) !== 0 || ! is_file($backup) || filesize($backup) < 100) {
    throw new RuntimeException('Falló el respaldo. No se limpió la base. '.$error);
}
echo 'Respaldo privado: '.basename($backup)."\n";

$status = Artisan::call('migrate', ['--path' => 'database/migrations/2026_10_28_000002_admin_security_devices.php', '--force' => true]);
echo Artisan::output();
if ($status !== 0) {
    throw new RuntimeException('No se pudo aplicar el esquema de seguridad. Respaldo conservado.');
}

DB::transaction(function () use ($admin) {
    DB::statement('LOCK TABLE users IN ACCESS EXCLUSIVE MODE');
    $names = collect(DB::select('SELECT tablename FROM pg_tables WHERE schemaname = current_schema()'))->pluck('tablename')->reject(fn ($name) => in_array($name, ['users', 'migrations'], true))->values();
    $quoted = $names->map(fn ($name) => '"'.str_replace('"', '""', $name).'"');
    // No CASCADE: a dependency reaching the protected users table aborts the
    // transaction rather than silently truncating the superadmin account.
    if ($quoted->isNotEmpty()) {
        DB::statement('TRUNCATE TABLE '.$quoted->implode(', ').' RESTART IDENTITY');
    }
    DB::table('users')->where('id', '!=', $admin['id'])->delete();
    $remaining = (array) DB::table('users')->where('id', $admin['id'])->first();
    if (array_intersect_key($remaining, $admin) !== $admin || DB::table('users')->count() !== 1) {
        throw new RuntimeException('La cuenta protegida cambió; se revierte la limpieza.');
    }
    foreach ($names as $name) {
        if (DB::table($name)->count() !== 0) {
            throw new RuntimeException('La tabla '.$name.' no quedó vacía. Se revierte la limpieza.');
        }
    }
    $sequence = DB::selectOne("SELECT pg_get_serial_sequence('users', 'id') AS name")->name;
    if ($sequence) {
        DB::select('SELECT setval(?::regclass, ?::bigint, true)', [$sequence, $admin['id']]);
    }
});
file_put_contents($folder.'/clean-report-'.$stamp.'.json', json_encode(['backup' => basename($backup), 'preserved_admin_id' => $admin['id'], 'counts_before' => $counts, 'users_after' => 1, 'business_tables_after' => 0], JSON_PRETTY_PRINT));
echo "Limpieza completada: un superadmin intacto; tablas operativas, sesiones y otros usuarios vacíos.\n";
