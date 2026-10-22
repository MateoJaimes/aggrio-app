<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lote;
use App\Models\DispositivoIot;
use App\Models\LecturaIot;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class LecturaIotApiController extends Controller
{
    private const TIPOS_MEDICION = [
        'temperatura',
        'humedad_suelo',
        'radiacion_solar',
        'humedad_ambiente',
    ];

    // La columna valor es decimal(8,2): fuera de este rango PostgreSQL rechaza
    // el insert y la API respondería 500 en lugar de 422.
    private const REGLA_VALOR = 'required|numeric|between:-999999.99,999999.99';

    /**
     * Listar globalmente las lecturas IoT para el panel Superadmin.
     */
    public function indexAdmin(Request $request): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $request->validate([
            'lote_id' => 'nullable|integer|exists:lotes,id',
            'tipo_medicion' => 'nullable|in:' . implode(',', self::TIPOS_MEDICION),
        ]);

        $lecturas = LecturaIot::with(['lote.finca.user'])
            ->when($request->filled('lote_id'), fn ($query) => $query->where('lote_id', $request->integer('lote_id')))
            ->when(
                $request->filled('tipo_medicion'),
                fn ($query) => $query->where('tipo_medicion', $request->string('tipo_medicion')->toString())
            )
            ->orderByDesc('fecha_medicion')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Lecturas IoT recuperadas con éxito.',
            'data' => $lecturas,
        ], 200);
    }

    /**
     * Consultar una lectura IoT globalmente desde el panel Superadmin.
     */
    public function showAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $lectura = LecturaIot::with(['lote.finca.user'])->find($id);

        if (! $lectura) {
            return $this->lecturaNoEncontrada();
        }

        return response()->json([
            'success' => true,
            'data' => $lectura,
        ], 200);
    }

    /**
     * Crear una lectura IoT en cualquier lote desde el panel Superadmin.
     */
    public function storeAdmin(Request $request): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $lectura = LecturaIot::create($this->validateAdminLectura($request));

        return response()->json([
            'success' => true,
            'message' => 'Lectura IoT creada exitosamente.',
            'data' => $lectura->load(['lote.finca.user']),
        ], 201);
    }

    /**
     * Actualizar una lectura IoT desde el panel Superadmin.
     */
    public function updateAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $lectura = LecturaIot::find($id);

        if (! $lectura) {
            return $this->lecturaNoEncontrada();
        }

        $lectura->update($this->validateAdminLectura($request));

        return response()->json([
            'success' => true,
            'message' => 'Lectura IoT actualizada exitosamente.',
            'data' => $lectura->fresh()->load(['lote.finca.user']),
        ], 200);
    }

    /**
     * Eliminar una lectura IoT desde el panel Superadmin.
     */
    public function destroyAdmin(Request $request, int $id): JsonResponse
    {
        if (! $this->isSuperAdmin($request)) {
            return $this->adminAccessDenied();
        }

        $lectura = LecturaIot::find($id);

        if (! $lectura) {
            return $this->lecturaNoEncontrada();
        }

        $lectura->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lectura IoT eliminada exitosamente.',
        ], 200);
    }

    /**
     * 1. ENDPOINT PARA EL SENSOR (POST): El dispositivo físico inyecta datos aquí.
     *
     * Con X-Device-Key válida (middleware iot.device) la lectura va al lote del
     * dispositivo registrado y se ignora el lote_id del cuerpo (RF002). Sin key,
     * mientras la transición lo permita, se usa el lote_id enviado.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var DispositivoIot|null $dispositivo */
        $dispositivo = $request->attributes->get('dispositivo_iot');

        // Validación estricta de los datos del sensor
        $datos = $request->validate([
            'lote_id'         => [$dispositivo ? 'nullable' : 'required', 'integer'],
            'mac_dispositivo' => 'nullable|string|max:50',
            'tipo_medicion'   => 'required|string|in:' . implode(',', self::TIPOS_MEDICION),
            'valor'           => self::REGLA_VALOR,
            'unidad'          => 'required|string|max:10'
        ]);

        $loteId = $dispositivo?->lote_id ?? (int) $datos['lote_id'];

        // Sólo se aceptan lecturas de lotes de fincas aprobadas. El mismo mensaje
        // para "no existe" y "no aprobada" evita que sirva para descubrir IDs.
        if (! $this->esLoteDeFincaAprobada($loteId)) {
            throw ValidationException::withMessages([
                'lote_id' => 'El lote no existe o su finca no está aprobada.',
            ]);
        }

        // Guardar la lectura en el Data Warehouse
        $lectura = LecturaIot::create([
            'lote_id'         => $loteId,
            'mac_dispositivo' => $dispositivo?->mac_dispositivo ?? $datos['mac_dispositivo'] ?? null,
            'tipo_medicion'   => $datos['tipo_medicion'],
            'valor'           => $datos['valor'],
            'unidad'          => $datos['unidad'],
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

    private function validateAdminLectura(Request $request): array
    {
        return $request->validate([
            'lote_id' => 'required|integer|exists:lotes,id',
            'mac_dispositivo' => 'nullable|string|max:50',
            'tipo_medicion' => 'required|in:' . implode(',', self::TIPOS_MEDICION),
            'valor' => self::REGLA_VALOR,
            'unidad' => 'required|string|max:10',
            'fecha_medicion' => 'required|date',
        ]);
    }

    private function esLoteDeFincaAprobada(int $loteId): bool
    {
        return Lote::whereKey($loteId)
            ->whereHas('finca', fn ($query) => $query->where('estado', 'aprobado'))
            ->exists();
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

    private function lecturaNoEncontrada(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Lectura IoT no encontrada.',
        ], 404);
    }
}
