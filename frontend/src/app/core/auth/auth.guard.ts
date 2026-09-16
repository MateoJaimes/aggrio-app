import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

import { AuthService } from './auth.service';

/** Exige sesión iniciada. */
export const authGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (auth.isAuthenticated()) {
    return true;
  }

  return router.createUrlTree(['/auth/login'], { queryParams: { redirect: state.url } });
};

/** Exige sesión iniciada y rol Superadmin (es lo que valida la API admin). */
export const adminGuard: CanActivateFn = (route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  const authenticated = authGuard(route, state);

  if (authenticated !== true) {
    return authenticated;
  }

  return auth.isSuperAdmin() ? true : router.createUrlTree(['/auth/login']);
};

/** Impide volver al login cuando ya hay sesión. */
export const guestGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  return auth.isAuthenticated() ? router.createUrlTree(['/estates']) : true;
};

/** La pantalla de doble factor sólo tiene sentido con un reto pendiente. */
export const twoFactorGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  return auth.hasPendingChallenge() ? true : router.createUrlTree(['/auth/login']);
};
