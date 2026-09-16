import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, catchError, map, of, tap, throwError } from 'rxjs';

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

/** Nombre con el que esta sesión aparece en el listado de tokens del backend. */
const DEVICE_NAME = 'panel-angular';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = environment.apiUrl;

  private readonly userSignal = signal<User | null>(readUser());

  readonly currentUser = this.userSignal.asReadonly();
  readonly isAuthenticated = computed(() => this.userSignal() !== null && this.getToken() !== null);
  readonly isSuperAdmin = computed(() => this.userSignal()?.is_superadmin === true);

  // -------------------------------------------------------------- login (RF003)

  /**
   * Paso 1. Si el rol exige doble factor la respuesta no trae tokens, sino un
   * challenge_token que queda guardado para la pantalla de verificación.
   */
  login(email: string, password: string): Observable<LoginPayload> {
    return this.http
      .post<ApiResponse<LoginPayload>>(`${this.baseUrl}/login`, {
        email,
        password,
        device_name: DEVICE_NAME,
      })
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
      .post<ApiResponse<TwoFactorSetupPayload>>(`${this.baseUrl}/login/2fa/setup`, {
        challenge_token: this.getChallengeToken(),
      })
      .pipe(map((response) => response.data as TwoFactorSetupPayload));
  }

  /** Confirma el alta del TOTP y completa el login. */
  confirmTwoFactorSetup(code: string): Observable<SessionPayload> {
    return this.completeChallenge(`${this.baseUrl}/login/2fa/confirm`, { code });
  }

  /** Paso 2 del login con el código de la app autenticadora. */
  verifyTwoFactorCode(code: string): Observable<SessionPayload> {
    return this.completeChallenge(`${this.baseUrl}/login/2fa/verify`, { code });
  }

  /** Paso 2 del login usando uno de los códigos de recuperación. */
  verifyRecoveryCode(recoveryCode: string): Observable<SessionPayload> {
    return this.completeChallenge(`${this.baseUrl}/login/2fa/verify`, { recovery_code: recoveryCode });
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
    return safeRead(ACCESS_TOKEN_KEY);
  }

  getRefreshToken(): string | null {
    return safeRead(REFRESH_TOKEN_KEY);
  }

  /** El access token venció o está a punto de vencer (margen de 30 s). */
  isAccessTokenExpiring(): boolean {
    const expiresAt = safeRead(EXPIRES_AT_KEY);

    if (!expiresAt) {
      return false;
    }

    return Date.parse(expiresAt) - Date.now() <= 30_000;
  }

  /** Canjea el refresh token por un par nuevo. */
  refreshSession(): Observable<TokenPair> {
    const refreshToken = this.getRefreshToken();

    if (!refreshToken) {
      return throwError(() => new Error('No hay refresh token disponible.'));
    }

    return this.http
      .post<ApiResponse<SessionPayload>>(
        `${this.baseUrl}/auth/refresh`,
        {},
        { headers: { Authorization: `Bearer ${refreshToken}` } },
      )
      .pipe(
        map((response) => response.data as SessionPayload),
        tap((payload) => this.storeSession(payload)),
      );
  }

  /**
   * Cierra la sesión de ESTE dispositivo. El backend revoca el par de tokens
   * (access + refresh) emitido en este login; las sesiones abiertas en otros
   * dispositivos siguen vivas.
   */
  logout(): Observable<void> {
    return this.revokeThen(`${this.baseUrl}/logout`);
  }

  /**
   * Cierra la sesión en TODOS los dispositivos revocando todos los tokens del
   * usuario. Útil si sospecha que alguien más tiene acceso a su cuenta.
   */
  logoutAll(): Observable<void> {
    return this.revokeThen(`${this.baseUrl}/logout-all`);
  }

  /**
   * Borra el rastro local de la sesión. No revoca nada en el servidor: para eso
   * están logout() y logoutAll().
   */
  clearSession(): void {
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
    if (!this.getToken()) {
      this.clearSession();

      return of(void 0);
    }

    return this.http.post<ApiResponse<null>>(url, {}).pipe(
      catchError(() => of(null)),
      tap(() => this.clearSession()),
      map(() => void 0),
    );
  }

  private completeChallenge(url: string, body: Record<string, string>): Observable<SessionPayload> {
    return this.http
      .post<ApiResponse<SessionPayload>>(url, { ...body, challenge_token: this.getChallengeToken() })
      .pipe(
        map((response) => response.data as SessionPayload),
        tap((payload) => {
          this.storeSession(payload);
          this.clearChallenge();
        }),
      );
  }

  private storeSession(payload: SessionPayload | (TokenPair & { user?: User })): void {
    safeWrite(ACCESS_TOKEN_KEY, payload.access_token);
    safeWrite(REFRESH_TOKEN_KEY, payload.refresh_token);
    safeWrite(EXPIRES_AT_KEY, payload.expires_at);

    if (payload.user) {
      safeWrite(USER_KEY, JSON.stringify(payload.user));
      this.userSignal.set(payload.user);
    }
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

function readUser(): User | null {
  const raw = safeRead(USER_KEY);

  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as User;
  } catch {
    return null;
  }
}
