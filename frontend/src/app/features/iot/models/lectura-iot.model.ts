import { Lote } from '../../lots/models/lote.model';

export type TipoMedicion = 'temperatura' | 'humedad_suelo' | 'radiacion_solar' | 'humedad_ambiente';

export interface LecturaIot {
  id: number;
  lote_id: number;
  lote?: Lote;
  mac_dispositivo?: string | null;
  tipo_medicion: TipoMedicion;
  valor: number | string;
  unidad: string;
  fecha_medicion: string;
  created_at?: string;
  updated_at?: string;
}

export interface LecturaIotPayload {
  lote_id: number;
  mac_dispositivo?: string | null;
  tipo_medicion: TipoMedicion;
  valor: number;
  unidad: string;
  fecha_medicion: string;
}

export const TIPOS_MEDICION: ReadonlyArray<{ value: TipoMedicion; label: string }> = [
  { value: 'temperatura', label: 'Temperatura' },
  { value: 'humedad_suelo', label: 'Humedad del suelo' },
  { value: 'radiacion_solar', label: 'Radiación solar' },
  { value: 'humedad_ambiente', label: 'Humedad ambiente' },
];

export const UNIDADES_POR_MEDICION: Record<TipoMedicion, readonly string[]> = {
  temperatura: ['°C', '°F'],
  humedad_suelo: ['%'],
  radiacion_solar: ['W/m²', 'lux'],
  humedad_ambiente: ['%'],
};
