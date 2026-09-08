<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ArchivoMultimedia extends Model
{
    protected $table = 'archivos_multimedia';
    

    protected $fillable = [
        'fileable_type',   // Estándar de Laravel
        'fileable_id',     // Estándar de Laravel
        'contenido_texto',
        'ruta_archivo',
        'tipo_archivo',
        'peso_bytes',
        'categoria', // 🚀 Añadimos la nueva columna aquí
    ];

    // Relación polimórfica principal
    public function fileable()
    {
        return $this->morphTo();
    }

    protected static function booted()
    {
        static::deleting(function ($archivo) {
            if (!empty($archivo->ruta_archivo)) {
                Storage::disk('s3')->delete($archivo->ruta_archivo);
            }
        });
    }
}
