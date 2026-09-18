export type FincaEstado = 'pendiente' | 'aprobado' | 'rechazado';

export interface UserSummary {
  id: number;
  name: string;
  email?: string;
}

export interface Finca {
  id?: number;
  user_id: number;
  user?: UserSummary;
  estado: FincaEstado;
  nombre?: string | null;
  latitud?: number | null;
  longitud?: number | null;
  hectareas_totales?: number | null;
  tipo_suelo?: string | null;
  created_at?: string;
  updated_at?: string;
}
