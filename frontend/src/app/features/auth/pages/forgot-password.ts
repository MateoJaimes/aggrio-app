import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../../core/auth/auth.service';
import { errorMessage } from '../error-message';

@Component({
  selector: 'app-forgot-password',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './forgot-password.html',
  styleUrl: './auth-shell.scss',
})
export class ForgotPassword {
  private readonly auth = inject(AuthService);

  readonly email = signal('');
  readonly isLoading = signal(false);
  readonly error = signal('');
  readonly notice = signal('');

  submit(): void {
    if (this.isLoading() || !this.email().trim()) {
      return;
    }

    this.isLoading.set(true);
    this.error.set('');
    this.notice.set('');

    this.auth.forgotPassword(this.email().trim()).subscribe({
      next: (message) => {
        this.isLoading.set(false);
        this.notice.set(message);
      },
      error: (error: unknown) => {
        this.isLoading.set(false);
        this.error.set(errorMessage(error, 'No fue posible enviar el correo.'));
      },
    });
  }
}
