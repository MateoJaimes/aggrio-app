<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Finca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FincaApiController extends Controller
{
    /**
     * Obtener las fincas del usuario autenticado
     */
    public function misFincas(Request $request): JsonResponse
    {

        // CLAVE: Filtramos para que Flutter solo reciba las fincas con luz verde
        $fincas = $request->user()->fincas()->where('estado', 'aprobado')->get();

        return response()->json([
            'success' => true,
            'message' => 'Fincas recuperadas con éxito',
            'data'    => $fincas
        ], 200);
    }

    /**
     * Completar el perfil técnico de la finca (Onboarding progresivo desde Flutter)
     */
    public function completarPerfil(Request $request, $id): JsonResponse
    {
       // 1. Validar los datos técnicos de la finca
        $request->validate([
            'nombre'            => 'sometimes|string|max:255',
            'latitud'           => 'required|numeric|between:-90,90',
            'longitud'          => 'required|numeric|between:-180,180',
            'hectareas_totales' => 'required|numeric|min:0',
            'tipo_suelo'        => 'nullable|string|max:255',
        ]);

        // 2. Buscar la finca y asegurar que le pertenezca a este usuario
        $finca = Finca::where('id', $id)
                      ->where('user_id', $request->user()->id)
                      ->first();

        if (!$finca) {
            return response()->json([
                'success' => false,
                'message' => 'Finca no encontrada o no tienes permisos para editarla.'
            ], 404);
        }

        // 🚀 NUEVO CANDADO DE SEGURIDAD
        if ($finca->estado !== 'aprobado') {
            return response()->json([
                'success' => false,
                'message' => 'No puedes modificar esta finca porque su estado actual es: ' . $finca->estado
            ], 403); // 403 = Prohibido
        }
        
        $finca->update($request->only([
            'nombre', 'latitud', 'longitud', 'hectareas_totales', 'tipo_suelo'
        ]));

        // 4. Devolver respuesta de éxito
        return response()->json([
            'success' => true,
            'message' => 'Perfil de la finca completado exitosamente',
            'data'    => $finca
        ], 200);
    }

    /**
     * Subir archivos al Data Lake (Fotos, Audios, etc.) y vincularlos a la Finca
     */
    public function subirMultimedia(Request $request, $id): JsonResponse
    {
        // 1. Validar que venga un archivo y el tipo de archivo (máx 10MB)
        $request->validate([
            'archivo'      => 'required|file|mimes:jpg,jpeg,png,mp3,wav,mp4,m4a|max:10240',
            'tipo_archivo' => 'required|string', // Ej: 'foto_finca', 'foto_plaga', 'nota_audio'
        ]);

        // 2. Verificar que la finca existe y pertenece al usuario autenticado
        $finca = Finca::where('id', $id)
                      ->where('user_id', $request->user()->id)
                      ->first();

        if (!$finca) {
            return response()->json([
                'success' => false,
                'message' => 'Finca no encontrada o sin permisos.'
            ], 404);
        }

        // 3. Procesar el archivo
        if ($request->hasFile('archivo')) {
            // Guardar físicamente el archivo en la carpeta public/fincas
            $path = $request->file('archivo')->store('fincas', 'public');

            // 4. Registrar el archivo en la base de datos (Magia Polimórfica)
            $multimedia = $finca->archivos()->create([
                'ruta_archivo' => $path,
                'tipo_archivo' => $request->tipo_archivo,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Archivo subido correctamente al Data Lake',
                'data'    => [
                    'id'   => $multimedia->id,
                    'url'  => asset('storage/' . $path), // Genera el link público para Flutter
                    'tipo' => $multimedia->tipo_archivo
                ]
            ], 201);
        }

        return response()->json(['success' => false, 'message' => 'Error al recibir el archivo'], 400);
    }

    /**
     * Solicitar una nueva finca (Nace en estado 'pendiente')
     */
    public function solicitar(Request $request): \Illuminate\Http\JsonResponse
    {
        // Validamos que al menos envíe un nombre tentativo para la finca
        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        // Creamos la finca amarrada al usuario actual, explicitando el estado pendiente
        $finca = \App\Models\Finca::create([
            'user_id' => $request->user()->id,
            'nombre'  => $request->nombre,
            'estado'  => 'pendiente', // 🚀 Clave: Nace bloqueada
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de finca creada con éxito. En espera de aprobación del administrador.',
            'data'    => $finca
        ], 201);
    }
}
