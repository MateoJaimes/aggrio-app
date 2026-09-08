<?php

namespace App\Filament\Resources\ArchivoMultimedia\Pages;

use App\Filament\Resources\ArchivoMultimedia\ArchivoMultimediaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditArchivoMultimedia extends EditRecord
{
    protected static string $resource = ArchivoMultimediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // NUEVO: Hacemos la misma validación por si el agricultor reemplaza la foto
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['es_nota_texto']) && $data['es_nota_texto'] === '1') {
            $data['tipo_archivo'] = 'nota_texto';
            $data['peso_bytes'] = null;
            $data['ruta_archivo'] = null; // Borramos archivo si lo cambió a texto
        } elseif (isset($data['ruta_archivo'])) {
            $path = $data['ruta_archivo'];

            // Solo recalculamos si el archivo físico realmente existe en el disco
            if (Storage::disk('s3')->exists($path)) {
                $data['tipo_archivo'] = Storage::disk('s3')->mimeType($path);
                $data['peso_bytes'] = Storage::disk('s3')->size($path);
            }
        }

        unset($data['es_nota_texto']);

        return $data;
    }
}