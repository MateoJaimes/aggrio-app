<?php

namespace App\Models;

use App\Models\Concerns\PreservesHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Actividad extends Model
{
    use PreservesHistory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function ($activity) {
            if (! $activity->exists) {
                return;
            }
            $items = ProductionRecord::where('tipo', 'costos')->where('actividad_id', $activity->id);
            if ((clone $items)->exists()) {
                $total = (float) $items->sum('total');
                if ($total > 99999999.99) {
                    throw ValidationException::withMessages(['actividad_id' => 'El desglose supera el límite monetario de una actividad. Divide las actividades.']);
                }
                $activity->costo = $total;
            }
        });
    }

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
        return $this->belongsTo(Lote::class)->withTrashed();
    }

    // ¡La Magia del Data Lake! Esta actividad puede tener muchas fotos (ej: foto de la plaga)
    public function archivos()
    {
        return $this->morphMany(ArchivoMultimedia::class, 'fileable');
    }
}
