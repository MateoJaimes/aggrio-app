import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../../environments/environment';
import { ApiResponse } from '../../../core/models/api-response.model';
import { AccessRequest, AccessRequestAction } from '../models/access-request.model';

@Injectable({ providedIn: 'root' })
export class AccessRequestAdminService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/admin/access-requests`;

  getAccessRequests(): Observable<ApiResponse<AccessRequest[]>> {
    return this.http.get<ApiResponse<AccessRequest[]>>(this.baseUrl);
  }

  updateStatus(id: number, action: AccessRequestAction): Observable<ApiResponse<AccessRequest>> {
    return this.http.patch<ApiResponse<AccessRequest>>(`${this.baseUrl}/${id}/status`, { action });
  }
}
