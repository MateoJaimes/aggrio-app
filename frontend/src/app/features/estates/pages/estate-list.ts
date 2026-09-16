import { CommonModule } from '@angular/common';
import { Component, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Finca } from '../models/finca.model';
import { FincaAdminService } from '../services/finca-admin.service';
import { SessionMenu } from '../../../core/auth/session-menu';

@Component({
  selector: 'app-estate-list',
  standalone: true,
  imports: [CommonModule, RouterLink, SessionMenu],
  template: `
    <section class="page-shell">
      <aside class="sidebar">
        <div class="brand">
          <span class="brand-mark">A</span>
          <div>
            <small>Panel</small>
            <strong>Aggrio</strong>
          </div>
        </div>

        <nav class="nav">
          <a class="nav-item active" [routerLink]="['/estates']">
            <span>🏡</span>
            Fincas
          </a>
          <a class="nav-item" [routerLink]="['/lots']">
            <span>🌱</span>
            Lotes
          </a>
          <a class="nav-item" [routerLink]="['/activities']">
            <span>📋</span>
            Actividades
          </a>
          <a class="nav-item" [routerLink]="['/iot']">
            <span>📡</span>
            Lecturas IoT
          </a>
          <a class="nav-item" [routerLink]="['/multimedia']">
            <span>🖼️</span>
            Archivos Multimedia
          </a>
          <a class="nav-item" [routerLink]="['/access-requests']">
            <span>📥</span>
            Solicitudes de acceso
          </a>
          <a class="nav-item" [routerLink]="['/estates/new']">
            <span>➕</span>
            Nueva finca
          </a>
        </nav>

        <div class="sidebar-card">
          <p>Resumen</p>
          <div class="mini-stat">
            <span>Total</span>
            <strong>{{ totalFincas() }}</strong>
          </div>
          <div class="mini-stat">
            <span>Pendientes</span>
            <strong>{{ pendientes() }}</strong>
          </div>
          <div class="mini-stat">
            <span>Aprobadas</span>
            <strong>{{ aprobadas() }}</strong>
          </div>
          <div class="mini-stat">
            <span>Rechazadas</span>
            <strong>{{ rechazadas() }}</strong>
          </div>
        </div>

        <app-session-menu />
      </aside>

      <main class="content">
        <div class="topbar">
          <div>
            <p class="eyebrow">Administración</p>
            <h1>Fincas</h1>
          </div>

          <a class="button primary" [routerLink]="['/estates/new']">Nueva finca</a>
        </div>

        <div class="stats-grid">
          <div class="stat-card">
            <span class="label">Total</span>
            <strong>{{ totalFincas() }}</strong>
          </div>
          <div class="stat-card pending">
            <span class="label">Pendientes</span>
            <strong>{{ pendientes() }}</strong>
          </div>
          <div class="stat-card approved">
            <span class="label">Aprobadas</span>
            <strong>{{ aprobadas() }}</strong>
          </div>
          <div class="stat-card rejected">
            <span class="label">Rechazadas</span>
            <strong>{{ rechazadas() }}</strong>
          </div>
        </div>

        @if (error()) {
          <div class="alert error">{{ error() }}</div>
        }

        <div class="table-panel">
          <div class="table-header">
            <h2>Listado general</h2>
          </div>

          @if (isLoading()) {
            <div class="loading">Cargando fincas...</div>
          } @else {
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Propietario</th>
                    <th>Nombre</th>
                    <th>Hectáreas</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  @for (finca of fincas(); track finca.id) {
                    <tr>
                      <td>{{ finca.user?.name ?? 'Sin propietario' }}</td>
                      <td>{{ finca.nombre ?? 'Sin nombre' }}</td>
                      <td>{{ finca.hectareas_totales ?? '—' }}</td>
                      <td>
                        <span
                          class="badge"
                          [class.pending]="finca.estado === 'pendiente'"
                          [class.approved]="finca.estado === 'aprobado'"
                          [class.rejected]="finca.estado === 'rechazado'"
                        >
                          {{ finca.estado }}
                        </span>
                      </td>
                      <td class="actions">
                        <a class="edit-link" [routerLink]="['/estates', finca.id, 'edit']">Editar</a>

                        @if (finca.estado === 'pendiente') {
                          <button type="button" (click)="handleEstado(finca, 'aprobar')">Aprobar</button>
                          <button type="button" class="danger" (click)="handleEstado(finca, 'rechazar')">Rechazar</button>
                        }
                      </td>
                    </tr>
                  } @empty {
                    <tr>
                      <td colspan="5" class="empty">No hay fincas registradas.</td>
                    </tr>
                  }
                </tbody>
              </table>
            </div>
          }
        </div>
      </main>
    </section>
  `,
  styles: [
    `
      :host {
        display: block;
        min-height: 100vh;
        background: linear-gradient(135deg, #eef4ff 0%, #f8fafc 35%, #edf6ff 100%);
        color: #0f172a;
      }

      .page-shell {
        display: flex;
        min-height: 100vh;
      }

      .sidebar {
        width: 280px;
        background: linear-gradient(180deg, #0f172a 0%, #111827 100%);
        color: white;
        padding: 1.5rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
      }

      .brand {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding: 0.5rem 0.75rem;
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.06);
      }

      .brand-mark {
        width: 2.2rem;
        height: 2.2rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        background: linear-gradient(135deg, #60a5fa 0%, #2563eb 100%);
        font-weight: 800;
      }

      .brand small {
        display: block;
        font-size: 0.68rem;
        opacity: 0.7;
      }

      .brand strong {
        font-size: 1rem;
      }

      .nav {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
      }

      .nav-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.85rem 0.9rem;
        border-radius: 0.85rem;
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        font-weight: 600;
        transition: background 0.2s ease, transform 0.2s ease;
      }

      .nav-item:hover {
        background: rgba(255, 255, 255, 0.08);
        transform: translateX(2px);
      }

      .nav-item.active {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        box-shadow: 0 12px 24px rgba(37, 99, 235, 0.25);
      }

      .sidebar-card {
        margin-top: auto;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 1rem;
        padding: 1rem;
      }

      .sidebar-card p {
        margin: 0 0 0.75rem;
        opacity: 0.75;
        text-transform: uppercase;
        letter-spacing: 0.08rem;
        font-size: 0.72rem;
      }

      .mini-stat {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.55rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      }

      .mini-stat:last-child {
        border-bottom: none;
      }

      .mini-stat span {
        opacity: 0.8;
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
        color: #4f46e5;
        text-transform: uppercase;
        font-size: 0.72rem;
        letter-spacing: 0.12rem;
        font-weight: 700;
      }

      h1 {
        margin: 0.15rem 0 0;
        font-size: clamp(2rem, 2.6vw, 2.8rem);
        letter-spacing: -0.05em;
      }

      .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
      }

      .stat-card {
        background: rgba(255, 255, 255, 0.85);
        border: 1px solid #dbe4f0;
        border-radius: 1rem;
        padding: 1rem 1.1rem;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
      }

      .stat-card.pending {
        border-color: #fcd34d;
      }

      .stat-card.approved {
        border-color: #86efac;
      }

      .stat-card.rejected {
        border-color: #fca5a5;
      }

      .stat-card .label {
        display: block;
        font-size: 0.78rem;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.08rem;
        margin-bottom: 0.4rem;
      }

      .stat-card strong {
        font-size: 1.7rem;
        letter-spacing: -0.05em;
      }

      .alert {
        padding: 1rem 1.1rem;
        border-radius: 0.85rem;
        margin-bottom: 1rem;
        border: 1px solid transparent;
      }

      .alert.error {
        background: #fef2f2;
        color: #991b1b;
        border-color: #fecaca;
      }

      .table-panel {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid #dbe4f0;
        border-radius: 1rem;
        box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
        overflow: hidden;
      }

      .table-header {
        padding: 1.15rem 1.25rem;
        border-bottom: 1px solid #e5e7eb;
        background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
      }

      .table-header h2 {
        margin: 0;
        font-size: 1.05rem;
      }

      .loading {
        padding: 1.5rem;
        color: #475569;
      }

      .table-wrap {
        overflow-x: auto;
      }

      table {
        width: 100%;
        border-collapse: collapse;
      }

      th,
      td {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
        vertical-align: middle;
      }

      th {
        background: #f8fafc;
        font-size: 0.85rem;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.07rem;
      }

      tbody tr:hover {
        background: rgba(59, 130, 246, 0.03);
      }

      .badge {
        display: inline-flex;
        padding: 0.4rem 0.7rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: capitalize;
      }

      .badge.pending {
        background: #fef3c7;
        color: #92400e;
      }

      .badge.approved {
        background: #dcfce7;
        color: #166534;
      }

      .badge.rejected {
        background: #fee2e2;
        color: #991b1b;
      }

      .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
      }

      .edit-link {
        color: #1d4ed8;
        text-decoration: none;
        font-weight: 700;
      }

      .button,
      button,
      a {
        border-radius: 0.85rem;
        font: inherit;
      }

      .button.primary,
      button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.85rem 1.2rem;
        border: none;
        cursor: pointer;
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: white;
        text-decoration: none;
        box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
      }

      button.danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        box-shadow: 0 10px 20px rgba(239, 68, 68, 0.2);
      }

      .empty {
        text-align: center;
        color: #64748b;
        padding: 2rem 1rem;
      }

      @media (max-width: 980px) {
        .page-shell {
          flex-direction: column;
        }

        .sidebar {
          width: 100%;
        }

        .stats-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
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

        .stats-grid {
          grid-template-columns: 1fr;
        }
      }
    `,
  ],
})
export class EstateList implements OnInit {
  private readonly fincaAdminService = inject(FincaAdminService);

  readonly fincas = signal<Finca[]>([]);
  readonly isLoading = signal(false);
  readonly error = signal('');

  ngOnInit(): void {
    this.loadFincas();
  }

  totalFincas(): number {
    return this.fincas().length;
  }

  pendientes(): number {
    return this.fincas().filter((finca) => finca.estado === 'pendiente').length;
  }

  aprobadas(): number {
    return this.fincas().filter((finca) => finca.estado === 'aprobado').length;
  }

  rechazadas(): number {
    return this.fincas().filter((finca) => finca.estado === 'rechazado').length;
  }

  loadFincas(): void {
    this.isLoading.set(true);
    this.error.set('');

    this.fincaAdminService.getAdminFincas().subscribe({
      next: (response) => {
        this.fincas.set(response.data ?? []);
        this.error.set('');
        this.isLoading.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error));
        this.isLoading.set(false);
      },
    });
  }

  handleEstado(finca: Finca, accion: 'aprobar' | 'rechazar'): void {
    const actionText = accion === 'aprobar' ? 'aprobar' : 'rechazar';
    const confirmed = window.confirm(`¿Deseas ${actionText} la finca “${finca.nombre ?? 'sin nombre'}”?`);

    if (!confirmed || !finca.id) {
      return;
    }

    this.fincaAdminService.updateEstadoAdmin(finca.id, accion).subscribe({
      next: () => this.loadFincas(),
      error: (error) => this.error.set(this.getErrorMessage(error)),
    });
  }

  private getErrorMessage(error: unknown): string {
    const message = (error as { error?: { message?: string } })?.error?.message;

    if (message) {
      return message;
    }

    return 'No se pudieron cargar las fincas.';
  }
}
