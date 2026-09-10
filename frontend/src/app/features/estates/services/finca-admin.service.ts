import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../../environments/environment';
import { ApiResponse } from '../../../core/models/api-response.model';
import { Finca } from '../models/finca.model';

@Injectable({ providedIn: 'root' })
export class FincaAdminService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/admin/fincas`;

  getAdminFincas(): Observable<ApiResponse<Finca[]>> {
    return this.http.get<ApiResponse<Finca[]>>(this.baseUrl);
  }

  updateEstadoAdmin(id: number, accion: 'aprobar' | 'rechazar'): Observable<ApiResponse<Finca>> {
    return this.http.patch<ApiResponse<Finca>>(`${this.baseUrl}/${id}/estado`, { accion });
  }

  createFinca(payload: Partial<Finca>): Observable<ApiResponse<Finca>> {
    return this.http.post<ApiResponse<Finca>>(this.baseUrl, payload);
  }

  updateFinca(id: number, payload: Partial<Finca>): Observable<ApiResponse<Finca>> {
    return this.http.put<ApiResponse<Finca>>(`${this.baseUrl}/${id}`, payload);
  }
}
