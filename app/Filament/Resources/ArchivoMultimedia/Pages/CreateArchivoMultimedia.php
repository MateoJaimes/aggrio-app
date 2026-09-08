<?php

namespace App\Filament\Resources\ArchivoMultimedia\Pages;

use App\Filament\Resources\ArchivoMultimedia\ArchivoMultimediaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateArchivoMultimedia extends CreateRecord
{
    protected static string $resource = ArchivoMultimediaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $modelClass = static::getModel();
        $record = null;

        // 1. NOTA DE TEXTO (Corregido: usamos == para evitar el fallo de tipos de Livewire)
        if (isset($data['es_nota_texto']) && $data['es_nota_texto'] == 1) {
            $record = $modelClass::create([
                'fileable_type'   => $data['fileable_type'],
                'fileable_id'     => $data['fileable_id'],
                'contenido_texto' => $data['contenido_texto'],
                'tipo_archivo'    => 'nota_texto', // Clave para identificar que no es un archivo físico
                'categoria'       => $data['categoria'] ?? 'seguimiento',
            ]);
        } 
        // 2. ARCHIVOS MULTIMEDIA
        elseif (isset($data['ruta_archivo'])) {
            
            $archivos = is_array($data['ruta_archivo']) ? $data['ruta_archivo'] : [$data['ruta_archivo']];
            
            foreach ($archivos as $path) {
                $record = $modelClass::create([
                    'fileable_type' => $data['fileable_type'],
                    'fileable_id'   => $data['fileable_id'],
                    'ruta_archivo'  => $path,
                    'tipo_archivo'  => Storage::disk('s3')->mimeType($path),
                    'peso_bytes'    => Storage::disk('s3')->size($path),
                    'categoria'     => $data['categoria'] ?? 'seguimiento',
                ]);
            }
        }

        // Devolvemos el registro guardado para que Filament pueda leer su ID final
        return $record ?? new $modelClass();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}