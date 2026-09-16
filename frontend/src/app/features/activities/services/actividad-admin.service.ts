import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../../environments/environment';
import { ApiResponse } from '../../../core/models/api-response.model';
import { Actividad, ActividadPayload, TipoActividad } from '../models/actividad.model';

@Injectable({ providedIn: 'root' })
export class ActividadAdminService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/admin/actividades`;

  getActividades(tipoActividad?: TipoActividad): Observable<ApiResponse<Actividad[]>> {
    const params = tipoActividad ? new HttpParams().set('tipo_actividad', tipoActividad) : undefined;

    return this.http.get<ApiResponse<Actividad[]>>(this.baseUrl, { params });
  }

  getActividad(id: number): Observable<ApiResponse<Actividad>> {
    return this.http.get<ApiResponse<Actividad>>(`${this.baseUrl}/${id}`);
  }

  createActividad(payload: ActividadPayload): Observable<ApiResponse<Actividad>> {
    return this.http.post<ApiResponse<Actividad>>(this.baseUrl, payload);
  }

  updateActividad(id: number, payload: ActividadPayload): Observable<ApiResponse<Actividad>> {
    return this.http.put<ApiResponse<Actividad>>(`${this.baseUrl}/${id}`, payload);
  }

  deleteActividad(id: number): Observable<ApiResponse<void>> {
    return this.http.delete<ApiResponse<void>>(`${this.baseUrl}/${id}`);
  }
}
