import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Settings } from './settings';
import { AuthService } from '../../core/auth/auth.service';

describe('Configuración de seguridad', () => {
  let page: Settings;
  let http: HttpTestingController;
  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [Settings],
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        {
          provide: AuthService,
          useValue: { currentUser: () => ({ name: 'Admin' }), isSuperAdmin: () => true },
        },
      ],
    });
    page = TestBed.createComponent(Settings).componentInstance;
    http = TestBed.inject(HttpTestingController);
  });
  afterEach(() => http.verify());
  it('pide verificar la identidad antes de registrar una nueva app y limpia la contraseña después', async () => {
    page.choose('add');
    page.name = 'Mi teléfono';
    page.password = 'Password123';
    page.currentCode = '123456';
    const request = page.submit();
    const call = http.expectOne('/api/admin/settings/security/authenticators');
    expect(call.request.body).toEqual({
      password: 'Password123',
      code: '123456',
      name: 'Mi teléfono',
      mode: 'add',
    });
    call.flush({
      success: true,
      data: {
        id: 'new-device',
        name: 'Mi teléfono',
        secret: 'NEWSECRET',
        qr_code: 'data:image/svg+xml;base64,AAA',
        mode: 'add',
        expires_at: '2026-10-28',
      },
    });
    await request;
    expect(page.pending()?.id).toBe('new-device');
    expect(page.password).toBe('');
    expect(page.currentCode).toBe('');
  });
  it('mantiene pendiente la app al rechazar un código sin sustituir la app actual', async () => {
    page.pending.set({
      id: 'new-device',
      name: 'Nueva',
      secret: 'SECRET',
      qr_code: '',
      mode: 'replace',
      expires_at: '',
    });
    page.newCode = '123456';
    const request = page.confirm();
    http
      .expectOne('/api/admin/settings/security/authenticators/new-device/confirm')
      .flush({ message: 'Código incorrecto.' }, { status: 422, statusText: 'Unprocessable' });
    await request;
    expect(page.pending()?.mode).toBe('replace');
    expect(page.newCode).toBe('');
    expect(page.error()).toBe('Código incorrecto.');
  });
  it('elimina las claves y códigos mostrados al salir de configuración', () => {
    page.password = 'Password123';
    page.currentCode = '123456';
    page.codes.set(['private-recovery']);
    page.pending.set({
      id: 'x',
      name: 'Nueva',
      secret: 'SECRET',
      qr_code: '',
      mode: 'add',
      expires_at: '',
    });
    page.ngOnDestroy();
    expect(page.password).toBe('');
    expect(page.currentCode).toBe('');
    expect(page.codes()).toEqual([]);
    expect(page.pending()).toBeNull();
  });
});
