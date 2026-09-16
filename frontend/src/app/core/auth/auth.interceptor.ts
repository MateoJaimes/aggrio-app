import {
  HttpErrorResponse,
  HttpEvent,
  HttpHandlerFn,
  HttpInterceptorFn,
  HttpRequest,
} from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, catchError, filter, switchMap, take, throwError } from 'rxjs';

import { environment } from '../../../environments/environment';
import { AuthService } from './auth.service';
import { refreshState } from './refresh-state';

/**
 * Rutas que nunca deben llevar el access token ni disparar un refresh:
 * son las que sirven precisamente para obtener una sesión.
 */
const PUBLIC_PATHS = [
  '/login',
  '/login/2fa/setup',
  '/login/2fa/confirm',
  '/login/2fa/verify',
  '/password/forgot',
  '/password/reset',
  '/auth/refresh',
];

export const authInterceptor: HttpInterceptorFn = (req: HttpRequest<unknown>, next: HttpHandlerFn) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (isPublic(req.url)) {
    return next(req);
  }

  const token = auth.getToken();

  if (!token) {
    return next(req);
  }

  // Renovación automática (RF004): si al token le quedan segundos, se cambia
  // antes de mandar la petición en lugar de esperar al 401.
  if (auth.isAccessTokenExpiring() && auth.getRefreshToken()) {
    return runRefresh(auth, router, req, next);
  }

  return next(withToken(req, token)).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse && error.status === 401 && auth.getRefreshToken()) {
        return runRefresh(auth, router, req, next);
      }

      return throwError(() => error);
    }),
  );
};

function runRefresh(
  auth: AuthService,
  router: Router,
  req: HttpRequest<unknown>,
  next: HttpHandlerFn,
): Observable<HttpEvent<unknown>> {
  if (refreshState.isRefreshing) {
    return refreshState.token$.pipe(
      filter((value): value is string => value !== null),
      take(1),
      switchMap((freshToken) => next(withToken(req, freshToken))),
    );
  }

  refreshState.begin();

  return auth.refreshSession().pipe(
    switchMap((pair) => {
      refreshState.succeed(pair.access_token);

      return next(withToken(req, pair.access_token));
    }),
    catchError((error: unknown) => {
      refreshState.fail();

      // El refresh token también caducó o fue revocado: la sesión terminó.
      auth.clearSession();
      void router.navigate(['/auth/login'], { queryParams: { expired: 1 } });

      return throwError(() => error);
    }),
  );
}

function withToken(req: HttpRequest<unknown>, token: string): HttpRequest<unknown> {
  return req.clone({ setHeaders: { Authorization: `Bearer ${token}` } });
}

function isPublic(url: string): boolean {
  const path = url.startsWith(environment.apiUrl) ? url.slice(environment.apiUrl.length) : url;

  return PUBLIC_PATHS.some((publicPath) => path === publicPath || path.startsWith(`${publicPath}?`));
}
