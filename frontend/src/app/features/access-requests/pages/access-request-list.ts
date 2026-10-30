import { AdminSidebar } from '../../admin-sidebar';
import { CommonModule, DatePipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';

import {
  AccessRequest,
  AccessRequestAction,
  AccessRequestStatus,
} from '../models/access-request.model';
import { AccessRequestAdminService } from '../services/access-request-admin.service';

@Component({
  selector: 'app-access-request-list',
  standalone: true,
  imports: [AdminSidebar, CommonModule, DatePipe],
  templateUrl: './access-request-list.html',
  styleUrl: './access-request-list.scss',
})
export class AccessRequestList implements OnInit {
  private readonly accessRequestService = inject(AccessRequestAdminService);

  readonly requests = signal<AccessRequest[]>([]);
  readonly isLoading = signal(false);
  readonly processingId = signal<number | null>(null);
  readonly error = signal('');
  readonly notice = signal('');
  readonly selectedRequest = signal<AccessRequest | null>(null);
  readonly search = signal('');
  readonly filter = signal<AccessRequestStatus | 'all'>('all');
  readonly mailPreview = signal('');
  readonly deliveryReady = signal(true);
  readonly consoleResetEnabled = signal(false);

  invitationLabel(status?: string | null): string {
    return (
      (
        {
          queued: 'Correo en cola',
          sent: 'Entregado al servidor de correo',
          preview: 'Correo de prueba disponible',
          failed: 'Error de envío',
          expired: 'Contraseña temporal caducada',
          completed: 'Primer acceso completado',
          cancelled: 'Invitación cancelada por suspensión de la cuenta',
        } as Record<string, string>
      )[status ?? ''] ?? 'Sin invitación registrada'
    );
  }
  resend(request: AccessRequest, resetPassword = false): void {
    if (
      !window.confirm(
        resetPassword ? '¿Generar una nueva contraseña temporal para esta cuenta de prueba? Se reemplazará su contraseña actual y se cerrarán sus sesiones. La nueva aparecerá en la consola del trabajador.' : '¿Reenviar las instrucciones de acceso? Si aún utiliza una contraseña temporal, se generará una nueva.',
      )
    )
      return;
    this.processingId.set(request.id);
    this.error.set('');
    this.accessRequestService.resend(request.id, resetPassword).subscribe({
      next: (r) => {
        if (r.data) this.replaceRequest(r.data);
        this.notice.set(r.message ?? 'Invitación encolada.');
        this.processingId.set(null);
      },
      error: (e) => {
        this.error.set(this.getErrorMessage(e, 'No se pudo reenviar.'));
        this.processingId.set(null);
      },
    });
  }
  previewInvitation(request: AccessRequest): void {
    this.accessRequestService.preview(request.id).subscribe({
      next: (r) => this.mailPreview.set(r.data?.html ?? ''),
      error: (e) =>
        this.error.set(this.getErrorMessage(e, 'No se pudo abrir el correo de prueba.')),
    });
  }

  readonly visibleRequests = computed(() => {
    const term = this.search().trim().toLocaleLowerCase();
    const filter = this.filter();

    return this.requests().filter((request) => {
      const matchesStatus = filter === 'all' || request.status === filter;
      const matchesSearch =
        !term ||
        [
          request.firstname,
          request.lastname,
          request.email,
          request.landname,
          request.id_number,
        ].some((value) => value.toLocaleLowerCase().includes(term));

      return matchesStatus && matchesSearch;
    });
  });

  ngOnInit(): void {
    this.loadRequests();
  }

  count(status?: AccessRequestStatus): number {
    return status
      ? this.requests().filter((request) => request.status === status).length
      : this.requests().length;
  }

  loadRequests(): void {
    this.isLoading.set(true);
    this.error.set('');

    this.accessRequestService.getAccessRequests().subscribe({
      next: (response) => {
        this.requests.set(response.data ?? []);
        this.deliveryReady.set(response.delivery?.configured ?? true);
        this.consoleResetEnabled.set(response.delivery?.console_reset ?? false);
        this.isLoading.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudieron cargar las solicitudes.'));
        this.isLoading.set(false);
      },
    });
  }

  setSearch(event: Event): void {
    this.search.set((event.target as HTMLInputElement).value);
  }

  setFilter(filter: AccessRequestStatus | 'all'): void {
    this.filter.set(filter);
  }

  openDetails(request: AccessRequest): void {
    this.selectedRequest.set(request);
  }

  closeDetails(): void {
    this.selectedRequest.set(null);
  }

  applyAction(request: AccessRequest, action: AccessRequestAction): void {
    const labels: Record<AccessRequestAction, string> = {
      approve: 'permitir',
      waitlist: 'poner en espera',
      deny: 'negar',
    };

    const confirmed = window.confirm(
      action === 'approve'
        ? `¿Aprobar la solicitud de ${request.firstname} ${request.lastname}? Se generará una contraseña temporal y se enviarán las instrucciones por correo. Si la cuenta ya existe, su contraseña anterior se reemplazará y se cerrarán sus sesiones. Deberá cambiar la contraseña temporal antes de acceder al panel.`
        : `¿Deseas ${labels[action]} la solicitud de ${request.firstname} ${request.lastname}?`,
    );

    if (!confirmed) {
      return;
    }

    this.processingId.set(request.id);
    this.error.set('');
    this.notice.set('');

    this.accessRequestService.updateStatus(request.id, action).subscribe({
      next: (response) => {
        if (response.data) {
          this.replaceRequest(response.data);
        }

        this.notice.set(response.message ?? 'La solicitud fue actualizada.');
        this.processingId.set(null);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudo actualizar la solicitud.'));
        this.processingId.set(null);
      },
    });
  }

  statusLabel(status: AccessRequestStatus): string {
    return {
      pending: 'Pendiente',
      approved: 'Aprobado',
      waitlisted: 'En espera',
      denied: 'Rechazado',
    }[status];
  }

  private replaceRequest(updatedRequest: AccessRequest): void {
    this.requests.update((requests) =>
      requests.map((request) => (request.id === updatedRequest.id ? updatedRequest : request)),
    );

    if (this.selectedRequest()?.id === updatedRequest.id) {
      this.selectedRequest.set(updatedRequest);
    }
  }

  private getErrorMessage(error: unknown, fallback: string): string {
    const response = (error as { error?: { message?: string; errors?: Record<string, string[]> } })
      ?.error;
    const validationError = response?.errors ? Object.values(response.errors).flat()[0] : undefined;

    return validationError ?? response?.message ?? fallback;
  }
}
