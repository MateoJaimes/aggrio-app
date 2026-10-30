import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import {
  Observable,
  catchError,
  map,
  of,
  tap,
  timeout,
  defer,
  from,
  firstValueFrom,
  switchMap,
} from 'rxjs';

import { environment } from '../../../environments/environment';
import { ApiResponse } from '../models/api-response.model';
import {
  LoginPayload,
  SessionPayload,
  TokenPair,
  TwoFactorSetupPayload,
  TwoFactorStatusPayload,
  User,
  requiresTwoFactor,
} from './auth.models';
import { refreshState } from './refresh-state';

const ACCESS_TOKEN_KEY = 'access_token';
const REFRESH_TOKEN_KEY = 'refresh_token';
const EXPIRES_AT_KEY = 'token_expires_at';
const USER_KEY = 'currentUser';
const CHALLENGE_KEY = 'two_factor_challenge';
const CHALLENGE_ENROLLED_KEY = 'two_factor_enrolled';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = environment.apiUrl;

  private readonly userSignal = signal<User | null>(null);
  private accessToken: string | null = null;
  private expiresAt: string | null = null;
  private csrfToken: string | null = null;
  constructor() {
    // Remove credentials left by older web releases; web refresh credentials live only in HttpOnly cookies.
    for (const key of [ACCESS_TOKEN_KEY, REFRESH_TOKEN_KEY, EXPIRES_AT_KEY, USER_KEY]) {
      safeRemove(key);
      safeRemove(key, sessionStorage);
    }
  }
  private webHeaders(csrf = false): Record<string, string> {
    return {
      'X-Aggrio-Web': '1',
      ...(csrf && this.csrfToken ? { 'X-CSRF-Token': this.csrfToken } : {}),
    };
  }
  getCsrfToken(): string | null {
    return this.csrfToken;
  }
  canRefresh(): boolean {
    return this.csrfToken !== null;
  }
  restoreSession(): Observable<unknown> {
    if (safeRead('aggrio:logout-pending') === 'true') {
      return this.http
        .get<ApiResponse<{ csrf_token: string }>>(`${this.baseUrl}/web/auth/csrf`, {
          withCredentials: true,
          headers: this.webHeaders(),
        })
        .pipe(
          timeout(10000),
          tap((r) => (this.csrfToken = r.data?.csrf_token ?? null)),
          map(() => null),
          // Finish a logout that could not reach the server while offline.
          switchMap(() => this.revokeThen(`${this.baseUrl}/web/auth/logout`)),
          catchError((e) => {
            if ([401, 403].includes(e.status)) safeRemove('aggrio:logout-pending');
            this.clearSession();
            return of(null);
          }),
        );
    }
    return this.refreshSession().pipe(
      catchError(() => {
        this.clearSession();
        return of(null);
      }),
    );
  }
  private async sessionLock<T>(work: () => Promise<T>): Promise<T> {
    return navigator.locks ? navigator.locks.request('aggrio-web-session', work) : work();
  }

  readonly currentUser = this.userSignal.asReadonly();
  readonly isAuthenticated = computed(() => this.userSignal() !== null && this.getToken() !== null);
  readonly isSuperAdmin = computed(() => this.userSignal()?.is_superadmin === true);

  updateContact(name: string, phone: string): void {
    const user = this.userSignal();
    if (user) {
      const updated = { ...user, name, phone };
      this.userSignal.set(updated);
    }
  }

  // -------------------------------------------------------------- login (RF003)

  /**
   * Paso 1. Si el rol exige doble factor la respuesta no trae tokens, sino un
   * challenge_token que queda guardado para la pantalla de verificación.
   */
  login(email: string, password: string): Observable<LoginPayload> {
    return this.http
      .post<ApiResponse<LoginPayload>>(
        `${this.baseUrl}/web/auth/login`,
        { email, password },
        { withCredentials: true, headers: this.webHeaders() },
      )
      .pipe(
        map((response) => response.data as LoginPayload),
        tap((payload) => {
          if (requiresTwoFactor(payload)) {
            this.storeChallenge(payload.challenge_token, payload.two_factor_enrolled);
          } else {
            this.storeSession(payload);
          }
        }),
      );
  }

  /** Alta del TOTP: devuelve el QR para escanear. */
  startTwoFactorSetup(): Observable<TwoFactorSetupPayload> {
    return this.http
      .post<ApiResponse<TwoFactorSetupPayload>>(
        `${this.baseUrl}/web/auth/2fa/setup`,
        { challenge_token: this.getChallengeToken() },
        { withCredentials: true, headers: this.webHeaders() },
      )
      .pipe(map((response) => response.data as TwoFactorSetupPayload));
  }

  /** Confirma el alta del TOTP y completa el login. */
  confirmTwoFactorSetup(
    code: string,
    rememberDevice = false,
    deviceName = '',
  ): Observable<SessionPayload> {
    return this.completeChallenge(`${this.baseUrl}/web/auth/2fa/confirm`, {
      code,
      remember_device: rememberDevice,
      trusted_device_name: deviceName || null,
    });
  }

  /** Paso 2 del login con el código de la app autenticadora. */
  verifyTwoFactorCode(
    code: string,
    rememberDevice = false,
    deviceName = '',
  ): Observable<SessionPayload> {
    return this.completeChallenge(`${this.baseUrl}/web/auth/2fa/verify`, {
      code,
      remember_device: rememberDevice,
      trusted_device_name: deviceName || null,
    });
  }

  /** Paso 2 del login usando uno de los códigos de recuperación. */
  verifyRecoveryCode(
    recoveryCode: string,
    rememberDevice = false,
    deviceName = '',
  ): Observable<SessionPayload> {
    return this.completeChallenge(`${this.baseUrl}/web/auth/2fa/verify`, {
      recovery_code: recoveryCode,
      remember_device: rememberDevice,
      trusted_device_name: deviceName || null,
    });
  }

  twoFactorStatus(): Observable<TwoFactorStatusPayload> {
    return this.http
      .get<ApiResponse<TwoFactorStatusPayload>>(`${this.baseUrl}/2fa/status`)
      .pipe(map((response) => response.data as TwoFactorStatusPayload));
  }

  hasPendingChallenge(): boolean {
    return this.getChallengeToken() !== null;
  }

  isChallengeEnrolled(): boolean {
    return safeRead(CHALLENGE_ENROLLED_KEY, sessionStorage) === 'true';
  }

  // ------------------------------------------------------------ sesión (RF004)

  getToken(): string | null {
    return this.accessToken;
  }
  isAccessTokenExpiring(): boolean {
    return this.expiresAt !== null && Date.parse(this.expiresAt) - Date.now() <= 30000;
  }
  refreshSession(): Observable<TokenPair> {
    return defer(() =>
      from(
        this.sessionLock(async () => {
          const csrf = await firstValueFrom(
            this.http
              .get<ApiResponse<{ csrf_token: string }>>(`${this.baseUrl}/web/auth/csrf`, {
                withCredentials: true,
                headers: this.webHeaders(),
              })
              .pipe(timeout(10000)),
          );
          this.csrfToken = csrf.data?.csrf_token ?? null;
          const response = await firstValueFrom(
            this.http
              .post<ApiResponse<SessionPayload>>(
                `${this.baseUrl}/web/auth/refresh`,
                {},
                { withCredentials: true, headers: this.webHeaders(true) },
              )
              .pipe(timeout(10000)),
          );
          const payload = response.data as SessionPayload;
          this.storeSession(payload);
          return payload;
        }),
      ),
    );
  }

  /**
   * Cierra la sesión de ESTE dispositivo. El backend revoca el par de tokens
   * (access + refresh) emitido en este login; las sesiones abiertas en otros
   * dispositivos siguen vivas.
   */
  logout(): Observable<void> {
    return this.revokeThen(`${this.baseUrl}/web/auth/logout`);
  }

  /**
   * Cierra la sesión en TODOS los dispositivos revocando todos los tokens del
   * usuario. Útil si sospecha que alguien más tiene acceso a su cuenta.
   */
  logoutAll(): Observable<void> {
    return this.revokeThen(`${this.baseUrl}/web/auth/logout-all`);
  }

  /**
   * Borra el rastro local de la sesión. No revoca nada en el servidor: para eso
   * están logout() y logoutAll().
   */
  clearSession(): void {
    try {
      Object.keys(sessionStorage)
        .filter((key) => key.startsWith('aggrio:draft:'))
        .forEach((key) => sessionStorage.removeItem(key));
    } catch {
      /* Storage can be unavailable in private browsing. */
    }
    this.accessToken = this.expiresAt = this.csrfToken = null;
    safeRemove(ACCESS_TOKEN_KEY);
    safeRemove(REFRESH_TOKEN_KEY);
    safeRemove(EXPIRES_AT_KEY);
    safeRemove(USER_KEY);
    this.clearChallenge();
    // Si quedaba una renovación a medias, que no contamine la sesión siguiente.
    refreshState.reset();
    this.userSignal.set(null);
  }

  // --------------------------------------------------- recuperación (RF005)

  forgotPassword(email: string): Observable<string> {
    return this.http
      .post<ApiResponse<null>>(`${this.baseUrl}/password/forgot`, { email })
      .pipe(map((response) => response.message ?? 'Revisa tu correo.'));
  }

  changePassword(
    currentPassword: string | null,
    password: string,
    confirmation: string,
  ): Observable<SessionPayload> {
    return this.http
      .put<ApiResponse<SessionPayload>>(
        `${this.baseUrl}/web/auth/password`,
        {
          ...(currentPassword ? { current_password: currentPassword } : {}),
          password,
          password_confirmation: confirmation,
        },
        { withCredentials: true, headers: this.webHeaders(true) },
      )
      .pipe(
        timeout(20000),
        map((response) => response.data as SessionPayload),
        tap((payload) => this.storeSession(payload)),
      );
  }

  resetPassword(input: {
    token: string;
    email: string;
    password: string;
    passwordConfirmation: string;
  }): Observable<string> {
    return this.http
      .post<ApiResponse<null>>(`${this.baseUrl}/password/reset`, {
        token: input.token,
        email: input.email,
        password: input.password,
        password_confirmation: input.passwordConfirmation,
      })
      .pipe(map((response) => response.message ?? 'Contraseña actualizada.'));
  }

  // ------------------------------------------------------------------ interno

  /**
   * Pide al backend que revoque los tokens y limpia el estado local.
   *
   * La limpieza local ocurre pase lo que pase: si el token ya había caducado o
   * no hay red, la sesión debe terminar igualmente en este navegador.
   */
  private revokeThen(url: string): Observable<void> {
    safeWrite('aggrio:logout-pending', 'true');
    return this.http
      .post<ApiResponse<null>>(url, {}, { withCredentials: true, headers: this.webHeaders(true) })
      .pipe(
        timeout(10000),
        tap(() => safeRemove('aggrio:logout-pending')),
        catchError((e) => {
          if ([401, 403].includes(e.status)) safeRemove('aggrio:logout-pending');
          return of(null);
        }),
        tap(() => this.clearSession()),
        map(() => void 0),
      );
  }

  private completeChallenge(
    url: string,
    body: Record<string, string | boolean | null>,
  ): Observable<SessionPayload> {
    return this.http
      .post<ApiResponse<SessionPayload>>(
        url,
        {
          ...body,
          challenge_token: this.getChallengeToken(),
        },
        { withCredentials: true, headers: this.webHeaders() },
      )
      .pipe(
        map((response) => response.data as SessionPayload),
        tap((payload) => {
          this.storeSession(payload);
          this.clearChallenge();
        }),
      );
  }

  private storeSession(payload: SessionPayload | (TokenPair & { user?: User })): void {
    this.accessToken = payload.access_token;
    this.expiresAt = payload.expires_at;
    this.csrfToken = payload.csrf_token ?? this.csrfToken;
    safeRemove('aggrio:logout-pending');
    if (payload.user) this.userSignal.set(payload.user);
  }

  private storeChallenge(token: string, enrolled: boolean): void {
    safeWrite(CHALLENGE_KEY, token, sessionStorage);
    safeWrite(CHALLENGE_ENROLLED_KEY, String(enrolled), sessionStorage);
  }

  private getChallengeToken(): string | null {
    return safeRead(CHALLENGE_KEY, sessionStorage);
  }

  private clearChallenge(): void {
    safeRemove(CHALLENGE_KEY, sessionStorage);
    safeRemove(CHALLENGE_ENROLLED_KEY, sessionStorage);
  }
}

// El acceso a storage se envuelve porque en modo privado puede lanzar.

function safeRead(key: string, store: Storage = localStorage): string | null {
  try {
    return store.getItem(key);
  } catch {
    return null;
  }
}

function safeWrite(key: string, value: string, store: Storage = localStorage): void {
  try {
    store.setItem(key, value);
  } catch {
    /* almacenamiento no disponible */
  }
}

function safeRemove(key: string, store: Storage = localStorage): void {
  try {
    store.removeItem(key);
  } catch {
    /* almacenamiento no disponible */
  }
}
