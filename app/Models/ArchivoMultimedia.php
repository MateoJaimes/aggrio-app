<?php

namespace App\Models;

use App\Models\Concerns\PreservesHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ArchivoMultimedia extends Model
{
    use PreservesHistory, SoftDeletes;

    protected $table = 'archivos_multimedia';

    protected $fillable = [
        'fileable_type',   // Estándar de Laravel
        'fileable_id',     // Estándar de Laravel
        'contenido_texto',
        'ruta_archivo',
        'tipo_archivo',
        'peso_bytes',
        'categoria', // 🚀 Añadimos la nueva columna aquí
        'storage_disk', 'transcription_status', 'transcription_text', 'captured_at', 'latitud', 'longitud', 'gps_precision',
    ];

    // Relación polimórfica principal
    public function fileable()
    {
        return $this->morphTo();
    }

    protected static function booted()
    {
        static::forceDeleting(function ($archivo) {
            if (! empty($archivo->ruta_archivo)) {
                if (! Storage::disk($archivo->storage_disk)->delete($archivo->ruta_archivo)) {
                    throw new \RuntimeException('No se pudo eliminar el objeto. Se conserva su registro para reintentar.');
                }
            }
        });
    }
}
