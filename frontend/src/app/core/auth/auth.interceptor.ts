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
  '/access-requests',
  '/web/auth/login',
  '/web/auth/2fa/setup',
  '/web/auth/2fa/confirm',
  '/web/auth/2fa/verify',
  '/web/auth/refresh',
  '/web/auth/csrf',
  '/web/auth/logout',
  '/web/auth/logout-all',
];

export const authInterceptor: HttpInterceptorFn = (
  req: HttpRequest<unknown>,
  next: HttpHandlerFn,
) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  // Never forward a human session to MinIO, a signed URL or another origin.
  if (!isApiUrl(req.url) || isPublic(req.url)) {
    return next(req);
  }

  const token = auth.getToken();

  if (!token) {
    return next(req);
  }

  // Renovación automática (RF004): si al token le quedan segundos, se cambia
  // antes de mandar la petición en lugar de esperar al 401.
  if (auth.isAccessTokenExpiring() && auth.canRefresh()) {
    return runRefresh(auth, router, req, next);
  }

  return next(withToken(req, token, auth)).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse && error.status === 401 && auth.canRefresh()) {
        return runRefresh(auth, router, req, next);
      }

      if (
        error instanceof HttpErrorResponse &&
        error.status === 403 &&
        error.error?.code === 'account_suspended'
      ) {
        auth.clearSession();
        void router.navigate(['/auth/login'], { queryParams: { suspended: 1 } });
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
      switchMap((freshToken) =>
        freshToken
          ? next(withToken(req, freshToken, auth))
          : throwError(() => new Error('La renovación de sesión falló.')),
      ),
    );
  }

  refreshState.begin();

  return auth.refreshSession().pipe(
    catchError((error: unknown) => {
      refreshState.fail();

      // El refresh token también caducó o fue revocado: la sesión terminó.
      auth.clearSession();
      void router.navigate(['/auth/login'], { queryParams: { expired: 1 } });

      return throwError(() => error);
    }),
    switchMap((pair) => {
      refreshState.succeed(pair.access_token);
      return next(withToken(req, pair.access_token, auth));
    }),
  );
}

function withToken(
  req: HttpRequest<unknown>,
  token: string,
  auth: AuthService,
): HttpRequest<unknown> {
  const web = new URL(req.url, window.location.origin).pathname.includes('/web/auth/');
  return req.clone({
    setHeaders: {
      Authorization: `Bearer ${token}`,
      ...(web
        ? {
            'X-Aggrio-Web': '1',
            ...(auth.getCsrfToken() ? { 'X-CSRF-Token': auth.getCsrfToken()! } : {}),
          }
        : {}),
    },
    ...(web ? { withCredentials: true } : {}),
  });
}

function isPublic(url: string): boolean {
  const path = url.startsWith(environment.apiUrl) ? url.slice(environment.apiUrl.length) : url;

  return PUBLIC_PATHS.some(
    (publicPath) => path === publicPath || path.startsWith(`${publicPath}?`),
  );
}

function isApiUrl(url: string): boolean {
  const base = new URL(environment.apiUrl.replace(/\/$/, '') + '/', window.location.origin);
  const target = new URL(url, window.location.origin);
  return target.origin === base.origin && target.pathname.startsWith(base.pathname);
}
