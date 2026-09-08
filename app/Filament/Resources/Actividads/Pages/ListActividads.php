<?php

namespace App\Filament\Resources\Actividads\Pages;

use App\Filament\Resources\Actividads\ActividadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\InversionMensualChart;

class ListActividads extends ListRecords
{
    protected static string $resource = ActividadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            InversionMensualChart::class,
        ];
    }
}
