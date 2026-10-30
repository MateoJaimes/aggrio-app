<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IdempotentWrite
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('Idempotency-Key');
        if (! $key || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }
        abort_if(strlen($key) > 100, 422, 'Identificador de operación inválido.');
        $fingerprint = $request->except('password', 'password_confirmation');
        foreach ($request->allFiles() as $name => $file) {
            if (! is_array($file)) {
                $fingerprint[$name] = hash_file('sha256', $file->getRealPath());
            }
        }
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.json_encode($fingerprint));

        return DB::transaction(function () use ($request, $next, $key, $hash) {
            $where = ['user_id' => $request->user()->id, 'operation_key' => hash('sha256', $key)];
            DB::table('idempotent_operations')->insertOrIgnore($where + ['request_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $entry = DB::table('idempotent_operations')->where($where)->lockForUpdate()->first();
            abort_unless(hash_equals($entry->request_hash, $hash), 409, 'La operación ya fue utilizada con otros datos.');
            if ($entry->response !== null) {
                return response($entry->response, $entry->status)->header('Content-Type', 'application/json');
            }
            $response = $next($request);
            if ($response->isSuccessful()) {
                DB::table('idempotent_operations')->where('id', $entry->id)->update(['response' => $response->getContent(), 'status' => $response->getStatusCode(), 'updated_at' => now()]);
            } else {
                DB::table('idempotent_operations')->where('id', $entry->id)->delete();
            }

            return $response;
        }, 3);
    }
}
