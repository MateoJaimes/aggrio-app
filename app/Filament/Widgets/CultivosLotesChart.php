<?php

namespace App\Filament\Widgets;

use App\Models\Lote;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CultivosLotesChart extends ChartWidget
{
    protected ?string $heading = 'Hectáreas por Tipo de Cultivo';
    protected static ?int $sort = 3;
    protected ?string $maxHeight = '250px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isSuper = $user->isSuperAdmin();

        $datos = Lote::query()
            ->when(! $isSuper, fn ($query) => $query->whereHas('finca', fn ($q) => $q->where('user_id', $user->id)))
            ->select('tipo_cultivo', DB::raw('SUM(hectareas) as total'))
            ->groupBy('tipo_cultivo')
            ->pluck('total', 'tipo_cultivo');

        return [
            'datasets' => [
                [
                    'label' => 'Total Hectáreas',
                    'data' => $datos->values()->toArray(),
                    'backgroundColor' => '#1B4D3E', // Tu verde corporativo
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $datos->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}