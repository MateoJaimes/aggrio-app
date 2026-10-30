<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class TelemetryRules
{
    public static function validate(array $data): void
    {
        [$unit, $min, $max] = match ($data['tipo_medicion']) {
            'temperatura' => ['°C', -100, 100],
            'humedad_suelo', 'humedad_ambiente' => ['%', 0, 100],
            'radiacion_solar' => ['W/m2', 0, 2500],
            default => throw ValidationException::withMessages(['tipo_medicion' => 'Variable no admitida.']),
        };
        if (! is_finite((float) $data['valor']) || $data['valor'] < $min || $data['valor'] > $max) {
            throw ValidationException::withMessages(['valor' => "Valor fuera del rango técnico {$min}–{$max}."]);
        }
        if ($data['unidad'] !== $unit) {
            throw ValidationException::withMessages(['unidad' => "Utiliza la unidad {$unit}."]);
        }
    }
}
