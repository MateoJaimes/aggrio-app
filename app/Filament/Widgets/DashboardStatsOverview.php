<?php

namespace App\Filament\Widgets;

use App\Models\Finca;
use App\Models\Lote;
use App\Models\Actividad;
use App\Models\LecturaIot;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsOverview extends BaseWidget
{
    // Esto hace que las tarjetas aparezcan de primeras en la pantalla
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        // Guardamos en una variable si es el admin o no
        $isSuper = $user->isSuperAdmin();

        return [
            // 1. Total Fincas (Tu diseño + Lógica de seguridad)
            Stat::make('Total Fincas', Finca::query()
                ->when(! $isSuper, fn ($query) => $query->where('user_id', $user->id))
                ->count()
            )
                ->description('Propiedades registradas')
                ->descriptionIcon('heroicon-m-home')
                ->color('success'),
                
            // 2. NUEVO: Lotes Activos
            Stat::make('Lotes Activos', Lote::query()
                ->when(! $isSuper, fn ($query) => $query->whereHas('finca', fn ($q) => $q->where('user_id', $user->id)))
                ->count()
            )
                ->description('Sectores de cultivo')
                ->descriptionIcon('heroicon-m-rectangle-group')
                ->color('primary'),

            // 3. Hectáreas Cultivadas (Tu diseño + Lógica de seguridad para sumar)
            Stat::make('Hectáreas Cultivadas', Lote::query()
                ->when(! $isSuper, fn ($query) => $query->whereHas('finca', fn ($q) => $q->where('user_id', $user->id)))
                ->sum('hectareas')
            )
                ->description('Área productiva total')
                ->descriptionIcon('heroicon-m-map')
                ->color('info'),
                
            // 4. Inversión Total (Tu diseño + Lógica de seguridad encadenada)
            Stat::make('Inversión Total', '$ ' . number_format(Actividad::query()
                ->when(! $isSuper, fn ($query) => $query->whereHas('lote.finca', fn ($q) => $q->where('user_id', $user->id)))
                ->sum('costo'), 0, ',', '.'
            ))
                ->description('Gasto registrado en campo')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('warning'),

            // 5. NUEVO: Datos IoT
            Stat::make('Registros IoT', LecturaIot::query()
                ->when(! $isSuper, fn ($query) => $query->whereHas('lote.finca', fn ($q) => $q->where('user_id', $user->id)))
                ->count()
            )
                ->description('Mediciones de sensores')
                ->descriptionIcon('heroicon-m-wifi') 
                ->color('danger'),
            // 6. NUEVO: Actividades Registradas
            Stat::make('Actividades Registradas', Actividad::query()
                ->when(! $isSuper, fn ($query) => $query->whereHas('lote.finca', fn ($q) => $q->where('user_id', $user->id)))
                ->count()
            )
                ->description('Labores ejecutadas en campo')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('gray'),
        ];

    }
}