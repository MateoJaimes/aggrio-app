<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessRequest;
use App\Models\Actividad;
use App\Models\ArchivoMultimedia;
use App\Models\DispositivoIot;
use App\Models\Finca;
use App\Models\LecturaIot;
use App\Models\Lote;
use App\Models\ProductionRecord;
use App\Services\FieldAccess;
use App\Services\ProductionReport;
use App\Services\TelemetryRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CampoApiController extends Controller
{
    public function __construct(private readonly FieldAccess $access) {}

    public function perfil(Request $r): JsonResponse
    {
        if ($r->isMethod('put')) {
            $data = $r->validate(['name' => 'required|string|max:255', 'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() .-]{5,30}$/']]);
            // Role, email and approval cannot be mass assigned from this surface.
            $r->user()->update($data);
        }

        return $this->ok($r->user()->toAuthPayload() + ['registration' => AccessRequest::where('email', $r->user()->email)
            ->first(['firstname', 'lastname', 'id_type', 'id_number', 'country', 'department', 'city', 'status', 'created_at'])]);
    }

    public function fincas(Request $r): JsonResponse
    {
        return $this->ok($r->user()->fincas()->orderByDesc('id')->get()->map(function ($f) {
            $f->setAttribute('hectareas_disponibles', $f->hectareasDisponibles());
            $f->setAttribute('perfil_completo', $f->hectareas_totales > 0 && $f->latitud !== null && $f->longitud !== null);

            return $f;
        }));
    }

    public function lotes(Request $r, int $finca): JsonResponse
    {
        $f = $this->access->entity($r, 'finca', $finca);
        $r->validate(['archivados' => 'nullable|boolean']);

        return $this->ok($f->lotes()->when($r->boolean('archivados'), fn ($q) => $q->withTrashed())->orderBy('id')->get());
    }

    public function guardarLote(Request $r, int $finca, ?int $id = null): JsonResponse
    {
        $f = $this->access->entity($r, 'finca', $finca, true);
        $lote = $id ? $this->access->entity($r, 'lote', $id) : new Lote;
        abort_if($id && $lote->finca_id !== $f->id, 404);
        abort_if($id && $lote->trashed(), 422, 'Un lote archivado no se puede editar.');
        $data = $r->validate([
            'nombre' => 'required|string|max:255', 'hectareas' => 'required|numeric|min:0.01|max:999999.99|decimal:0,2',
            'tipo_cultivo' => 'nullable|string|max:255', 'variedad' => 'nullable|string|max:255',
            'fecha_siembra' => 'nullable|date|before_or_equal:today',
            'estado' => ['sometimes', Rule::in(Lote::ESTADOS)],
            'latitud' => 'nullable|numeric|between:-90,90', 'longitud' => 'nullable|numeric|between:-180,180',
        ]);
        if (! $id && empty($data['tipo_cultivo'])) {
            $data['tipo_cultivo'] = 'Sin cultivo';
        }
        if ($id && empty($data['tipo_cultivo'])) {
            unset($data['tipo_cultivo']);
        }
        $lote->fill($data + ['finca_id' => $f->id]);
        if (! $id) {
            $lote->estado = $data['estado'] ?? 'disponible';
        }
        $lote->save();

        return $this->ok($lote->fresh(), $id ? 200 : 201);
    }

    public function archivarLote(Request $r, int $id): JsonResponse
    {
        $lote = $this->access->entity($r, 'lote', $id);
        $this->access->operational($lote->finca);
        $r->validate(['motivo' => 'required|string|max:1000']);
        DB::transaction(function () use ($lote) {
            $lote->delete();
            DispositivoIot::where('lote_id', $lote->id)->update(['activo' => false]);
        });

        return $this->ok(null);
    }

    public function actividades(Request $r, int $lote): JsonResponse
    {
        $l = $this->access->entity($r, 'lote', $lote);
        $r->validate(['desde' => 'nullable|date', 'hasta' => 'nullable|date|after_or_equal:desde', 'tipo_actividad' => 'nullable|string|max:255', 'page' => 'nullable|integer|min:1']);

        return $this->ok($l->actividades()
            ->when($r->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $r->desde))
            ->when($r->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $r->hasta))
            ->when($r->filled('tipo_actividad'), fn ($q) => $q->where('tipo_actividad', $r->tipo_actividad))
            ->orderByDesc('fecha')->orderByDesc('id')->paginate(30));
    }

    public function guardarActividad(Request $r, int $lote): JsonResponse
    {
        $l = $this->access->entity($r, 'lote', $lote, true);
        $data = $r->validate([
            'tipo_actividad' => ['required', Rule::in(['preparacion', 'siembra', 'fertilizacion', 'riego', 'control_plagas', 'poda', 'cosecha', 'mantenimiento'])],
            'fecha' => 'required|date|before_or_equal:today', 'costo' => 'required|numeric|min:0|max:99999999.99|decimal:0,2',
            'observaciones' => 'nullable|string|max:10000',
        ]);

        return $this->ok($l->actividades()->create($data), 201);
    }

    public function lecturas(Request $r, int $lote): JsonResponse
    {
        $l = $this->access->entity($r, 'lote', $lote);
        $r->validate(['desde' => 'nullable|date', 'hasta' => 'nullable|date|after_or_equal:desde', 'tipo_medicion' => 'nullable|string|max:50', 'page' => 'nullable|integer|min:1']);

        return $this->ok($l->lecturasIot()
            ->when($r->filled('desde'), fn ($q) => $q->whereDate('fecha_medicion', '>=', $r->desde))
            ->when($r->filled('hasta'), fn ($q) => $q->whereDate('fecha_medicion', '<=', $r->hasta))
            ->when($r->filled('tipo_medicion'), fn ($q) => $q->where('tipo_medicion', $r->tipo_medicion))
            ->orderByDesc('fecha_medicion')->orderByDesc('id')->paginate(50));
    }

    public function lecturaManual(Request $r, int $lote): JsonResponse
    {
        $l = $this->access->entity($r, 'lote', $lote, true);
        $data = $r->validate([
            'tipo_medicion' => 'required|in:temperatura,humedad_suelo,humedad_ambiente,radiacion_solar',
            'valor' => 'required|numeric|between:-999999.99,999999.99', 'unidad' => 'required|string|max:10',
            'fecha_medicion' => 'required|date|before_or_equal:now', 'motivo' => 'required|string|max:1000',
        ]);
        TelemetryRules::validate($data);
        unset($data['motivo']);

        return $this->ok($l->lecturasIot()->create($data + ['origen' => 'manual', 'received_at' => now()]), 201);
    }

    public function evidencia(Request $r, string $tipo, int $id): JsonResponse
    {
        $entity = $this->access->entity($r, $tipo, $id);
        $r->validate(['categoria' => 'nullable|in:seguimiento,enfermedad', 'page' => 'nullable|integer|min:1']);

        return $this->ok($entity->archivos()->when($r->filled('categoria'), fn ($q) => $q->where('categoria', $r->categoria))
            ->orderByDesc('id')->paginate(30));
    }

    public function descargar(Request $r, int $id)
    {
        $a = ArchivoMultimedia::findOrFail($id);
        $type = match ($a->fileable_type) {
            Finca::class => 'finca', Lote::class => 'lote', Actividad::class => 'actividad', ProductionRecord::class => 'registro', default => abort(404)
        };
        $this->access->entity($r, $type, $a->fileable_id);
        abort_unless($a->ruta_archivo, 404);

        return Storage::disk($a->storage_disk)->download($a->ruta_archivo, 'evidencia-'.$a->id.'.'.pathinfo($a->ruta_archivo, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function dashboard(Request $r): JsonResponse
    {
        // Scope is applied to each independent aggregate, before grouping.
        $fincas = Finca::where('user_id', $r->user()->id);
        $lotes = Lote::whereIn('finca_id', (clone $fincas)->select('id'));
        $allLots = Lote::withTrashed()->whereIn('finca_id', (clone $fincas)->select('id'));
        $activities = Actividad::whereIn('lote_id', (clone $allLots)->select('id'));
        $readings = LecturaIot::whereIn('lote_id', (clone $allLots)->select('id'));
        $dayExpression = match (DB::connection()->getDriverName()) {
            'pgsql' => "DATE(fecha_medicion AT TIME ZONE 'UTC' AT TIME ZONE 'America/Bogota')",
            'sqlite' => "DATE(fecha_medicion, '-5 hours')",
            default => "DATE(CONVERT_TZ(fecha_medicion, '+00:00', '-05:00'))",
        };

        return $this->ok([
            'produccion' => array_intersect_key(app(ProductionReport::class)->build($r), array_flip(['costos_produccion', 'inversion', 'ventas', 'cobrado', 'resultado', 'margen_porcentaje'])),
            'kpis' => ['fincas' => (clone $fincas)->count(), 'lotes_activos' => (clone $lotes)->where('estado', 'en_uso')->count(),
                'hectareas_cultivadas' => (clone $lotes)->where('estado', 'en_uso')->sum('hectareas'), 'inversion_total' => (clone $activities)->sum('costo'),
                'actividades' => (clone $activities)->count(), 'registros_iot' => (clone $readings)->count()],
            'estado_fincas' => (clone $fincas)->select('estado')->selectRaw('COUNT(*) as total')->groupBy('estado')->get(),
            'cultivos_hectareas' => (clone $lotes)->where('estado', 'en_uso')->select('tipo_cultivo')->selectRaw('SUM(hectareas) as hectareas')->groupBy('tipo_cultivo')->get(),
            'inversion_actividad' => (clone $activities)->select('tipo_actividad')->selectRaw('SUM(costo) as total')->groupBy('tipo_actividad')->get(),
            'inversion_mensual' => (clone $activities)->where('fecha', '>=', now()->startOfMonth()->subMonths(11)->toDateString())->get(['fecha', 'costo'])
                ->groupBy(fn ($a) => substr($a->fecha, 0, 7))->map(fn ($items, $key) => ['periodo' => $key, 'total' => $items->sum('costo')])->values(),
            'sensores_siete_dias' => (clone $readings)->where('fecha_medicion', '>=', Carbon::now('America/Bogota')->startOfDay()->subDays(6)->utc())->where('fecha_medicion', '<=', now())->select('tipo_medicion', 'unidad')->selectRaw($dayExpression.' as fecha, AVG(valor) as promedio, COUNT(*) as lecturas')->groupBy('tipo_medicion', 'unidad', DB::raw($dayExpression))->orderBy('fecha')->get(),
            'estado_sensores' => (clone $readings)->whereNotNull('mac_dispositivo')->select('mac_dispositivo')->selectRaw('MAX(fecha_medicion) as ultima_lectura')->groupBy('mac_dispositivo')->get()->map(function ($row) {
                $row->ultima_lectura = Carbon::parse($row->ultima_lectura, 'UTC')->toIso8601String();

                return $row;
            }),
            'metadata' => ['moneda' => 'COP', 'zona_horaria' => 'America/Bogota', 'generado_en' => now()->toIso8601String()],
        ]);
    }

    private function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
