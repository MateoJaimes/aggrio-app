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

  getAccessRequests(): Observable<
    ApiResponse<AccessRequest[]> & { delivery?: { configured: boolean; local_preview: boolean; console_reset?: boolean } }
  > {
    return this.http.get<
      ApiResponse<AccessRequest[]> & { delivery?: { configured: boolean; local_preview: boolean; console_reset?: boolean } }
    >(this.baseUrl);
  }

  updateStatus(id: number, action: AccessRequestAction): Observable<ApiResponse<AccessRequest>> {
    return this.http.patch<ApiResponse<AccessRequest>>(`${this.baseUrl}/${id}/status`, { action });
  }
  resend(id: number, resetPassword = false) {
    return this.http.post<ApiResponse<AccessRequest>>(`${this.baseUrl}/${id}/invitation`, {reset_password: resetPassword});
  }
  preview(id: number) {
    return this.http.get<ApiResponse<{ html: string }>>(`${this.baseUrl}/${id}/invitation-preview`);
  }
}
