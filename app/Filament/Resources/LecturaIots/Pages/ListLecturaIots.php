<?php

namespace App\Filament\Resources\LecturaIots\Pages;

use App\Filament\Resources\LecturaIots\LecturaIotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\ComportamientoIotChart;

class ListLecturaIots extends ListRecords
{
    protected static string $resource = LecturaIotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    // NUEVO: Agregamos la gráfica al pie de la página
    protected function getFooterWidgets(): array
    {
        return [
            ComportamientoIotChart::class,
        ];
    }
}
