<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\Finca;
use App\Models\ProductionRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    public function __construct(private FieldAccess $access) {}

    public function scope(Request $r)
    {
        return ProductionRecord::query()->whereIn('finca_id', $this->farms($r)->select('id'));
    }

    public function farms(Request $r)
    {
        return Finca::query()->when(! $r->user()->isSuperAdmin(), fn ($q) => $q->where('user_id', $r->user()->id));
    }

    public function find(Request $r, int $id): ProductionRecord
    {
        return $this->scope($r)->findOrFail($id);
    }

    public function save(Request $r, string $type, ?int $id = null): ProductionRecord
    {
        $definition = ProductionSchema::definitions()[$type] ?? abort(404);

        return DB::transaction(function () use ($r, $type, $id, $definition) {
            $record = $id ? $this->scope($r)->where('tipo', $type)->lockForUpdate()->findOrFail($id) : new ProductionRecord(['tipo' => $type]);
            $rules = ProductionSchema::rules($definition['fields']) + [
                'finca_id' => 'required|integer', 'nombre' => 'required|string|max:255', 'fecha' => 'required|date',
                'estado' => ['required', Rule::in($definition['states'] ?? ($type === 'ciclos' ? ['activo', 'cerrado'] : ['activo']))],
                'observaciones' => 'nullable|string|max:5000', 'version' => $id ? 'required|integer|min:1' : 'nullable|integer',
            ];
            $data = $r->validate($rules);
            $farm = $this->access->entity($r, 'finca', $data['finca_id']);
            Finca::whereKey($farm->id)->lockForUpdate()->firstOrFail();
            if ($type !== 'catalogos') {
                $this->access->operational($farm);
            }
            if ($id && (int) $data['version'] !== $record->version) {
                abort(409, 'El registro cambió. Vuelve a cargarlo antes de guardar.');
            }
            $relations = ['lote_id', 'cultivo_id', 'ciclo_id', 'cosecha_id', 'costo_id', 'venta_id', 'actividad_id'];
            if ($id) {
                foreach (['finca_id', ...array_intersect($relations, array_keys($rules))] as $key) {
                    if ((int) ($data[$key] ?? 0) !== (int) $record->$key) {
                        $this->invalid($key, 'No se puede cambiar el contexto de un registro con historial.');
                    }
                }
            }
            $linked = [];
            foreach (['cultivo_id' => 'cultivos', 'ciclo_id' => 'ciclos', 'cosecha_id' => 'cosechas', 'costo_id' => 'costos', 'venta_id' => 'ventas'] as $key => $expected) {
                if (empty($data[$key])) {
                    continue;
                }
                $parent = $this->scope($r)->where('tipo', $expected)->lockForUpdate()->findOrFail($data[$key]);
                if ($parent->finca_id !== $farm->id) {
                    $this->invalid($key, 'El registro pertenece a otra finca.');
                }
                $linked[$key] = $parent;
            }
            // Harvest lineage is authoritative for sales/transport; cycle lineage
            // must agree with the selected crop and plot, including on updates.
            foreach (['venta_id', 'cosecha_id', 'ciclo_id', 'cultivo_id'] as $key) {
                if (! isset($linked[$key])) {
                    continue;
                }
                $parent = $linked[$key];
                foreach (['lote_id', 'cultivo_id', 'ciclo_id', 'cosecha_id'] as $context) {
                    if ($context === $key || ! $parent->$context) {
                        continue;
                    }
                    if (! empty($data[$context]) && (int) $data[$context] !== $parent->$context) {
                        $this->invalid($context, 'El contexto no coincide con el registro seleccionado.');
                    }
                    $data[$context] = $parent->$context;
                }
            }
            if (! empty($data['lote_id'])) {
                $plot = $this->access->entity($r, 'lote', $data['lote_id'], true);
                if ($plot->finca_id !== $farm->id) {
                    $this->invalid('lote_id', 'El lote pertenece a otra finca.');
                }
            }
            if (! empty($data['actividad_id'])) {
                $activity = $this->access->entity($r, 'actividad', $data['actividad_id'], true);
                if (empty($data['lote_id']) || $activity->lote_id !== (int) $data['lote_id']) {
                    $this->invalid('actividad_id', 'La actividad debe pertenecer al mismo lote.');
                }
                foreach (['cultivo_id', 'ciclo_id'] as $context) {
                    if ($activity->$context && (int) ($data[$context] ?? 0) !== $activity->$context) {
                        $this->invalid($context, 'Selecciona el contexto de la actividad.');
                    }
                }
            }
            if (isset($linked['ciclo_id']) && $linked['ciclo_id']->estado === 'cerrado' && ! $id) {
                $this->invalid('ciclo_id', 'El ciclo está cerrado. Reábrelo antes de registrar operaciones.');
            }
            $total = 0;
            if (str_starts_with($type, 'sanidad_')) {
                app(PlantHealthRecords::class)->validate($r, $type, $data, $record, $this);
            }
            if (in_array($type, ['costos', 'ventas', 'cobros', 'cosechas', 'transportes', 'analisis', 'certificaciones']) && $data['fecha'] > now('America/Bogota')->toDateString()) {
                $this->invalid('fecha', 'Registra una fecha de ejecución hasta hoy. Las operaciones futuras se programan en el plan de trabajo.');
            }
            if ($type === 'cultivos' || $type === 'ciclos') {
                if ($data['fecha'] > now('America/Bogota')->toDateString()) {
                    $this->invalid('fecha', 'La fecha de siembra no puede ser futura. Usa la guía de actividades para planearla.');
                }
                if (($data['area'] ?? 0) <= 0 || (float) $data['area'] > (float) $plot->hectareas) {
                    $this->invalid('area', 'El área debe ser positiva y no superar la del lote.');
                }
                if ($type === 'ciclos') {
                    if ($linked['cultivo_id']->fecha && $data['fecha'] < $linked['cultivo_id']->fecha) {
                        $this->invalid('fecha', 'El ciclo no puede comenzar antes del establecimiento del cultivo.');
                    }
                    if ($data['fecha_cosecha_estimada'] < $data['fecha']) {
                        $this->invalid('fecha_cosecha_estimada', 'La cosecha no puede preceder a la siembra.');
                    }
                    if ((float) $data['area'] > (float) ($linked['cultivo_id']->datos['area'] ?? 0)) {
                        $this->invalid('area', 'El ciclo supera el área del cultivo.');
                    }
                }
                if ($type === 'cultivos' && $id && ProductionRecord::where('tipo', 'ciclos')->where('cultivo_id', $id)->get()->contains(fn ($c) => (float) ($c->datos['area'] ?? 0) > (float) $data['area'])) {
                    $this->invalid('area', 'El área no puede ser menor que la de sus ciclos registrados.');
                }
            }
            if (in_array($type, ['labores', 'costos', 'cosechas']) && ! empty($data['cultivo_id']) && empty($data['ciclo_id'])) {
                $crop = $linked['cultivo_id'] ?? ProductionRecord::findOrFail($data['cultivo_id']);
                if (($crop->datos['tipo'] ?? '') === 'transitorio') {
                    $this->invalid('ciclo_id', 'Selecciona el ciclo del cultivo transitorio.');
                }
            }
            if (in_array($type, ['costos', 'ventas'])) {
                if ($data['cantidad'] <= 0) {
                    $this->invalid('cantidad', 'La cantidad debe ser mayor que cero.');
                }
                $total = round((float) $data['cantidad'] * (float) $data['precio_unitario'], 2);
                if ($total > 9999999999999) {
                    $this->invalid('precio_unitario', 'El total excede el límite permitido.');
                }
            }
            if ($type === 'ventas') {
                $harvest = $linked['cosecha_id'];
                $sold = $this->sold($harvest->id, $id);
                $available = (float) $harvest->datos['cantidad'] - (float) ($harvest->datos['perdida'] ?? 0);
                if (round($sold + $data['cantidad'], 4) > round($available, 4)) {
                    $this->invalid('cantidad', 'La venta supera el producto disponible de esta cosecha.');
                }
                $abonos = $id ? ProductionRecord::where('tipo', 'cobros')->where('venta_id', $id)->sum('total') : 0;
                if ((float) $data['cobrado'] + $abonos > $total) {
                    $this->invalid('cobrado', 'El cobro inicial más los abonos no puede superar la venta.');
                }
                if (($data['cobrado'] ?? 0) > 0 && empty($data['fecha_cobro'])) {
                    $this->invalid('fecha_cobro', 'Registra la fecha del cobro.');
                }
                if (! empty($data['fecha_cobro']) && ($data['fecha_cobro'] < $data['fecha'] || $data['fecha_cobro'] > now('America/Bogota')->toDateString())) {
                    $this->invalid('fecha_cobro', 'El cobro debe estar entre la venta y hoy.');
                }
                $data['unidad'] = $harvest->datos['unidad'];
            }
            if ($type === 'cobros') {
                $sale = $linked['venta_id'];
                $paid = ProductionRecord::where('tipo', 'cobros')->where('venta_id', $sale->id)->where('id', '!=', $id ?? 0)->sum('total');
                if ($data['importe'] <= 0 || round($paid + (float) ($sale->datos['cobrado'] ?? 0) + $data['importe'], 2) > (float) $sale->total) {
                    $this->invalid('importe', 'El abono supera el saldo de la venta.');
                }
                if ($data['fecha'] < $sale->fecha || $data['fecha'] > now('America/Bogota')->toDateString()) {
                    $this->invalid('fecha', 'El cobro debe estar entre la venta y hoy.');
                }
                $total = round($data['importe'], 2);
            }
            if ($type === 'cosechas') {
                if ($data['fecha'] > now('America/Bogota')->toDateString()) {
                    $this->invalid('fecha', 'La cosecha no puede tener una fecha futura.');
                }
                $available = (float) $data['cantidad'] - (float) ($data['perdida'] ?? 0);
                if ($data['cantidad'] <= 0 || $available < 0 || ($id && $available < $this->sold($id))) {
                    $this->invalid('cantidad', 'Revisa las pérdidas y el producto ya vendido.');
                }
                if ($id && $record->datos['unidad'] !== $data['unidad'] && ProductionRecord::where('cosecha_id', $id)->exists()) {
                    $this->invalid('unidad', 'No cambies la unidad de una cosecha con movimientos.');
                }
                if ($id && ProductionRecord::where('tipo', 'transportes')->where('cosecha_id', $id)->get()->contains(fn ($t) => (float) $t->datos['cantidad'] > $available)) {
                    $this->invalid('cantidad', 'La cosecha no puede ser menor que una carga registrada.');
                }
                if (! empty($data['ciclo_id'])) {
                    $cycle = $linked['ciclo_id'];
                    if ($cycle->datos['unidad'] !== $data['unidad']) {
                        $this->invalid('unidad', 'Usa la unidad definida en el ciclo.');
                    }
                    if ($data['fecha'] < $cycle->fecha) {
                        $this->invalid('fecha', 'La cosecha no puede preceder a la siembra del ciclo.');
                    }
                }
                if (isset($linked['cultivo_id']) && $linked['cultivo_id']->fecha && $data['fecha'] < $linked['cultivo_id']->fecha) {
                    $this->invalid('fecha', 'La cosecha no puede preceder al cultivo.');
                }
                if (ProductionRecord::where('tipo', 'cosechas')->where('finca_id', $farm->id)->where('id', '!=', $id ?? 0)->where('datos->lote_producto', $data['lote_producto'])->exists()) {
                    $this->invalid('lote_producto', 'Este código de producto ya existe en la finca.');
                }
            }
            if ($type === 'transportes') {
                $h = $linked['cosecha_id'];
                if ($data['cantidad'] <= 0 || $data['cantidad'] > (float) $h->datos['cantidad'] - (float) ($h->datos['perdida'] ?? 0)) {
                    $this->invalid('cantidad', 'La carga supera el producto disponible.');
                }
                $data['unidad'] = $h->datos['unidad'];
                if (! empty($data['fecha_entrega']) && $data['fecha_entrega'] < $data['fecha']) {
                    $this->invalid('fecha_entrega', 'La entrega debe ser posterior a la salida.');
                }
            }
            if (in_array($type, ['ventas', 'transportes']) && $data['fecha'] < $linked['cosecha_id']->fecha) {
                $this->invalid('fecha', 'La operación no puede preceder a la cosecha.');
            }
            if ($type === 'analisis' && $data['ph'] > 14) {
                $this->invalid('ph', 'El pH debe estar entre 0 y 14.');
            }
            if ($type === 'certificaciones' && $data['vencimiento'] < $data['fecha']) {
                $this->invalid('vencimiento', 'El vencimiento no puede preceder a la expedición.');
            }
            if ($type === 'repartos') {
                $cost = $linked['costo_id'];
                if ($cost->cultivo_id || $cost->ciclo_id || $cost->actividad_id) {
                    $this->invalid('costo_id', 'Solo se distribuyen gastos generales sin cultivo, ciclo o actividad.');
                }
                $allocations = ProductionRecord::where('tipo', 'repartos')->where('costo_id', $cost->id)->where('id', '!=', $id ?? 0)->get();
                $percent = $allocations->sum(fn ($a) => (float) $a->datos['porcentaje']);
                if ($data['porcentaje'] <= 0 || round($percent + $data['porcentaje'], 4) > 100) {
                    $this->invalid('porcentaje', 'La suma de asignaciones no puede superar el 100%.');
                }
                if ($cost->lote_id && (int) $data['lote_id'] !== $cost->lote_id) {
                    $this->invalid('lote_id', 'El gasto está limitado a otro lote.');
                }
                $total = round((float) $cost->total * $data['porcentaje'] / 100, 2);
            }
            if ($type === 'catalogos') {
                $duplicate = ProductionRecord::where('tipo', 'catalogos')->where('finca_id', $farm->id)->where('nombre', $data['nombre'])
                    ->where('datos->catalogo', $data['catalogo'])->where('id', '!=', $id ?? 0)->exists();
                if ($duplicate) {
                    $this->invalid('nombre', 'Esta opción ya existe en el catálogo.');
                }
            }
            if ($type === 'labores') {
                if ($id && $record->estado === 'terminado' && $data['estado'] !== 'terminado') {
                    $this->invalid('estado', 'La actividad ya fue ejecutada. Conserva su historial.');
                }
                if ($data['estado'] === 'terminado') {
                    if (($data['costo_real'] ?? 0) > 99999999.99) {
                        $this->invalid('costo_real', 'El costo por actividad no puede superar 99.999.999,99 COP. Usa partidas de costo para el desglose.');
                    }
                    if (empty($data['fecha_real']) || $data['fecha_real'] > now('America/Bogota')->toDateString()) {
                        $this->invalid('fecha_real', 'Registra una fecha de ejecución válida.');
                    }
                    $activity = $record->actividad_id ? Actividad::findOrFail($record->actividad_id) : new Actividad;
                    $activity->fill(['lote_id' => $data['lote_id'], 'tipo_actividad' => $data['tipo_actividad'], 'fecha' => $data['fecha_real'],
                        'costo' => $data['costo_real'] ?? 0, 'observaciones' => $data['observaciones'] ?? null]);
                    $activity->cultivo_id = $data['cultivo_id'] ?? null;
                    $activity->ciclo_id = $data['ciclo_id'] ?? null;
                    $activity->save();
                    $data['actividad_id'] = $activity->id;
                    $data['costo_real'] = (float) $activity->costo;
                }
            }
            $record->fill(array_intersect_key($data, array_flip(['finca_id', 'nombre', 'fecha', 'estado', ...$relations])));
            $record->datos = array_diff_key($data, array_flip(['finca_id', 'nombre', 'fecha', 'estado', 'version', ...$relations]));
            $record->total = $total;
            $record->version = $id ? $record->version + 1 : 1;
            $record->dispositivo = mb_substr((string) $r->userAgent(), 0, 255);
            $record->save();
            $this->rememberCatalogs($r, $farm->id, $definition['fields'], $data);
            if ($type === 'costos' && $record->actividad_id) {
                $this->syncActivityCosts($record->actividad_id);
            }
            if (in_array($type, ['costos', 'repartos'])) {
                $cost = $type === 'costos' ? $record : $linked['costo_id'];
                $this->recalculateShares($cost);
            }

            return $record->fresh();
        }, 3);
    }

    public function sold(int $harvest, ?int $except = null): float
    {
        return ProductionRecord::where('tipo', 'ventas')->where('cosecha_id', $harvest)->where('id', '!=', $except ?? 0)->get()->sum(fn ($r) => (float) $r->datos['cantidad']);
    }

    private function rememberCatalogs(Request $request, int $farm, array $fields, array $data): void
    {
        foreach ($fields as $field) {
            if ($field['type'] !== 'catalog' || empty($data[$field['key']])) {
                continue;
            }
            $value = $data[$field['key']];
            $catalog = $field['options'];
            $parent = match ($catalog) {
                'variedades' => $data['especie'] ?? null, 'municipios' => $data['departamento'] ?? null, 'veredas' => $data['municipio'] ?? null, default => null
            };
            $defaults = ProductionSchema::catalogDefaults()[$catalog] ?? [];
            if (in_array($catalog, ['variedades'])) {
                $defaults = $defaults[$parent ?? ''] ?? [];
            }
            if (in_array($value, $defaults, true)) {
                continue;
            }
            $exists = ProductionRecord::where('tipo', 'catalogos')->where('finca_id', $farm)->where('nombre', $value)->where('datos->catalogo', $catalog)
                ->when($parent, fn ($q) => $q->where('datos->padre', $parent))->exists();
            if (! $exists) {
                ProductionRecord::create(['tipo' => 'catalogos', 'finca_id' => $farm, 'nombre' => $value, 'fecha' => now('America/Bogota')->toDateString(), 'estado' => 'activo',
                    'datos' => ['catalogo' => $catalog, 'padre' => $parent], 'dispositivo' => mb_substr((string) $request->userAgent(), 0, 255)]);
            }
        }
    }

    public function recalculateShares(ProductionRecord $cost): void
    {
        $percent = 0;
        foreach (ProductionRecord::where('tipo', 'repartos')->where('costo_id', $cost->id)->orderBy('id')->get() as $share) {
            $next = $percent + (float) $share->datos['porcentaje'];
            $amount = round((float) $cost->total * $next / 100, 2) - round((float) $cost->total * $percent / 100, 2);
            $percent = $next;
            if (round((float) $share->total, 2) !== round($amount, 2)) {
                $share->total = $amount;
                $share->version++;
                $share->save();
            }
        }
    }

    public function syncActivityCosts(int $id): void
    {
        $activity = Actividad::withTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();
        $activity->costo = ProductionRecord::where('tipo', 'costos')->where('actividad_id', $id)->sum('total');
        $activity->save();
        foreach (ProductionRecord::where('tipo', 'labores')->where('actividad_id', $id)->get() as $labor) {
            $data = $labor->datos;
            if ((float) ($data['costo_real'] ?? 0) !== (float) $activity->costo) {
                $data['costo_real'] = (float) $activity->costo;
                $labor->datos = $data;
                $labor->version++;
                $labor->save();
            }
        }
    }

    public function parameters(Request $r, string $type, int $id)
    {
        return DB::transaction(function () use ($r, $type, $id) {
            $entity = $this->access->entity($r, $type, $id);
            $entity = $entity->newQuery()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($type === 'lote' && $entity->trashed()) {
                abort(422, 'El lote está archivado.');
            }
            $data = Validator::make($r->all(), ProductionSchema::rules(ProductionSchema::parameters($type)))->validate();
            if ($type === 'finca') {
                $area = array_sum(array_map(fn ($key) => (float) ($data[$key] ?? 0), ['area_productiva', 'area_conservacion', 'area_infraestructura', 'area_sin_uso']));
                if (round($area, 2) > (float) $entity->hectareas_totales) {
                    $this->invalid('area_productiva', 'El desglose supera la superficie total de la finca.');
                }
                $protected = (float) ($data['area_conservacion'] ?? 0) + (float) ($data['area_infraestructura'] ?? 0) + (float) ($data['area_sin_uso'] ?? 0);
                if (round($protected + (float) $entity->lotes()->withTrashed()->sum('hectareas'), 2) > (float) $entity->hectareas_totales) {
                    $this->invalid('area_conservacion', 'Los lotes y las áreas reservadas superan la finca.');
                }
                if (! empty($data['autoriza_datos']) && empty($data['fecha_autorizacion'])) {
                    $this->invalid('fecha_autorizacion', 'Indica la fecha de la autorización.');
                }
                if (! empty($data['fecha_autorizacion']) && $data['fecha_autorizacion'] > now('America/Bogota')->toDateString()) {
                    $this->invalid('fecha_autorizacion', 'La autorización no puede tener fecha futura.');
                }
                if (isset($data['tipo_suelo'])) {
                    $entity->tipo_suelo = $data['tipo_suelo'];
                }
            } else {
                if (($data['pendiente'] ?? 0) > 100) {
                    $this->invalid('pendiente', 'La pendiente no puede superar el 100%.');
                }
                if (($data['area_cubierta'] ?? 0) > (float) $entity->hectareas * 10000) {
                    $this->invalid('area_cubierta', 'El área cubierta supera el lote.');
                }
                if (! empty($data['ano_construccion']) && ($data['ano_construccion'] < 1900 || $data['ano_construccion'] > now('America/Bogota')->year)) {
                    $this->invalid('ano_construccion', 'Revisa el año de construcción.');
                }
                if (! empty($data['poligono'])) {
                    $this->validatePolygon($data['poligono']);
                }
            }
            $entity->parametros = $data;
            $entity->save();
            $this->rememberCatalogs($r, $type === 'finca' ? $entity->id : $entity->finca_id, ProductionSchema::parameters($type), $data);

            return $entity->fresh();
        }, 3);
    }

    private function validatePolygon(string $json): void
    {
        $p = json_decode($json, true);
        if (($p['type'] ?? '') !== 'Polygon' || empty($p['coordinates']) || ! is_array($p['coordinates'])) {
            $this->invalid('poligono', 'Usa un Polygon GeoJSON válido.');
        }
        foreach ($p['coordinates'] as $ring) {
            if (! is_array($ring) || count($ring) < 4 || $ring[0] !== $ring[count($ring) - 1]) {
                $this->invalid('poligono', 'Cada anillo debe tener al menos cuatro puntos y estar cerrado.');
            }
            foreach ($ring as $point) {
                if (! is_array($point) || count($point) < 2 || ! is_numeric($point[0]) || ! is_numeric($point[1]) || abs($point[0]) > 180 || abs($point[1]) > 90) {
                    $this->invalid('poligono', 'Coordenadas fuera de rango.');
                }
            }
        }
    }

    public function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
