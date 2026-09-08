<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActividadApiController extends Controller
{
    /**
     * Registra una nueva labor agrícola en un lote específico desde Flutter
     */
    public function store(Request $request, $lote_id): JsonResponse
    {
        // 1. Validar los datos que envía la app móvil
        $request->validate([
            'tipo_actividad' => 'required|string|max:255',
            'fecha'          => 'required|date',
            'costo'          => 'required|numeric|min:0',
            'observaciones'  => 'nullable|string'
        ]);

        // 2. Buscar el lote y asegurar que la finca dueña le pertenezca al usuario autenticado
        $lote = Lote::with('finca')->where('id', $lote_id)->first();

        if (!$lote || $lote->finca->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Lote no encontrado o acceso denegado.'
            ], 404);
        }

        // 3. Validar que el lote permita registrar actividades
        if (!in_array($lote->estado, ['disponible', 'en_uso'])) {
            return response()->json([
                'success' => false,
                'message' => 'El lote no permite registrar actividades. Estado actual: ' . $lote->estado
            ], 403);
        }

        // 4. Crear la actividad en la base de datos
        $actividad = $lote->actividades()->create([
            'tipo_actividad' => $request->tipo_actividad,
            'fecha'          => $request->fecha,
            'costo'          => $request->costo,
            'observaciones'  => $request->observaciones
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Actividad registrada exitosamente en el campo.',
            'data'    => $actividad
        ], 201);
    }

    /**
     * Devuelve el historial de labores agrícolas de un lote específico
     */
    public function index(Request $request, $lote_id): JsonResponse
    {
        // 1. Buscar el lote y validar permisos (usamos has() o with() para seguridad)
        $lote = Lote::with('finca')->where('id', $lote_id)->first();

        if (!$lote || $lote->finca->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Lote no encontrado o acceso denegado.'
            ], 404);
        }

        // 2. Obtener las actividades ordenadas por la más reciente
        $actividades = $lote->actividades()->orderBy('fecha', 'desc')->get();

        return response()->json([
            'success' => true,
            'data'    => $actividades
        ], 200);
    }
}