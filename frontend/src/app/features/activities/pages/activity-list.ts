import { CommonModule, DatePipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Actividad, TipoActividad, TIPOS_ACTIVIDAD } from '../models/actividad.model';
import { ActividadAdminService } from '../services/actividad-admin.service';

@Component({
  selector: 'app-activity-list',
  standalone: true,
  imports: [CommonModule, DatePipe, RouterLink],
  templateUrl: './activity-list.html',
  styleUrls: ['../../lots/pages/lots-shell.scss', '../../lots/pages/lot-list.scss', './activity-list.scss'],
})
export class ActivityList implements OnInit {
  private readonly actividadAdminService = inject(ActividadAdminService);

  readonly actividades = signal<Actividad[]>([]);
  readonly isLoading = signal(false);
  readonly deletingId = signal<number | null>(null);
  readonly error = signal('');
  readonly notice = signal('');
  readonly selectedTipo = signal<TipoActividad | null>(null);
  readonly search = signal('');
  readonly tiposActividad = TIPOS_ACTIVIDAD;

  readonly visibleActividades = computed(() => {
    const term = this.search().trim().toLocaleLowerCase();

    if (!term) {
      return this.actividades();
    }

    return this.actividades().filter((actividad) => [
      this.tipoLabel(actividad.tipo_actividad),
      actividad.lote?.nombre,
      actividad.lote?.finca?.nombre,
      actividad.lote?.finca?.user?.name,
      actividad.observaciones,
    ].some((value) => value?.toLocaleLowerCase().includes(term)));
  });

  ngOnInit(): void {
    this.loadActividades();
  }

  loadActividades(): void {
    this.isLoading.set(true);
    this.error.set('');

    this.actividadAdminService.getActividades(this.selectedTipo() ?? undefined).subscribe({
      next: (response) => {
        this.actividades.set(response.data ?? []);
        this.isLoading.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudieron cargar las actividades.'));
        this.isLoading.set(false);
      },
    });
  }

  filterByTipo(event: Event): void {
    const value = (event.target as HTMLSelectElement).value as TipoActividad | '';
    this.selectedTipo.set(value || null);
    this.loadActividades();
  }

  setSearch(event: Event): void {
    this.search.set((event.target as HTMLInputElement).value);
  }

  deleteActividad(actividad: Actividad): void {
    const confirmed = window.confirm(
      `¿Deseas eliminar la actividad “${this.tipoLabel(actividad.tipo_actividad)}” del ${actividad.fecha}? Esta acción no se puede deshacer.`,
    );

    if (!confirmed) {
      return;
    }

    this.deletingId.set(actividad.id);
    this.error.set('');
    this.notice.set('');

    this.actividadAdminService.deleteActividad(actividad.id).subscribe({
      next: (response) => {
        this.notice.set(response.message ?? 'Actividad eliminada exitosamente.');
        this.deletingId.set(null);
        this.loadActividades();
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudo eliminar la actividad.'));
        this.deletingId.set(null);
      },
    });
  }

  tipoLabel(tipo: TipoActividad): string {
    return this.tiposActividad.find((item) => item.value === tipo)?.label ?? tipo;
  }

  countByTipo(tipo: TipoActividad): number {
    return this.actividades().filter((actividad) => actividad.tipo_actividad === tipo).length;
  }

  formatCosto(costo: number | string): string {
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      maximumFractionDigits: 0,
    }).format(Number(costo));
  }

  private getErrorMessage(error: unknown, fallback: string): string {
    const response = (error as { error?: { message?: string; errors?: Record<string, string[]> } })?.error;
    const validationError = response?.errors ? Object.values(response.errors).flat()[0] : undefined;

    return validationError ?? response?.message ?? fallback;
  }
}
