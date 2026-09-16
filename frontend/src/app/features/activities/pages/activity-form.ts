import { CommonModule } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';

import { Lote } from '../../lots/models/lote.model';
import { LoteAdminService } from '../../lots/services/lote-admin.service';
import { Actividad, ActividadPayload, TipoActividad, TIPOS_ACTIVIDAD } from '../models/actividad.model';
import { ActividadAdminService } from '../services/actividad-admin.service';

@Component({
  selector: 'app-activity-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './activity-form.html',
  styleUrls: ['../../lots/pages/lots-shell.scss', '../../lots/pages/lot-form.scss', './activity-form.scss'],
})
export class ActivityForm implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly actividadAdminService = inject(ActividadAdminService);
  private readonly loteAdminService = inject(LoteAdminService);

  readonly lotes = signal<Lote[]>([]);
  readonly isEdit = signal(false);
  readonly isLoading = signal(false);
  readonly isSaving = signal(false);
  readonly error = signal('');
  readonly tiposActividad = TIPOS_ACTIVIDAD;
  readonly today = new Date().toISOString().slice(0, 10);
  private actividadId: number | null = null;

  readonly form = this.fb.nonNullable.group({
    lote_id: [0, [Validators.required, Validators.min(1)]],
    tipo_actividad: ['preparacion' as TipoActividad, [Validators.required]],
    fecha: [this.today, [Validators.required]],
    costo: [0, [Validators.required, Validators.min(0)]],
    observaciones: ['', [Validators.maxLength(65535)]],
  });

  ngOnInit(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    if (this.route.snapshot.paramMap.has('id') && (!Number.isInteger(id) || id < 1)) {
      this.error.set('El identificador de la actividad no es válido.');
      return;
    }

    if (id) {
      this.isEdit.set(true);
      this.actividadId = id;
      this.loadEditData(id);
      return;
    }

    this.loadLotes();
  }

  loadLotes(): void {
    this.isLoading.set(true);

    this.loteAdminService.getLotes().subscribe({
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

  loadEditData(id: number): void {
    this.isLoading.set(true);

    forkJoin({
      lotes: this.loteAdminService.getLotes(),
      actividad: this.actividadAdminService.getActividad(id),
    }).subscribe({
      next: ({ lotes, actividad }) => {
        this.lotes.set(lotes.data ?? []);

        if (!actividad.data) {
          this.error.set('No se encontró la actividad seleccionada.');
        } else {
          this.patchActividad(actividad.data);
        }

        this.isLoading.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudieron cargar los datos de la actividad.'));
        this.isLoading.set(false);
      },
    });
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    this.error.set('');

    const payload = this.buildPayload();
    const request = this.isEdit() && this.actividadId
      ? this.actividadAdminService.updateActividad(this.actividadId, payload)
      : this.actividadAdminService.createActividad(payload);

    request.subscribe({
      next: () => {
        this.isSaving.set(false);
        this.router.navigate(['/activities']);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudo guardar la actividad.'));
        this.isSaving.set(false);
      },
    });
  }

  loteLabel(lote: Lote): string {
    const finca = lote.finca?.nombre ?? `Finca #${lote.finca_id}`;
    const owner = lote.finca?.user?.name ?? 'Sin propietario';

    return `${lote.nombre} · ${finca} · ${owner}`;
  }

  private patchActividad(actividad: Actividad): void {
    this.form.patchValue({
      lote_id: actividad.lote_id,
      tipo_actividad: actividad.tipo_actividad,
      fecha: actividad.fecha,
      costo: Number(actividad.costo),
      observaciones: actividad.observaciones ?? '',
    });
  }

  private buildPayload(): ActividadPayload {
    const raw = this.form.getRawValue();

    return {
      lote_id: Number(raw.lote_id),
      tipo_actividad: raw.tipo_actividad,
      fecha: raw.fecha,
      costo: Number(raw.costo),
      observaciones: raw.observaciones.trim() || null,
    };
  }

  private getErrorMessage(error: unknown, fallback: string): string {
    const response = (error as { error?: { message?: string; errors?: Record<string, string[]> } })?.error;
    const validationError = response?.errors ? Object.values(response.errors).flat()[0] : undefined;

    return validationError ?? response?.message ?? fallback;
  }
}
