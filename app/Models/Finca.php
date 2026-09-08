<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Finca extends Model
{
    protected $fillable = [
        'user_id',
        'estado',
        'nombre',
        'latitud',
        'longitud',
        'hectareas_totales',
        'tipo_suelo'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function archivos()
    {
        return $this->morphMany(ArchivoMultimedia::class, 'fileable');
    }

    public function lotes()
    {
        return $this->hasMany(Lote::class);
    }

    public function tieneEspacioDisponible(float $hectareas, ?int $excludeLoteId = null): bool
    {
        if ($this->hectareas_totales === null) {
            return false;
        }

        $query = $this->lotes();
        if ($excludeLoteId !== null) {
            $query->where('id', '!=', $excludeLoteId);
        }

        $hectareasOcupadas = $query->sum('hectareas');

        return ($hectareasOcupadas + $hectareas) <= $this->hectareas_totales;
    }

    public function hectareasDisponibles(?int $excludeLoteId = null): float
    {
        if ($this->hectareas_totales === null) {
            return 0;
        }

        $query = $this->lotes();
        if ($excludeLoteId !== null) {
            $query->where('id', '!=', $excludeLoteId);
        }

        $hectareasOcupadas = $query->sum('hectareas');

        return max(0, $this->hectareas_totales - $hectareasOcupadas);
    }
}
