<?php

namespace App\Filament\Resources\Lotes\Pages;

use App\Filament\Resources\Lotes\LoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Widgets\CultivosLotesChart;

class ListLotes extends ListRecords
{
    protected static string $resource = LoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            CultivosLotesChart::class,
        ];
    }
}
