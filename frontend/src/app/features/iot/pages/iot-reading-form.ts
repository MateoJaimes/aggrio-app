import { CommonModule } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';

import { Lote } from '../../lots/models/lote.model';
import { LoteAdminService } from '../../lots/services/lote-admin.service';
import {
  LecturaIot,
  LecturaIotPayload,
  TipoMedicion,
  TIPOS_MEDICION,
  UNIDADES_POR_MEDICION,
} from '../models/lectura-iot.model';
import { LecturaIotAdminService } from '../services/lectura-iot-admin.service';

@Component({
  selector: 'app-iot-reading-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './iot-reading-form.html',
  styleUrls: ['../../lots/pages/lots-shell.scss', '../../lots/pages/lot-form.scss', './iot-reading-form.scss'],
})
export class IotReadingForm implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly lecturaIotAdminService = inject(LecturaIotAdminService);
  private readonly loteAdminService = inject(LoteAdminService);

  readonly lotes = signal<Lote[]>([]);
  readonly isEdit = signal(false);
  readonly isLoading = signal(false);
  readonly isSaving = signal(false);
  readonly error = signal('');
  readonly tiposMedicion = TIPOS_MEDICION;
  readonly tipoMedicionSeleccionado = signal<TipoMedicion>('temperatura');
  private lecturaId: number | null = null;

  readonly form = this.fb.nonNullable.group({
    lote_id: [0, [Validators.required, Validators.min(1)]],
    mac_dispositivo: ['', [Validators.maxLength(50)]],
    tipo_medicion: ['temperatura' as TipoMedicion, [Validators.required]],
    valor: [0, [Validators.required]],
    unidad: ['°C', [Validators.required, Validators.maxLength(10)]],
    fecha_medicion: [this.toDateTimeLocal(new Date()), [Validators.required]],
  });

  ngOnInit(): void {
    const id = Number(this.route.snapshot.paramMap.get('id'));

    if (this.route.snapshot.paramMap.has('id') && (!Number.isInteger(id) || id < 1)) {
      this.error.set('El identificador de la lectura no es válido.');
      return;
    }

    if (id) {
      this.isEdit.set(true);
      this.lecturaId = id;
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
      lectura: this.lecturaIotAdminService.getLectura(id),
    }).subscribe({
      next: ({ lotes, lectura }) => {
        this.lotes.set(lotes.data ?? []);

        if (!lectura.data) {
          this.error.set('No se encontró la lectura seleccionada.');
        } else {
          this.patchLectura(lectura.data);
        }

        this.isLoading.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudieron cargar los datos de la lectura.'));
        this.isLoading.set(false);
      },
    });
  }

  onTipoMedicionChange(event: Event): void {
    const tipo = (event.target as HTMLSelectElement).value as TipoMedicion;
    this.tipoMedicionSeleccionado.set(tipo);

    if (!this.unidadesActuales().includes(this.form.controls.unidad.value)) {
      this.form.controls.unidad.setValue(this.unidadesActuales()[0]);
    }
  }

  unidadesActuales(): readonly string[] {
    return UNIDADES_POR_MEDICION[this.tipoMedicionSeleccionado()];
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    this.error.set('');

    const payload = this.buildPayload();
    const request = this.isEdit() && this.lecturaId
      ? this.lecturaIotAdminService.updateLectura(this.lecturaId, payload)
      : this.lecturaIotAdminService.createLectura(payload);

    request.subscribe({
      next: () => {
        this.isSaving.set(false);
        this.router.navigate(['/iot']);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudo guardar la lectura IoT.'));
        this.isSaving.set(false);
      },
    });
  }

  loteLabel(lote: Lote): string {
    const finca = lote.finca?.nombre ?? `Finca #${lote.finca_id}`;
    const owner = lote.finca?.user?.name ?? 'Sin propietario';

    return `${lote.nombre} · ${finca} · ${owner}`;
  }

  private patchLectura(lectura: LecturaIot): void {
    this.tipoMedicionSeleccionado.set(lectura.tipo_medicion);
    this.form.patchValue({
      lote_id: lectura.lote_id,
      mac_dispositivo: lectura.mac_dispositivo ?? '',
      tipo_medicion: lectura.tipo_medicion,
      valor: Number(lectura.valor),
      unidad: lectura.unidad,
      fecha_medicion: this.toDateTimeLocal(lectura.fecha_medicion),
    });
  }

  private buildPayload(): LecturaIotPayload {
    const raw = this.form.getRawValue();

    return {
      lote_id: Number(raw.lote_id),
      mac_dispositivo: raw.mac_dispositivo.trim() || null,
      tipo_medicion: raw.tipo_medicion,
      valor: Number(raw.valor),
      unidad: raw.unidad,
      fecha_medicion: raw.fecha_medicion,
    };
  }

  private toDateTimeLocal(value: string | Date): string {
    if (value instanceof Date) {
      const localTime = new Date(value.getTime() - value.getTimezoneOffset() * 60_000);

      return localTime.toISOString().slice(0, 16);
    }

    return value.replace(' ', 'T').slice(0, 16);
  }

  private getErrorMessage(error: unknown, fallback: string): string {
    const response = (error as { error?: { message?: string; errors?: Record<string, string[]> } })?.error;
    const validationError = response?.errors ? Object.values(response.errors).flat()[0] : undefined;

    return validationError ?? response?.message ?? fallback;
  }
}
