import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response.model';

export interface DashboardData {
  kpis: { fincas: number; lotes_activos: number; hectareas_cultivadas: number | string; inversion_total: number | string; registros_iot: number; actividades: number };
  estado_fincas: Array<{ estado: string; total: number }>;
  cultivos_hectareas: Array<{ tipo_cultivo: string; hectareas: number | string }>;
  inversion_actividad: Array<{ tipo_actividad: string; total: number | string }>;
  inversion_mensual: Array<{ periodo: string; total: number | string }>;
  sensores_siete_dias: Array<{ fecha: string; tipo_medicion: string; unidad: string; promedio: number | string; lecturas: number }>;
  estado_sensores: {
    identificados: number;
    criterio_disponible: boolean;
    umbral_vigencia_minutos: number;
    reporte_reciente: number;
    sin_reporte_reciente: number;
    ultima_lectura_por_dispositivo: Array<{
      mac_dispositivo: string;
      ultima_lectura: string;
      estado: 'reporte_reciente' | 'sin_reporte_reciente';
      minutos_desde_ultima_lectura: number | null;
    }>;
  };
  metadata: { moneda: string; zona_horaria: string; zona_horaria_offset: string; rango_iot_desde: string; rango_iot_hasta: string };
}

@Injectable({ providedIn: 'root' })
export class DashboardService {
  private readonly http = inject(HttpClient);
  getDashboard(): Observable<ApiResponse<DashboardData>> {
    return this.http.get<ApiResponse<DashboardData>>(`${environment.apiUrl}/admin/dashboard`);
  }
}
