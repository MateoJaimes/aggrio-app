import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { map } from 'rxjs';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response.model';

export interface Farm {
  id: number;
  nombre: string;
  estado: string;
  latitud: number | null;
  longitud: number | null;
  hectareas_totales: number | null;
  tipo_suelo: string | null;
  perfil_completo: boolean;
  hectareas_disponibles: number;
}
export interface Plot {
  id: number;
  finca_id: number;
  nombre: string;
  estado: string;
  hectareas: number;
  tipo_cultivo: string;
  variedad: string | null;
  fecha_siembra: string | null;
  deleted_at: string | null;
}
export interface ProducerProfile {
  name: string;
  email: string;
  phone: string | null;
  registration: {
    firstname: string;
    lastname: string;
    id_type: string;
    id_number: string;
    country: string;
    department: string;
    city: string;
    status: string;
    created_at: string;
  } | null;
}
export interface Activity {
  id: number;
  tipo_actividad: string;
  fecha: string;
  costo: number;
  observaciones: string | null;
}
export interface Reading {
  id: number;
  tipo_medicion: string;
  valor: number;
  unidad: string;
  fecha_medicion: string;
  origen: string;
}
export interface Evidence {
  id: number;
  tipo_archivo: string;
  contenido_texto: string | null;
  ruta_archivo: string | null;
  categoria: string;
  peso_bytes: number | null;
  created_at: string;
  transcription_status: string;
  transcription_text: string | null;
}
export interface Page<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}
export interface Summary {
  produccion?: { costos_produccion: number; inversion: number; ventas: number; cobrado: number; resultado: number; margen_porcentaje: number | null };
  kpis: Record<string, number>;
  estado_fincas: { estado: string; total: number }[];
  cultivos_hectareas: { tipo_cultivo: string; hectareas: number }[];
  inversion_actividad: { tipo_actividad: string; total: number }[];
  inversion_mensual: { periodo: string; total: number }[];
  sensores_siete_dias: { fecha: string; tipo_medicion: string; unidad: string; promedio: number }[];
  estado_sensores: { mac_dispositivo: string; ultima_lectura: string }[];
}

@Injectable({ providedIn: 'root' })
export class CampoApi {
  private http = inject(HttpClient);
  readonly base = environment.apiUrl;
  get<T>(path: string) {
    return this.http.get<ApiResponse<T>>(`${this.base}${path}`).pipe(map((r) => r.data as T));
  }
  write<T>(method: 'POST' | 'PUT' | 'PATCH' | 'DELETE', path: string, body: unknown, key: string) {
    return this.http
      .request<ApiResponse<T>>(method, `${this.base}${path}`, {
        body,
        headers: new HttpHeaders({ 'Idempotency-Key': key }),
      })
      .pipe(map((r) => r.data as T));
  }
  download(id: number) {
    return this.http.get(`${this.base}/campo/archivos/${id}/descargar`, { responseType: 'blob' });
  }
}
