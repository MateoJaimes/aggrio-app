import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import { AuthService } from '../../../core/auth/auth.service';
import { requiresTwoFactor } from '../../../core/auth/auth.models';
import { errorMessage } from '../error-message';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './login.html',
  styleUrl: './auth-shell.scss',
})
export class Login {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  readonly email = signal('');
  readonly password = signal('');
  readonly showPassword = signal(false);
  readonly isLoading = signal(false);
  readonly error = signal('');
  readonly notice = signal(
    this.route.snapshot.queryParamMap.get('expired')
      ? 'Tu sesión expiró. Vuelve a iniciar sesión.'
      : '',
  );

  togglePassword(): void {
    this.showPassword.update((value) => !value);
  }

  submit(): void {
    if (this.isLoading()) {
      return;
    }

    this.isLoading.set(true);
    this.error.set('');
    this.notice.set('');

    this.auth.login(this.email().trim(), this.password()).subscribe({
      next: (payload) => {
        this.isLoading.set(false);

        if (requiresTwoFactor(payload)) {
          void this.router.navigate(['/auth/two-factor']);

          return;
        }

        void this.router.navigateByUrl(this.redirectTarget());
      },
      error: (error: unknown) => {
        this.isLoading.set(false);
        this.error.set(errorMessage(error, 'No fue posible iniciar sesión.'));
      },
    });
  }

  private redirectTarget(): string {
    return this.route.snapshot.queryParamMap.get('redirect') ?? '/estates';
  }
}
