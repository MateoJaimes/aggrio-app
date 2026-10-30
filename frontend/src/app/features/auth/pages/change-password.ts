import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Title } from '@angular/platform-browser';
import { AuthService } from '../../../core/auth/auth.service';
import { errorMessage } from '../error-message';

@Component({
  standalone: true,
  imports: [FormsModule, RouterLink],
  styleUrl: './auth-shell.scss',
  template: `<main class="auth-shell">
    <form class="auth-card" #form="ngForm" (ngSubmit)="submit()">
      <div class="brand">
        <span class="brand-mark">A</span
        ><span><strong>Aggrio</strong><small>Protege tu cuenta</small></span>
      </div>
      <h1>
        {{
          auth.currentUser()?.must_change_password
            ? 'Crea tu contraseña personal'
            : 'Cambiar contraseña'
        }}
      </h1>
      <p class="subtitle">
        {{
          auth.currentUser()?.must_change_password
            ? 'Antes de entrar a tu panel, cambia la contraseña temporal que recibiste por correo.'
            : 'Al guardar se cerrarán las demás sesiones de tu cuenta.'
        }}
      </p>
      @if (error()) {
        <p class="alert error" role="alert">{{ error() }}</p>
      }
      @if (saved()) {
        <p class="alert success" role="status">
          Tu contraseña ya se guardó. Puedes entrar a tu panel.
        </p>
        <a class="button" [routerLink]="auth.isSuperAdmin() ? '/dashboard' : '/campo'"
          >Entrar a mi panel</a
        >
      } @else {
        @if (!auth.currentUser()?.must_change_password) {
          <div class="field">
            <label for="current-password">Contraseña actual</label
            ><input
              id="current-password"
              name="current"
              [type]="showPasswords() ? 'text' : 'password'"
              autocomplete="current-password"
              [(ngModel)]="current"
              required
              [disabled]="busy()"
            />
          </div>
        }
        <div class="field">
          <label for="new-password">Nueva contraseña</label
          ><input
            id="new-password"
            name="password"
            [type]="showPasswords() ? 'text' : 'password'"
            autocomplete="new-password"
            [(ngModel)]="password"
            required
            minlength="12"
            pattern="(?=.*[A-Za-z])(?=.*[0-9]).*"
            [disabled]="busy()"
          /><small class="hint">Al menos 12 caracteres, con letras y números.</small>
        </div>
        <div class="field">
          <label for="confirm-password">Confirma tu nueva contraseña</label
          ><input
            id="confirm-password"
            name="confirmation"
            [type]="showPasswords() ? 'text' : 'password'"
            autocomplete="new-password"
            [(ngModel)]="confirmation"
            required
            [disabled]="busy()"
          />
        </div>
        <label class="visibility-toggle"
          ><input
            type="checkbox"
            [checked]="showPasswords()"
            (change)="showPasswords.set(!showPasswords())"
            [disabled]="busy()"
          /><span>Mostrar contraseñas</span></label
        >
        @if (confirmation && password !== confirmation) {
          <p class="alert error">Las contraseñas no coinciden.</p>
        }
        <button type="submit" class="button" [disabled]="busy()" [attr.aria-busy]="busy()">
          {{ busy() ? 'Guardando…' : 'Guardar y entrar a mi panel' }}
        </button>
      }
      <p class="foot">
        @if (!auth.currentUser()?.must_change_password) {
          <a class="linkish" [routerLink]="auth.isSuperAdmin() ? '/dashboard' : '/campo'"
            >Volver a mi panel</a
          >
        } @else {
          <button type="button" class="linkish" (click)="logout()" [disabled]="busy()">
            Cerrar sesión
          </button>
        }
      </p>
    </form>
  </main>`,
})
export class ChangePassword {
  readonly auth = inject(AuthService);
  private router = inject(Router);
  readonly busy = signal(false);
  readonly error = signal('');
  readonly saved = signal(false);
  readonly showPasswords = signal(false);
  current = '';
  password = '';
  confirmation = '';
  constructor() {
    inject(Title).setTitle('Aggrio | Cambiar contraseña');
  }
  submit() {
    if (this.busy() || this.saved()) return;
    const issue =
      !this.auth.currentUser()?.must_change_password && !this.current
        ? 'Ingresa tu contraseña actual.'
        : !this.password
          ? 'Ingresa tu nueva contraseña.'
          : this.password.length < 12
            ? 'La nueva contraseña debe tener al menos 12 caracteres.'
            : !/[A-Za-z]/.test(this.password) || !/[0-9]/.test(this.password)
              ? 'La nueva contraseña debe combinar letras y números.'
              : !this.confirmation
                ? 'Confirma tu nueva contraseña.'
                : this.password !== this.confirmation
                  ? 'Las contraseñas nuevas no coinciden.'
                  : this.password === this.current
                    ? 'La nueva contraseña debe ser diferente de la actual.'
                    : '';
    if (issue) {
      this.error.set(issue);
      return;
    }
    this.busy.set(true);
    this.error.set('');
    this.auth
      .changePassword(
        this.auth.currentUser()?.must_change_password ? null : this.current,
        this.password,
        this.confirmation,
      )
      .subscribe({
        next: () => {
          this.busy.set(false);
          this.saved.set(true);
          this.showPasswords.set(false);
          this.current = this.password = this.confirmation = '';
          void this.router
            .navigateByUrl(this.auth.isSuperAdmin() ? '/dashboard' : '/campo')
            .then((opened) => {
              if (!opened)
                this.error.set(
                  'La contraseña se guardó. Pulsa «Entrar a mi panel» para continuar.',
                );
            })
            .catch(() =>
              this.error.set(
                'La contraseña se guardó, pero no se pudo abrir el panel. Pulsa «Entrar a mi panel» para reintentar.',
              ),
            );
        },
        error: (e) => {
          this.busy.set(false);
          this.error.set(
            errorMessage(
              e,
              e?.name === 'TimeoutError'
                ? 'La solicitud tardó demasiado. Revisa tu conexión; si el cambio ya se completó, inicia sesión con tu nueva contraseña.'
                : 'No se pudo cambiar la contraseña.',
            ),
          );
        },
      });
  }
  logout() {
    this.auth.logout().subscribe(() => void this.router.navigateByUrl('/auth/login'));
  }
}
