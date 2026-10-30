<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\ArchivoMultimedia;
use App\Models\Finca;
use App\Models\Lote;
use App\Models\ProductionRecord;
use App\Services\EvidenceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MultimediaApiController extends Controller
{
    /**
     * Lista toda la evidencia multimedia para el panel Superadmin.
     * El filtro user_id resuelve el propietario de la finca para evidencias
     * asociadas a fincas, lotes o actividades.
     */
    public function indexAdmin(Request $request): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $query = $this->adminMultimediaQuery();

        if ($request->filled('user_id')) {
            $this->filterByFincaOwner($query, $request->integer('user_id'));
        }

        $archivos = $query->orderByDesc('created_at')->orderByDesc('id')->get();
        $archivos->each(fn (ArchivoMultimedia $archivo) => $this->appendFileUrl($archivo));

        return response()->json([
            'success' => true,
            'message' => 'Evidencia multimedia recuperada con éxito.',
            'data' => $archivos,
        ], 200);
    }

    /**
     * Consulta un archivo de evidencia globalmente desde el panel Superadmin.
     */
    public function showAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $archivo = $this->adminMultimediaQuery()->find($id);

        if (! $archivo) {
            return $this->archivoNoEncontrado();
        }

        $this->appendFileUrl($archivo);

        return response()->json([
            'success' => true,
            'data' => $archivo,
        ], 200);
    }

    /**
     * Administra los metadatos de una evidencia sin sustituir el archivo físico.
     */
    public function updateAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $archivo = ArchivoMultimedia::find($id);

        if (! $archivo) {
            return $this->archivoNoEncontrado();
        }

        $data = $request->validate([
            'motivo' => 'required|string|max:1000',
            'categoria' => 'required|in:seguimiento,enfermedad',
            'contenido_texto' => 'nullable|string',
        ]);

        unset($data['motivo']);
        $archivo->update($data);
        $archivo = $this->adminMultimediaQuery()->findOrFail($id);
        $this->appendFileUrl($archivo);

        return response()->json([
            'success' => true,
            'message' => 'Evidencia multimedia actualizada exitosamente.',
            'data' => $archivo,
        ], 200);
    }

    /**
     * Elimina el registro y, cuando existe, su archivo físico de MinIO.
     */
    public function destroyAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $archivo = ArchivoMultimedia::find($id);

        if (! $archivo) {
            return $this->archivoNoEncontrado();
        }

        $request->validate(['motivo' => 'required|string|max:1000']);
        $archivo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Evidencia archivada. El archivo y su historial se conservan.',
        ], 200);
    }

    /**
     * Guarda los registros de los archivos que Flutter ya subió a MinIO
     * y los enlaza polimórficamente a un Lote, Finca o Actividad.
     */
    public function store(Request $request, EvidenceService $evidence): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $evidence->finalize($request)], 201);
    }

    private function adminMultimediaQuery(): Builder
    {
        return ArchivoMultimedia::query()->with([
            'fileable' => function (MorphTo $morphTo): void {
                $morphTo->morphWith([
                    Finca::class => ['user'],
                    Lote::class => ['finca.user'],
                    Actividad::class => ['lote.finca.user'],
                    ProductionRecord::class => ['finca.user'],
                ]);
            },
        ]);
    }

    private function filterByFincaOwner(Builder $query, int $userId): void
    {
        $query->where(function (Builder $ownerQuery) use ($userId): void {
            $ownerQuery
                ->where(function (Builder $fincaQuery) use ($userId): void {
                    $fincaQuery
                        ->where('fileable_type', Finca::class)
                        ->whereHasMorph('fileable', [Finca::class], fn (Builder $fileableQuery) => $fileableQuery->where('user_id', $userId));
                })
                ->orWhere(function (Builder $loteQuery) use ($userId): void {
                    $loteQuery
                        ->where('fileable_type', Lote::class)
                        ->whereHasMorph('fileable', [Lote::class], fn (Builder $fileableQuery) => $fileableQuery->whereHas('finca', fn (Builder $fincaQuery) => $fincaQuery->where('user_id', $userId)));
                })
                ->orWhere(function (Builder $actividadQuery) use ($userId): void {
                    $actividadQuery
                        ->where('fileable_type', Actividad::class)
                        ->whereHasMorph('fileable', [Actividad::class], fn (Builder $fileableQuery) => $fileableQuery->whereHas('lote.finca', fn (Builder $fincaQuery) => $fincaQuery->where('user_id', $userId)));
                })
                ->orWhere(function (Builder $recordQuery) use ($userId): void {
                    $recordQuery->where('fileable_type', ProductionRecord::class)
                        ->whereHasMorph('fileable', [ProductionRecord::class], fn (Builder $q) => $q->whereHas('finca', fn (Builder $f) => $f->where('user_id', $userId)));
                });
        });
    }

    private function appendFileUrl(ArchivoMultimedia $archivo): void
    {
        $archivo->setAttribute(
            'url_archivo',
            $archivo->ruta_archivo ? url('/api/campo/archivos/'.$archivo->id.'/descargar') : null,
        );
    }

    private function isSuperAdmin(Request $request): bool
    {
        return (bool) $request->user()?->isSuperAdmin();
    }

    private function adminAccessDenied(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'No tienes permisos para acceder a esta funcionalidad.',
        ], 403);
    }

    private function archivoNoEncontrado(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Evidencia multimedia no encontrada.',
        ], 404);
    }
}
