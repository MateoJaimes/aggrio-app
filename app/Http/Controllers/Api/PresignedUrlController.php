<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Finca;
use App\Models\Lote;
use App\Models\Actividad;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PresignedUrlController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'modelo_tipo' => 'required|string|in:finca,lote,actividad',
            'modelo_id'   => 'required|integer',
            'filename'    => 'required|string',
            'mime_type'   => 'required|string',
            'categoria'   => 'nullable|string|in:seguimiento,enfermedad',
        ]);

        $finca = null;

        if ($request->modelo_tipo === 'finca') {
            $finca = Finca::find($request->modelo_id);
        } elseif ($request->modelo_tipo === 'lote') {
            $lote = Lote::find($request->modelo_id);
            $finca = $lote ? $lote->finca : null;
        } elseif ($request->modelo_tipo === 'actividad') {
            $actividad = Actividad::find($request->modelo_id);
            $finca = ($actividad && $actividad->lote) ? $actividad->lote->finca : null;
        }

        if (!$finca || $finca->user_id !== $request->user()->id || $finca->estado !== 'aprobado') {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Finca bloqueada o inexistente.',
            ], 403);
        }

        $extension = pathinfo($request->filename, PATHINFO_EXTENSION) ?: 'bin';
        $userId = $request->user()->id;
        $categoria = $request->categoria ?? 'seguimiento';

        $tipoCarpeta = match (true) {
            str_starts_with($request->mime_type, 'image/') => 'imagenes',
            str_starts_with($request->mime_type, 'audio/') => 'audios',
            str_starts_with($request->mime_type, 'video/') => 'videos',
            default => 'documentos',
        };

        $fileKey = "documentos/usuario_{$userId}/{$categoria}/{$tipoCarpeta}/" . Str::uuid() . '.' . $extension;

        $disk = Storage::disk('s3');

        $uploadUrl = $disk->temporaryUploadUrl(
            $fileKey,
            now()->addMinutes(15),
            [
                'ContentType' => $request->mime_type,
            ]
        );

        $uploadUrlString = is_array($uploadUrl)
            ? ($uploadUrl['url'] ?? reset($uploadUrl))
            : (string) $uploadUrl;

        $fullUrl = $disk->url($fileKey);

        return response()->json([
            'success' => true,
            'data' => [
                'upload_url' => $uploadUrlString,
                'file_key'   => $fileKey,
                'full_url'   => $fullUrl,
            ],
        ], 200);
    }
}
