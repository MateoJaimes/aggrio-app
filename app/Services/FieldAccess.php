<?php

namespace App\Services;

use App\Models\AccessRequest;
use App\Models\Actividad;
use App\Models\Finca;
use App\Models\Lote;
use App\Models\ProductionRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FieldAccess
{
    public function approvedProducer(int $id): void
    {
        $user = User::findOrFail($id);
        if ($user->isSuperAdmin() || ! AccessRequest::where('email', $user->email)->where('status', 'approved')->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Selecciona un productor con acceso aprobado.']);
        }
    }

    public function entity(Request $request, string $type, int $id, bool $write = false): Model
    {
        $class = match ($type) {
            'finca' => Finca::class, 'lote' => Lote::class, 'actividad' => Actividad::class, 'registro' => ProductionRecord::class,
            default => abort(422, 'Tipo de recurso inválido.'),
        };
        $query = $class::query();
        if ($type !== 'finca' && ! $write) {
            $query->withTrashed();
        }
        $entity = $query->findOrFail($id);
        $finca = match ($type) {
            'finca' => $entity, 'lote', 'registro' => $entity->finca, 'actividad' => $entity->lote?->finca,
        };
        abort_unless($finca && ($request->user()->isSuperAdmin() || $finca->user_id === $request->user()->id), 404);
        if ($write) {
            $this->operational($finca);
            $lote = $type === 'lote' ? $entity : (in_array($type, ['actividad', 'registro']) ? $entity->lote : null);
            if ($lote && ($lote->trashed() || $lote->estado === 'no_disponible')) {
                throw ValidationException::withMessages(['lote_id' => 'El lote está archivado o no disponible.']);
            }
        }

        return $entity;
    }

    public function operational(Finca $finca): void
    {
        if ($finca->estado !== 'aprobado' || $finca->hectareas_totales <= 0 || $finca->latitud === null || $finca->longitud === null) {
            throw ValidationException::withMessages(['finca_id' => 'La finca debe estar aprobada y tener su perfil técnico completo.']);
        }
    }
}
