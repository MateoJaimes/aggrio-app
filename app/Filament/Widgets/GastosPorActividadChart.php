<?php

namespace App\Filament\Widgets;

use App\Models\Actividad;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class GastosPorActividadChart extends ChartWidget
{
    // CORRECCIÓN AQUÍ: Le quitamos la palabra "static" a $heading
    protected ?string $heading = 'Distribución de Inversión';
    
    // Esta línea sí mantiene el "static" porque es una regla de ordenamiento
    protected static ?int $sort = 2; 

    protected function getData(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $isSuper = $user->isSuperAdmin();
        // Agrupar actividades por tipo y sumar su costo total
        $datos = Actividad::query()
            ->when(! $isSuper, fn ($query) => $query->whereHas('lote.finca', fn ($q) => $q->where('user_id', $user->id)))
            ->select('tipo_actividad', DB::raw('SUM(costo) as total_costo'))
            ->groupBy('tipo_actividad')
            ->pluck('total_costo', 'tipo_actividad');

        return [
            'datasets' => [
                [
                    'label' => 'Costo Total ($)',
                    'data' => $datos->values()->toArray(),
                    'backgroundColor' => [
                        '#10b981', // Verde
                        '#ef4444', // Rojo
                        '#f59e0b', // Amarillo
                        '#3b82f6', // Azul
                        '#8b5cf6', // Morado
                    ],
                ],
            ],
            'labels' => $datos->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut'; 
    }
}