<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessRequest;
use App\Models\Actividad;
use App\Models\Finca;
use App\Models\Lote;
use App\Models\ProductionRecord;
use App\Services\FieldAccess;
use App\Services\ProductionReport;
use App\Services\ProductionSchema;
use App\Services\ProductionService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductionController extends Controller
{
    public function __construct(private ProductionService $service) {}

    public function bootstrap(Request $r)
    {
        $farms = $this->service->farms($r)->orderBy('nombre')->get();
        $farms->load('user');
        foreach ($farms as $farm) {
            $registration = AccessRequest::where('email', $farm->user->email)->first();
            $farm->setAttribute('productor', ['nombre' => $farm->user->name, 'email' => $farm->user->email, 'contacto' => $farm->user->phone, 'documento' => $registration?->id_number]);
            $farm->unsetRelation('user');
        }
        $plots = Lote::withTrashed()->whereIn('finca_id', $farms->pluck('id'))->orderBy('nombre')->get();

        return $this->ok(['schemas' => ProductionSchema::definitions(), 'parameters' => ['finca' => ProductionSchema::parameters('finca'), 'lote' => ProductionSchema::parameters('lote')],
            'defaults' => ProductionSchema::catalogDefaults(), 'fincas' => $farms, 'lotes' => $plots,
            'records' => $this->service->scope($r)->withTrashed()->orderByDesc('id')->get(),
            'actividades' => Actividad::withTrashed()->whereIn('lote_id', $plots->pluck('id'))->orderByDesc('fecha')->get()]);
    }

    public function save(Request $r, string $type, ?int $id = null)
    {
        return $this->ok($this->service->save($r, $type, $id), $id ? 200 : 201);
    }

    public function planting(Request $r)
    {
        return DB::transaction(function () use ($r) {
            $crop = $this->service->save($r, 'cultivos');
            if (($crop->datos['tipo'] ?? '') === 'transitorio') {
                $cycleRequest = Request::create($r->path(), 'POST', [
                    'finca_id' => $crop->finca_id, 'lote_id' => $crop->lote_id, 'cultivo_id' => $crop->id,
                    'nombre' => 'Siembra de '.$crop->datos['especie'].' · '.$crop->fecha,
                    'fecha' => $crop->fecha, 'estado' => 'activo', 'area' => $crop->datos['area'],
                    'numero_plantas' => $crop->datos['numero_plantas'] ?? null,
                    'fecha_cosecha_estimada' => $r->input('fecha_cosecha_estimada'),
                    'rendimiento_esperado' => $r->input('rendimiento_esperado'), 'unidad' => $r->input('unidad', 'kg'),
                    'cultivo_anterior' => $r->input('cultivo_anterior'),
                ]);
                $cycleRequest->setUserResolver(fn () => $r->user());
                $cycleRequest->server->set('HTTP_USER_AGENT', (string) $r->userAgent());
                $cycle = $this->service->save($cycleRequest, 'ciclos');
                $crop->setAttribute('ciclo_inicial', $cycle);
            }

            return $this->ok($crop, 201);
        }, 3);
    }

    public function parameters(Request $r, string $type, int $id)
    {
        abort_unless(in_array($type, ['finca', 'lote']), 404);

        return $this->ok($this->service->parameters($r, $type, $id));
    }

    public function archive(Request $r, int $id)
    {
        $r->validate(['motivo' => 'required|string|max:1000', 'version' => 'required|integer']);

        return DB::transaction(function () use ($r, $id) {
            $record = $this->service->scope($r)->whereKey($id)->lockForUpdate()->firstOrFail();
            app(FieldAccess::class)->entity($r, 'finca', $record->finca_id, true);
            Finca::whereKey($record->finca_id)->lockForUpdate()->firstOrFail();
            abort_if($record->version !== $r->integer('version'), 409, 'El registro cambió. Recarga la información.');
            $dependent = ProductionRecord::where(fn ($q) => $q->where('cultivo_id', $id)->orWhere('ciclo_id', $id)->orWhere('cosecha_id', $id)->orWhere('costo_id', $id)->orWhere('venta_id', $id))->exists();
            $activities = Actividad::where(fn ($q) => $q->where('cultivo_id', $id)->orWhere('ciclo_id', $id))->exists();
            abort_if($dependent || $activities || ($record->tipo === 'labores' && $record->actividad_id), 422, 'El registro tiene movimientos relacionados. Conserva el historial y cierra el ciclo cuando corresponda.');
            $record->delete();
            if ($record->tipo === 'repartos') {
                $this->service->recalculateShares(ProductionRecord::findOrFail($record->costo_id));
            }
            if ($record->tipo === 'costos' && $record->actividad_id) {
                $this->service->syncActivityCosts($record->actividad_id);
            }

            return $this->ok(null);
        });
    }

    public function guide(Request $r)
    {
        $data = $r->validate(['cultivo_id' => 'required|integer', 'ciclo_id' => 'nullable|integer']);
        $crop = $this->service->find($r, $data['cultivo_id']);
        abort_unless($crop->tipo === 'cultivos', 422);

        return DB::transaction(function () use ($r, $crop, $data) {
            Finca::whereKey($crop->finca_id)->lockForUpdate()->firstOrFail();
            $created = [];
            foreach (['analisis_suelo' => 'Análisis de suelo', 'preparacion' => 'Preparación del terreno', 'compra_material' => 'Compra de semillas o plántulas', 'contratacion' => 'Contratación de personal', 'maquinaria' => 'Programar maquinaria', 'instalacion_riego' => 'Instalar o revisar el riego', 'siembra' => 'Siembra'] as $key => $name) {
                $exists = ProductionRecord::where('tipo', 'labores')->where('cultivo_id', $crop->id)->where('ciclo_id', $data['ciclo_id'] ?? null)->where('datos->tipo_actividad', $key)->exists();
                if ($exists) {
                    continue;
                }
                $child = Request::create($r->path(), 'POST', ['finca_id' => $crop->finca_id, 'lote_id' => $crop->lote_id, 'cultivo_id' => $crop->id,
                    'ciclo_id' => $data['ciclo_id'] ?? null, 'nombre' => $name, 'fecha' => now('America/Bogota')->toDateString(), 'estado' => 'pendiente',
                    'tipo_actividad' => $key, 'responsable' => $crop->datos['responsable'] ?? $r->user()->name, 'presupuesto' => 0]);
                $child->setUserResolver(fn () => $r->user());
                $child->server->set('HTTP_USER_AGENT', (string) $r->userAgent());
                $created[] = $this->service->save($child, 'labores');
            }

            return $this->ok($created, 201);
        });
    }

    public function report(Request $r, ProductionReport $report)
    {
        return $this->ok($report->build($r));
    }

    public function exportRecords(Request $r, string $type)
    {
        $definition = ProductionSchema::definitions()[$type] ?? abort(404);
        $r->validate(['finca_id' => 'nullable|integer', 'lote_id' => 'nullable|integer', 'cultivo_id' => 'nullable|integer', 'ciclo_id' => 'nullable|integer', 'desde' => 'nullable|date', 'hasta' => 'nullable|date|after_or_equal:desde']);
        $query = $this->service->scope($r)->where('tipo', $type)->when($r->boolean('archivados'), fn ($q) => $q->withTrashed());
        foreach (['finca_id', 'lote_id', 'cultivo_id', 'ciclo_id'] as $key) {
            if ($r->filled($key)) {
                $query->where($type === 'cultivos' && $key === 'cultivo_id' || $type === 'ciclos' && $key === 'ciclo_id' ? 'id' : $key, $r->integer($key));
            }
        }
        $query->when($r->filled('desde'), fn ($q) => $q->where('fecha', '>=', $r->desde))->when($r->filled('hasta'), fn ($q) => $q->where('fecha', '<=', $r->hasta));

        return response()->streamDownload(function () use ($query, $definition) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Código UUID', 'Nombre', 'Finca', 'Fecha', 'Estado', ...array_column($definition['fields'], 'label'), 'Total COP', 'Observaciones', 'ID registro', 'Incidencia (%)', 'Versión'], ';', '"', '');
            foreach ($query->orderBy('id')->cursor() as $row) {
                $values = [$row->uuid, $row->nombre, $row->finca_id, $row->fecha, $row->deleted_at ? 'archivado' : $row->estado];
                foreach ($definition['fields'] as $field) {
                    $values[] = $field['type'] === 'relation' ? ($row->{$field['key']} ?? $row->datos[$field['key']] ?? '') : ($row->datos[$field['key']] ?? '');
                }
                $values[] = $row->total;
                $values[] = $row->datos['observaciones'] ?? '';
                $values[] = $row->id;
                $values[] = $row->datos['incidencia_porcentaje'] ?? '';
                $values[] = $row->version;
                $values = array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-\t\r]/u', $value) ? "'".$value : $value, $values);
                fputcsv($file, $values, ';', '"', '');
            }
            fclose($file);
        }, 'aggrio-'.$type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function export(Request $r, ProductionReport $report)
    {
        $data = $report->build($r);

        return response()->streamDownload(function () use ($data) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Fecha', 'Descripción', 'Finca', 'Lote', 'Cultivo', 'Ciclo', 'Tipo', 'Valor COP', 'Origen'], ';', '"', '');
            foreach ($data['movimientos'] as $row) {
                $name = preg_match('/^[=+@\-\t\r]/u', $row['nombre']) ? "'".$row['nombre'] : $row['nombre'];
                fputcsv($file, [$row['fecha'], $name, $row['finca_id'], $row['lote_id'], $row['cultivo_id'], $row['ciclo_id'], $row['tipo'], $row['valor'], $row['fuente']], ';', '"', '');
            }
            fclose($file);
        }, 'aggrio-produccion.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function history(Request $r, string $type, int $id)
    {
        $entity = $type === 'registro' ? $this->service->scope($r)->withTrashed()->findOrFail($id) : app(FieldAccess::class)->entity($r, $type, $id);
        $history = DB::table('audit_entries')->leftJoin('users', 'audit_entries.user_id', '=', 'users.id')
            ->where('resource_type', $entity::class)->where('resource_id', $id)->select('audit_entries.*', 'users.name as actor')->orderByDesc('audit_entries.id')->get()
            ->map(function ($entry) {
                $entry->created_at = Carbon::parse($entry->created_at, 'UTC')->toIso8601String();

                return $entry;
            });
        $related = $this->service->scope($r)->withTrashed()->where(fn ($q) => $q->where('cultivo_id', $id)->orWhere('ciclo_id', $id)->orWhere('cosecha_id', $id)->orWhere('costo_id', $id)->orWhere('venta_id', $id)->orWhere('datos->caso_id', $id)->orWhere('datos->tratamiento_id', $id))->orderBy('fecha')->orderBy('id')->get();
        if ($type !== 'registro') {
            $related = $this->service->scope($r)->withTrashed()->where($type === 'finca' ? 'finca_id' : 'lote_id', $id)->get();
        }
        $url = rtrim(config('app.frontend_url') ?: config('app.url'), '/').'/produccion?uuid='.$entity->uuid;
        $qr = (new QRCode(new QROptions(['outputType' => 'svg', 'outputBase64' => true])))->render($url);

        return $this->ok(['entity' => $entity, 'history' => $history, 'related' => $related, 'qr' => $qr, 'url' => $url]);
    }

    private function ok(mixed $data, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
