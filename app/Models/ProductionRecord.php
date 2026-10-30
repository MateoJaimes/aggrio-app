<?php

namespace App\Models;

use App\Models\Concerns\PreservesHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ProductionRecord extends Model
{
    use PreservesHistory, SoftDeletes;

    protected $guarded = ['id'];

    protected $appends = ['edad_anos'];

    protected function casts(): array
    {
        return ['datos' => 'array', 'total' => 'decimal:2', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function ($record) {
            $record->uuid = (string) Str::uuid();
            $record->registrado_por ??= auth()->id();
        });
    }

    public function finca()
    {
        return $this->belongsTo(Finca::class);
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class)->withTrashed();
    }

    public function archivos()
    {
        return $this->morphMany(ArchivoMultimedia::class, 'fileable');
    }

    public function getEdadAnosAttribute(): ?int
    {
        return $this->tipo === 'cultivos' && $this->fecha
            ? max(0, (int) Carbon::parse($this->fecha)->diffInYears(now('America/Bogota'), false))
            : null;
    }
}
