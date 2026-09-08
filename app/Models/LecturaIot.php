<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LecturaIot extends Model
{
    // Forzamos el nombre correcto de la tabla en español
    protected $table = 'lecturas_iot';

    protected $fillable = [
        'lote_id',
        'mac_dispositivo',
        'tipo_medicion',
        'valor',
        'unidad',
        'fecha_medicion',
    ];

    // Relación: Esta lectura pertenece a un Lote específico
    public function lote()
    {
        return $this->belongsTo(Lote::class);
    }
}