import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Accounts } from './accounts';
import { AuthService } from '../../core/auth/auth.service';
import { environment } from '../../../environments/environment';
describe('Administración de acceso', () => {
  let http: HttpTestingController;
  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [Accounts],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        {
          provide: AuthService,
          useValue: {
            currentUser: () => ({ name: 'Administrador', email: 'admin@example.test' }),
            isSuperAdmin: () => true,
          },
        },
      ],
    });
    http = TestBed.inject(HttpTestingController);
  });
  afterEach(() => http.verify());
  it('distingue suspensión, reactivación y recuperación y exige un motivo', () => {
    const fixture = TestBed.createComponent(Accounts);
    http
      .expectOne((r) => r.url === `${environment.apiUrl}/admin/accounts`)
      .flush({ data: { data: [], current_page: 1, last_page: 1, total: 0 } });
    const user = {
      id: 4,
      name: 'Productor',
      email: 'producer@example.test',
      role: 'productor',
      phone: null,
      account_status: 'active',
      approval_status: 'approved',
      must_change_password: false,
      fincas_count: 1,
      suspension_reason: null,
    };
    fixture.componentInstance.open(user, 'suspend');
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain(
      'Sus fincas, archivos e historial se conservarán',
    );
    fixture.componentInstance.reason = 'corto';
    fixture.componentInstance.submit();
    http.expectNone(`${environment.apiUrl}/admin/accounts/4/actions`);
    expect(fixture.componentInstance.error()).toContain('10 caracteres');
    fixture.componentInstance.reason = 'Suspensión solicitada por revisión';
    fixture.componentInstance.submit();
    const request = http.expectOne(`${environment.apiUrl}/admin/accounts/4/actions`);
    expect(request.request.body.action).toBe('suspend');
    request.flush({ success: true, message: 'Suspendida' });
    http
      .expectOne((r) => r.url === `${environment.apiUrl}/admin/accounts`)
      .flush({ data: { data: [], current_page: 1, last_page: 1, total: 0 } });
    expect(fixture.componentInstance.busy()).toBe(false);
    fixture.destroy();
  });
});
