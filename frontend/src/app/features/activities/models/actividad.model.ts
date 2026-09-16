import { Lote } from '../../lots/models/lote.model';

export type TipoActividad =
  | 'preparacion'
  | 'siembra'
  | 'fertilizacion'
  | 'riego'
  | 'control_plagas'
  | 'poda'
  | 'cosecha'
  | 'mantenimiento';

export interface Actividad {
  id: number;
  lote_id: number;
  lote?: Lote;
  tipo_actividad: TipoActividad;
  fecha: string;
  costo: number | string;
  observaciones?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface ActividadPayload {
  lote_id: number;
  tipo_actividad: TipoActividad;
  fecha: string;
  costo: number;
  observaciones?: string | null;
}

export const TIPOS_ACTIVIDAD: ReadonlyArray<{ value: TipoActividad; label: string }> = [
  { value: 'preparacion', label: 'Preparación del terreno' },
  { value: 'siembra', label: 'Siembra' },
  { value: 'fertilizacion', label: 'Fertilización' },
  { value: 'riego', label: 'Riego' },
  { value: 'control_plagas', label: 'Control de plagas y enfermedades' },
  { value: 'poda', label: 'Poda' },
  { value: 'cosecha', label: 'Cosecha' },
  { value: 'mantenimiento', label: 'Mantenimiento general' },
];
