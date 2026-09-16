import { CommonModule, DatePipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Lote } from '../../lots/models/lote.model';
import { LoteAdminService } from '../../lots/services/lote-admin.service';
import { LecturaIot, TipoMedicion, TIPOS_MEDICION } from '../models/lectura-iot.model';
import { LecturaIotAdminService } from '../services/lectura-iot-admin.service';

@Component({
  selector: 'app-iot-reading-list',
  standalone: true,
  imports: [CommonModule, DatePipe, RouterLink],
  templateUrl: './iot-reading-list.html',
  styleUrls: ['../../lots/pages/lots-shell.scss', '../../lots/pages/lot-list.scss', './iot-reading-list.scss'],
})
export class IotReadingList implements OnInit {
  private readonly lecturaIotAdminService = inject(LecturaIotAdminService);
  private readonly loteAdminService = inject(LoteAdminService);

  readonly lecturas = signal<LecturaIot[]>([]);
  readonly lotes = signal<Lote[]>([]);
  readonly isLoading = signal(false);
  readonly isLoadingLotes = signal(false);
  readonly deletingId = signal<number | null>(null);
  readonly error = signal('');
  readonly notice = signal('');
  readonly selectedLoteId = signal<number | null>(null);
  readonly selectedTipo = signal<TipoMedicion | null>(null);
  readonly search = signal('');
  readonly tiposMedicion = TIPOS_MEDICION;

  readonly visibleLecturas = computed(() => {
    const term = this.search().trim().toLocaleLowerCase();

    if (!term) {
      return this.lecturas();
    }

    return this.lecturas().filter((lectura) => [
      this.tipoLabel(lectura.tipo_medicion),
      lectura.mac_dispositivo,
      lectura.lote?.nombre,
      lectura.lote?.finca?.nombre,
      lectura.lote?.finca?.user?.name,
      lectura.unidad,
    ].some((value) => value?.toLocaleLowerCase().includes(term)));
  });

  ngOnInit(): void {
    this.loadLotes();
    this.loadLecturas();
  }

  loadLotes(): void {
    this.isLoadingLotes.set(true);

    this.loteAdminService.getLotes().subscribe({
      next: (response) => {
        this.lotes.set(response.data ?? []);
        this.isLoadingLotes.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudieron cargar los lotes para el filtro.'));
        this.isLoadingLotes.set(false);
      },
    });
  }

  loadLecturas(): void {
    this.isLoading.set(true);
    this.error.set('');

    this.lecturaIotAdminService.getLecturas({
      loteId: this.selectedLoteId() ?? undefined,
      tipoMedicion: this.selectedTipo() ?? undefined,
    }).subscribe({
      next: (response) => {
        this.lecturas.set(response.data ?? []);
        this.isLoading.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudieron cargar las lecturas IoT.'));
        this.isLoading.set(false);
      },
    });
  }

  filterByLote(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    this.selectedLoteId.set(value ? Number(value) : null);
    this.loadLecturas();
  }

  filterByTipo(event: Event): void {
    const value = (event.target as HTMLSelectElement).value as TipoMedicion | '';
    this.selectedTipo.set(value || null);
    this.loadLecturas();
  }

  setSearch(event: Event): void {
    this.search.set((event.target as HTMLInputElement).value);
  }

  deleteLectura(lectura: LecturaIot): void {
    const confirmed = window.confirm(
      `¿Deseas eliminar la lectura de ${this.tipoLabel(lectura.tipo_medicion)} del ${lectura.fecha_medicion}? Esta acción no se puede deshacer.`,
    );

    if (!confirmed) {
      return;
    }

    this.deletingId.set(lectura.id);
    this.error.set('');
    this.notice.set('');

    this.lecturaIotAdminService.deleteLectura(lectura.id).subscribe({
      next: (response) => {
        this.notice.set(response.message ?? 'Lectura IoT eliminada exitosamente.');
        this.deletingId.set(null);
        this.loadLecturas();
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudo eliminar la lectura IoT.'));
        this.deletingId.set(null);
      },
    });
  }

  tipoLabel(tipo: TipoMedicion): string {
    return this.tiposMedicion.find((item) => item.value === tipo)?.label ?? tipo;
  }

  countByTipo(tipo: TipoMedicion): number {
    return this.lecturas().filter((lectura) => lectura.tipo_medicion === tipo).length;
  }

  loteLabel(lote: Lote): string {
    const finca = lote.finca?.nombre ?? `Finca #${lote.finca_id}`;

    return `${lote.nombre} · ${finca}`;
  }

  private getErrorMessage(error: unknown, fallback: string): string {
    const response = (error as { error?: { message?: string; errors?: Record<string, string[]> } })?.error;
    const validationError = response?.errors ? Object.values(response.errors).flat()[0] : undefined;

    return validationError ?? response?.message ?? fallback;
  }
}
