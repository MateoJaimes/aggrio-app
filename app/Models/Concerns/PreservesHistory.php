<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

trait PreservesHistory
{
    public static function bootPreservesHistory(): void
    {
        static::created(fn ($m) => $m->recordChange('created', [], $m->getAttributes()));
        static::updating(fn ($m) => $m->recordChange('updated', $m->getRawOriginal(), $m->getAttributes()));
        static::deleted(fn ($m) => $m->recordChange('archived', $m->getRawOriginal(), []));
    }

    private function recordChange(string $action, array $before, array $after): void
    {
        // Only business fields are retained; never copy user/session/device secrets.
        $allowed = ['finca_id', 'lote_id', 'user_id', 'nombre', 'estado', 'hectareas', 'hectareas_totales',
            'tipo_actividad', 'fecha', 'costo', 'observaciones', 'categoria', 'contenido_texto',
            'tipo_medicion', 'valor', 'unidad', 'fecha_medicion', 'origen', 'deleted_at', 'status', 'activo',
            'uuid', 'parametros', 'tipo_cultivo', 'variedad', 'fecha_siembra', 'latitud', 'longitud', 'tipo_suelo',
            'tipo', 'datos', 'cultivo_id', 'ciclo_id', 'cosecha_id', 'costo_id', 'venta_id', 'actividad_id', 'total', 'version', 'registrado_por', 'dispositivo'];
        $safeAfter = array_intersect_key($after, array_flip($allowed));
        if (isset($after['api_key_hash']) && ($before['api_key_hash'] ?? null) !== $after['api_key_hash']) {
            $safeAfter['credential_rotated'] = true;
        }
        DB::table('audit_entries')->insert([
            'user_id' => auth()->id(), 'resource_type' => static::class, 'resource_id' => $this->getKey(),
            'action' => $action, 'before' => json_encode(array_intersect_key($before, array_flip($allowed))),
            'after' => json_encode($safeAfter),
            'reason' => is_string(request()->input('motivo')) ? mb_substr(request()->input('motivo'), 0, 1000) : null, 'created_at' => now(),
        ]);
    }

    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }

    public function save(array $options = [])
    {
        return DB::transaction(fn () => parent::save($options), 3);
    }
}
