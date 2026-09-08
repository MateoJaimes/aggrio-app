<?php

namespace App\Filament\Widgets;

use App\Models\Actividad;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class InversionMensualChart extends ChartWidget
{
    protected ?string $heading = 'Inversión Mensual (Año Actual)';
    protected static ?int $sort = 3;
    protected ?string $maxHeight = '250px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isSuper = $user->isSuperAdmin();

        // Obtenemos las actividades de este año y las agrupamos por mes usando PHP
        $actividades = Actividad::query()
            ->when(! $isSuper, fn ($query) => $query->whereHas('lote.finca', fn ($q) => $q->where('user_id', $user->id)))
            ->whereYear('fecha', now()->year)
            ->get()
            ->groupBy(fn($val) => Carbon::parse($val->fecha)->format('n')); // 'n' da el mes del 1 al 12

        $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $totales = array_fill(1, 12, 0); // Llenamos los 12 meses con $0 por defecto

        foreach ($actividades as $mes => $registros) {
            $totales[$mes] = $registros->sum('costo');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Costo ($)',
                    'data' => array_values($totales),
                    'borderColor' => '#E85D04', // Tu naranja corporativo
                    'backgroundColor' => '#E85D0433',
                    'fill' => true,
                    'tension' => 0.3, // Curva suave
                ],
            ],
            'labels' => $meses,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}