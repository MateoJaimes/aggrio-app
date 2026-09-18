import { CommonModule } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { SessionPayload, TwoFactorSetupPayload } from '../../../core/auth/auth.models';
import { AuthService } from '../../../core/auth/auth.service';
import { errorMessage } from '../error-message';

type Step = 'enroll' | 'verify' | 'recovery-codes';

@Component({
  selector: 'app-two-factor',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './two-factor.html',
  styleUrl: './auth-shell.scss',
})
export class TwoFactor implements OnInit {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  readonly step = signal<Step>('verify');
  readonly setup = signal<TwoFactorSetupPayload | null>(null);
  readonly recoveryCodes = signal<string[]>([]);
  readonly code = signal('');
  readonly useRecoveryCode = signal(false);
  readonly isLoading = signal(false);
  readonly error = signal('');

  ngOnInit(): void {
    // Un superadmin que aún no dio de alta su TOTP tiene que escanear el QR
    // antes de poder entrar (RF003).
    if (!this.auth.isChallengeEnrolled()) {
      this.step.set('enroll');
      this.loadSetup();
    }
  }

  toggleRecoveryCode(): void {
    this.useRecoveryCode.update((value) => !value);
    this.code.set('');
    this.error.set('');
  }

  submit(): void {
    if (this.isLoading() || !this.code().trim()) {
      return;
    }

    this.isLoading.set(true);
    this.error.set('');

    const value = this.code().trim();

    const request$ = this.step() === 'enroll'
      ? this.auth.confirmTwoFactorSetup(value)
      : this.useRecoveryCode()
        ? this.auth.verifyRecoveryCode(value)
        : this.auth.verifyTwoFactorCode(value);

    request$.subscribe({
      next: (payload) => this.onVerified(payload),
      error: (error: unknown) => {
        this.isLoading.set(false);
        this.code.set('');
        this.error.set(errorMessage(error, 'No fue posible verificar el código.'));

        // El backend invalida el reto tras agotar los intentos.
        if ((error as { status?: number })?.status === 401) {
          void this.router.navigate(['/auth/login']);
        }
      },
    });
  }

  finish(): void {
    void this.router.navigate(['/estates']);
  }

  backToLogin(): void {
    this.auth.clearSession();
    void this.router.navigate(['/auth/login']);
  }

  private loadSetup(): void {
    this.isLoading.set(true);

    this.auth.startTwoFactorSetup().subscribe({
      next: (payload) => {
        this.setup.set(payload);
        this.isLoading.set(false);
      },
      error: (error: unknown) => {
        this.isLoading.set(false);
        this.error.set(errorMessage(error, 'No fue posible generar el código QR.'));
      },
    });
  }

  private onVerified(payload: SessionPayload): void {
    this.isLoading.set(false);

    // Los códigos de recuperación sólo se muestran una vez, al dar de alta el 2FA.
    if (payload.recovery_codes?.length) {
      this.recoveryCodes.set(payload.recovery_codes);
      this.step.set('recovery-codes');

      return;
    }

    this.finish();
  }
}
