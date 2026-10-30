<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DispositivoIot;
use App\Models\Finca;
use App\Models\Lote;
use App\Services\FieldAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoteApiController extends Controller
{
    /**
     * Listar todos los lotes del sistema para el panel Superadmin.
     */
    public function indexAdmin(Request $request): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $request->validate([
            'finca_id' => 'nullable|integer|exists:fincas,id',
        ]);

        $lotes = Lote::with(['finca.user'])
            ->when($request->filled('finca_id'), fn ($query) => $query->where('finca_id', $request->integer('finca_id')))
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Lotes recuperados con éxito.',
            'data' => $lotes,
        ], 200);
    }

    /**
     * Consultar un lote globalmente desde el panel Superadmin.
     */
    public function showAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $lote = Lote::with(['finca.user'])->find($id);

        if (! $lote) {
            return response()->json([
                'success' => false,
                'message' => 'Lote no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $lote,
        ], 200);
    }

    /**
     * Crear un lote en cualquier finca desde el panel Superadmin.
     */
    public function storeAdmin(Request $request): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $data = $this->validateAdminLote($request);
        app(FieldAccess::class)->operational(Finca::findOrFail($data['finca_id']));
        $lote = Lote::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Lote creado exitosamente.',
            'data' => $lote->load(['finca.user']),
        ], 201);
    }

    /**
     * Actualizar un lote desde el panel Superadmin.
     */
    public function updateAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $lote = Lote::find($id);

        if (! $lote) {
            return response()->json([
                'success' => false,
                'message' => 'Lote no encontrado.',
            ], 404);
        }

        $data = $this->validateAdminLote($request);
        app(FieldAccess::class)->operational(Finca::findOrFail($data['finca_id']));
        $lote->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Lote actualizado exitosamente.',
            'data' => $lote->fresh()->load(['finca.user']),
        ], 200);
    }

    /**
     * Eliminar un lote desde el panel Superadmin.
     */
    public function destroyAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $lote = Lote::find($id);

        if (! $lote) {
            return response()->json([
                'success' => false,
                'message' => 'Lote no encontrado.',
            ], 404);
        }

        $request->validate(['motivo' => 'required|string|max:1000']);
        DB::transaction(function () use ($lote) {
            $lote->delete();
            DispositivoIot::where('lote_id', $lote->id)->update(['activo' => false]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Lote archivado; su historial se conserva.',
        ], 200);
    }

    /**
     * Devuelve todos los lotes pertenecientes a una finca específica
     */
    public function index(Request $request, $finca_id): JsonResponse
    {
        // 1. Validar que la finca exista y pertenezca al usuario del Token y su estado sea aprovado
        $finca = Finca::where('id', $finca_id)
            ->where('user_id', $request->user()->id)
            ->first();

        // CLAVE: Validar que la finca esté aprobada antes de mostrar sus lotes

        if (! $finca || $finca->estado !== 'aprobado') {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. La finca no existe o aún no ha sido aprobada.',
            ], 403);
        }

        // 2. Obtener los lotes de la finca
        $lotes = $finca->lotes()->get();

        return response()->json([
            'success' => true,
            'data' => $lotes,
        ], 200);
    }

    /**
     * Permite al agricultor crear un nuevo lote desde la app móvil (Flutter)
     */
    public function store(Request $request, $finca_id): JsonResponse
    {
        return app(CampoApiController::class)->guardarLote($request, (int) $finca_id);
    }

    /**
     * Permite al agricultor cambiar el estado de un lote (disponible, en_uso, no_disponible)
     */
    public function updateEstado(Request $request, $lote_id): JsonResponse
    {
        $request->validate([
            'estado' => 'required|in:disponible,en_uso,no_disponible',
        ]);

        $lote = Lote::with('finca')->where('id', $lote_id)->first();

        if (! $lote || ! $lote->finca || $lote->finca->user_id !== $request->user()->id || $lote->finca->estado !== 'aprobado') {
            return response()->json([
                'success' => false,
                'message' => 'Lote no encontrado o acceso denegado.',
            ], 404);
        }

        $lote->update(['estado' => $request->estado]);

        return response()->json([
            'success' => true,
            'message' => 'Estado del lote actualizado correctamente.',
            'data' => $lote,
        ], 200);
    }

    private function validateAdminLote(Request $request): array
    {
        return $request->validate([
            'finca_id' => 'required|integer|exists:fincas,id',
            'nombre' => 'required|string|max:255',
            'hectareas' => 'required|numeric|min:0.01',
            'tipo_cultivo' => 'required|string|max:255',
            'variedad' => 'nullable|string|max:255',
            'fecha_siembra' => 'nullable|date|before_or_equal:today',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'estado' => 'required|in:disponible,en_uso,no_disponible',
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
}
