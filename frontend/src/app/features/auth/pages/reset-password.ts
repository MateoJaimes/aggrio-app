import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import { AuthService } from '../../../core/auth/auth.service';
import { errorMessage } from '../error-message';

@Component({
  selector: 'app-reset-password',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './reset-password.html',
  styleUrl: './auth-shell.scss',
})
export class ResetPassword {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  // El correo enlaza a /auth/reset-password?token=...&email=...
  private readonly token = this.route.snapshot.queryParamMap.get('token') ?? '';
  readonly email = signal(this.route.snapshot.queryParamMap.get('email') ?? '');

  readonly password = signal('');
  readonly passwordConfirmation = signal('');
  readonly showPassword = signal(false);
  readonly isLoading = signal(false);
  readonly error = signal('');
  readonly notice = signal('');

  readonly hasValidLink = computed(() => this.token !== '' && this.email() !== '');

  // Mismas reglas que valida el backend: mínimo 8, con letras y números.
  readonly passwordIssue = computed(() => {
    const password = this.password();

    if (!password) {
      return '';
    }

    if (password.length < 8) {
      return 'Debe tener al menos 8 caracteres.';
    }

    if (!/[a-zA-Z]/.test(password) || !/\d/.test(password)) {
      return 'Debe combinar letras y números.';
    }

    if (this.passwordConfirmation() && password !== this.passwordConfirmation()) {
      return 'Las contraseñas no coinciden.';
    }

    return '';
  });

  readonly canSubmit = computed(
    () =>
      this.hasValidLink() &&
      !this.isLoading() &&
      this.password() !== '' &&
      this.passwordConfirmation() !== '' &&
      this.passwordIssue() === '',
  );

  togglePassword(): void {
    this.showPassword.update((value) => !value);
  }

  submit(): void {
    if (!this.canSubmit()) {
      return;
    }

    this.isLoading.set(true);
    this.error.set('');

    this.auth
      .resetPassword({
        token: this.token,
        email: this.email().trim(),
        password: this.password(),
        passwordConfirmation: this.passwordConfirmation(),
      })
      .subscribe({
        next: (message) => {
          this.isLoading.set(false);
          this.notice.set(message);

          // El backend cerró todas las sesiones: hay que entrar de nuevo.
          this.auth.clearSession();
          setTimeout(() => void this.router.navigate(['/auth/login']), 2000);
        },
        error: (error: unknown) => {
          this.isLoading.set(false);
          this.error.set(errorMessage(error, 'No fue posible restablecer la contraseña.'));
        },
      });
  }
}
