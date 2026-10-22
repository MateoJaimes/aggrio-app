<?php

namespace App\Http\Middleware;

use App\Models\DispositivoIot;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifica al sensor que envía una lectura por su cabecera X-Device-Key. RF002.
 *
 * - Key válida y dispositivo activo: deja el dispositivo en el request
 *   (atributo "dispositivo_iot") y registra su última conexión.
 * - Key inválida o de un dispositivo desactivado: 401, siempre.
 * - Sin key: 401 si config('iot.require_device_key') está activo; si no, deja
 *   pasar la lectura y lo anota en el log para saber qué sensores faltan migrar.
 */
class IdentifyIotDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainKey = (string) $request->header(DispositivoIot::KEY_HEADER, '');

        if ($plainKey !== '') {
            $dispositivo = DispositivoIot::buscarActivoPorKey($plainKey);

            if (! $dispositivo) {
                return $this->rechazar('API key de dispositivo inválida o desactivada.');
            }

            $dispositivo->forceFill(['ultima_conexion' => now()])->saveQuietly();
            $request->attributes->set('dispositivo_iot', $dispositivo);

            return $next($request);
        }

        if (config('iot.require_device_key')) {
            return $this->rechazar('Falta la cabecera '.DispositivoIot::KEY_HEADER.'.');
        }

        Log::warning('Lectura IoT recibida sin API key de dispositivo.', [
            'ip' => $request->ip(),
            'lote_id' => $request->input('lote_id'),
            'mac_dispositivo' => $request->input('mac_dispositivo'),
        ]);

        return $next($request);
    }

    private function rechazar(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 401);
    }
}
