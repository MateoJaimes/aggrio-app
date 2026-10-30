<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Finca;
use App\Services\EvidenceService;
use App\Services\FieldAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FincaApiController extends Controller
{
    /**
     * Obtener las fincas del usuario autenticado
     */
    public function misFincas(Request $request): JsonResponse
    {

        // CLAVE: Filtramos para que Flutter solo reciba las fincas con luz verde
        $fincas = $request->user()->fincas()->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'message' => 'Fincas recuperadas con éxito',
            'data' => $fincas,
        ], 200);
    }

    /**
     * Completar el perfil técnico de la finca (Onboarding progresivo desde Flutter)
     */
    public function completarPerfil(Request $request, $id): JsonResponse
    {
        // 1. Validar los datos técnicos de la finca
        $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
            'hectareas_totales' => 'required|numeric|min:0.01|max:999999.99|decimal:0,2',
            'tipo_suelo' => 'nullable|string|max:255',
        ]);

        // 2. Buscar la finca y asegurar que le pertenezca a este usuario
        $finca = Finca::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $finca) {
            return response()->json([
                'success' => false,
                'message' => 'Finca no encontrada o no tienes permisos para editarla.',
            ], 404);
        }

        // 🚀 NUEVO CANDADO DE SEGURIDAD
        if ($finca->estado !== 'aprobado') {
            return response()->json([
                'success' => false,
                'message' => 'No puedes modificar esta finca porque su estado actual es: '.$finca->estado,
            ], 403); // 403 = Prohibido
        }

        $finca->update($request->only([
            'nombre', 'latitud', 'longitud', 'hectareas_totales', 'tipo_suelo',
        ]));

        // 4. Devolver respuesta de éxito
        return response()->json([
            'success' => true,
            'message' => 'Perfil de la finca completado exitosamente',
            'data' => $finca,
        ], 200);
    }

    /**
     * Listar todas las fincas para el panel de administración Angular (RF001)
     */
    public function indexAdmin(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para acceder a esta funcionalidad.',
            ], 403);
        }

        $fincas = Finca::with('user')->orderBy('id')->get();

        return response()->json([
            'success' => true,
            'message' => 'Fincas recuperadas con éxito',
            'data' => $fincas,
        ], 200);
    }

    /**
     * Cambiar el estado de una finca desde el panel de administración Angular (RF002/RF003)
     */
    public function updateEstadoAdmin(Request $request, $id): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para acceder a esta funcionalidad.',
            ], 403);
        }

        $request->validate([
            'accion' => 'required|in:aprobar,rechazar',
        ]);

        $finca = Finca::find($id);

        if (! $finca) {
            return response()->json([
                'success' => false,
                'message' => 'Finca no encontrada.',
            ], 404);
        }

        if ($finca->estado !== 'pendiente') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede cambiar el estado de fincas en estado pendiente.',
            ], 422);
        }

        $nuevoEstado = $request->accion === 'aprobar' ? 'aprobado' : 'rechazado';
        $finca->update(['estado' => $nuevoEstado]);

        return response()->json([
            'success' => true,
            'message' => 'Estado de la finca actualizado exitosamente',
            'data' => $finca,
        ], 200);
    }

    /**
     * Crear una finca desde el panel de administración Angular (RF004)
     */
    public function storeAdmin(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para acceder a esta funcionalidad.',
            ], 403);
        }

        $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('role', 'productor')],
            'nombre' => 'sometimes|string|max:255',
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
            'hectareas_totales' => 'required|numeric|min:0.01|max:999999.99|decimal:0,2',
            'tipo_suelo' => 'nullable|string|max:255',
        ]);

        app(FieldAccess::class)->approvedProducer((int) $request->user_id);
        $finca = Finca::create($request->only([
            'user_id', 'nombre', 'latitud', 'longitud', 'hectareas_totales', 'tipo_suelo',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Finca creada exitosamente',
            'data' => $finca->load('user'),
        ], 201);
    }

    /**
     * Editar una finca desde el panel de administración Angular (RF004)
     */
    public function updateAdmin(Request $request, $id): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para acceder a esta funcionalidad.',
            ], 403);
        }

        $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('role', 'productor')],
            'nombre' => 'sometimes|string|max:255',
            'latitud' => 'required|numeric|between:-90,90',
            'longitud' => 'required|numeric|between:-180,180',
            'hectareas_totales' => 'required|numeric|min:0.01|max:999999.99|decimal:0,2',
            'tipo_suelo' => 'nullable|string|max:255',
        ]);

        $finca = Finca::find($id);

        if (! $finca) {
            return response()->json([
                'success' => false,
                'message' => 'Finca no encontrada.',
            ], 404);
        }

        app(FieldAccess::class)->approvedProducer((int) $request->user_id);
        $finca->update($request->only([
            'user_id', 'nombre', 'latitud', 'longitud', 'hectareas_totales', 'tipo_suelo',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Finca actualizada exitosamente',
            'data' => $finca->fresh()->load('user'),
        ], 200);
    }

    /**
     * Subir archivos al Data Lake (Fotos, Audios, etc.) y vincularlos a la Finca
     */
    public function subirMultimedia(Request $request, $id): JsonResponse
    {
        $request->merge(['modelo_tipo' => 'finca', 'modelo_id' => (int) $id]);

        return response()->json(['success' => true, 'data' => app(EvidenceService::class)->upload($request)], 201);
    }

    /**
     * Solicitar una nueva finca (Nace en estado 'pendiente')
     */
    public function solicitar(Request $request): JsonResponse
    {
        // Validamos que al menos envíe un nombre tentativo para la finca
        $request->validate([
            'nombre' => 'required|string|max:255',
        ]);

        // Creamos la finca amarrada al usuario actual, explicitando el estado pendiente
        $finca = Finca::create([
            'user_id' => $request->user()->id,
            'nombre' => $request->nombre,
            'estado' => 'pendiente', // 🚀 Clave: Nace bloqueada
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de finca creada con éxito. En espera de aprobación del administrador.',
            'data' => $finca,
        ], 201);
    }
}
