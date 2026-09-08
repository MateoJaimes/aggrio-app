<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TranscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MultimediaApiController extends Controller
{
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
}