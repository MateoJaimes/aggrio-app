<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DispositivoIot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Registro de sensores IoT y gestión de sus API keys desde el panel Superadmin. RF002.
 *
 * La key en claro sólo aparece en la respuesta de storeAdmin y regenerarKeyAdmin;
 * hay que copiarla al firmware del ESP32 en ese momento, no se puede consultar después.
 */
class DispositivoIotApiController extends Controller
{
    public function indexAdmin(Request $request): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $request->validate([
            'lote_id' => 'nullable|integer|exists:lotes,id',
        ]);

        $dispositivos = DispositivoIot::with(['lote.finca'])
            ->when($request->filled('lote_id'), fn ($query) => $query->where('lote_id', $request->integer('lote_id')))
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Dispositivos IoT recuperados con éxito.',
            'data' => $dispositivos,
        ], 200);
    }

    public function showAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $dispositivo = DispositivoIot::with(['lote.finca'])->find($id);

        if (! $dispositivo) {
            return $this->dispositivoNoEncontrado();
        }

        return response()->json([
            'success' => true,
            'data' => $dispositivo,
        ], 200);
    }

    /**
     * Registra un sensor y devuelve su API key (única vez que se muestra).
     */
    public function storeAdmin(Request $request): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $datos = $request->validate([
            'lote_id' => 'required|integer|exists:lotes,id',
            'nombre' => 'required|string|max:100',
            'mac_dispositivo' => 'nullable|string|max:50|unique:dispositivos_iot,mac_dispositivo',
        ]);

        $dispositivo = new DispositivoIot($datos);
        $apiKey = $dispositivo->asignarNuevaKey();
        $dispositivo->save();

        return response()->json([
            'success' => true,
            'message' => 'Dispositivo registrado. Copia la API key ahora: no se volverá a mostrar.',
            'data' => [
                'dispositivo' => $dispositivo->load(['lote.finca']),
                'api_key' => $apiKey,
            ],
        ], 201);
    }

    /**
     * Cambia nombre, MAC, lote o estado. Con activo=false el sensor deja de
     * poder enviar lecturas sin perder su registro.
     */
    public function updateAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $dispositivo = DispositivoIot::find($id);

        if (! $dispositivo) {
            return $this->dispositivoNoEncontrado();
        }

        $datos = $request->validate([
            'lote_id' => 'sometimes|integer|exists:lotes,id',
            'nombre' => 'sometimes|string|max:100',
            'mac_dispositivo' => [
                'sometimes', 'nullable', 'string', 'max:50',
                Rule::unique('dispositivos_iot', 'mac_dispositivo')->ignore($dispositivo->id),
            ],
            'activo' => 'sometimes|boolean',
        ]);

        $dispositivo->update($datos);

        return response()->json([
            'success' => true,
            'message' => 'Dispositivo IoT actualizado exitosamente.',
            'data' => $dispositivo->fresh()->load(['lote.finca']),
        ], 200);
    }

    /**
     * Invalida la key actual y entrega una nueva (p. ej. si se filtró).
     */
    public function regenerarKeyAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $dispositivo = DispositivoIot::find($id);

        if (! $dispositivo) {
            return $this->dispositivoNoEncontrado();
        }

        $apiKey = $dispositivo->asignarNuevaKey();
        $dispositivo->save();

        return response()->json([
            'success' => true,
            'message' => 'API key regenerada. La anterior ya no funciona; copia la nueva ahora.',
            'data' => [
                'dispositivo' => $dispositivo->load(['lote.finca']),
                'api_key' => $apiKey,
            ],
        ], 200);
    }

    public function destroyAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $dispositivo = DispositivoIot::find($id);

        if (! $dispositivo) {
            return $this->dispositivoNoEncontrado();
        }

        $dispositivo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dispositivo IoT eliminado exitosamente.',
        ], 200);
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

    private function dispositivoNoEncontrado(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Dispositivo IoT no encontrado.',
        ], 404);
    }
}
