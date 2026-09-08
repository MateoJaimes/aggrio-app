<?php

namespace App\Filament\Resources\Actividads\Pages;

use App\Filament\Resources\Actividads\ActividadResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditActividad extends EditRecord
{
    protected static string $resource = ActividadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
