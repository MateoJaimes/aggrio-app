import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { Title } from '@angular/platform-browser';
import { environment } from '../../../environments/environment';

@Component({
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './access-request.html',
  styleUrl: './access-request.scss',
})
export class AccessRequestPage {
  constructor() {
    inject(Title).setTitle('Aggrio | Solicitar acceso');
  }
  private http = inject(HttpClient);
  readonly busy = signal(false);
  readonly error = signal('');
  readonly notice = signal('');
  data = {
    firstname: '',
    lastname: '',
    id_type: '',
    id_number: '',
    email: '',
    landname: '',
    country: '',
    department: '',
    city: '',
    consent: false,
  };
  submit() {
    if (this.busy()) return;
    this.busy.set(true);
    this.error.set('');
    this.http
      .post<{ message: string }>(`${environment.apiUrl}/access-requests`, this.data)
      .subscribe({
        next: (r) => {
          this.notice.set(r.message);
          this.busy.set(false);
        },
        error: (e) => {
          this.busy.set(false);
          this.error.set(
            e.error?.errors
              ? Object.values(e.error.errors).flat().join(' ')
              : (e.error?.message ?? 'No se pudo enviar. Revisa tu conexión e intenta de nuevo.'),
          );
        },
      });
  }
}
