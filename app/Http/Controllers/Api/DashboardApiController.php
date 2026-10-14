<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Finca;
use App\Models\LecturaIot;
use App\Models\Lote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        if (! $request->user()?->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para consultar el dashboard global.'], 403);
        }

        $databaseTimezone = config('app.timezone');
        $databaseTimezoneLabel = $databaseTimezone;
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $databaseClock = DB::selectOne('SELECT CURRENT_TIMESTAMP AS database_now, UTC_TIMESTAMP() AS utc_now');
            $databaseWallTime = Carbon::parse($databaseClock->database_now, 'UTC');
            $utcWallTime = Carbon::parse($databaseClock->utc_now, 'UTC');
            $offsetSeconds = $databaseWallTime->getTimestamp() - $utcWallTime->getTimestamp();
            $offsetMinutes = (int) ($offsetSeconds / 60);
            $offset = sprintf('%s%02d:%02d', $offsetMinutes < 0 ? '-' : '+', intdiv(abs($offsetMinutes), 60), abs($offsetMinutes) % 60);
            $databaseTimezone = $offset;
            $databaseTimezoneLabel = $offsetMinutes === 0 ? 'UTC' : "UTC{$offset}";
            $now = Carbon::parse($databaseClock->database_now, $databaseTimezone);
        } else {
            $now = Carbon::now($databaseTimezone);
        }
        $databaseTimezoneOffset = $now->format('O');

        $sevenDaysStart = $now->copy()->startOfDay()->subDays(6);
        $monthsStart = $now->copy()->startOfMonth()->subMonths(11);

        // Each aggregate reads its own table to prevent one-to-many joins from
        // multiplying hectares, activity costs, or IoT records.
        $activeLots = Lote::query()->where('estado', 'en_uso');
        $activityInvestment = Actividad::query()->sum('costo');

        $farmStates = Finca::query()
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')->orderBy('estado')->get();

        $cropAreas = Lote::query()
            ->where('estado', 'en_uso')
            ->select('tipo_cultivo', DB::raw('SUM(hectareas) as hectareas'))
            ->groupBy('tipo_cultivo')->orderBy('tipo_cultivo')->get();

        $investmentByActivity = Actividad::query()
            ->select('tipo_actividad', DB::raw('SUM(costo) as total'))
            ->groupBy('tipo_actividad')->orderBy('tipo_actividad')->get();

        $monthExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', fecha)",
            'pgsql' => "to_char(fecha, 'YYYY-MM')",
            default => "DATE_FORMAT(fecha, '%Y-%m')",
        };
        $monthlyInvestment = Actividad::query()
            ->whereDate('fecha', '>=', $monthsStart->toDateString())
            ->whereDate('fecha', '<=', $now->toDateString())
            ->selectRaw("{$monthExpression} as periodo, SUM(costo) as total")
            ->groupBy('periodo')->orderBy('periodo')->get();

        $sensorReadings = LecturaIot::query()
            ->where('fecha_medicion', '>=', $sevenDaysStart)
            ->where('fecha_medicion', '<=', $now)
            ->selectRaw('DATE(fecha_medicion) as fecha, tipo_medicion, unidad, AVG(valor) as promedio, COUNT(*) as lecturas')
            ->groupBy('fecha', 'tipo_medicion', 'unidad')->orderBy('fecha')->get();

        $sensorStaleMinutes = config('dashboard.sensor_stale_minutes');
        $latestReadings = LecturaIot::query()
            ->select('mac_dispositivo', DB::raw('MAX(fecha_medicion) as ultima_lectura'))
            ->whereNotNull('mac_dispositivo')->where('mac_dispositivo', '<>', '')
            ->groupBy('mac_dispositivo')->get();
        $latestReadings = $latestReadings->map(function ($sensor) use ($sensorStaleMinutes, $now, $databaseTimezone) {
            $lastReading = Carbon::parse($sensor->ultima_lectura, $databaseTimezone);
            $elapsedSeconds = $now->getTimestamp() - $lastReading->getTimestamp();
            $isRecent = $elapsedSeconds >= 0 && $elapsedSeconds <= $sensorStaleMinutes * 60;

            $sensor->ultima_lectura = $lastReading->toIso8601String();
            $sensor->estado = $isRecent ? 'reporte_reciente' : 'sin_reporte_reciente';
            $sensor->minutos_desde_ultima_lectura = $elapsedSeconds >= 0
                ? (int) floor($elapsedSeconds / 60)
                : null;

            return $sensor;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => [
                    'fincas' => Finca::query()->count(),
                    'lotes_activos' => (clone $activeLots)->count(),
                    'hectareas_cultivadas' => (clone $activeLots)->sum('hectareas'),
                    'inversion_total' => $activityInvestment,
                    'registros_iot' => LecturaIot::query()->count(),
                    'actividades' => Actividad::query()->count(),
                ],
                'estado_fincas' => $farmStates,
                'cultivos_hectareas' => $cropAreas,
                'inversion_actividad' => $investmentByActivity,
                'inversion_mensual' => $monthlyInvestment,
                'sensores_siete_dias' => $sensorReadings,
                'estado_sensores' => [
                    'identificados' => $latestReadings->count(),
                    'criterio_disponible' => true,
                    'umbral_vigencia_minutos' => $sensorStaleMinutes,
                    'reporte_reciente' => $latestReadings->where('estado', 'reporte_reciente')->count(),
                    'sin_reporte_reciente' => $latestReadings->where('estado', 'sin_reporte_reciente')->count(),
                    'ultima_lectura_por_dispositivo' => $latestReadings,
                ],
                'metadata' => [
                    'moneda' => config('dashboard.currency'),
                    'zona_horaria' => $databaseTimezoneLabel,
                    'zona_horaria_offset' => $databaseTimezoneOffset,
                    'rango_iot_desde' => $sevenDaysStart->toIso8601String(),
                    'rango_iot_hasta' => $now->toIso8601String(),
                ],
            ],
        ]);
    }
}
