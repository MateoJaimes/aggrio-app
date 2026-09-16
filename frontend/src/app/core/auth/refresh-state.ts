import { BehaviorSubject } from 'rxjs';

/**
 * Estado compartido de la renovación de sesión (RF004).
 *
 * Vive fuera del interceptor para que el AuthService pueda reiniciarlo al cerrar
 * sesión sin crear una dependencia circular entre ambos.
 *
 * Si varias peticiones reciben un 401 a la vez, sólo la primera renueva y las
 * demás esperan al token nuevo publicado aquí.
 */
export const refreshState = {
  isRefreshing: false,
  token$: new BehaviorSubject<string | null>(null),

  /** Marca el inicio de una renovación e invalida el token anterior. */
  begin(): void {
    this.isRefreshing = true;
    this.token$.next(null);
  },

  /** Publica el token nuevo y libera a las peticiones en espera. */
  succeed(token: string): void {
    this.isRefreshing = false;
    this.token$.next(token);
  },

  /** La renovación falló: las peticiones en espera quedan colgadas y caducan con la sesión. */
  fail(): void {
    this.isRefreshing = false;
  },

  /**
   * Deja el estado limpio al cerrar sesión, para que la siguiente sesión no
   * herede un token publicado por la anterior.
   */
  reset(): void {
    this.isRefreshing = false;
    this.token$.next(null);
  },
};
