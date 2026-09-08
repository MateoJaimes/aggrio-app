<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Actividad extends Model
{
    // Opcional pero recomendado: especificar la tabla explícitamente en español
    protected $table = 'actividades';

    protected $fillable = [
        'lote_id',
        'tipo_actividad',
        'fecha',
        'costo',
        'observaciones',
    ];

    // Relación Inversa: Una actividad se realiza en un lote
    public function lote()
    {
        return $this->belongsTo(Lote::class);
    }

    // ¡La Magia del Data Lake! Esta actividad puede tener muchas fotos (ej: foto de la plaga)
    public function archivos()
    {
        return $this->morphMany(ArchivoMultimedia::class, 'fileable');
    }
}