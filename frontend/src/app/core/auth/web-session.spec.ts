import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { firstValueFrom } from 'rxjs';
import { AuthService } from './auth.service';
import { environment } from '../../../environments/environment';

describe('Credenciales de la sesión web', () => {
  let auth: AuthService;
  let http: HttpTestingController;
  const base = environment.apiUrl;
  const payload = {
    requires_two_factor: false,
    user: {
      id: 4,
      name: 'Productor',
      email: 'producer@example.test',
      is_superadmin: false,
      must_change_password: false,
    },
    access_token: 'access-memory',
    csrf_token: 'csrf-session',
    expires_at: new Date(Date.now() + 900000).toISOString(),
  };
  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpTestingController);
    auth = TestBed.inject(AuthService);
  });
  afterEach(() => {
    http.verify();
    localStorage.clear();
    sessionStorage.clear();
  });
  it('envía el login web con credenciales y conserva solo el acceso en memoria', () => {
    auth.login('producer@example.test', 'clave').subscribe();
    const request = http.expectOne(`${base}/web/auth/login`);
    expect(request.request.withCredentials).toBe(true);
    expect(request.request.headers.get('X-Aggrio-Web')).toBe('1');
    request.flush({ data: { ...payload, refresh_token: 'must-not-persist' } });
    expect(auth.getToken()).toBe('access-memory');
    expect(auth.getCsrfToken()).toBe('csrf-session');
    expect(localStorage.getItem('refresh_token')).toBeNull();
    expect(localStorage.getItem('access_token')).toBeNull();
    expect(sessionStorage.getItem('refresh_token')).toBeNull();
  });
  it('restaura al recargar usando CSRF y cookie sin leer un refresh desde JavaScript', async () => {
    const pending = firstValueFrom(auth.restoreSession());
    const csrf = http.expectOne(`${base}/web/auth/csrf`);
    expect(csrf.request.withCredentials).toBe(true);
    csrf.flush({ data: { csrf_token: 'csrf-restored' } });
    await Promise.resolve();
    const refresh = http.expectOne(`${base}/web/auth/refresh`);
    expect(refresh.request.headers.get('X-CSRF-Token')).toBe('csrf-restored');
    expect(refresh.request.headers.has('Authorization')).toBe(false);
    refresh.flush({ data: payload });
    await pending;
    expect(auth.isAuthenticated()).toBe(true);
    expect(auth.currentUser()?.id).toBe(4);
  });
  it('permite mostrar el login si no hay una cookie de sesión válida', async () => {
    const pending = firstValueFrom(auth.restoreSession());
    http.expectOne(`${base}/web/auth/csrf`).flush({}, { status: 401, statusText: 'Unauthorized' });
    await pending;
    expect(auth.isAuthenticated()).toBe(false);
  });
  it('elimina las credenciales persistidas por versiones anteriores', () => {
    for (const key of ['access_token', 'refresh_token', 'currentUser', 'token_expires_at'])
      localStorage.setItem(key, 'old-secret');
    const fresh = TestBed.runInInjectionContext(() => new AuthService());
    expect(fresh.getToken()).toBeNull();
  });
  it('cierra sesión por cookie incluso si el acceso en memoria ya caducó', () => {
    auth.login('producer@example.test', 'clave').subscribe();
    http.expectOne(`${base}/web/auth/login`).flush({ data: payload });
    auth.logout().subscribe();
    const request = http.expectOne(`${base}/web/auth/logout`);
    expect(request.request.withCredentials).toBe(true);
    expect(request.request.headers.get('X-CSRF-Token')).toBe('csrf-session');
    request.flush({ success: true });
    expect(auth.isAuthenticated()).toBe(false);
    expect(localStorage.getItem('aggrio:logout-pending')).toBeNull();
  });
  it('no restaura una sesión que el usuario cerró sin conexión', async () => {
    localStorage.setItem('aggrio:logout-pending', 'true');
    const pending = firstValueFrom(auth.restoreSession());
    http.expectOne(`${base}/web/auth/csrf`).flush({ data: { csrf_token: 'csrf-pending' } });
    const logout = http.expectOne(`${base}/web/auth/logout`);
    logout.flush({ success: true });
    await pending;
    expect(auth.isAuthenticated()).toBe(false);
    http.expectNone(`${base}/web/auth/refresh`);
  });
});
