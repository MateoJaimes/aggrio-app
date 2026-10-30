import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { timeout } from 'rxjs';
import { AdminSidebar } from '../admin-sidebar';
import { environment } from '../../../environments/environment';
import { ApiResponse } from '../../core/models/api-response.model';

interface Account {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: string;
  account_status: string;
  approval_status: string | null;
  must_change_password: boolean;
  fincas_count: number;
  suspension_reason: string | null;
}
interface Page {
  data: Account[];
  current_page: number;
  last_page: number;
  total: number;
}
interface AccountEvent {
  id: number;
  action: string;
  reason: string;
  actor_name: string;
  created_at: string;
}
@Component({
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, AdminSidebar],
  templateUrl: './accounts.html',
  styleUrls: ['../lots/pages/lots-shell.scss', './accounts.scss'],
})
export class Accounts {
  private http = inject(HttpClient);
  private base = environment.apiUrl + '/admin/accounts';
  readonly page = signal<Page | null>(null);
  readonly loading = signal(false);
  readonly busy = signal(false);
  readonly error = signal('');
  readonly notice = signal('');
  readonly selected = signal<Account | null>(null);
  readonly history = signal<AccountEvent[] | null>(null);
  search = '';
  status = '';
  action = 'suspend';
  reason = '';
  private epoch = 0;
  private operation: { fingerprint: string; key: string } | null = null;
  constructor() {
    this.load();
  }
  load(page = 1) {
    const epoch = ++this.epoch;
    this.loading.set(true);
    this.error.set('');
    const params = { page: String(page), search: this.search.trim(), status: this.status };
    this.http.get<ApiResponse<Page>>(this.base, { params }).subscribe({
      next: (r) => {
        if (epoch !== this.epoch) return;
        this.page.set(r.data ?? null);
        this.loading.set(false);
      },
      error: (e) => {
        if (epoch !== this.epoch) return;
        this.loading.set(false);
        this.fail(e);
      },
    });
  }
  open(user: Account, action: string) {
    this.operation = null;
    this.selected.set(user);
    this.action = action;
    this.reason = '';
    this.history.set(null);
    this.error.set('');
  }
  close() {
    if (!this.busy()) {
      this.selected.set(null);
      this.history.set(null);
    }
  }
  get actionTitle() {
    return (
      { suspend: 'Suspender cuenta', reactivate: 'Reactivar cuenta', recover: 'Recuperar acceso' }[
        this.action
      ] ?? 'Administrar cuenta'
    );
  }
  get actionHelp() {
    return (
      {
        suspend:
          'El productor no podrá entrar y se cerrarán sus sesiones. Sus fincas, archivos e historial se conservarán.',
        reactivate:
          'La cuenta vuelve a estar activa. Para entrar necesita su solicitud aprobada y una contraseña vigente. No cambia su contraseña ni aprueba sus fincas.',
        recover:
          'Se reemplazará la contraseña por una temporal y se cerrarán sus sesiones. Recibirá instrucciones y deberá crear su contraseña personal al entrar. Esta acción no reactiva cuentas suspendidas.',
      }[this.action] ?? ''
    );
  }
  submit() {
    const user = this.selected();
    if (!user || this.busy()) return;
    if (this.reason.trim().length < 10) {
      this.error.set('Explica el motivo con al menos 10 caracteres.');
      return;
    }
    this.busy.set(true);
    this.error.set('');
    const body = { action: this.action, reason: this.reason.trim() };
    const fingerprint = JSON.stringify({ id: user.id, ...body });
    if (this.operation?.fingerprint !== fingerprint)
      this.operation = { fingerprint, key: crypto.randomUUID() };
    this.http
      .post<ApiResponse<unknown>>(
        `${this.base}/${user.id}/actions`,
        {
          action: this.action,
          reason: this.reason.trim(),
        },
        { headers: { 'Idempotency-Key': this.operation!.key } },
      )
      .pipe(timeout(15000))
      .subscribe({
        next: (r) => {
          this.busy.set(false);
          this.operation = null;
          this.selected.set(null);
          this.notice.set(r.message ?? 'Cuenta actualizada.');
          this.load(this.page()?.current_page ?? 1);
        },
        error: (e) => {
          this.busy.set(false);
          this.fail(e);
        },
      });
  }
  showHistory(user: Account) {
    this.selected.set(user);
    this.history.set([]);
    this.error.set('');
    this.http.get<ApiResponse<AccountEvent[]>>(`${this.base}/${user.id}/history`).subscribe({
      next: (r) => {
        if (this.selected()?.id === user.id) this.history.set(r.data ?? []);
      },
      error: (e) => this.fail(e),
    });
  }
  eventLabel(action: string) {
    return (
      { suspend: 'Suspensión', reactivate: 'Reactivación', recover: 'Recuperación de acceso' }[
        action
      ] ?? action
    );
  }
  private fail(e: any) {
    this.error.set(
      e.error?.errors
        ? Object.values(e.error.errors).flat().join(' ')
        : (e.error?.message ?? 'No se pudo completar la operación.'),
    );
  }
}
