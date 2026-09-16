import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../../environments/environment';
import { ApiResponse } from '../../../core/models/api-response.model';
import { Lote, LotePayload } from '../models/lote.model';

@Injectable({ providedIn: 'root' })
export class LoteAdminService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/admin/lotes`;

  getLotes(fincaId?: number): Observable<ApiResponse<Lote[]>> {
    const params = fincaId ? new HttpParams().set('finca_id', fincaId) : undefined;

    return this.http.get<ApiResponse<Lote[]>>(this.baseUrl, { params });
  }

  getLote(id: number): Observable<ApiResponse<Lote>> {
    return this.http.get<ApiResponse<Lote>>(`${this.baseUrl}/${id}`);
  }

  createLote(payload: LotePayload): Observable<ApiResponse<Lote>> {
    return this.http.post<ApiResponse<Lote>>(this.baseUrl, payload);
  }

  updateLote(id: number, payload: LotePayload): Observable<ApiResponse<Lote>> {
    return this.http.put<ApiResponse<Lote>>(`${this.baseUrl}/${id}`, payload);
  }

  deleteLote(id: number): Observable<ApiResponse<void>> {
    return this.http.delete<ApiResponse<void>>(`${this.baseUrl}/${id}`);
  }
}
