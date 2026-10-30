import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Production } from './production';
import { AuthService } from '../../core/auth/auth.service';

describe('Producción integrada', () => {
  let page: Production;
  let http: HttpTestingController;
  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [Production],
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        {
          provide: AuthService,
          useValue: { isSuperAdmin: () => false, currentUser: () => ({ name: 'Ana' }) },
        },
      ],
    });
    page = TestBed.createComponent(Production).componentInstance;
    http = TestBed.inject(HttpTestingController);
    page.data.set({
      schemas: { costos: { label: 'Costos', fields: [] } },
      parameters: {},
      defaults: { variedades: { Café: ['Castillo'], Cacao: ['CCN51'] } },
      fincas: [
        {
          id: 1,
          nombre: 'Finca',
          uuid: 'f',
          estado: 'aprobado',
          hectareas_totales: 10,
          latitud: 6,
          longitud: -73,
        },
      ],
      lotes: [{ id: 2, finca_id: 1, nombre: 'Lote', uuid: 'p', estado: 'en_uso' }],
      records: [
        {
          id: 3,
          finca_id: 1,
          lote_id: 2,
          tipo: 'cultivos',
          nombre: 'Café',
          uuid: 'c',
          estado: 'activo',
        },
        {
          id: 4,
          finca_id: 1,
          lote_id: 2,
          cultivo_id: 3,
          tipo: 'ciclos',
          nombre: 'Ciclo',
          uuid: 'cy',
          estado: 'activo',
        },
        {
          id: 5,
          finca_id: 9,
          lote_id: 8,
          tipo: 'ciclos',
          nombre: 'Otra finca',
          uuid: 'o',
          estado: 'activo',
        },
        {
          id: 6,
          finca_id: 1,
          tipo: 'catalogos',
          nombre: 'Colombia',
          uuid: 'v',
          estado: 'activo',
          datos: { catalogo: 'variedades', padre: 'Café' },
        },
      ],
      actividades: [],
    });
  });
  afterEach(() => http.verify());

  it('cierra el contorno del lote sin pedir al usuario escribir datos técnicos', () => {
    page.vertices = [
      { latitud: 6, longitud: -73 },
      { latitud: 6.1, longitud: -73 },
      { latitud: 6.1, longitud: -73.1 },
    ];
    page.updatePolygon();
    const polygon = JSON.parse(page.model['poligono']);
    expect(polygon.coordinates[0]).toEqual([
      [-73, 6],
      [-73, 6.1],
      [-73.1, 6.1],
      [-73, 6],
    ]);
    page.removeVertex(2);
    expect(page.model['poligono']).toBeNull();
  });

  it('permite consultar los archivados sin ofrecerlos como destino de operaciones', () => {
    const current = page.data()!;
    page.data.set({
      ...current,
      records: [
        ...current.records,
        {
          id: 7,
          finca_id: 1,
          nombre: 'Costo archivado',
          uuid: 'old',
          tipo: 'costos',
          estado: 'activo',
          deleted_at: '2026-10-01',
        },
      ],
    });
    page.section.set('costos');
    expect(page.rows()).toHaveLength(0);
    page.showArchived = true;
    expect(page.rows()).toHaveLength(1);
    page.model = { finca_id: 1 };
    expect(
      page.relations({
        key: 'costo_id',
        label: 'Costo',
        type: 'relation',
        required: true,
        options: 'costos',
        show: null,
      }),
    ).toHaveLength(0);
  });

  it('limita los desplegables al contexto de finca, lote y cultivo', () => {
    page.model = { finca_id: 1, lote_id: 2, cultivo_id: 3 };
    expect(
      page
        .relations({
          key: 'ciclo_id',
          label: 'Ciclo',
          type: 'relation',
          required: false,
          options: 'ciclos',
          show: null,
        })
        .map((r) => r.id),
    ).toEqual([4]);
    page.modelChanged('lote_id');
    expect(page.model['cultivo_id']).toBeNull();
    expect(page.model['ciclo_id']).toBeNull();
  });

  it('combina variedades del catálogo con opciones propias de la misma especie', () => {
    page.model = { finca_id: 1, especie: 'Café' };
    const field = {
      key: 'variedad',
      label: 'Variedad',
      type: 'catalog',
      required: false,
      options: 'variedades',
      show: null,
    };
    expect(page.catalog(field)).toEqual(['Castillo', 'Colombia']);
    page.model['especie'] = 'Cacao';
    expect(page.catalog(field)).toEqual(['CCN51']);
  });

  it('conserva el formulario y la clave de operación al reintentar un fallo de red', async () => {
    page.section.set('costos');
    page.modal.set(true);
    page.model = { finca_id: 1, nombre: 'Semillas', cantidad: 2, precio_unitario: 10 };
    page.detailedCosts = true;
    const first = page.save();
    const request = http.expectOne('/api/produccion/registros/costos');
    const key = request.request.headers.get('Idempotency-Key');
    request.error(new ProgressEvent('error'));
    await first;
    expect(page.modal()).toBe(true);
    expect(page.model['nombre']).toBe('Semillas');
    const second = page.save();
    const retry = http.expectOne('/api/produccion/registros/costos');
    expect(retry.request.headers.get('Idempotency-Key')).toBe(key);
    retry.flush(
      { message: 'Revisa la cantidad.', errors: { cantidad: ['Cantidad inválida'] } },
      { status: 422, statusText: 'Unprocessable' },
    );
    await second;
    expect(page.errors()['cantidad']).toEqual(['Cantidad inválida']);
  });

  it('guía a completar la finca antes de ofrecer operaciones que el servidor rechazaría', () => {
    page.farmId = 1;
    page.section.set('cultivos');
    expect(page.canCreate()).toBe(true);
    const data = page.data()!;
    page.data.set({ ...data, fincas: [{ ...data.fincas[0], latitud: null }] });
    expect(page.canCreate()).toBe(false);
    expect(page.preparationHint()).toContain('ubicación');
  });

  it('adapta el área sugerida al cambiar de lote y conserva los datos entre pasos', () => {
    const data = page.data()!;
    page.data.set({
      ...data,
      lotes: [
        ...data.lotes,
        { id: 8, uuid: 'new', finca_id: 1, nombre: 'Huerta', estado: 'disponible', hectareas: '1' },
      ],
    });
    page.section.set('cultivos');
    page.model = { lote_id: 8, area: 2, especie: 'Maíz', tipo: 'transitorio' };
    page.modelChanged('lote_id');
    expect(page.model['area']).toBe(1);
    page.submitStep();
    expect(page.formStep).toBe(1);
    expect(page.model['especie']).toBe('Maíz');
    expect(page.steps()).toHaveLength(3);
  });

  it('convierte un gasto sencillo en un solo importe sin exigir cálculos al productor', async () => {
    page.section.set('costos');
    page.model = { finca_id: 1, fecha: '2026-10-28', recurso: 'Abono' };
    page.quickTotal = 45000;
    page.detailedCosts = false;
    const save = page.save();
    const request = http.expectOne('/api/produccion/registros/costos');
    expect(request.request.body).toMatchObject({
      cantidad: 1,
      unidad: 'unidad',
      precio_unitario: 45000,
    });
    request.flush({ message: 'Revisa el gasto' }, { status: 422, statusText: 'Unprocessable' });
    await save;
  });

  it('ofrece todos los cultivos de la finca aunque esté seleccionado otro lote', () => {
    const data = page.data()!;
    page.data.set({
      ...data,
      records: [
        ...data.records,
        {
          id: 80,
          uuid: 'other-crop',
          tipo: 'cultivos',
          finca_id: 1,
          lote_id: 8,
          nombre: 'Maíz',
          estado: 'activo',
        },
      ],
    });
    page.farmId = 1;
    page.plotId = 2;
    expect(page.farmCrops().map((c) => c.id)).toEqual([3, 80]);
    expect(page.crops().map((c) => c.id)).toEqual([3]);
  });

  it('filtra inversión ventas y ganancia por el cultivo y cambia al lote correspondiente', async () => {
    page.farmId = 1;
    page.plotId = 8;
    page.cropId = 3;
    page.cycleId = 4;
    const changed = page.filterChanged('crop');
    const request = http.expectOne((r) => r.url === '/api/produccion/reporte');
    expect(request.request.params.get('cultivo_id')).toBe('3');
    expect(request.request.params.get('lote_id')).toBe('2');
    expect(request.request.params.has('ciclo_id')).toBe(false);
    request.flush({ data: { costos_produccion: 100, ventas: 300, resultado: 200 } });
    await changed;
    expect(page.report()?.['resultado']).toBe(200);
    expect(page.plotId).toBe(2);
  });

  it('compara la incidencia sanitaria solo con la misma unidad y método', () => {
    const original = {
      id: 90,
      uuid: 'health',
      tipo: 'sanidad_casos',
      nombre: 'Problema',
      estado: 'abierto',
      finca_id: 1,
      lote_id: 2,
      cultivo_id: 3,
      datos: { unidad_muestreo: 'planta', metodo_muestreo: 'Zigzag', incidencia_porcentaje: 40 },
    };
    const review = {
      id: 91,
      uuid: 'review',
      tipo: 'sanidad_seguimientos',
      nombre: 'Revisión',
      estado: 'registrado',
      finca_id: 1,
      lote_id: 2,
      cultivo_id: 3,
      fecha: '2026-10-20',
      datos: {
        caso_id: 90,
        unidad_muestreo: 'planta',
        metodo_muestreo: 'Zigzag',
        incidencia_porcentaje: 20,
      },
    };
    page.data.set({ ...page.data()!, records: [original, review] });
    expect(page.healthProgress(original).reduction).toBe(50);
    review.datos.unidad_muestreo = 'hoja';
    expect(page.healthProgress(original).reduction).toBeNull();
  });

  it('conserva los datos del usuario ante un conflicto de edición', async () => {
    page.section.set('costos');
    page.open({
      id: 20,
      uuid: 'cost',
      nombre: 'Factura',
      finca_id: 1,
      estado: 'activo',
      version: 2,
      fecha: '2026-10-01',
      datos: { cantidad: 1, precio_unitario: 300 },
    });
    const save = page.save();
    const request = http.expectOne('/api/produccion/registros/costos/20');
    expect(request.request.body.version).toBe(2);
    request.flush(
      { message: 'El registro cambió. Vuelve a cargarlo antes de guardar.' },
      { status: 409, statusText: 'Conflict' },
    );
    await save;
    expect(page.modal()).toBe(true);
    expect(page.error()).toContain('El registro cambió');
  });
});
