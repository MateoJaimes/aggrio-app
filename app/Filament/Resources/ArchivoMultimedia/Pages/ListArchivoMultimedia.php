<?php

namespace App\Filament\Resources\ArchivoMultimedia\Pages;

use App\Filament\Resources\ArchivoMultimedia\ArchivoMultimediaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListArchivoMultimedia extends ListRecords
{
    protected static string $resource = ArchivoMultimediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
