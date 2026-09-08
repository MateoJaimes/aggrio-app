<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lote;
use App\Models\LecturaIot;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LecturaIotApiController extends Controller
{
    /**
     * 1. ENDPOINT PARA EL SENSOR (POST): El dispositivo físico inyecta datos aquí.
     */
    public function store(Request $request): JsonResponse
    {
        // Validación estricta de los datos del sensor
        $request->validate([
            'lote_id'         => 'required|integer|exists:lotes,id',
            'mac_dispositivo' => 'nullable|string|max:50',
            'tipo_medicion'   => 'required|string|in:temperatura,humedad_suelo,radiacion_solar,humedad_ambiente',
            'valor'           => 'required|numeric',
            'unidad'          => 'required|string|max:10'
        ]);

        // Guardar la lectura en el Data Warehouse
        $lectura = LecturaIot::create([
            'lote_id'         => $request->lote_id,
            'mac_dispositivo' => $request->mac_dispositivo,
            'tipo_medicion'   => $request->tipo_medicion,
            'valor'           => $request->valor,
            'unidad'          => $request->unidad,
            'fecha_medicion'  => now() // Marca de tiempo exacta del servidor
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Métrica de telemetría almacenada correctamente.',
            'data'    => $lectura
        ], 201);
    }

    /**
     * 2. ENDPOINT PARA FLUTTER (GET): La app móvil descarga el historial filtrado por lote.
     */
    public function index(Request $request, $lote_id): JsonResponse
    {
        // Seguridad: Verificar que el lote pertenezca a una finca propiedad del usuario autenticado
        $lote = Lote::with('finca')->where('id', $lote_id)->first();

        if (!$lote || $lote->finca->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Lote no encontrado o acceso denegado.'
            ], 404);
        }

        // Obtener las últimas 50 lecturas del lote para no saturar la pantalla del celular
        $lecturas = $lote->lecturasIot()
                         ->orderBy('fecha_medicion', 'desc')
                         ->take(50)
                         ->get();

        return response()->json([
            'success' => true,
            'data'    => $lecturas
        ], 200);
    }
}