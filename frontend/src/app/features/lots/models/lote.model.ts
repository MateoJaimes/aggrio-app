import { Finca } from '../../estates/models/finca.model';

export type LoteEstado = 'disponible' | 'en_uso' | 'no_disponible';

export interface Lote {
  id: number;
  finca_id: number;
  finca?: Finca;
  nombre: string;
  hectareas: number | string;
  tipo_cultivo: string;
  variedad?: string | null;
  fecha_siembra?: string | null;
  latitud?: number | string | null;
  longitud?: number | string | null;
  estado: LoteEstado;
  created_at?: string;
  updated_at?: string;
}

export interface LotePayload {
  finca_id: number;
  nombre: string;
  hectareas: number;
  tipo_cultivo: string;
  variedad?: string | null;
  fecha_siembra?: string | null;
  latitud?: number | null;
  longitud?: number | null;
  estado: LoteEstado;
}
