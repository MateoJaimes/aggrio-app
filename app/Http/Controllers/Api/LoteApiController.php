<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Finca;
use App\Models\Lote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LoteApiController extends Controller
{
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

        if (!$finca || $finca->estado !== 'aprobado') {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. La finca no existe o aún no ha sido aprobada.'
            ], 403);
        }

        // 2. Obtener los lotes de la finca
        $lotes = $finca->lotes()->get();

        return response()->json([
            'success' => true,
            'data'    => $lotes
        ], 200);
    }

    /**
     * Permite al agricultor crear un nuevo lote desde la app móvil (Flutter)
     */
    public function store(Request $request, $finca_id): JsonResponse
    {
        // 1. Validar los datos que el celular debe enviar
        $request->validate([
            'nombre'        => 'required|string|max:255',
            'hectareas'     => 'required|numeric|min:0.01',
            'tipo_cultivo'  => 'required|string|max:255',
            'variedad'      => 'nullable|string|max:255',
            'fecha_siembra' => 'nullable|date',
            'latitud'       => 'nullable|numeric|between:-90,90',
            'longitud'      => 'nullable|numeric|between:-180,180',
        ]);

        // 2. Seguridad de hierro: Verificar que la finca existe y es propiedad del usuario
        $finca = Finca::where('id', $finca_id)
                      ->where('user_id', $request->user()->id)
                      ->first();

        // CLAVE: Validar que la finca esté aprobada antes de crear un lote en ella

        if (!$finca || $finca->estado !== 'aprobado') {
            return response()->json([
                'success' => false,
                'message' => 'No puedes crear lotes. La finca no existe o aún no ha sido aprobada.'
            ], 403);
        }

        // Validar que la finca tenga hectáreas totales definidas
        if ($finca->hectareas_totales === null) {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden crear lotes. La finca no tiene hectáreas totales definidas.'
            ], 400);
        }

        // Validar que haya espacio disponible
        $hectareasOcupadas = $finca->lotes()->sum('hectareas');
        $hectareasDisponibles = $finca->hectareas_totales - $hectareasOcupadas;

        if ($request->hectareas > $hectareasDisponibles) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'No hay suficiente espacio. El lote requiere %s hectáreas pero solo quedan %s disponibles.',
                    number_format($request->hectareas, 2),
                    number_format($hectareasDisponibles, 2)
                )
            ], 400);
        }

        // 3. Crear el lote en la base de datos (nace como disponible)
        $lote = $finca->lotes()->create([
            'estado'        => 'disponible',
            'nombre'        => $request->nombre,
            'hectareas'     => $request->hectareas,
            'tipo_cultivo'  => $request->tipo_cultivo,
            'variedad'      => $request->variedad,
            'fecha_siembra' => $request->fecha_siembra,
            'latitud'       => $request->latitud,
            'longitud'      => $request->longitud,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lote creado exitosamente desde el campo',
            'data'    => $lote
        ], 201);
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

        if (!$lote || !$lote->finca || $lote->finca->user_id !== $request->user()->id || $lote->finca->estado !== 'aprobado') {
            return response()->json([
                'success' => false,
                'message' => 'Lote no encontrado o acceso denegado.'
            ], 404);
        }

        $lote->update(['estado' => $request->estado]);

        return response()->json([
            'success' => true,
            'message' => 'Estado del lote actualizado correctamente.',
            'data'    => $lote
        ], 200);
    }
}