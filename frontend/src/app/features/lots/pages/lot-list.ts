import { CommonModule, DatePipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Finca } from '../../estates/models/finca.model';
import { FincaAdminService } from '../../estates/services/finca-admin.service';
import { Lote, LoteEstado } from '../models/lote.model';
import { LoteAdminService } from '../services/lote-admin.service';

@Component({
  selector: 'app-lot-list',
  standalone: true,
  imports: [CommonModule, DatePipe, RouterLink],
  templateUrl: './lot-list.html',
  styleUrls: ['./lots-shell.scss', './lot-list.scss'],
})
export class LotList implements OnInit {
  private readonly loteAdminService = inject(LoteAdminService);
  private readonly fincaAdminService = inject(FincaAdminService);

  readonly lotes = signal<Lote[]>([]);
  readonly fincas = signal<Finca[]>([]);
  readonly isLoading = signal(false);
  readonly isLoadingFincas = signal(false);
  readonly deletingId = signal<number | null>(null);
  readonly error = signal('');
  readonly notice = signal('');
  readonly selectedFincaId = signal<number | null>(null);
  readonly search = signal('');

  readonly visibleLotes = computed(() => {
    const term = this.search().trim().toLocaleLowerCase();

    if (!term) {
      return this.lotes();
    }

    return this.lotes().filter((lote) => [
      lote.nombre,
      lote.tipo_cultivo,
      lote.variedad,
      lote.finca?.nombre,
      lote.finca?.user?.name,
    ].some((value) => value?.toLocaleLowerCase().includes(term)));
  });

  ngOnInit(): void {
    this.loadFincas();
    this.loadLotes();
  }

  loadFincas(): void {
    this.isLoadingFincas.set(true);

    this.fincaAdminService.getAdminFincas().subscribe({
      next: (response) => {
        this.fincas.set(response.data ?? []);
        this.isLoadingFincas.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudieron cargar las fincas para el filtro.'));
        this.isLoadingFincas.set(false);
      },
    });
  }

  loadLotes(): void {
    this.isLoading.set(true);
    this.error.set('');

    this.loteAdminService.getLotes(this.selectedFincaId() ?? undefined).subscribe({
      next: (response) => {
        this.lotes.set(response.data ?? []);
        this.isLoading.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudieron cargar los lotes.'));
        this.isLoading.set(false);
      },
    });
  }

  filterByFinca(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    this.selectedFincaId.set(value ? Number(value) : null);
    this.loadLotes();
  }

  setSearch(event: Event): void {
    this.search.set((event.target as HTMLInputElement).value);
  }

  deleteLote(lote: Lote): void {
    const confirmed = window.confirm(`¿Deseas eliminar el lote “${lote.nombre}”? Esta acción no se puede deshacer.`);

    if (!confirmed) {
      return;
    }

    this.deletingId.set(lote.id);
    this.error.set('');
    this.notice.set('');

    this.loteAdminService.deleteLote(lote.id).subscribe({
      next: (response) => {
        this.notice.set(response.message ?? 'Lote eliminado exitosamente.');
        this.deletingId.set(null);
        this.loadLotes();
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudo eliminar el lote.'));
        this.deletingId.set(null);
      },
    });
  }

  statusLabel(status: LoteEstado): string {
    return {
      disponible: 'Disponible',
      en_uso: 'En uso',
      no_disponible: 'No disponible',
    }[status];
  }

  countByEstado(status: LoteEstado): number {
    return this.lotes().filter((lote) => lote.estado === status).length;
  }

  fincaLabel(finca: Finca): string {
    return finca.nombre ?? `Finca #${finca.id}`;
  }

  private getErrorMessage(error: unknown, fallback: string): string {
    const response = (error as { error?: { message?: string; errors?: Record<string, string[]> } })?.error;
    const validationError = response?.errors ? Object.values(response.errors).flat()[0] : undefined;

    return validationError ?? response?.message ?? fallback;
  }
}
