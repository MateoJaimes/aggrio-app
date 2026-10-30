import { AdminSidebar } from '../../admin-sidebar';
import { CommonModule } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import { FincaAdminService } from '../services/finca-admin.service';
import { Finca, UserSummary } from '../models/finca.model';

@Component({
  selector: 'app-estate-form',
  standalone: true,
  imports: [AdminSidebar, CommonModule, ReactiveFormsModule, RouterLink],
  template: `
    <section class="page-shell">
      <app-admin-sidebar />

      <main class="content">
        <div class="topbar">
          <div>
            <p class="eyebrow">Administración</p>
            <h1>{{ isEdit() ? 'Editar finca' : 'Nueva finca' }}</h1>
          </div>

          <a class="button secondary" [routerLink]="['/estates']">Volver</a>
        </div>

        @if (error()) {
          <div class="alert error">{{ error() }}</div>
        }

        <div class="card-layout">
          <form [formGroup]="form" (ngSubmit)="submit()" class="card form-card">
            <div class="field">
              <label for="user_id">Propietario</label>

              @if (owners().length) {
                <select id="user_id" formControlName="user_id">
                  <option value="">Selecciona un productor</option>
                  @for (owner of owners(); track owner.id) {
                    <option [value]="owner.id">{{ owner.name }} (ID {{ owner.id }})</option>
                  }
                </select>
              } @else {
                <p>No hay productores disponibles. Aprueba primero una solicitud.</p>
              }
            </div>

            <div class="field">
              <label for="nombre">Nombre</label>
              <input id="nombre" type="text" formControlName="nombre" placeholder="Nombre de la finca" />
            </div>

            <div class="grid two-columns">
              <div class="field">
                <label for="latitud">Latitud</label>
                <input id="latitud" type="number" step="0.000001" formControlName="latitud" />
              </div>

              <div class="field">
                <label for="longitud">Longitud</label>
                <input id="longitud" type="number" step="0.000001" formControlName="longitud" />
              </div>
            </div>

            <div class="grid two-columns">
              <div class="field">
                <label for="hectareas_totales">Hectáreas totales</label>
                <input id="hectareas_totales" type="number" step="0.01" min="0" formControlName="hectareas_totales" />
              </div>

              <div class="field">
                <label for="tipo_suelo">Tipo de suelo</label>
                <input id="tipo_suelo" type="text" formControlName="tipo_suelo" list="tipos-suelo" placeholder="Selecciona o escribe el suelo" />
                <datalist id="tipos-suelo"><option value="franco"></option><option value="arcilloso"></option><option value="arenoso"></option><option value="limoso"></option><option value="franco_arcilloso"></option><option value="franco_arenoso"></option></datalist>
              </div>
            </div>

            <div class="actions">
              <a routerLink="/produccion">Completar tenencia, agua, infraestructura y cultivos</a>
              <button type="submit" [disabled]="isSaving() || form.invalid">
                {{ isSaving() ? 'Guardando...' : (isEdit() ? 'Guardar cambios' : 'Crear finca') }}
              </button>
            </div>
          </form>

          <aside class="card info-panel">
            <h3>Información</h3>
            <div class="info-block">
              <span>Estado inicial</span>
              <strong>Pendiente</strong>
            </div>
            <div class="info-block">
              <span>Acción</span>
              <strong>{{ isEdit() ? 'Editar registro' : 'Crear nueva finca' }}</strong>
            </div>
            <div class="info-block">
              <span>Propietario</span>
              <strong>{{ owners().length ? 'Listado disponible' : 'Sin productores disponibles' }}</strong>
            </div>
          </aside>
        </div>
      </main>
    </section>
  `,
  styles: [
    `
      :host {
        display: block;
        min-height: 100vh;
        background: linear-gradient(135deg, #fff5f5 0%, var(--aggrio-surface-muted) 35%, #fff8f8 100%);
        color: var(--aggrio-ink);
      }

      .page-shell {
        display: flex;
        min-height: 100vh;
      }

      .content {
        flex: 1;
        padding: 2rem;
      }

      .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
      }

      .eyebrow {
        margin: 0;
        color: #c51217;
        text-transform: uppercase;
        font-size: 0.72rem;
        letter-spacing: 0.12rem;
        font-weight: 700;
      }

      h1 {
        margin: 0.15rem 0 0;
        font-size: clamp(1.8rem, 2.6vw, 2.5rem);
        letter-spacing: -0.05em;
      }

      .card-layout {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(250px, 0.9fr);
        gap: 1.25rem;
        align-items: start;
      }

      .card {
        background: var(--aggrio-surface);
        border: 1px solid var(--aggrio-border);
        border-radius: 1rem;
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.07);
      }

      .form-card {
        padding: 1.5rem;
      }

      .info-panel {
        padding: 1.4rem;
        background: linear-gradient(180deg, #f8fbff 0%, var(--aggrio-primary-soft) 100%);
      }

      .info-panel h3 {
        margin: 0 0 1rem;
        font-size: 1.05rem;
      }

      .info-block {
        border: 1px solid #dbeafe;
        border-radius: 0.85rem;
        padding: 0.85rem 1rem;
        background: rgba(255, 255, 255, 0.65);
        margin-bottom: 0.85rem;
      }

      .info-block span {
        display: block;
        font-size: 0.76rem;
        text-transform: uppercase;
        letter-spacing: 0.08rem;
        color: var(--aggrio-muted);
        margin-bottom: 0.35rem;
      }

      .info-block strong {
        font-size: 0.95rem;
      }

      .field {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        margin-bottom: 1rem;
      }

      label {
        font-weight: 700;
        color: #374151;
      }

      input,
      select {
        width: 100%;
        padding: 0.85rem 0.9rem;
        border: 1px solid var(--aggrio-border);
        border-radius: 0.75rem;
        font: inherit;
        box-sizing: border-box;
        background: var(--aggrio-surface-muted);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
      }

      input:focus,
      select:focus {
        outline: none;
        border-color: #f16a6d;
        box-shadow: 0 0 0 4px rgba(241, 106, 109, 0.18);
      }

      .grid {
        display: grid;
        gap: 1rem;
      }

      .grid.two-columns {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 1rem;
      }

      .button.secondary,
      button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.85rem;
        font: inherit;
      }

      .button.secondary {
        padding: 0.8rem 1.1rem;
        border: 1px solid #c7d2fe;
        background: var(--aggrio-surface);
        color: var(--aggrio-ink);
        text-decoration: none;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
      }

      button {
        padding: 0.85rem 1.4rem;
        background: linear-gradient(135deg, var(--aggrio-primary) 0%, var(--aggrio-primary-dark) 100%);
        color: white;
        border: none;
        cursor: pointer;
        box-shadow: 0 10px 20px rgba(236, 28, 33, 0.22);
      }

      button:disabled {
        opacity: 0.65;
        cursor: not-allowed;
      }

      .alert {
        padding: 1rem 1.1rem;
        border-radius: 0.85rem;
        margin-bottom: 1rem;
        border: 1px solid transparent;
      }

      .alert.error {
        background: var(--aggrio-danger-background);
        color: var(--aggrio-danger);
        border-color: var(--aggrio-danger-border);
      }

      @media (max-width: 980px) {
        .page-shell {
          flex-direction: column;
        }

        .sidebar {
          width: 100%;
        }

        .card-layout {
          grid-template-columns: 1fr;
        }
      }

      @media (max-width: 720px) {
        .content {
          padding: 1rem;
        }

        .topbar {
          flex-direction: column;
          align-items: flex-start;
        }

        .grid.two-columns {
          grid-template-columns: 1fr;
        }
      }
    `,
  ],
})
export class EstateForm implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly fincaAdminService = inject(FincaAdminService);

  readonly isEdit = signal(false);
  readonly isSaving = signal(false);
  readonly error = signal('');
  readonly owners = signal<UserSummary[]>([]);

  readonly form = this.fb.nonNullable.group({
    user_id: [0, [Validators.required, Validators.min(1)]],
    nombre: ['', [Validators.required, Validators.maxLength(255)]],
    latitud: [null as number | null, [Validators.required, Validators.min(-90), Validators.max(90)]],
    longitud: [null as number | null, [Validators.required, Validators.min(-180), Validators.max(180)]],
    hectareas_totales: [null as number | null, [Validators.required, Validators.min(0)]],
    tipo_suelo: ['', [Validators.maxLength(255)]],
  });

  ngOnInit(): void {
    this.loadOwners();

    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.isEdit.set(true);
      this.loadFinca(Number(id));
    }
  }

  loadOwners(): void {
    this.fincaAdminService.getProductores().subscribe({
      next: response => this.owners.set(response.data ?? []),
      error: error => { this.owners.set([]); this.error.set(this.getErrorMessage(error)); },
    });
  }

  loadFinca(id: number): void {
    this.fincaAdminService.getAdminFincas().subscribe({
      next: (response) => {
        const finca = ((response.data ?? []) as Finca[]).find((item: Finca) => item.id === id);

        if (!finca) {
          this.error.set('No se encontró la finca seleccionada.');
          return;
        }

        this.form.patchValue({
          user_id: finca.user_id,
          nombre: finca.nombre ?? '',
          latitud: finca.latitud ?? null,
          longitud: finca.longitud ?? null,
          hectareas_totales: finca.hectareas_totales ?? null,
          tipo_suelo: finca.tipo_suelo ?? '',
        });
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error));
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

    const request = this.isEdit()
      ? this.fincaAdminService.updateFinca(Number(this.route.snapshot.paramMap.get('id')), payload)
      : this.fincaAdminService.createFinca(payload);

    request.subscribe({
      next: () => {
        this.isSaving.set(false);
        this.router.navigate(['/estates']);
      },
      error: (error) => {
        this.isSaving.set(false);
        this.error.set(this.getErrorMessage(error));
      },
    });
  }

  private getErrorMessage(error: unknown): string {
    const message = (error as { error?: { message?: string } })?.error?.message;

    if (message) {
      return message;
    }

    return 'No se pudo guardar la finca. Revisa los datos e intenta nuevamente.';
  }

  private buildPayload(): Partial<Finca> {
    const rawValue = this.form.getRawValue();

    return {
      user_id: Number(rawValue.user_id),
      nombre: rawValue.nombre?.trim() || undefined,
      latitud: rawValue.latitud === null || rawValue.latitud === undefined ? undefined : Number(rawValue.latitud),
      longitud: rawValue.longitud === null || rawValue.longitud === undefined ? undefined : Number(rawValue.longitud),
      hectareas_totales:
        rawValue.hectareas_totales === null || rawValue.hectareas_totales === undefined
          ? undefined
          : Number(rawValue.hectareas_totales),
      tipo_suelo: rawValue.tipo_suelo?.trim() || undefined,
    };
  }
}
