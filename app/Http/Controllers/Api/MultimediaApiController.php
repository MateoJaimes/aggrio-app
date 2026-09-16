<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\ArchivoMultimedia;
use App\Models\Finca;
use App\Models\Lote;
use App\Services\TranscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

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
            'categoria' => 'required|in:seguimiento,enfermedad',
            'contenido_texto' => 'nullable|string',
        ]);

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

        $archivo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Evidencia multimedia eliminada exitosamente.',
        ], 200);
    }

    /**
     * Guarda los registros de los archivos que Flutter ya subió a MinIO
     * y los enlaza polimórficamente a un Lote, Finca o Actividad.
     */
    public function store(Request $request, TranscriptionService $transcription): JsonResponse
    {
        // 1. Validar el JSON ligero (Ya no exigimos 'file', solo texto y arrays)
        $request->validate([
            'modelo_tipo' => 'required|string|in:actividad,lote,finca', 
            'modelo_id'   => 'required|integer',
            'archivos_subidos' => 'nullable|array',
            'archivos_subidos.*.ruta_archivo' => 'required_with:archivos_subidos|string',
            'archivos_subidos.*.tipo_archivo' => 'required_with:archivos_subidos|string',
            'archivos_subidos.*.peso_bytes'   => 'required_with:archivos_subidos|numeric',
            'texto'       => 'nullable|string',
            'categoria'   => 'nullable|string|in:seguimiento,enfermedad'
        ]);

        if (empty($request->archivos_subidos) && !$request->filled('texto')) {
            return response()->json([
                'success' => false,
                'message' => 'Debes enviar al menos los datos de un archivo o una nota de texto.'
            ], 400);
        }

        // 2. RASTREO JERÁRQUICO: Averiguar a qué Finca pertenece este archivo
        $finca = null;

        if ($request->modelo_tipo === 'finca') {
            $finca = \App\Models\Finca::find($request->modelo_id);
        } elseif ($request->modelo_tipo === 'lote') {
            $lote = \App\Models\Lote::find($request->modelo_id);
            $finca = $lote ? $lote->finca : null;
        } elseif ($request->modelo_tipo === 'actividad') {
            $actividad = \App\Models\Actividad::find($request->modelo_id);
            $finca = ($actividad && $actividad->lote) ? $actividad->lote->finca : null;
        }

        // 3. CANDADO MAESTRO: Seguridad
        if (!$finca || $finca->user_id !== $request->user()->id || $finca->estado !== 'aprobado') {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Finca bloqueada o inexistente.'
            ], 403);
        }

        // 4. Preparar el modelo polimórfico
        $modeloClase = match ($request->modelo_tipo) {
            'finca'     => \App\Models\Finca::class,
            'lote'      => \App\Models\Lote::class,
            'actividad' => \App\Models\Actividad::class,
        };

        $entidad = $modeloClase::findOrFail($request->modelo_id);
        $archivosGuardados = [];
        $categoria = $request->categoria ?? 'seguimiento';
        $textoTranscrito = $request->texto;

        // 5. Guardar Archivos en la Base de Datos (Flutter ya los subió a MinIO)
        if (!empty($request->archivos_subidos)) {
            foreach ($request->archivos_subidos as $archivo) {
                $esAudio = in_array($archivo['tipo_archivo'], ['audio', 'nota_audio']);
                
                $textoFinal = $textoTranscrito;

                // Si es audio y no hay texto, intentar transcribir
                if ($esAudio && empty($textoFinal)) {
                    $textoFinal = $transcription->transcribeAudio($archivo['ruta_archivo']);
                }

                $archivosGuardados[] = \App\Models\ArchivoMultimedia::create([
                    'fileable_type' => $modeloClase,  
                    'fileable_id'   => $entidad->id,  
                    'ruta_archivo'  => $archivo['ruta_archivo'],
                    'tipo_archivo'  => $archivo['tipo_archivo'],
                    'peso_bytes'    => $archivo['peso_bytes'],
                    'categoria'     => $categoria,
                    'contenido_texto' => $textoFinal,
                ]);
            }
        }

        // 6. Guardar Texto (si no viene de transcripción de audio)
        if ($request->filled('texto') && empty($request->archivos_subidos)) {
            $archivosGuardados[] = \App\Models\ArchivoMultimedia::create([
                'fileable_type'   => $modeloClase,
                'fileable_id'     => $entidad->id,
                'contenido_texto' => $request->texto,
                'tipo_archivo'    => 'nota_texto',
                'categoria'       => $categoria,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($archivosGuardados) . ' registro(s) multimedia guardado(s) correctamente.',
            'data'    => $archivosGuardados
        ], 201);
    }

    private function adminMultimediaQuery(): Builder
    {
        return ArchivoMultimedia::query()->with([
            'fileable' => function (MorphTo $morphTo): void {
                $morphTo->morphWith([
                    Finca::class => ['user'],
                    Lote::class => ['finca.user'],
                    Actividad::class => ['lote.finca.user'],
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
                });
        });
    }

    private function appendFileUrl(ArchivoMultimedia $archivo): void
    {
        $archivo->setAttribute(
            'url_archivo',
            $archivo->ruta_archivo ? Storage::disk('s3')->url($archivo->ruta_archivo) : null,
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
