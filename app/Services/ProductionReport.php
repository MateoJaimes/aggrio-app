<?php

namespace App\Services;

use App\Models\Actividad;
use Illuminate\Http\Request;

class ProductionReport
{
    public function __construct(private ProductionService $production) {}

    public function build(Request $r): array
    {
        $r->validate(['finca_id' => 'nullable|integer', 'lote_id' => 'nullable|integer', 'cultivo_id' => 'nullable|integer', 'ciclo_id' => 'nullable|integer',
            'desde' => 'nullable|date', 'hasta' => 'nullable|date|after_or_equal:desde']);
        $base = $this->production->scope($r)->when($r->filled('finca_id'), fn ($q) => $q->where('finca_id', $r->integer('finca_id')));
        $all = (clone $base)->get();
        $matches = function ($row, bool $dates = true) use ($r) {
            foreach (['lote_id', 'cultivo_id', 'ciclo_id'] as $key) {
                if ($r->filled($key) && (int) $row->$key !== $r->integer($key)) {
                    return false;
                }
            }

            return ! $dates || ((! $r->filled('desde') || $row->fecha >= $r->desde) && (! $r->filled('hasta') || $row->fecha <= $r->hasta));
        };
        $ledger = [];
        $add = function ($row, string $kind, float $amount, string $source, array $extra = []) use (&$ledger) {
            $ledger[] = array_merge(['id' => $row->id, 'fecha' => $row->fecha, 'nombre' => $row->nombre ?? $row->tipo_actividad,
                'finca_id' => $row->finca_id ?? $row->lote?->finca_id, 'lote_id' => $row->lote_id, 'cultivo_id' => $row->cultivo_id,
                'ciclo_id' => $row->ciclo_id, 'tipo' => $kind, 'valor' => round($amount, 2), 'fuente' => $source], $extra);
        };
        foreach ($all->where('tipo', 'costos') as $cost) {
            $shares = $all->where('tipo', 'repartos')->where('costo_id', $cost->id)->sortBy('id');
            $allocated = 0;
            $percent = 0;
            foreach ($shares as $share) {
                $next = $percent + (float) $share->datos['porcentaje'];
                // Cumulative rounding keeps allocations plus remainder equal to
                // the original expense, even for a one-cent shared cost.
                $amount = round((float) $cost->total * $next / 100, 2) - round((float) $cost->total * $percent / 100, 2);
                $percent = $next;
                $allocated += $amount;
                $share->fecha = $cost->fecha;
                if ($matches($share)) {
                    $add($share, $cost->datos['naturaleza'], $amount, 'reparto', ['nombre' => $cost->nombre]);
                }
            }
            if ($matches($cost)) {
                $add($cost, $cost->datos['naturaleza'], (float) $cost->total - $allocated, 'costo');
            }
        }
        $itemized = $all->where('tipo', 'costos')->pluck('actividad_id')->filter()->all();
        $activities = Actividad::with('lote')->whereHas('lote', fn ($q) => $q->withTrashed()->whereIn('finca_id', $this->production->farms($r)->select('id')))
            ->when($r->filled('finca_id'), fn ($q) => $q->whereHas('lote', fn ($l) => $l->withTrashed()->where('finca_id', $r->integer('finca_id'))))->get();
        foreach ($activities as $activity) {
            if (! in_array($activity->id, $itemized) && $matches($activity)) {
                $add($activity, 'produccion', (float) $activity->costo, 'actividad');
            }
        }
        $selected = $all->filter(fn ($row) => $matches($row));
        foreach ($selected->where('tipo', 'ventas') as $sale) {
            $paid = (float) $sale->datos['cobrado'] + $all->where('tipo', 'cobros')->where('venta_id', $sale->id)->sum('total');
            $add($sale, 'venta', (float) $sale->total, 'venta', ['cobrado' => $paid, 'unidad' => $sale->datos['unidad']]);
        }
        $entries = collect($ledger);
        $costs = round($entries->where('tipo', 'produccion')->sum('valor'), 2);
        $investment = round($entries->where('tipo', 'inversion')->sum('valor'), 2);
        $sales = round($entries->where('tipo', 'venta')->sum('valor'), 2);
        // Cash collections use each payment date, independently of sale date.
        $collections = $all->where('tipo', 'ventas')->filter(fn ($row) => $matches($row, false))->filter(fn ($row) => (! $r->filled('desde') || ($row->datos['fecha_cobro'] ?? '') >= $r->desde) && (! $r->filled('hasta') || ($row->datos['fecha_cobro'] ?? '') <= $r->hasta))->sum(fn ($s) => (float) $s->datos['cobrado']);
        $collections += $selected->where('tipo', 'cobros')->sum('total');
        $units = [];
        foreach ($selected->where('tipo', 'cosechas') as $h) {
            $unit = $h->datos['unidad'];
            $units[$unit] = ($units[$unit] ?? 0) + (float) $h->datos['cantidad'] - (float) ($h->datos['perdida'] ?? 0);
        }
        $pending = $all->where('tipo', 'labores')->filter(fn ($row) => $matches($row, false) && in_array($row->estado, ['pendiente', 'en_proceso']))->values();
        $certifications = $all->where('tipo', 'certificaciones')->filter(fn ($row) => $matches($row, false) && $row->datos['vencimiento'] <= now('America/Bogota')->addDays(30)->toDateString())->values();
        $budget = $selected->where('tipo', 'labores')->where('estado', '!=', 'cancelado')->sum(fn ($p) => (float) ($p->datos['presupuesto'] ?? 0));
        $cycles = $all->where('tipo', 'ciclos')->filter(fn ($row) => (! $r->filled('ciclo_id') || $row->id === $r->integer('ciclo_id')) && (! $r->filled('cultivo_id') || $row->cultivo_id === $r->integer('cultivo_id')) && (! $r->filled('lote_id') || $row->lote_id === $r->integer('lote_id')))->map(function ($cycle) use ($all) {
            $real = $all->where('tipo', 'cosechas')->where('ciclo_id', $cycle->id)->sum(fn ($h) => (float) $h->datos['cantidad'] - (float) ($h->datos['perdida'] ?? 0));

            return ['id' => $cycle->id, 'nombre' => $cycle->nombre, 'esperado' => (float) ($cycle->datos['rendimiento_esperado'] ?? 0), 'real' => round($real, 4), 'unidad' => $cycle->datos['unidad']];
        })->values();

        return ['moneda' => 'COP', 'costos_produccion' => $costs, 'inversion' => $investment, 'desembolso_total' => round($costs + $investment, 2),
            'ventas' => $sales, 'cobrado' => round($collections, 2),
            'por_cobrar_ventas_periodo' => round($sales - $entries->where('tipo', 'venta')->sum('cobrado'), 2),
            'resultado' => round($sales - $costs, 2), 'margen_porcentaje' => $sales > 0 ? round(($sales - $costs) / $sales * 100, 2) : null,
            'presupuesto' => round($budget, 2), 'desviacion_presupuesto' => round($costs - $budget, 2),
            'produccion_por_unidad' => $units, 'costo_por_unidad' => count($units) === 1 && array_sum($units) > 0 ? round($costs / array_sum($units), 2) : null,
            'pendientes' => $pending, 'certificaciones_por_vencer' => $certifications, 'rendimientos' => $cycles,
            'movimientos' => $ledger, 'por_cultivo' => $entries->groupBy(fn ($e) => $e['cultivo_id'] ?? 'sin_asignar')->map(function ($group, $key) use ($all) {
                return ['cultivo_id' => $key, 'nombre' => $all->firstWhere('id', $key)?->nombre ?? 'Gastos sin asignar a cultivo',
                    'costos' => round($group->where('tipo', 'produccion')->sum('valor'), 2), 'inversion' => round($group->where('tipo', 'inversion')->sum('valor'), 2), 'ventas' => round($group->where('tipo', 'venta')->sum('valor'), 2)];
            })->values(),
            'nota' => 'Resultado = ventas menos costos de producción. Inversión separada, sin depreciación. Cobros por fecha de cada abono. El costo unitario solo se muestra para una unidad de producción.'];
    }
}
