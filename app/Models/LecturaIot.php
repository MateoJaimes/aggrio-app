<?php

namespace App\Models;

use App\Models\Concerns\PreservesHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LecturaIot extends Model
{
    use PreservesHistory, SoftDeletes;

    // Forzamos el nombre correcto de la tabla en español
    protected $table = 'lecturas_iot';

    protected function casts(): array
    {
        return ['fecha_medicion' => 'immutable_datetime', 'received_at' => 'immutable_datetime'];
    }

    protected $fillable = [
        'lote_id',
        'mac_dispositivo',
        'tipo_medicion',
        'valor',
        'unidad',
        'fecha_medicion',
        'origen', 'dispositivo_id', 'event_id', 'received_at',
    ];

    // Relación: Esta lectura pertenece a un Lote específico
    public function lote()
    {
        return $this->belongsTo(Lote::class)->withTrashed();
    }
}
