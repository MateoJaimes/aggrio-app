import { TestBed, ComponentFixture } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { provideRouter, Router } from '@angular/router';
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { Campo } from './campo';
import { AuthService } from '../../core/auth/auth.service';
import { environment } from '../../../environments/environment';

describe('Operación del productor', () => {
  let fixture: ComponentFixture<Campo>;
  let http: HttpTestingController;
  const base = environment.apiUrl;
  beforeEach(() => {
    sessionStorage.clear();
    TestBed.configureTestingModule({
      imports: [Campo],
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        {
          provide: AuthService,
          useValue: {
            currentUser: () => ({
              id: 7,
              name: 'Productor',
              email: 'producer@example.test',
              is_superadmin: false,
            }),
            isSuperAdmin: () => false,
          },
        },
      ],
    });
    fixture = TestBed.createComponent(Campo);
    http = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    http.expectOne(`${base}/campo/perfil`).flush({
      data: {
        name: 'Productor',
        email: 'producer@example.test',
        phone: null,
        registration: null,
      },
    });
    http.expectOne(`${base}/campo/fincas`).flush({
      data: [
        {
          id: 1,
          nombre: 'Mi finca',
          estado: 'aprobado',
          hectareas_totales: 10,
          latitud: 8,
          longitud: -73,
          perfil_completo: true,
          hectareas_disponibles: 8,
        },
      ],
    });
    http.expectOne(`${base}/campo/dashboard`).flush({
      data: {
        kpis: { fincas: 1 },
        estado_fincas: [],
        cultivos_hectareas: [],
        inversion_actividad: [],
        inversion_mensual: [],
        sensores_siete_dias: [],
        estado_sensores: [],
      },
    });
    http.expectOne(`${base}/campo/fincas/1/lotes?archivados=0`).flush({
      data: [
        {
          id: 2,
          nombre: 'Lote',
          estado: 'en_uso',
          hectareas: 2,
          tipo_cultivo: 'Cacao',
          finca_id: 1,
          deleted_at: null,
        },
      ],
    });
    http
      .expectOne(`${base}/campo/lotes/2/actividades?page=1`)
      .flush({ data: { data: [], current_page: 1, last_page: 1, total: 0 } });
    http
      .expectOne(`${base}/campo/lotes/2/lecturas?page=1`)
      .flush({ data: { data: [], current_page: 1, last_page: 1, total: 0 } });
    http
      .expectOne(`${base}/campo/evidencia/lote/2?page=1`)
      .flush({ data: { data: [], current_page: 1, last_page: 1, total: 0 } });
    fixture.detectChanges();
  });
  afterEach(() => {
    http.verify();
    fixture.destroy();
    sessionStorage.clear();
  });
  it('muestra el contexto y las pantallas propias sin menú de administración', () => {
    expect(fixture.nativeElement.textContent).toContain('Mi finca');
    expect(fixture.nativeElement.textContent).toContain('¿Qué necesitas hacer?');
    expect(fixture.nativeElement.textContent).not.toContain('Dashboard ejecutivo');
    const navigation = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    fixture.componentInstance.selectTab('actividades');
    expect(navigation).toHaveBeenCalledWith(['/produccion'], expect.objectContaining({ queryParams: expect.objectContaining({ seccion: 'labores', area: 'trabajo' }) }));
  });
  it('abre Mi cuenta desde el mismo menú visual que utiliza la administración', () => {
    const button = Array.from(
      fixture.nativeElement.querySelectorAll('app-sidebar nav button'),
    ).find((element: any) => element.textContent.includes('Mi cuenta')) as HTMLButtonElement;
    button.click();
    fixture.detectChanges();
    expect(fixture.componentInstance.tab()).toBe('perfil');
    expect(fixture.nativeElement.textContent).toContain('Guardar perfil');
  });
  it('permite recorrer las pantallas del productor y consultar sus datos registrados', () => {
    const c = fixture.componentInstance;
    c.profile.set({
      name: 'Productor',
      email: 'producer@example.test',
      phone: null,
      registration: {
        firstname: 'Productor',
        lastname: 'Prueba',
        id_type: 'CC',
        id_number: '0012345678',
        country: 'Colombia',
        department: 'Santander',
        city: 'San Vicente',
        status: 'approved',
        created_at: '2026-10-24',
      },
    });
    for (const [tab] of c.tabs) {
      c.selectTab(tab);
      fixture.detectChanges();
      expect(fixture.nativeElement.querySelector('h1').textContent).toBe(c.title());
    }
    expect(fixture.nativeElement.textContent).toContain('0012345678');
    expect(fixture.nativeElement.textContent).toContain('Cambiar mi contraseña');
    c.selectTab('fincas');
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Superficie disponible');
    expect(fixture.nativeElement.textContent).toContain('Mi finca');
  });
  it('conserva los datos y el identificador de escritura tras un corte de red', () => {
    const c = fixture.componentInstance;
    c.activityForm.observaciones = 'Observación de campo';
    c.saveActivity();
    const first = http.expectOne(`${base}/campo/lotes/2/actividades`);
    const key = first.request.headers.get('Idempotency-Key');
    first.error(new ProgressEvent('network'));
    expect(c.busy()).toBe(false);
    expect(c.activityForm.observaciones).toBe('Observación de campo');
    c.saveActivity();
    const retry = http.expectOne(`${base}/campo/lotes/2/actividades`);
    expect(retry.request.headers.get('Idempotency-Key')).toBe(key);
    retry.error(new ProgressEvent('network'));
    expect(sessionStorage.getItem('aggrio:draft:7:campo')).toContain('Observación de campo');
  });
  it('cambiar finca limpia el historial del contexto anterior', () => {
    fixture.componentInstance.farmId = 0;
    fixture.componentInstance.changeFarm();
    expect(fixture.componentInstance.activities()).toBe(null);
    expect(fixture.componentInstance.readings()).toBe(null);
    expect(fixture.componentInstance.plotId).toBe(0);
  });
  it('ignora una respuesta tardía de lotes si se dejó de seleccionar la finca', () => {
    const c = fixture.componentInstance;
    c.changeFarm();
    const pending = http.expectOne(`${base}/campo/fincas/1/lotes?archivados=0`);
    c.farmId = 0;
    c.changeFarm();
    pending.flush({ data: [{ id: 99, nombre: 'Respuesta anterior', estado: 'en_uso' }] });
    expect(c.plotId).toBe(0);
    expect(c.plots()).toEqual([]);
  });
});
