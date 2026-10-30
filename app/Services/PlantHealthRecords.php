<?php

namespace App\Services;

use App\Models\ProductionRecord;
use Illuminate\Http\Request;

/** Validated structured observations; does not diagnose or prescribe products. */
class PlantHealthRecords
{
    public function validate(Request $request, string $type, array &$data, ProductionRecord $record, ProductionService $production): void
    {
        $invalid = fn ($key, $message) => $production->invalid($key, $message);
        $today = now('America/Bogota')->toDateString();
        $crop = $production->find($request, $data['cultivo_id']);
        if ($data['fecha'] < $crop->fecha) {
            $invalid('fecha', 'El registro sanitario no puede preceder al cultivo.');
        }
        if ($type === 'sanidad_seguimientos' && $record->exists && (int) ($record->datos['tratamiento_id'] ?? 0) !== (int) ($data['tratamiento_id'] ?? 0)) {
            $invalid('tratamiento_id', 'No cambies el tratamiento de una evaluación con historial.');
        }
        if (($type !== 'sanidad_tratamientos' || $data['estado'] !== 'planificado') && $data['fecha'] > $today) {
            $invalid('fecha', 'Una observación o aplicación realizada no puede tener fecha futura.');
        }
        if (! empty($data['fecha_revision']) && $data['fecha_revision'] < $data['fecha']) {
            $invalid('fecha_revision', 'La revisión debe ser posterior o igual al registro.');
        }
        if (isset($data['muestra_total'])) {
            if ($data['muestra_total'] < 1 || $data['muestra_afectada'] > $data['muestra_total']) {
                $invalid('muestra_afectada', 'Revisa al menos una unidad; las afectadas no pueden superar las revisadas.');
            }
            $data['incidencia_porcentaje'] = round(100 * $data['muestra_afectada'] / $data['muestra_total'], 2);
        }
        if (isset($data['severidad']) && $data['severidad'] > 100) {
            $invalid('severidad', 'La severidad debe estar entre 0 y 100.');
        }
        if ($type === 'sanidad_casos') {
            return;
        }
        $case = $production->find($request, $data['caso_id']);
        if ($case->tipo !== 'sanidad_casos') {
            $invalid('caso_id', 'Selecciona un problema sanitario.');
        }
        foreach (['finca_id', 'lote_id', 'cultivo_id', 'ciclo_id'] as $key) {
            if ((int) ($data[$key] ?? 0) !== (int) $case->$key) {
                $invalid($key, 'El registro debe pertenecer al mismo cultivo, lote y siembra del problema.');
            }
        }
        if ($record->exists && (int) $record->datos['caso_id'] !== (int) $data['caso_id']) {
            $invalid('caso_id', 'No cambies el problema de un registro con historial.');
        }
        if ($data['fecha'] < $case->fecha) {
            $invalid('fecha', 'El registro no puede preceder a la detección del problema.');
        }
        if ($type === 'sanidad_tratamientos') {
            if ($data['estado'] === 'no_realizado' && empty($data['motivo_decision'])) {
                $invalid('motivo_decision', 'Explica por qué no se realizó.');
            }
            if ($data['estado'] !== 'aplicado') {
                foreach (['producto', 'ingrediente_activo', 'registro_producto', 'lote_insumo', 'dosis', 'unidad_dosis', 'cantidad_aplicada', 'unidad_aplicada', 'area_tratada', 'volumen_mezcla', 'carencia_dias', 'reingreso_horas', 'equipo', 'condiciones_clima'] as $key) {
                    if (! empty($data[$key])) {
                        $invalid($key, 'Los datos de aplicación real se registran al marcar la medida como aplicada. Conserva lo planeado en la descripción.');
                    }
                }
            } elseif (in_array($data['metodo'], ['quimico', 'biologico'])) {
                foreach (['producto', 'dosis', 'unidad_dosis', 'cantidad_aplicada', 'unidad_aplicada'] as $key) {
                    if (empty($data[$key])) {
                        $invalid($key, 'Completa el producto, la dosis y la cantidad realmente utilizados con sus unidades.');
                    }
                }
                if ($data['metodo'] === 'quimico') {
                    foreach (['ingrediente_activo', 'registro_producto', 'lote_insumo', 'carencia_dias', 'reingreso_horas'] as $key) {
                        if (! isset($data[$key]) || $data[$key] === '') {
                            $invalid($key, 'Copia este dato del producto y de su etiqueta.');
                        }
                    }
                }
            }
            $crop = $production->find($request, $data['cultivo_id']);
            if (! empty($data['area_tratada']) && $data['area_tratada'] > ($crop->datos['area'] ?? 0)) {
                $invalid('area_tratada', 'La superficie tratada no puede superar la del cultivo.');
            }
            if ($record->exists && $record->estado === 'aplicado' && $data['estado'] !== 'aplicado') {
                $invalid('estado', 'Conserva la aplicación realizada; registra una nueva decisión por separado.');
            }
        } elseif (! empty($data['tratamiento_id'])) {
            $treatment = $production->find($request, $data['tratamiento_id']);
            if ($treatment->tipo !== 'sanidad_tratamientos' || (int) $treatment->datos['caso_id'] !== (int) $case->id) {
                $invalid('tratamiento_id', 'El tratamiento debe pertenecer al mismo problema.');
            }
            if ($treatment->estado === 'planificado') {
                $invalid('tratamiento_id', 'Primero registra si el tratamiento se realizó o no.');
            }
            if ($data['fecha'] < $treatment->fecha) {
                $invalid('fecha', 'La evaluación no puede preceder a la medida evaluada.');
            }
            if ($record->exists && (int) ($record->datos['tratamiento_id'] ?? 0) !== (int) $data['tratamiento_id']) {
                $invalid('tratamiento_id', 'No cambies el tratamiento de una evaluación con historial.');
            }
        }
    }
}
