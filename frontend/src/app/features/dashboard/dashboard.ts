import { AdminSidebar } from '../admin-sidebar';
import { CommonModule, DatePipe, DecimalPipe } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { DashboardData, DashboardService } from './dashboard.service';

@Component({
  selector: 'app-dashboard', standalone: true, imports: [AdminSidebar, CommonModule, DecimalPipe, DatePipe],
  templateUrl: './dashboard.html', styleUrl: './dashboard.scss',
})
export class Dashboard implements OnInit {
  private readonly service = inject(DashboardService);
  readonly data = signal<DashboardData | null>(null);
  readonly loading = signal(true);
  readonly error = signal('');

  ngOnInit(): void { this.load(); }
  load(): void {
    this.loading.set(true); this.error.set('');
    this.service.getDashboard().subscribe({
      next: response => { this.data.set(response.data ?? null); this.loading.set(false); },
      error: () => { this.error.set('No fue posible cargar el dashboard. Intenta nuevamente.'); this.loading.set(false); },
    });
  }
  amount(value: number | string): number { return Number(value); }
  absolute(value: number | string): number { return Math.abs(Number(value)); }
  formatCurrency(value: number | string, currency: string): string {
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency,
      currencyDisplay: 'code',
      maximumFractionDigits: 2,
    }).format(Number(value));
  }
  sensorScale(type: string, unit: string, rows: DashboardData['sensores_siete_dias']): number {
    return Math.max(1, ...rows.filter(row => row.tipo_medicion === type && row.unidad === unit).map(row => Math.abs(Number(row.promedio))));
  }
  max(values: Array<number | string>): number { return Math.max(1, ...values.map(Number)); }
  label(value: string): string { return value.replaceAll('_', ' '); }
  track(_index: number, item: object): object { return item; }
}
