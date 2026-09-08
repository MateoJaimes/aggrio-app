<?php

namespace App\Filament\Resources\LecturaIots\Pages;

use App\Filament\Resources\LecturaIots\LecturaIotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLecturaIot extends EditRecord
{
    protected static string $resource = LecturaIotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
