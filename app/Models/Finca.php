<?php

namespace App\Models;

use App\Models\Concerns\PreservesHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Finca extends Model
{
    use PreservesHistory;

    protected function casts(): array
    {
        return ['parametros' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(fn ($f) => $f->uuid = (string) Str::uuid());
    }

    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            if ($this->exists) {
                static::whereKey($this->id)->lockForUpdate()->firstOrFail();
                if ($this->isDirty('user_id') && ($this->lotes()->withTrashed()->exists() || ProductionRecord::withTrashed()->where('finca_id', $this->id)->exists())) {
                    throw ValidationException::withMessages(['user_id' => 'No se puede transferir una finca con historial.']);
                }
                if ($this->isDirty('hectareas_totales') && (float) $this->hectareas_totales < (float) $this->lotes()->withTrashed()->sum('hectareas')) {
                    throw ValidationException::withMessages(['hectareas_totales' => 'La superficie no puede ser menor que la asignada a sus lotes, incluidos los archivados.']);
                }
                if ($this->isDirty('hectareas_totales')) {
                    $reserved = $this->reservedArea();
                    if (round((float) $this->lotes()->withTrashed()->sum('hectareas') + $reserved, 2) > (float) $this->hectareas_totales) {
                        throw ValidationException::withMessages(['hectareas_totales' => 'La superficie debe cubrir los lotes y las áreas reservadas.']);
                    }
                }
            }

            return parent::save($options);
        }, 3);
    }

    protected $fillable = [
        'user_id',
        'estado',
        'nombre',
        'latitud',
        'longitud',
        'hectareas_totales',
        'tipo_suelo',
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

        $query = $this->lotes()->withTrashed();
        if ($excludeLoteId !== null) {
            $query->where('id', '!=', $excludeLoteId);
        }

        $hectareasOcupadas = $query->sum('hectareas');

        return (int) round((float) $hectareasOcupadas * 100) + (int) round($hectareas * 100)
            <= (int) round(((float) $this->hectareas_totales - $this->reservedArea()) * 100);
    }

    public function hectareasDisponibles(?int $excludeLoteId = null): float
    {
        if ($this->hectareas_totales === null) {
            return 0;
        }

        $query = $this->lotes()->withTrashed();
        if ($excludeLoteId !== null) {
            $query->where('id', '!=', $excludeLoteId);
        }

        $hectareasOcupadas = $query->sum('hectareas');

        return max(0, $this->hectareas_totales - $this->reservedArea() - $hectareasOcupadas);
    }

    public function reservedArea(): float
    {
        return array_sum(array_map(fn ($key) => (float) ($this->parametros[$key] ?? 0), ['area_conservacion', 'area_infraestructura', 'area_sin_uso']));
    }
}
