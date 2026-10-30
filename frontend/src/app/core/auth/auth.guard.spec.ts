import { TestBed } from '@angular/core/testing';
import {
  ActivatedRouteSnapshot,
  Router,
  RouterStateSnapshot,
  UrlTree,
  provideRouter,
} from '@angular/router';
import { describe, it, expect } from 'vitest';
import { authGuard, guestGuard } from './auth.guard';
import { AuthService } from './auth.service';

describe('Primer acceso del productor', () => {
  function setup(mustChange: boolean) {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        {
          provide: AuthService,
          useValue: {
            isAuthenticated: () => true,
            currentUser: () => ({ must_change_password: mustChange }),
            isSuperAdmin: () => false,
          },
        },
      ],
    });
  }
  it('obliga a cambiar la contraseña temporal antes de abrir el campo', () => {
    setup(true);
    const result = TestBed.runInInjectionContext(() =>
      authGuard({} as ActivatedRouteSnapshot, { url: '/campo' } as RouterStateSnapshot),
    );
    expect(TestBed.inject(Router).serializeUrl(result as UrlTree)).toBe('/auth/change-password');
  });
  it('permite abrir el formulario de cambio durante el primer acceso', () => {
    setup(true);
    expect(
      TestBed.runInInjectionContext(() =>
        authGuard(
          {} as ActivatedRouteSnapshot,
          { url: '/auth/change-password' } as RouterStateSnapshot,
        ),
      ),
    ).toBe(true);
  });
  it('permite entrar al campo con una contraseña personal', () => {
    setup(false);
    expect(
      TestBed.runInInjectionContext(() =>
        authGuard({} as ActivatedRouteSnapshot, { url: '/campo' } as RouterStateSnapshot),
      ),
    ).toBe(true);
  });
  it('no genera un bucle entre el login y el primer acceso', () => {
    setup(true);
    const result = TestBed.runInInjectionContext(() =>
      guestGuard({} as ActivatedRouteSnapshot, {} as RouterStateSnapshot),
    );
    expect(TestBed.inject(Router).serializeUrl(result as UrlTree)).toBe('/auth/change-password');
  });
});
