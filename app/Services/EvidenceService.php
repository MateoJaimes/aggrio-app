<?php

namespace App\Services;

use App\Jobs\TranscribeEvidence;
use App\Models\ArchivoMultimedia;
use App\Models\UploadTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EvidenceService
{
    public function __construct(private readonly FieldAccess $access) {}

    public function prepare(Request $r, bool $sign = true): array
    {
        $data = $r->validate(['modelo_tipo' => 'required|in:finca,lote,actividad,registro', 'modelo_id' => 'required|integer',
            'filename' => 'required|string|max:255', 'mime_type' => 'required|string|max:100', 'size_bytes' => 'nullable|integer|min:1',
            'categoria' => 'nullable|in:seguimiento,enfermedad']);
        $this->access->entity($r, $data['modelo_tipo'], $data['modelo_id'], true);
        $ext = config('evidence.mime_extensions')[$data['mime_type']] ?? null;
        if (! $ext) {
            throw ValidationException::withMessages(['mime_type' => 'Tipo de archivo no admitido.']);
        }
        $limit = str_starts_with($data['mime_type'], 'video/') ? config('evidence.video_limit') : config('evidence.file_limit');
        if (($data['size_bytes'] ?? 1) > $limit) {
            throw ValidationException::withMessages(['size_bytes' => 'El archivo supera el tamaño permitido.']);
        }
        $ticket = DB::transaction(function () use ($r, $data, $limit, $ext) {
            User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $quota = ArchivoMultimedia::withTrashed()->where('ruta_archivo', 'like', 'documentos/usuario_'.$r->user()->id.'/%')->sum('peso_bytes');
            $reserved = UploadTicket::where('user_id', $r->user()->id)->whereNull('consumed_at')->where('expires_at', '>', now())->sum('size_bytes');
            if ($quota + $reserved + ($data['size_bytes'] ?? $limit) > config('evidence.user_quota')) {
                throw ValidationException::withMessages(['archivo' => 'Se ha alcanzado la cuota de almacenamiento.']);
            }

            return UploadTicket::create([
                'id' => (string) Str::uuid(), 'user_id' => $r->user()->id, 'modelo_tipo' => $data['modelo_tipo'], 'modelo_id' => $data['modelo_id'],
                'storage_disk' => config('evidence.disk'),
                'file_key' => 'documentos/usuario_'.$r->user()->id.'/'.Str::uuid().'.'.$ext,
                'mime_type' => $data['mime_type'], 'size_bytes' => $data['size_bytes'] ?? $limit, 'expires_at' => now()->addMinutes(15),
            ]);
        }, 3);
        if (! $sign) {
            return ['upload_id' => $ticket->id, 'file_key' => $ticket->file_key];
        }
        if ($ticket->storage_disk === 'local') {
            return ['upload_id' => $ticket->id, 'file_key' => $ticket->file_key,
                'upload_url' => URL::temporarySignedRoute('evidence.upload', $ticket->expires_at, ['ticket' => $ticket->id]),
                'upload_headers' => ['Content-Type' => $ticket->mime_type], 'expires_at' => $ticket->expires_at];
        }
        try {
            $signed = Storage::disk(config('evidence.disk'))->temporaryUploadUrl($ticket->file_key, $ticket->expires_at, ['ContentType' => $data['mime_type']]);
        } catch (\Throwable $e) {
            $ticket->delete();
            report($e);
            abort(503, 'El almacenamiento no está disponible. Intenta de nuevo.');
        }

        return ['upload_id' => $ticket->id, 'file_key' => $ticket->file_key, 'upload_url' => is_array($signed) ? $signed['url'] : $signed,
            'upload_headers' => is_array($signed) ? ($signed['headers'] ?? []) : [], 'expires_at' => $ticket->expires_at];
    }

    public function upload(Request $r): array
    {
        $r->validate(['archivo' => 'required|file|max:51200']);
        $file = $r->file('archivo');
        $r->merge(['filename' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize()]);
        $ticket = $this->prepare($r, false);
        $disk = Storage::disk(config('evidence.disk'));
        try {
            $stored = $file->storeAs(dirname($ticket['file_key']), basename($ticket['file_key']), ['disk' => config('evidence.disk'), 'visibility' => 'private']);
            abort_unless($stored, 503, 'No se pudo guardar el archivo.');
            $r->merge(['archivos_subidos' => [['upload_id' => $ticket['upload_id'], 'ruta_archivo' => $ticket['file_key']]]]);

            return $this->finalize($r);
        } catch (\Throwable $e) {
            $finalKey = 'documentos/usuario_'.$r->user()->id.'/evidencia/'.$ticket['upload_id'].'.'.pathinfo($ticket['file_key'], PATHINFO_EXTENSION);
            try {
                $disk->delete($ticket['file_key']);
                if (! ArchivoMultimedia::withTrashed()->where('ruta_archivo', $finalKey)->exists()) {
                    $disk->delete($finalKey);
                }
            } catch (\Throwable $cleanup) {
                report($cleanup);
            }
            throw $e;
        }
    }

    public function finalize(Request $r): array
    {
        $data = $r->validate(['modelo_tipo' => 'required|in:finca,lote,actividad,registro', 'modelo_id' => 'required|integer',
            'texto' => 'nullable|string|max:10000', 'categoria' => 'nullable|in:seguimiento,enfermedad',
            'archivos_subidos' => 'nullable|array|max:'.config('evidence.max_files'),
            'archivos_subidos.*.upload_id' => 'nullable|uuid', 'archivos_subidos.*.ruta_archivo' => 'required|string|max:255',
            'captured_at' => 'nullable|date|before_or_equal:now', 'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180', 'gps_precision' => 'nullable|numeric|min:0|max:100000']);
        $entity = $this->access->entity($r, $data['modelo_tipo'], $data['modelo_id'], true);
        if (empty($data['archivos_subidos']) && ! $r->filled('texto')) {
            throw ValidationException::withMessages(['texto' => 'Añade una nota o un archivo.']);
        }
        $metadata = array_intersect_key($data, array_flip(['captured_at', 'latitud', 'longitud', 'gps_precision']));

        return DB::transaction(function () use ($r, $data, $entity, $metadata) {
            $result = [];
            foreach ($data['archivos_subidos'] ?? [] as $item) {
                $query = UploadTicket::where('user_id', $r->user()->id)->where('file_key', $item['ruta_archivo'])
                    ->where('modelo_tipo', $data['modelo_tipo'])->where('modelo_id', $data['modelo_id']);
                if (! empty($item['upload_id'])) {
                    $query->whereKey($item['upload_id']);
                }
                $ticket = $query->lockForUpdate()->first();
                if (! $ticket || $ticket->consumed_at || $ticket->expires_at->isPast()) {
                    throw ValidationException::withMessages(['archivos_subidos' => 'La autorización de carga no es válida, ya fue usada o caducó.']);
                }
                $disk = Storage::disk($ticket->storage_disk);
                try {
                    if (! $disk->exists($ticket->file_key)) {
                        throw ValidationException::withMessages(['archivos_subidos' => 'La carga todavía no existe en el almacenamiento.']);
                    }
                    $size = $disk->size($ticket->file_key);
                    $stream = $disk->readStream($ticket->file_key);
                    if (! is_resource($stream)) {
                        abort(503, 'No se pudo verificar el archivo.');
                    }
                    try {
                        $prefix = fread($stream, 65536);
                    } finally {
                        fclose($stream);
                    }
                    $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($prefix);
                } catch (ValidationException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    report($e);
                    abort(503, 'No se pudo verificar el archivo.');
                }
                // ISO BMFF containers may report video/mp4 even for audio-only M4A.
                $audioMp4 = in_array($ticket->mime_type, ['audio/mp4', 'audio/x-m4a'], true) && in_array($mime, ['audio/mp4', 'audio/x-m4a', 'video/mp4'], true);
                $wav = in_array($ticket->mime_type, ['audio/wav', 'audio/x-wav'], true) && in_array($mime, ['audio/wav', 'audio/x-wav'], true);
                if ($size < 1 || $size > $ticket->size_bytes || (! $audioMp4 && ! $wav && $mime !== $ticket->mime_type)) {
                    throw ValidationException::withMessages(['archivos_subidos' => 'El tamaño o contenido real del archivo no coincide con la autorización.']);
                }
                // The signed upload remains reusable until expiry. Promote a copy
                // to an immutable key so it can never overwrite registered evidence.
                $finalKey = 'documentos/usuario_'.$r->user()->id.'/evidencia/'.$ticket->id.'.'.pathinfo($ticket->file_key, PATHINFO_EXTENSION);
                abort_unless($disk->copy($ticket->file_key, $finalKey), 503, 'No se pudo confirmar el archivo.');
                $disk->setVisibility($finalKey, 'private');
                $finalStream = $disk->readStream($finalKey);
                abort_unless(is_resource($finalStream), 503, 'No se pudo verificar el archivo confirmado.');
                try {
                    $finalMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer(fread($finalStream, 65536));
                } finally {
                    fclose($finalStream);
                }
                if ($disk->size($finalKey) !== $size || $finalMime !== $mime) {
                    $disk->delete($finalKey);
                    throw ValidationException::withMessages(['archivos_subidos' => 'La carga cambió durante la confirmación. Intenta de nuevo.']);
                }
                $kind = explode('/', $ticket->mime_type)[0];
                $a = $entity->archivos()->create($metadata + [
                    'ruta_archivo' => $finalKey, 'storage_disk' => $ticket->storage_disk, 'peso_bytes' => $size,
                    'tipo_archivo' => match ($kind) {
                        'image' => 'imagen', 'audio' => 'audio', 'video' => 'video', default => 'documento'
                    },
                    'categoria' => $data['categoria'] ?? 'seguimiento', 'contenido_texto' => $data['texto'] ?? null,
                    'transcription_status' => $kind === 'audio' ? 'pending' : 'not_requested',
                ]);
                $ticket->update(['consumed_at' => now()]);
                DB::afterCommit(function () use ($disk, $ticket) {
                    try {
                        $disk->delete($ticket->file_key);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });
                if ($kind === 'audio') {
                    TranscribeEvidence::dispatch($a->id)->afterCommit();
                }
                $result[] = $a;
            }
            if (empty($data['archivos_subidos'])) {
                $result[] = $entity->archivos()->create($metadata + ['tipo_archivo' => 'nota_texto', 'contenido_texto' => $data['texto'], 'categoria' => $data['categoria'] ?? 'seguimiento']);
            }

            return $result;
        }, 3);
    }
}
