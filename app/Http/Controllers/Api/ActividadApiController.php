<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Lote;
use App\Services\FieldAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActividadApiController extends Controller
{
    private const TIPOS_ACTIVIDAD = [
        'preparacion',
        'siembra',
        'fertilizacion',
        'riego',
        'control_plagas',
        'poda',
        'cosecha',
        'mantenimiento',
    ];

    /**
     * Listar globalmente las actividades agrícolas para el panel Superadmin.
     */
    public function indexAdmin(Request $request): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $request->validate([
            'tipo_actividad' => 'nullable|in:'.implode(',', self::TIPOS_ACTIVIDAD),
        ]);

        $actividades = Actividad::with(['lote.finca.user'])
            ->when(
                $request->filled('tipo_actividad'),
                fn ($query) => $query->where('tipo_actividad', $request->string('tipo_actividad')->toString())
            )
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Actividades recuperadas con éxito.',
            'data' => $actividades,
        ], 200);
    }

    /**
     * Consultar una actividad globalmente desde el panel Superadmin.
     */
    public function showAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $actividad = Actividad::with(['lote.finca.user'])->find($id);

        if (! $actividad) {
            return $this->actividadNoEncontrada();
        }

        return response()->json([
            'success' => true,
            'data' => $actividad,
        ], 200);
    }

    /**
     * Crear una actividad en cualquier lote desde el panel Superadmin.
     */
    public function storeAdmin(Request $request): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $data = $this->validateAdminActividad($request);
        app(FieldAccess::class)->entity($request, 'lote', $data['lote_id'], true);
        $actividad = Actividad::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Actividad creada exitosamente.',
            'data' => $actividad->load(['lote.finca.user']),
        ], 201);
    }

    /**
     * Actualizar una actividad desde el panel Superadmin.
     */
    public function updateAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $actividad = Actividad::find($id);

        if (! $actividad) {
            return $this->actividadNoEncontrada();
        }

        $data = $this->validateAdminActividad($request);
        abort_if((int) $data['lote_id'] !== $actividad->lote_id, 422, 'No se puede trasladar una actividad.');
        $request->validate(['motivo' => 'required|string|max:1000']);
        app(FieldAccess::class)->entity($request, 'lote', $data['lote_id'], true);
        $actividad->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Actividad actualizada exitosamente.',
            'data' => $actividad->fresh()->load(['lote.finca.user']),
        ], 200);
    }

    /**
     * Eliminar una actividad desde el panel Superadmin.
     */
    public function destroyAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $actividad = Actividad::find($id);

        if (! $actividad) {
            return $this->actividadNoEncontrada();
        }

        $request->validate(['motivo' => 'required|string|max:1000']);
        $actividad->delete();

        return response()->json([
            'success' => true,
            'message' => 'Actividad anulada; su historial se conserva.',
        ], 200);
    }

    /**
     * Registra una nueva labor agrícola en un lote específico desde Flutter
     */
    public function store(Request $request, $lote_id): JsonResponse
    {
        return app(CampoApiController::class)->guardarActividad($request, (int) $lote_id);
    }

    /**
     * Devuelve el historial de labores agrícolas de un lote específico
     */
    public function index(Request $request, $lote_id): JsonResponse
    {
        // 1. Buscar el lote y validar permisos (usamos has() o with() para seguridad)
        $lote = Lote::with('finca')->where('id', $lote_id)->first();

        if (! $lote || $lote->finca->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Lote no encontrado o acceso denegado.',
            ], 404);
        }

        // 2. Obtener las actividades ordenadas por la más reciente
        $actividades = $lote->actividades()->orderBy('fecha', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $actividades,
        ], 200);
    }

    private function validateAdminActividad(Request $request): array
    {
        return $request->validate([
            'lote_id' => 'required|integer|exists:lotes,id',
            'tipo_actividad' => 'required|in:'.implode(',', self::TIPOS_ACTIVIDAD),
            'fecha' => 'required|date|before_or_equal:today',
            'costo' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string',
        ]);
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

    private function actividadNoEncontrada(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Actividad no encontrada.',
        ], 404);
    }
}
