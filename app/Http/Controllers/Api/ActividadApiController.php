<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Lote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
            'tipo_actividad' => 'nullable|in:' . implode(',', self::TIPOS_ACTIVIDAD),
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

        $actividad = Actividad::create($this->validateAdminActividad($request));

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

        $actividad->update($this->validateAdminActividad($request));

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

        $actividad->delete();

        return response()->json([
            'success' => true,
            'message' => 'Actividad eliminada exitosamente.',
        ], 200);
    }

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

    private function validateAdminActividad(Request $request): array
    {
        return $request->validate([
            'lote_id' => 'required|integer|exists:lotes,id',
            'tipo_actividad' => 'required|in:' . implode(',', self::TIPOS_ACTIVIDAD),
            'fecha' => 'required|date',
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
