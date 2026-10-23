import { CommonModule } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';

import { AuthService } from '../../../core/auth/auth.service';
import { errorMessage } from '../../auth/error-message';
import { ProducerSidebar } from '../producer-sidebar';

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ProducerSidebar],
  templateUrl: './profile.html',
  styleUrl: './account-shell.scss',
})
export class Profile implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  readonly isLoading = signal(true);
  readonly isSaving = signal(false);
  readonly isChangingPassword = signal(false);
  readonly notice = signal('');
  readonly error = signal('');
  readonly form = this.fb.nonNullable.group({
    firstname: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(40), Validators.pattern(/^[A-Za-zÀ-ÿ\s]+$/)]],
    lastname: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(40), Validators.pattern(/^[A-Za-zÀ-ÿ\s]+$/)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(255)]],
    idType: ['CC' as 'CC' | 'TI' | 'Pasaporte', [Validators.required]],
    idNumber: ['', [Validators.required, Validators.pattern(/^[0-9A-Za-z]{5,20}$/)]],
    country: ['', [Validators.required, Validators.maxLength(255)]],
    department: ['', [Validators.required, Validators.maxLength(255)]],
    city: ['', [Validators.required, Validators.maxLength(255)]],
  });
  readonly passwordForm = this.fb.nonNullable.group({
    currentPassword: ['', [Validators.required]],
    password: ['', [Validators.required, Validators.minLength(8), Validators.pattern(/^(?=.*[A-Za-z])(?=.*\d).+$/)]],
    passwordConfirmation: ['', [Validators.required]],
  });

  ngOnInit(): void {
    this.auth.getProfile().subscribe({
      next: (user) => {
        this.form.patchValue({
          firstname: user.firstname,
          lastname: user.lastname,
          email: user.email,
          idType: user.id_type ?? 'CC',
          idNumber: user.id_number ?? '',
          country: user.country ?? '',
          department: user.department ?? '',
          city: user.city ?? '',
        });
        this.isLoading.set(false);
      },
      error: (error: unknown) => {
        this.error.set(errorMessage(error, 'No fue posible cargar tu perfil.'));
        this.isLoading.set(false);
      },
    });
  }

  save(): void {
    if (this.isSaving() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const values = this.form.getRawValue();
    this.isSaving.set(true);
    this.error.set('');
    this.notice.set('');
    this.auth.updateProfile({
      firstname: values.firstname.trim(),
      lastname: values.lastname.trim(),
      email: values.email.trim(),
      idType: values.idType,
      idNumber: values.idNumber.trim(),
      country: values.country.trim(),
      department: values.department.trim(),
      city: values.city.trim(),
    }).subscribe({
      next: () => {
        this.isSaving.set(false);
        this.notice.set('Tus datos fueron guardados correctamente.');
      },
      error: (error: unknown) => {
        this.isSaving.set(false);
        this.error.set(errorMessage(error, 'No fue posible guardar los cambios.'));
      },
    });
  }

  changePassword(): void {
    if (this.isChangingPassword() || this.passwordForm.invalid) {
      this.passwordForm.markAllAsTouched();
      return;
    }

    const values = this.passwordForm.getRawValue();

    if (values.password !== values.passwordConfirmation) {
      this.error.set('La confirmación de contraseña no coincide.');
      return;
    }

    this.isChangingPassword.set(true);
    this.error.set('');
    this.notice.set('');
    this.auth.changePassword(values).subscribe({
      next: () => {
        this.auth.clearSession();
        void this.router.navigate(['/auth/login'], { queryParams: { passwordChanged: 1 } });
      },
      error: (error: unknown) => {
        this.isChangingPassword.set(false);
        this.error.set(errorMessage(error, 'No fue posible cambiar la contraseña.'));
      },
    });
  }
}
