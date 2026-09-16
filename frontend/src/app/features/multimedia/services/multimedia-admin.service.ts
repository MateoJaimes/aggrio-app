import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../../environments/environment';
import { ApiResponse } from '../../../core/models/api-response.model';
import { ArchivoMultimedia, ArchivoMultimediaPayload } from '../models/archivo-multimedia.model';

@Injectable({ providedIn: 'root' })
export class MultimediaAdminService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/admin/archivos-multimedia`;

  getArchivos(userId?: number): Observable<ApiResponse<ArchivoMultimedia[]>> {
    const params = userId ? new HttpParams().set('user_id', userId) : undefined;

    return this.http.get<ApiResponse<ArchivoMultimedia[]>>(this.baseUrl, { params });
  }

  getArchivo(id: number): Observable<ApiResponse<ArchivoMultimedia>> {
    return this.http.get<ApiResponse<ArchivoMultimedia>>(`${this.baseUrl}/${id}`);
  }

  updateArchivo(id: number, payload: ArchivoMultimediaPayload): Observable<ApiResponse<ArchivoMultimedia>> {
    return this.http.put<ApiResponse<ArchivoMultimedia>>(`${this.baseUrl}/${id}`, payload);
  }

  deleteArchivo(id: number): Observable<ApiResponse<void>> {
    return this.http.delete<ApiResponse<void>>(`${this.baseUrl}/${id}`);
  }
}
