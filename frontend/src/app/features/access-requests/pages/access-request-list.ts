import { CommonModule, DatePipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { AccessRequest, AccessRequestAction, AccessRequestStatus } from '../models/access-request.model';
import { AccessRequestAdminService } from '../services/access-request-admin.service';

@Component({
  selector: 'app-access-request-list',
  standalone: true,
  imports: [CommonModule, DatePipe, RouterLink],
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

  readonly visibleRequests = computed(() => {
    const term = this.search().trim().toLocaleLowerCase();
    const filter = this.filter();

    return this.requests().filter((request) => {
      const matchesStatus = filter === 'all' || request.status === filter;
      const matchesSearch = !term || [
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
    return status ? this.requests().filter((request) => request.status === status).length : this.requests().length;
  }

  loadRequests(): void {
    this.isLoading.set(true);
    this.error.set('');

    this.accessRequestService.getAccessRequests().subscribe({
      next: (response) => {
        this.requests.set(response.data ?? []);
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
      `¿Deseas ${labels[action]} la solicitud de ${request.firstname} ${request.lastname}?`,
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
      requests.map((request) => request.id === updatedRequest.id ? updatedRequest : request),
    );

    if (this.selectedRequest()?.id === updatedRequest.id) {
      this.selectedRequest.set(updatedRequest);
    }
  }

  private getErrorMessage(error: unknown, fallback: string): string {
    const response = (error as { error?: { message?: string; errors?: Record<string, string[]> } })?.error;
    const validationError = response?.errors ? Object.values(response.errors).flat()[0] : undefined;

    return validationError ?? response?.message ?? fallback;
  }
}
