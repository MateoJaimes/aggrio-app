import { Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable } from 'rxjs';

import { AuthService } from './auth.service';

/**
 * Identidad del usuario y cierre de sesión, para el pie de la barra lateral.
 *
 * Distingue las dos formas de terminar una sesión (RF004):
 *  - «Cerrar sesión» revoca el par de tokens de ESTE dispositivo.
 *  - «Cerrar en todos los dispositivos» revoca todos los tokens del usuario.
 */
@Component({
  selector: 'app-session-menu',
  standalone: true,
  template: `
    @if (user(); as currentUser) {
      <div class="session">
        <div class="identity">
          <span class="avatar" aria-hidden="true">{{ initials(currentUser.name) }}</span>
          <span class="who">
            <strong [title]="currentUser.name">{{ currentUser.name }}</strong>
            <small [title]="currentUser.email">{{ currentUser.email }}</small>
          </span>
        </div>

        @if (currentUser.is_superadmin) {
          <span class="role">Superadmin</span>
        }

        @if (error()) {
          <p class="session-error" role="alert">{{ error() }}</p>
        }

        <button type="button" class="logout" (click)="logout()" [disabled]="isBusy()">
          {{ isBusy() ? 'Cerrando…' : 'Cerrar sesión' }}
        </button>

        <button type="button" class="logout-all" (click)="logoutAll()" [disabled]="isBusy()">
          Cerrar en todos los dispositivos
        </button>
      </div>
    }
  `,
  styles: [
    `
      :host { display: block; margin-top: 1rem; }
      .session { display: grid; gap: .55rem; padding: .9rem; border: 1px solid #ffffff1f; border-radius: 1rem; background: #ffffff0d; }
      .identity { display: flex; gap: .6rem; align-items: center; min-width: 0; }
      .avatar { display: grid; flex: none; width: 2rem; height: 2rem; place-items: center; border-radius: 50%; background: #2563eb; color: #fff; font-size: .78rem; font-weight: 700; }
      .who { display: grid; min-width: 0; }
      .who strong { overflow: hidden; color: #fff; font-size: .86rem; text-overflow: ellipsis; white-space: nowrap; }
      .who small { overflow: hidden; color: #ffffff9e; font-size: .72rem; text-overflow: ellipsis; white-space: nowrap; }
      .role { justify-self: start; padding: .15rem .5rem; border-radius: 999px; background: #2563eb33; color: #bfdbfe; font-size: .66rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
      .session-error { margin: 0; color: #fecaca; font-size: .74rem; }
      button { width: 100%; border: 0; border-radius: .65rem; font: inherit; cursor: pointer; }
      button:disabled { cursor: wait; opacity: .55; }
      .logout { padding: .6rem .7rem; background: #ffffff17; color: #fff; font-size: .82rem; font-weight: 700; }
      .logout:hover:not(:disabled) { background: #ffffff2b; }
      .logout-all { padding: .35rem; background: none; color: #ffffff8f; font-size: .72rem; }
      .logout-all:hover:not(:disabled) { color: #fecaca; text-decoration: underline; }
      button:focus-visible { outline: 2px solid #bfdbfe; outline-offset: 2px; }
    `,
  ],
})
export class SessionMenu {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  readonly user = this.auth.currentUser;
  readonly isBusy = signal(false);
  readonly error = signal('');

  logout(): void {
    this.end(() => this.auth.logout());
  }

  logoutAll(): void {
    const confirmed = window.confirm(
      'Se cerrarán todas tus sesiones, incluida la de la aplicación móvil. ¿Continuar?',
    );

    if (confirmed) {
      this.end(() => this.auth.logoutAll());
    }
  }

  initials(name: string): string {
    return name
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toLocaleUpperCase() ?? '')
      .join('');
  }

  private end(revoke: () => Observable<void>): void {
    if (this.isBusy()) {
      return;
    }

    this.isBusy.set(true);
    this.error.set('');

    revoke().subscribe({
      next: () => {
        this.isBusy.set(false);
        void this.router.navigate(['/auth/login']);
      },
      error: () => {
        // revokeThen() ya limpió la sesión local; sólo queda salir.
        this.isBusy.set(false);
        void this.router.navigate(['/auth/login']);
      },
    });
  }
}
