<?php

namespace App\Filament\Widgets;

use App\Models\Finca;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class EstadoFincasChart extends ChartWidget
{
    protected ?string $heading = 'Estado de Mis Fincas';
    protected static ?int $sort = 3;
    protected ?string $maxHeight = '250px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isSuper = $user->isSuperAdmin();

        $datos = Finca::query()
            ->when(! $isSuper, fn ($query) => $query->where('user_id', $user->id))
            ->select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $colores = [
            'aprobado' => '#10b981', // Verde
            'pendiente' => '#f59e0b', // Amarillo
            'rechazado' => '#ef4444', // Rojo
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Cantidad',
                    'data' => $datos->values()->toArray(),
                    'backgroundColor' => collect($datos->keys())->map(fn($estado) => $colores[$estado] ?? '#6b7280')->toArray(),
                ],
            ],
            'labels' => collect($datos->keys())->map(fn($e) => ucfirst($e))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}