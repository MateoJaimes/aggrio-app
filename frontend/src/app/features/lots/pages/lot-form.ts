import { CommonModule } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';

import { Finca } from '../../estates/models/finca.model';
import { FincaAdminService } from '../../estates/services/finca-admin.service';
import { Lote, LoteEstado, LotePayload } from '../models/lote.model';
import { LoteAdminService } from '../services/lote-admin.service';

@Component({
  selector: 'app-lot-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './lot-form.html',
  styleUrls: ['./lots-shell.scss', './lot-form.scss'],
})
export class LotForm implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly loteAdminService = inject(LoteAdminService);
  private readonly fincaAdminService = inject(FincaAdminService);

  readonly fincas = signal<Finca[]>([]);
  readonly isEdit = signal(false);
  readonly isLoading = signal(false);
  readonly isSaving = signal(false);
  readonly error = signal('');
  readonly today = new Date().toISOString().slice(0, 10);
  private loteId: number | null = null;

  readonly form = this.fb.nonNullable.group({
    finca_id: [0, [Validators.required, Validators.min(1)]],
    nombre: ['', [Validators.required, Validators.maxLength(255)]],
    hectareas: [0, [Validators.required, Validators.min(0.01)]],
    tipo_cultivo: ['', [Validators.required, Validators.maxLength(255)]],
    variedad: ['', [Validators.maxLength(255)]],
    fecha_siembra: [''],
    latitud: ['', [Validators.min(-90), Validators.max(90)]],
    longitud: ['', [Validators.min(-180), Validators.max(180)]],
    estado: ['disponible' as LoteEstado, [Validators.required]],
  });

  ngOnInit(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    if (this.route.snapshot.paramMap.has('id') && (!Number.isInteger(id) || id < 1)) {
      this.error.set('El identificador del lote no es válido.');
      return;
    }

    if (id) {
      this.isEdit.set(true);
      this.loteId = id;
      this.loadEditData(id);
      return;
    }

    this.loadFincas();
  }

  loadFincas(): void {
    this.isLoading.set(true);

    this.fincaAdminService.getAdminFincas().subscribe({
      next: (response) => {
        this.fincas.set(response.data ?? []);
        this.isLoading.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudieron cargar las fincas.'));
        this.isLoading.set(false);
      },
    });
  }

  loadEditData(id: number): void {
    this.isLoading.set(true);

    forkJoin({
      fincas: this.fincaAdminService.getAdminFincas(),
      lote: this.loteAdminService.getLote(id),
    }).subscribe({
      next: ({ fincas, lote }) => {
        this.fincas.set(fincas.data ?? []);

        if (!lote.data) {
          this.error.set('No se encontró el lote seleccionado.');
        } else {
          this.patchLote(lote.data);
        }

        this.isLoading.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudieron cargar los datos del lote.'));
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
    const request = this.isEdit() && this.loteId
      ? this.loteAdminService.updateLote(this.loteId, payload)
      : this.loteAdminService.createLote(payload);

    request.subscribe({
      next: () => {
        this.isSaving.set(false);
        this.router.navigate(['/lots']);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudo guardar el lote.'));
        this.isSaving.set(false);
      },
    });
  }

  fincaLabel(finca: Finca): string {
    const name = finca.nombre ?? `Finca #${finca.id}`;
    const owner = finca.user?.name ?? 'Sin propietario';
    const area = finca.hectareas_totales === null || finca.hectareas_totales === undefined
      ? 'sin área definida'
      : `${finca.hectareas_totales} ha`;

    return `${name} · ${owner} · ${area}`;
  }

  private patchLote(lote: Lote): void {
    this.form.patchValue({
      finca_id: lote.finca_id,
      nombre: lote.nombre,
      hectareas: Number(lote.hectareas),
      tipo_cultivo: lote.tipo_cultivo,
      variedad: lote.variedad ?? '',
      fecha_siembra: lote.fecha_siembra ?? '',
      latitud: lote.latitud === null || lote.latitud === undefined ? '' : String(lote.latitud),
      longitud: lote.longitud === null || lote.longitud === undefined ? '' : String(lote.longitud),
      estado: lote.estado,
    });
  }

  private buildPayload(): LotePayload {
    const raw = this.form.getRawValue();

    return {
      finca_id: Number(raw.finca_id),
      nombre: raw.nombre.trim(),
      hectareas: Number(raw.hectareas),
      tipo_cultivo: raw.tipo_cultivo.trim(),
      variedad: raw.variedad.trim() || null,
      fecha_siembra: raw.fecha_siembra || null,
      latitud: raw.latitud === '' ? null : Number(raw.latitud),
      longitud: raw.longitud === '' ? null : Number(raw.longitud),
      estado: raw.estado,
    };
  }

  private getErrorMessage(error: unknown, fallback: string): string {
    const response = (error as { error?: { message?: string; errors?: Record<string, string[]> } })?.error;
    const validationError = response?.errors ? Object.values(response.errors).flat()[0] : undefined;

    return validationError ?? response?.message ?? fallback;
  }
}
