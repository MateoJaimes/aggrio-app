<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Lote extends Model
{
    protected $fillable = [
        'finca_id',
        'estado',
        'nombre',
        'hectareas',
        'tipo_cultivo',
        'variedad',
        'fecha_siembra',
        'latitud',
        'longitud',
    ];

    public const ESTADOS = [
        'disponible',
        'en_uso',
        'no_disponible',
    ];

    protected static function booted()
    {
        static::creating(function ($lote) {
            $finca = $lote->finca;
            if ($finca === null) {
                throw ValidationException::withMessages([
                    'finca_id' => 'La finca especificada no existe.'
                ]);
            }

            if ($finca->hectareas_totales === null) {
                throw ValidationException::withMessages([
                    'hectareas' => 'No se pueden crear lotes. La finca no tiene hectáreas totales definidas.'
                ]);
            }

            if (!$finca->tieneEspacioDisponible($lote->hectareas)) {
                $disponible = number_format($finca->hectareasDisponibles(), 2);
                throw ValidationException::withMessages([
                    'hectareas' => "Solo quedan {$disponible} hectáreas disponibles en la finca."
                ]);
            }
        });

        static::updating(function ($lote) {
            if ($lote->isDirty('hectareas') || $lote->isDirty('finca_id')) {
                $finca = Finca::find($lote->finca_id);

                if ($finca === null) {
                    throw ValidationException::withMessages([
                        'finca_id' => 'La finca especificada no existe.'
                    ]);
                }

                if ($finca->hectareas_totales === null) {
                    throw ValidationException::withMessages([
                        'hectareas' => 'No se pueden crear lotes. La finca no tiene hectáreas totales definidas.'
                    ]);
                }

                $hectareas = $lote->isDirty('hectareas') ? $lote->hectareas : $lote->getOriginal('hectareas');

                if (!$finca->tieneEspacioDisponible($hectareas, $lote->id)) {
                    $disponible = number_format($finca->hectareasDisponibles($lote->id), 2);
                    throw ValidationException::withMessages([
                        'hectareas' => "Solo quedan {$disponible} hectáreas disponibles en la finca."
                    ]);
                }
            }
        });
    }

    public function scopeDisponibles($query)
    {
        return $query->where('estado', 'disponible');
    }

    public function finca()
    {
        return $this->belongsTo(Finca::class);
    }

    public function archivos()
    {
        return $this->morphMany(ArchivoMultimedia::class, 'fileable');
    }

    public function actividades()
    {
        return $this->hasMany(Actividad::class);
    }

    public function lecturasIot()
    {
        return $this->hasMany(LecturaIot::class);
    }
}