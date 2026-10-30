import { TestBed } from '@angular/core/testing';
import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Router } from '@angular/router';
import { Subject } from 'rxjs';
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { authInterceptor } from './auth.interceptor';
import { AuthService } from './auth.service';
import { TokenPair } from './auth.models';
import { refreshState } from './refresh-state';
import { environment } from '../../../environments/environment';

describe('Sesiones y destinos de la API', () => {
  let http: HttpClient;
  let requests: HttpTestingController;
  let renewal: Subject<TokenPair>;
  let auth: {
    getToken: () => string;
    canRefresh: () => boolean;
    getCsrfToken: () => string;
    isAccessTokenExpiring: () => boolean;
    refreshSession: ReturnType<typeof vi.fn>;
    clearSession: ReturnType<typeof vi.fn>;
  };
  beforeEach(() => {
    refreshState.reset();
    renewal = new Subject<TokenPair>();
    auth = {
      getToken: () => 'access-secret',
      canRefresh: () => true,
      getCsrfToken: () => 'csrf-test',
      isAccessTokenExpiring: () => false,
      refreshSession: vi.fn(() => renewal),
      clearSession: vi.fn(),
    };
    TestBed.configureTestingModule({
      providers: [
        { provide: Router, useValue: { navigate: vi.fn(() => Promise.resolve(true)) } },
        provideHttpClient(withInterceptors([authInterceptor])),
        provideHttpClientTesting(),
        { provide: AuthService, useValue: auth },
      ],
    });
    http = TestBed.inject(HttpClient);
    requests = TestBed.inject(HttpTestingController);
  });
  afterEach(() => {
    requests.verify();
    refreshState.reset();
  });
  it('envía el token solo al origen y prefijo exactos de la API', () => {
    http.get(`${environment.apiUrl}/campo/fincas`).subscribe();
    const api = requests.expectOne(`${environment.apiUrl}/campo/fincas`);
    expect(api.request.headers.get('Authorization')).toBe('Bearer access-secret');
    api.flush({});
    http
      .put('https://storage.example.test/private/file?signature=abc', new Blob(['file']))
      .subscribe();
    const storage = requests.expectOne('https://storage.example.test/private/file?signature=abc');
    expect(storage.request.headers.has('Authorization')).toBe(false);
    storage.flush({});
    http.get(`${environment.apiUrl}-attacker/file`).subscribe();
    const wrong = requests.expectOne(`${environment.apiUrl}-attacker/file`);
    expect(wrong.request.headers.has('Authorization')).toBe(false);
    wrong.flush({});
  });
  it('no adjunta sesiones al formulario público', () => {
    http.post(`${environment.apiUrl}/access-requests`, {}).subscribe();
    const req = requests.expectOne(`${environment.apiUrl}/access-requests`);
    expect(req.request.headers.has('Authorization')).toBe(false);
    req.flush({});
  });
  it('libera con error todas las peticiones pendientes si falla refresh', () => {
    const errors: unknown[] = [];
    for (const resource of ['first', 'second'])
      http.get(`${environment.apiUrl}/${resource}`).subscribe({ error: (e) => errors.push(e) });
    requests
      .expectOne(`${environment.apiUrl}/first`)
      .flush({}, { status: 401, statusText: 'Unauthorized' });
    requests
      .expectOne(`${environment.apiUrl}/second`)
      .flush({}, { status: 401, statusText: 'Unauthorized' });
    expect(auth.refreshSession).toHaveBeenCalledTimes(1);
    renewal.error(new Error('Revoked'));
    expect(errors).toHaveLength(2);
    expect(auth.clearSession).toHaveBeenCalledTimes(1);
  });
  it('un error de validación después de renovar no cierra la sesión', () => {
    const onError = vi.fn();
    http.post(`${environment.apiUrl}/campo/perfil`, {}).subscribe({ error: onError });
    requests
      .expectOne(`${environment.apiUrl}/campo/perfil`)
      .flush({}, { status: 401, statusText: 'Unauthorized' });
    renewal.next({ access_token: 'new-access' } as TokenPair);
    const retry = requests.expectOne(`${environment.apiUrl}/campo/perfil`);
    expect(retry.request.headers.get('Authorization')).toBe('Bearer new-access');
    retry.flush({}, { status: 422, statusText: 'Unprocessable' });
    expect(onError).toHaveBeenCalledTimes(1);
    expect(auth.clearSession).not.toHaveBeenCalled();
  });
});
