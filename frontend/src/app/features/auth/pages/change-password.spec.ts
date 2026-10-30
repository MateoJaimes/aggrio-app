import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import {
  ActivatedRouteSnapshot,
  Router,
  RouterStateSnapshot,
  provideRouter,
} from '@angular/router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ChangePassword } from './change-password';
import { AuthService } from '../../../core/auth/auth.service';
import { authGuard } from '../../../core/auth/auth.guard';
import { environment } from '../../../../environments/environment';

describe('Cambiar la contraseña temporal y acceder al perfil', () => {
  let http: HttpTestingController;
  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      imports: [ChangePassword],
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpTestingController);
    login(true);
  });
  function login(mustChange: boolean) {
    TestBed.inject(AuthService).login('producer@example.test', 'test-password').subscribe();
    http
      .expectOne(`${environment.apiUrl}/web/auth/login`)
      .flush({
        data: {
          requires_two_factor: false,
          user: {
            id: 7,
            name: 'Productor',
            email: 'producer@example.test',
            is_superadmin: false,
            must_change_password: mustChange,
          },
          access_token: 'temporary-session',
          csrf_token: 'test-csrf',
          expires_at: new Date(Date.now() + 3600000).toISOString(),
        },
      });
  }
  afterEach(() => {
    http.verify();
    localStorage.clear();
  });

  it('pide solo la nueva contraseña y su confirmación y después permite abrir el panel', async () => {
    const fixture = TestBed.createComponent(ChangePassword);
    fixture.detectChanges();
    await fixture.whenStable();
    expect(fixture.nativeElement.querySelector('#current-password')).toBeNull();
    expect(fixture.nativeElement.querySelectorAll('input[type=password]').length).toBe(2);
    const router = TestBed.inject(Router);
    const navigate = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
    fixture.componentInstance.password = fixture.componentInstance.confirmation =
      'MiClavePersonal123';
    fixture.componentInstance.submit();
    const request = http.expectOne(`${environment.apiUrl}/web/auth/password`);
    expect(request.request.body).not.toHaveProperty('current_password');
    request.flush({
      data: {
        user: {
          id: 7,
          name: 'Productor',
          email: 'producer@example.test',
          is_superadmin: false,
          must_change_password: false,
        },
        access_token: 'personal-session',
        refresh_token: 'personal-refresh',
        expires_at: new Date(Date.now() + 3600000).toISOString(),
      },
    });
    expect(TestBed.inject(AuthService).currentUser()?.must_change_password).toBe(false);
    expect(localStorage.getItem('access_token')).toBeNull();
    expect(TestBed.inject(AuthService).getToken()).toBe('personal-session');
    expect(navigate).toHaveBeenCalledWith('/campo');
    expect(
      TestBed.runInInjectionContext(() =>
        authGuard({} as ActivatedRouteSnapshot, { url: '/campo' } as RouterStateSnapshot),
      ),
    ).toBe(true);
    fixture.destroy();
  });

  it('conserva la validación de contraseña actual para cambios posteriores', async () => {
    login(false);
    const fixture = TestBed.createComponent(ChangePassword);
    fixture.detectChanges();
    await fixture.whenStable();
    expect(fixture.nativeElement.querySelector('#current-password')).not.toBeNull();
    fixture.componentInstance.password = fixture.componentInstance.confirmation =
      'MiClavePersonal123';
    fixture.componentInstance.submit();
    http.expectNone(`${environment.apiUrl}/web/auth/password`);
    fixture.destroy();
  });

  it('explica una contraseña corta al pulsar el botón sin mostrar un falso estado de carga', async () => {
    const fixture = TestBed.createComponent(ChangePassword);
    fixture.detectChanges();
    await fixture.whenStable();
    for (const id of ['new-password', 'confirm-password']) {
      const input = fixture.nativeElement.querySelector(`#${id}`) as HTMLInputElement;
      input.value = 'Nueva123';
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    fixture.detectChanges();
    await fixture.whenStable();
    const button = fixture.nativeElement.querySelector('button[type=submit]') as HTMLButtonElement;
    expect(button.disabled).toBe(false);
    button.click();
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('[role=alert]').textContent).toContain(
      '12 caracteres',
    );
    expect(fixture.componentInstance.busy()).toBe(false);
    http.expectNone(`${environment.apiUrl}/web/auth/password`);
    fixture.destroy();
  });

  it('muestra y oculta las contraseñas sin perder lo escrito', async () => {
    const fixture = TestBed.createComponent(ChangePassword);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.componentInstance.password = fixture.componentInstance.confirmation =
      'MiClavePersonal123';
    fixture.detectChanges();
    await fixture.whenStable();
    const toggle = fixture.nativeElement.querySelector('input[type=checkbox]') as HTMLInputElement;
    toggle.click();
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('#new-password').type).toBe('text');
    expect(fixture.nativeElement.querySelector('#confirm-password').type).toBe('text');
    expect(fixture.componentInstance.password).toBe('MiClavePersonal123');
    toggle.click();
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('#new-password').type).toBe('password');
    fixture.destroy();
  });

  it('libera el botón y permite reintentar si falla el servidor', async () => {
    const fixture = TestBed.createComponent(ChangePassword);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.componentInstance.password = fixture.componentInstance.confirmation =
      'MiClavePersonal123';
    fixture.componentInstance.submit();
    http
      .expectOne(`${environment.apiUrl}/web/auth/password`)
      .flush({ message: 'No se pudo guardar.' }, { status: 503, statusText: 'Unavailable' });
    fixture.detectChanges();
    expect(fixture.componentInstance.busy()).toBe(false);
    expect(fixture.componentInstance.error()).toBe('No se pudo guardar.');
    expect(fixture.nativeElement.querySelector('button[type=submit]').disabled).toBe(false);
    expect(fixture.componentInstance.password).toBe('MiClavePersonal123');
    fixture.destroy();
  });

  it('conserva un acceso al panel y deja de cargar si falla la navegación después de guardar', async () => {
    const fixture = TestBed.createComponent(ChangePassword);
    fixture.detectChanges();
    await fixture.whenStable();
    vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockRejectedValue(
      new Error('Navigation failed'),
    );
    fixture.componentInstance.password = fixture.componentInstance.confirmation =
      'MiClavePersonal123';
    fixture.componentInstance.submit();
    http.expectOne(`${environment.apiUrl}/web/auth/password`).flush({
      data: {
        user: { id: 7, name: 'Productor', is_superadmin: false, must_change_password: false },
        access_token: 'new-access',
        refresh_token: 'new-refresh',
        expires_at: new Date(Date.now() + 3600000).toISOString(),
      },
    });
    await Promise.resolve();
    await Promise.resolve();
    fixture.detectChanges();
    expect(fixture.componentInstance.busy()).toBe(false);
    expect(fixture.componentInstance.saved()).toBe(true);
    expect(fixture.nativeElement.querySelector('a.button').getAttribute('href')).toBe('/campo');
    expect(fixture.nativeElement.textContent).toContain('Tu contraseña ya se guardó');
    fixture.destroy();
  });

  it('detiene la espera si la petición no recibe respuesta', async () => {
    const fixture = TestBed.createComponent(ChangePassword);
    fixture.detectChanges();
    await fixture.whenStable();
    vi.useFakeTimers();
    try {
      fixture.componentInstance.password = fixture.componentInstance.confirmation =
        'MiClavePersonal123';
      fixture.componentInstance.submit();
      http.expectOne(`${environment.apiUrl}/web/auth/password`);
      vi.advanceTimersByTime(20001);
      expect(fixture.componentInstance.busy()).toBe(false);
      expect(fixture.componentInstance.error()).toContain('tardó demasiado');
    } finally {
      vi.useRealTimers();
      fixture.destroy();
    }
  });
});
