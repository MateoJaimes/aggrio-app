import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Component, OnDestroy, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../core/auth/auth.service';
import { AdminSidebar } from '../admin-sidebar';

interface Authenticator {
  id: string;
  name: string;
  confirmed_at: string;
}
interface TrustedBrowser {
  id: string;
  name: string;
  last_used_at: string;
  expires_at: string;
  is_current: boolean;
}
interface SecurityState {
  authenticators: Authenticator[];
  trusted_devices: TrustedBrowser[];
  recovery_codes_remaining: number;
  remember_days: number;
  events: { id: number; action: string; device_name: string | null; created_at: string }[];
}
interface Pending {
  id: string;
  name: string;
  mode: string;
  secret: string;
  qr_code: string;
  expires_at: string;
}
interface Response<T> {
  success: boolean;
  data: T;
}

@Component({
  selector: 'app-settings',
  standalone: true,
  imports: [CommonModule, FormsModule, AdminSidebar],
  templateUrl: './settings.html',
  styleUrl: './settings.scss',
})
export class Settings implements OnInit, OnDestroy {
  readonly auth = inject(AuthService);
  private http = inject(HttpClient);
  private base = `${environment.apiUrl}/admin/settings/security`;
  readonly state = signal<SecurityState | null>(null);
  readonly pending = signal<Pending | null>(null);
  readonly codes = signal<string[]>([]);
  readonly busy = signal(false);
  readonly error = signal('');
  readonly notice = signal('');
  readonly action = signal<'add' | 'replace' | 'remove' | 'recovery' | null>(null);
  target: Authenticator | null = null;
  name = '';
  password = '';
  currentCode = '';
  newCode = '';
  useRecovery = false;
  async ngOnInit() {
    await this.load();
  }
  ngOnDestroy() {
    this.clearCredentials();
    this.pending.set(null);
    this.codes.set([]);
  }
  clearCredentials() {
    this.password = '';
    this.currentCode = '';
    this.newCode = '';
  }
  async load() {
    this.busy.set(true);
    try {
      this.state.set(
        (await firstValueFrom(this.http.get<Response<SecurityState>>(this.base))).data,
      );
    } catch (e) {
      this.fail(e);
    } finally {
      this.busy.set(false);
    }
  }
  choose(action: 'add' | 'replace' | 'remove' | 'recovery', target: Authenticator | null = null) {
    this.clearCredentials();
    this.pending.set(null);
    this.codes.set([]);
    this.error.set('');
    this.notice.set('');
    this.name = '';
    this.useRecovery = false;
    this.target = target;
    this.action.set(action);
  }
  cancel() {
    this.action.set(null);
    this.pending.set(null);
    this.clearCredentials();
    this.error.set('');
  }
  async submit() {
    if (!this.action() || this.busy()) return;
    this.busy.set(true);
    this.error.set('');
    const body = {
      password: this.password,
      ...(this.useRecovery
        ? { recovery_code: this.currentCode.trim() }
        : { code: this.currentCode.trim() }),
    };
    try {
      if (this.action() === 'add' || this.action() === 'replace') {
        this.pending.set(
          (
            await firstValueFrom(
              this.http.post<Response<Pending>>(`${this.base}/authenticators`, {
                ...body,
                name: this.name.trim(),
                mode: this.action(),
              }),
            )
          ).data,
        );
        this.action.set(null);
        this.clearCredentials();
      } else if (this.action() === 'remove' && this.target) {
        await firstValueFrom(
          this.http.delete(`${this.base}/authenticators/${this.target.id}`, { body }),
        );
        this.cancel();
        this.notice.set(
          'Aplicación retirada. Los demás navegadores deberán verificar su identidad nuevamente.',
        );
        await this.load();
      } else if (this.action() === 'recovery') {
        const data = (
          await firstValueFrom(
            this.http.post<Response<{ recovery_codes: string[] }>>(
              `${this.base}/recovery-codes`,
              body,
            ),
          )
        ).data;
        this.codes.set(data.recovery_codes);
        this.action.set(null);
        this.clearCredentials();
        await this.load();
      }
    } catch (e) {
      this.fail(e);
      this.password = '';
      this.currentCode = '';
    } finally {
      this.busy.set(false);
    }
  }
  async confirm() {
    const pending = this.pending();
    if (!pending || this.busy()) return;
    this.busy.set(true);
    this.error.set('');
    try {
      await firstValueFrom(
        this.http.post(`${this.base}/authenticators/${pending.id}/confirm`, {
          code: this.newCode.trim(),
        }),
      );
      this.pending.set(null);
      this.newCode = '';
      this.notice.set(
        'Aplicación confirmada. Los navegadores recordados se revocaron y las otras sesiones se cerraron. Tu sesión actual continúa.',
      );
      await this.load();
    } catch (e) {
      this.fail(e);
      this.newCode = '';
    } finally {
      this.busy.set(false);
    }
  }
  async forget(id: string) {
    this.busy.set(true);
    this.error.set('');
    try {
      await firstValueFrom(this.http.delete(`${this.base}/trusted-devices/${id}`));
      this.notice.set(
        id === 'all'
          ? 'Navegadores olvidados. El próximo ingreso pedirá doble factor.'
          : 'Navegador olvidado. Su próximo ingreso pedirá doble factor.',
      );
      await this.load();
    } catch (e) {
      this.fail(e);
    } finally {
      this.busy.set(false);
    }
  }
  eventLabel(action: string): string {
    return (
      (
        {
          browser_remembered: 'Navegador recordado',
          browser_forgotten: 'Navegador olvidado',
          all_browsers_forgotten: 'Todos los navegadores olvidados',
          authenticator_added: 'Aplicación añadida',
          authenticator_replaced: 'Aplicación principal reemplazada',
          authenticator_removed: 'Aplicación retirada',
          recovery_codes_regenerated: 'Códigos de recuperación renovados',
        } as Record<string, string>
      )[action] ?? action
    );
  }
  fail(e: any) {
    this.error.set(e?.error?.message ?? 'No se pudo completar la operación. Intenta nuevamente.');
  }
}
