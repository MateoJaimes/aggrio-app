import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../../environments/environment';
import { ApiResponse } from '../../../core/models/api-response.model';
import { LecturaIot, LecturaIotPayload, TipoMedicion } from '../models/lectura-iot.model';

export interface LecturaIotFilters {
  loteId?: number;
  tipoMedicion?: TipoMedicion;
}

@Injectable({ providedIn: 'root' })
export class LecturaIotAdminService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/admin/lecturas-iot`;

  getLecturas(filters: LecturaIotFilters = {}): Observable<ApiResponse<LecturaIot[]>> {
    let params = new HttpParams();

    if (filters.loteId) {
      params = params.set('lote_id', filters.loteId);
    }

    if (filters.tipoMedicion) {
      params = params.set('tipo_medicion', filters.tipoMedicion);
    }

    return this.http.get<ApiResponse<LecturaIot[]>>(this.baseUrl, { params });
  }

  getLectura(id: number): Observable<ApiResponse<LecturaIot>> {
    return this.http.get<ApiResponse<LecturaIot>>(`${this.baseUrl}/${id}`);
  }

  createLectura(payload: LecturaIotPayload): Observable<ApiResponse<LecturaIot>> {
    return this.http.post<ApiResponse<LecturaIot>>(this.baseUrl, payload);
  }

  updateLectura(id: number, payload: LecturaIotPayload): Observable<ApiResponse<LecturaIot>> {
    return this.http.put<ApiResponse<LecturaIot>>(`${this.baseUrl}/${id}`, payload);
  }

  deleteLectura(id: number): Observable<ApiResponse<void>> {
    return this.http.delete<ApiResponse<void>>(`${this.baseUrl}/${id}`);
  }
}
