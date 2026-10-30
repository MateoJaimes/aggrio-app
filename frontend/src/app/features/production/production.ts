import { CommonModule } from '@angular/common';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Component, OnDestroy, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../../environments/environment';
import { AuthService } from '../../core/auth/auth.service';
import { AppSidebar } from '../../shared/app-sidebar';
import { FarmContext } from '../../shared/farm-context';
import { Subscription } from 'rxjs';

interface Field {
  key: string;
  label: string;
  type: string;
  required: boolean;
  options: string[] | string;
  show: string | null;
}
interface Definition {
  label: string;
  fields: Field[];
  states?: string[];
}
interface Row {
  id: number;
  uuid: string;
  finca_id?: number;
  lote_id?: number;
  cultivo_id?: number;
  ciclo_id?: number;
  cosecha_id?: number;
  actividad_id?: number;
  costo_id?: number;
  venta_id?: number;
  nombre: string;
  tipo?: string;
  fecha?: string;
  estado: string;
  version?: number;
  datos?: Record<string, any>;
  parametros?: Record<string, any>;
  total?: string;
  tipo_actividad?: string;
  costo?: string;
  hectareas?: string;
  hectareas_totales?: string | number;
  latitud?: string | number | null;
  longitud?: string | number | null;
  tipo_suelo?: string;
  deleted_at?: string | null;
  edad_anos?: number;
  productor?: { nombre: string; email: string; contacto: string | null; documento: string | null };
}
interface Bootstrap {
  schemas: Record<string, Definition>;
  parameters: Record<string, Field[]>;
  defaults: Record<string, any>;
  fincas: Row[];
  lotes: Row[];
  records: Row[];
  actividades: Row[];
}
interface Api<T> {
  success: boolean;
  data: T;
}

@Component({
  selector: 'app-production',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, AppSidebar],
  templateUrl: './production.html',
  styleUrl: './production.scss',
})
export class Production implements OnInit, OnDestroy {
  readonly auth = inject(AuthService);
  private http = inject(HttpClient);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private context = inject(FarmContext);
  private querySubscription?: Subscription;
  private openedFromLink = false;
  private base = environment.apiUrl;
  private lastWrite: { signature: string; key: string } | null = null;
  readonly data = signal<Bootstrap | null>(null);
  readonly report = signal<Record<string, any> | null>(null);
  readonly busy = signal(false);
  readonly error = signal('');
  readonly notice = signal('');
  readonly section = signal('resumen');
  readonly area = signal('dinero');
  formStep = 0;
  showOptional = false;
  detailedCosts = false;
  quickTotal: number | null = null;
  paymentMode = 'completo';
  readonly areas = {
    sanidad: {
      title: 'Sanidad del cultivo',
      intro: 'Problemas, decisiones, tratamientos y resultados de cada cultivo.',
      sections: ['sanidad_casos', 'sanidad_tratamientos', 'sanidad_seguimientos'],
    },
    cultivos: {
      title: 'Mis cultivos',
      intro: 'Lo que sembraste, dónde está y cómo va cada siembra.',
      sections: ['cultivos', 'ciclos'],
    },
    trabajo: {
      title: 'Trabajo del día',
      intro: 'Anota lo que hiciste y lo que recogiste de tu cultivo.',
      sections: ['labores', 'cosechas', 'transportes'],
    },
    dinero: {
      title: 'Mi dinero',
      intro: 'Lleva tus gastos, ventas y pagos en un solo lugar.',
      sections: ['resumen', 'costos', 'ventas', 'cobros'],
    },
    detalles: {
      title: 'Datos de mi finca',
      intro: 'Completa esta información por partes, cuando la tengas disponible.',
      sections: ['parametros', 'analisis', 'certificaciones', 'catalogos'],
    },
  };
  readonly modal = signal(false);
  readonly detail = signal<Record<string, any> | null>(null);
  readonly evidence = signal<any[]>([]);
  readonly errors = signal<Record<string, string[]>>({});
  farmId = 0;
  showArchived = false;
  plotId = 0;
  cropId = 0;
  cycleId = 0;
  desde = '';
  hasta = '';
  search = '';
  page = 1;
  editing: Row | null = null;
  model: Record<string, any> = {};
  parameterType = 'finca';
  parameterId = 0;
  archiveReason = '';
  note = '';
  file: File | null = null;
  detailType = 'registro';
  detailId = 0;
  gps: { latitud: number; longitud: number; gps_precision: number } | null = null;
  vertices: { latitud: number | null; longitud: number | null }[] = [];
  readonly today = new Date().toLocaleDateString('en-CA', { timeZone: 'America/Bogota' });

  async ngOnInit() {
    const context = this.context.read();
    this.farmId = context.farmId;
    this.plotId = context.plotId;
    this.querySubscription = this.route.queryParamMap.subscribe((params) => {
      const requestedArea = params.get('area');
      const requestedSection = params.get('seccion');
      if (requestedArea && requestedArea in this.areas) this.area.set(requestedArea);
      const defaultSection = this.areaDefinition().sections[0];
      this.section.set(
        requestedSection &&
          (requestedSection === 'resumen' ||
            requestedSection === 'parametros' ||
            this.data()?.schemas[requestedSection])
          ? requestedSection
          : defaultSection,
      );
      if (requestedSection && !this.data()) this.section.set(requestedSection);
      if (params.has('finca')) this.farmId = Number(params.get('finca')) || 0;
      if (params.has('lote')) this.plotId = Number(params.get('lote')) || 0;
      if (params.has('cultivo')) this.cropId = Number(params.get('cultivo')) || 0;
      if (this.data()) {
        void this.loadReport();
        this.openLinkedForm();
      }
    });
    await this.reload();
  }
  ngOnDestroy() {
    this.querySubscription?.unsubscribe();
  }
  areaDefinition() {
    return this.areas[this.area() as keyof typeof this.areas] ?? this.areas.dinero;
  }
  private areaFor(section: string) {
    return (
      Object.entries(this.areas).find(([, value]) => value.sections.includes(section))?.[0] ??
      'dinero'
    );
  }
  private openLinkedForm() {
    if (
      !this.openedFromLink &&
      this.route.snapshot.queryParamMap.get('nuevo') === '1' &&
      this.section() !== 'parametros' &&
      this.section() !== 'resumen'
    ) {
      this.openedFromLink = true;
      if (this.canCreate()) this.open();
      void this.router.navigate([], {
        relativeTo: this.route,
        queryParams: { nuevo: null },
        queryParamsHandling: 'merge',
        replaceUrl: true,
      });
    }
  }
  goCampo(section: string) {
    void this.router.navigate(['/campo'], { queryParams: { seccion: section } });
  }
  entries() {
    return this.areaDefinition().sections.map(
      (key) => [key, { label: this.sectionLabel(key) }] as const,
    );
  }
  sectionLabel(key: string): string {
    return (
      (
        {
          resumen: 'Mis cuentas',
          cultivos: 'Cultivos',
          sanidad_casos: 'Problemas detectados',
          sanidad_tratamientos: 'Tratamientos y decisiones',
          sanidad_seguimientos: 'Revisiones y resultados',
          ciclos: 'Siembras y cortes',
          labores: 'Actividades',
          costos: 'Gastos',
          cosechas: 'Cosechas',
          ventas: 'Ventas',
          cobros: 'Pagos recibidos',
          transportes: 'Transportes',
          parametros: 'Datos de la finca',
          analisis: 'Análisis de suelo',
          certificaciones: 'Certificados',
          catalogos: 'Mis opciones',
          repartos: 'Repartir un gasto',
        } as Record<string, string>
      )[key] ?? key
    );
  }
  actionLabel(): string {
    return (
      (
        {
          cultivos: 'Añadir cultivo',
          sanidad_casos: 'Registrar un problema',
          sanidad_tratamientos: 'Registrar una medida',
          sanidad_seguimientos: 'Revisar un resultado',
          ciclos: 'Nueva siembra o corte',
          labores: 'Anotar trabajo',
          costos: 'Anotar gasto',
          cosechas: 'Registrar cosecha',
          ventas: 'Anotar venta',
          cobros: 'Registrar pago',
          transportes: 'Anotar transporte',
          analisis: 'Añadir análisis',
          certificaciones: 'Añadir certificado',
          catalogos: 'Añadir opción',
          repartos: 'Repartir un gasto',
        } as Record<string, string>
      )[this.section()] ?? 'Añadir'
    );
  }
  label(value: unknown): string {
    return (
      (
        {
          transitorio: 'Una siembra por temporada',
          permanente: 'Cultivo que dura varios años',
          semipermanente: 'Cultivo con varios cortes',
          terminado: 'Ya lo hice',
          pendiente: 'Lo voy a hacer',
          en_proceso: 'En proceso',
          cancelado: 'Cancelado',
          produccion: 'Gasto de producción',
          inversion: 'Compra de equipos o infraestructura',
          preparacion: 'Preparación del terreno',
          control_plagas: 'Control de plagas',
          semillas_plantulas: 'Semillas y plántulas',
          mano_obra: 'Jornales y mano de obra',
          activo: 'En curso',
          cerrado: 'Finalizado',
        } as Record<string, string>
      )[String(value)] ?? String(value ?? '').replaceAll('_', ' ')
    );
  }
  currency(value: unknown) {
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
  }
  fields(): Field[] {
    let fields =
      this.section() === 'parametros'
        ? (this.data()?.parameters[this.parameterType] ?? [])
        : (this.data()?.schemas[this.section()]?.fields ?? []);
    if (this.section() === 'cultivos' && !this.editing && this.model['tipo'] === 'transitorio')
      fields = [
        ...fields,
        {
          key: 'fecha_cosecha_estimada',
          label: '¿Cuándo esperas cosechar?',
          type: 'date',
          required: true,
          options: [],
          show: null,
        },
        {
          key: 'rendimiento_esperado',
          label: 'Cantidad que esperas recoger (opcional)',
          type: 'number',
          required: false,
          options: [],
          show: null,
        },
        {
          key: 'unidad',
          label: 'Unidad de la cosecha',
          type: 'select',
          required: true,
          options: ['kg', 't', 'unidad', 'litro'],
          show: null,
        },
      ];
    const crop = this.data()?.records.find((r) => r.id === Number(this.model['cultivo_id']));
    return fields.map((f) =>
      (f.key === 'ciclo_id' && crop?.datos?.['tipo'] === 'transitorio') ||
      (f.key === 'cultivo_id' && this.section() === 'cosechas') ||
      (this.section() === 'sanidad_tratamientos' &&
        this.model['estado'] === 'aplicado' &&
        ((['quimico', 'biologico'].includes(this.model['metodo']) &&
          ['producto', 'dosis', 'unidad_dosis', 'cantidad_aplicada', 'unidad_aplicada'].includes(
            f.key,
          )) ||
          (this.model['metodo'] === 'quimico' &&
            [
              'ingrediente_activo',
              'registro_producto',
              'lote_insumo',
              'carencia_dias',
              'reingreso_horas',
            ].includes(f.key))))
        ? { ...f, required: true }
        : f,
    );
  }
  steps(): { title: string; keys: string[] }[] {
    if (this.section() === 'parametros')
      return this.parameterType === 'finca'
        ? [
            {
              title: 'Ubicación y contacto',
              keys: ['departamento', 'municipio', 'vereda', 'contacto', 'altitud'],
            },
            {
              title: 'Tenencia y documentos',
              keys: [
                'tenencia',
                'documento_productor',
                'cedula_catastral',
                'matricula',
                'registro_ica',
                'asistencia_tecnica',
                'autoriza_datos',
                'fecha_autorizacion',
              ],
            },
            {
              title: 'Uso del terreno',
              keys: [
                'area_productiva',
                'area_conservacion',
                'area_infraestructura',
                'area_sin_uso',
              ],
            },
            {
              title: 'Agua y suelo',
              keys: [
                'fuente_agua',
                'concesion',
                'riego',
                'tipo_suelo',
                'precipitacion',
                'temperatura',
              ],
            },
            { title: 'Infraestructura', keys: ['beneficiadero', 'bodega', 'secadero', 'vias'] },
          ]
        : [
            {
              title: 'Cómo es el lote',
              keys: ['estado_agronomico', 'ambiente', 'tipo_suelo', 'pendiente', 'uso_anterior'],
            },
            {
              title: 'Agua y cultivo',
              keys: ['riego', 'drenaje', 'medio', 'control_riego', 'fertirriego'],
            },
            {
              title: 'Cubierta y equipos',
              keys: [
                'area_cubierta',
                'ano_construccion',
                'cubierta',
                'ultimo_cambio',
                'ventilacion',
                'control_climatico',
                'sensor_temperatura',
                'sensor_humedad',
                'sensor_co2',
              ],
            },
            { title: 'Contorno del terreno', keys: ['poligono'] },
          ];
    const map: Record<string, { title: string; keys: string[] }[]> = {
      sanidad_casos: [
        {
          title: '¿Qué problema encontraste?',
          keys: [
            'lote_id',
            'cultivo_id',
            'ciclo_id',
            '$fecha',
            'clase_problema',
            'problema',
            'diagnostico',
            'sintomas',
            '$state',
          ],
        },
        {
          title: '¿Qué observaste en la muestra?',
          keys: [
            'metodo_muestreo',
            'unidad_muestreo',
            'muestra_total',
            'muestra_afectada',
            'severidad',
            'responsable',
            'fecha_revision',
          ],
        },
      ],
      sanidad_tratamientos: [
        {
          title: 'La decisión y su estado',
          keys: [
            'lote_id',
            'cultivo_id',
            'ciclo_id',
            'caso_id',
            '$fecha',
            '$state',
            'metodo',
            'accion',
            'motivo_decision',
            'responsable',
          ],
        },
        {
          title: 'Lo que realmente se aplicó',
          keys: [
            'producto',
            'ingrediente_activo',
            'registro_producto',
            'lote_insumo',
            'dosis',
            'unidad_dosis',
            'cantidad_aplicada',
            'unidad_aplicada',
            'area_tratada',
            'volumen_mezcla',
            'carencia_dias',
            'reingreso_horas',
            'equipo',
            'condiciones_clima',
            'fecha_revision',
          ],
        },
      ],
      sanidad_seguimientos: [
        {
          title: '¿Qué problema estás revisando?',
          keys: [
            'lote_id',
            'cultivo_id',
            'ciclo_id',
            'caso_id',
            'tratamiento_id',
            '$fecha',
            'responsable',
            'resultado_observado',
          ],
        },
        {
          title: 'Medición y próximo paso',
          keys: [
            'metodo_muestreo',
            'unidad_muestreo',
            'muestra_total',
            'muestra_afectada',
            'severidad',
            'efectos_adversos',
            'siguiente_accion',
            'fecha_revision',
          ],
        },
      ],
      cultivos: [
        { title: '¿Qué sembraste?', keys: ['lote_id', 'tipo', 'especie', 'variedad'] },
        { title: '¿Cómo está sembrado?', keys: ['area', 'numero_plantas', '$fecha'] },
      ],
      ciclos: [
        {
          title: 'La nueva siembra o corte',
          keys: ['lote_id', 'cultivo_id', '$fecha', 'fecha_cosecha_estimada'],
        },
        {
          title: 'Lo que esperas recoger',
          keys: ['area', 'numero_plantas', 'rendimiento_esperado', 'unidad', 'cultivo_anterior'],
        },
      ],
      labores: [
        {
          title: '¿Qué trabajo hiciste?',
          keys: ['lote_id', 'cultivo_id', 'ciclo_id', 'tipo_actividad', '$state'],
        },
        { title: 'Fecha y gasto del trabajo', keys: ['$fecha', 'fecha_real', 'costo_real'] },
      ],
      costos: [
        {
          title: '¿En qué gastaste?',
          keys: [
            'categoria',
            'recurso',
            '$fecha',
            'cantidad',
            'unidad',
            'precio_unitario',
            '$total',
          ],
        },
        { title: '¿Para dónde fue el gasto?', keys: ['lote_id', 'cultivo_id', 'ciclo_id'] },
      ],
      cosechas: [
        {
          title: '¿De qué cultivo recogiste?',
          keys: ['lote_id', 'cultivo_id', 'ciclo_id', '$fecha'],
        },
        { title: '¿Cuánto recogiste?', keys: ['cantidad', 'unidad', 'calidad'] },
      ],
      ventas: [
        { title: '¿Qué vendiste?', keys: ['cosecha_id', 'cantidad', 'precio_unitario', '$fecha'] },
        {
          title: '¿Quién compró y cuánto pagó?',
          keys: ['comprador', 'cobrado', 'fecha_cobro', '$payment'],
        },
      ],
      cobros: [
        { title: '¿Qué pago recibiste?', keys: ['venta_id', 'importe', 'medio_pago', '$fecha'] },
      ],
      transportes: [
        {
          title: 'La carga y el viaje',
          keys: ['cosecha_id', 'cantidad', '$fecha', 'origen', 'destino', 'transportista'],
        },
      ],
    };
    const steps = map[this.section()] ?? [
      {
        title: this.sectionLabel(this.section()),
        keys: this.fields()
          .map((f) => f.key)
          .concat(['$fecha', '$name']),
      },
    ];
    if (this.section() === 'cultivos' && !this.editing && this.model['tipo'] === 'transitorio')
      return [
        ...steps,
        {
          title: '¿Cuándo vas a cosechar?',
          keys: ['fecha_cosecha_estimada', 'rendimiento_esperado', 'unidad'],
        },
      ];
    return steps;
  }
  stepHas(key: string) {
    return this.steps()[Math.min(this.formStep, this.steps().length - 1)].keys.includes(key);
  }
  optionalFields() {
    const used = this.steps().flatMap((s) => s.keys);
    return this.fields().filter((f) => !used.includes(f.key));
  }
  currentFields() {
    return this.fields().filter(
      (f) =>
        this.stepHas(f.key) ||
        (this.showOptional &&
          this.formStep === this.steps().length - 1 &&
          this.optionalFields().some((o) => o.key === f.key)),
    );
  }
  fieldLabel(f: Field) {
    return (
      (
        {
          tipo: '¿Cuánto dura este cultivo?',
          especie: '¿Qué cultivo es?',
          area: 'Superficie sembrada (hectáreas)',
          numero_plantas: 'Número de plantas o plántulas',
          recurso: '¿En qué gastaste?',
          costo_real: '¿Cuánto gastaste en este trabajo? (COP)',
          lote_id: '¿En qué lote?',
          cultivo_id: '¿De qué cultivo?',
          ciclo_id: '¿De qué siembra o corte?',
          tipo_actividad: '¿Qué trabajo fue?',
          cosecha_id: '¿De qué cosecha?',
          venta_id: '¿De qué venta?',
          cobrado: '¿Cuánto te pagaron? (COP)',
          fecha_cobro: 'Fecha del pago',
          lote_producto: 'Referencia de esta cosecha',
          fecha_real: '¿Cuándo lo hiciste?',
        } as Record<string, string>
      )[f.key] ?? f.label
    );
  }
  currentContext() {
    const plot = this.data()?.lotes.find((p) => p.id === Number(this.model['lote_id']));
    return this.farmName(this.model['finca_id']) + (plot ? ' · ' + plot.nombre : '');
  }
  submitStep() {
    if (this.section() === 'parametros' || this.formStep === this.steps().length - 1)
      void this.save();
    else this.formStep++;
  }
  legacyActivities() {
    const known = new Set(
      this.data()
        ?.records.filter((r) => r.tipo === 'labores')
        .map((r) => r.actividad_id),
    );
    return (
      this.data()?.actividades.filter(
        (r) =>
          !r.deleted_at &&
          !known.has(r.id) &&
          (!this.farmId ||
            this.data()?.lotes.find((l) => l.id === r.lote_id)?.finca_id === this.farmId) &&
          (!this.plotId || r.lote_id === this.plotId),
      ) ?? []
    );
  }
  states(): string[] {
    return (
      this.data()?.schemas[this.section()]?.states ??
      (this.section() === 'ciclos' ? ['activo', 'cerrado'] : ['activo'])
    );
  }
  title() {
    return this.sectionLabel(this.section());
  }
  farmName(id: unknown) {
    return this.data()?.fincas.find((f) => f.id === Number(id))?.nombre ?? 'Sin finca';
  }
  plotName(id: unknown) {
    return this.data()?.lotes.find((l) => l.id === Number(id))?.nombre ?? '';
  }
  sectionHint(): string {
    if (this.section().startsWith('sanidad_'))
      return (
        {
          sanidad_casos:
            'Registra la detección y una medición inicial. Añade fotos y notas desde Ver detalle.',
          sanidad_tratamientos:
            'Distingue lo planeado, lo aplicado y lo que no se realizó. Registra sus gastos una sola vez en Mi dinero con este cultivo.',
          sanidad_seguimientos:
            'Repite el método de muestreo para comparar. Registra también resultados negativos y lo que harás después.',
        } as Record<string, string>
      )[this.section()];
    return (
      (
        {
          cultivos:
            'Registra aquí lo que sembraste. La primera siembra se guarda en el mismo recorrido.',
          ciclos: 'Cada nueva siembra o corte conserva su propio historial.',
          labores:
            'Anota lo que hiciste o programa el próximo trabajo. Las fotos se añaden en Ver detalle.',
          costos: 'Registra cada pago una sola vez. También puedes asociarlo a un lote o cultivo.',
          cosechas: 'Anota cuánto recogiste y de qué cultivo. Después podrás registrar su venta.',
          ventas: 'Elige una cosecha, su cantidad y lo que te pagaron.',
          cobros: 'Registra los pagos que llegan después de una venta a crédito.',
          transportes:
            'Anota los viajes de tus cosechas. El valor del viaje se registra en Gastos.',
          analisis: 'Guarda los resultados que te entrega el laboratorio.',
          certificaciones: 'Guarda los certificados y sus fechas de vencimiento.',
          catalogos:
            'Estas opciones se guardan automáticamente cuando las escribes en un formulario.',
          repartos: 'Asigna porcentajes de un gasto general a los cultivos que lo utilizaron.',
        } as Record<string, string>
      )[this.section()] ?? ''
    );
  }
  emptyTitle() {
    return this.search || this.desde || this.hasta
      ? 'No hay resultados con estos filtros'
      : ((
          {
            cultivos: 'Añade tu primer cultivo',
            labores: 'Aquí verás tu trabajo',
            costos: 'Aquí verás tus gastos',
            cosechas: 'Aquí verás tus cosechas',
            ventas: 'Aquí verás tus ventas',
          } as Record<string, string>
        )[this.section()] ?? 'Todavía no hay registros');
  }
  farmReady(farm: Row) {
    return (
      farm.estado === 'aprobado' &&
      Number(farm.hectareas_totales) > 0 &&
      farm.latitud != null &&
      farm.longitud != null
    );
  }
  preparationHint() {
    const farm = this.data()?.fincas.find((f) => f.id === this.farmId);
    if (
      !farm ||
      this.section() === 'catalogos' ||
      this.section() === 'parametros' ||
      this.section() === 'resumen'
    )
      return '';
    if (farm.estado !== 'aprobado')
      return 'Esta finca está pendiente de aprobación. Puedes preparar sus datos desde Mi finca.';
    if (!this.farmReady(farm))
      return 'Antes de registrar el trabajo, completa la superficie y la ubicación de esta finca.';
    if (
      ['cultivos', 'ciclos', 'labores', 'cosechas', 'analisis'].includes(this.section()) &&
      !this.plots().some((p) => !p.deleted_at && p.estado !== 'no_disponible')
    )
      return 'Crea el primer lote para indicar dónde sembraste y dónde haces el trabajo.';
    return '';
  }
  canCreate() {
    const farms = this.data()?.fincas ?? [];
    const farm = farms.find((f) => f.id === this.farmId);
    const ready = farm ? this.farmReady(farm) : farms.some((f) => this.farmReady(f));
    return this.section() === 'catalogos'
      ? farms.length > 0
      : ready &&
          (!['cultivos', 'ciclos', 'labores', 'cosechas', 'analisis'].includes(this.section()) ||
            this.plots().some((p) => !p.deleted_at && p.estado !== 'no_disponible'));
  }
  async newCycle(row: Row) {
    this.cropId = row.id;
    this.plotId = row.lote_id ?? 0;
    this.farmId = row.finca_id ?? 0;
    await this.changeSection('ciclos');
    this.open();
  }
  relationName(id: unknown) {
    return this.data()?.records.find((r) => r.id === Number(id))?.nombre ?? '';
  }
  plots() {
    return (
      this.data()?.lotes.filter(
        (p) =>
          (this.showArchived || !p.deleted_at) &&
          (!this.farmId || p.finca_id === Number(this.farmId)),
      ) ?? []
    );
  }
  crops() {
    return (
      this.data()?.records.filter(
        (r) =>
          r.tipo === 'cultivos' &&
          (this.showArchived || !r.deleted_at) &&
          (!this.farmId || r.finca_id === Number(this.farmId)) &&
          (!this.plotId || r.lote_id === Number(this.plotId)),
      ) ?? []
    );
  }
  healthRecords(type: string) {
    return (this.data()?.records ?? []).filter(
      (r) =>
        r.tipo === type &&
        !r.deleted_at &&
        (!this.farmId || r.finca_id === this.farmId) &&
        (!this.plotId || r.lote_id === this.plotId) &&
        (!this.cropId || r.cultivo_id === this.cropId),
    );
  }
  healthProgress(row: Row) {
    const latest = this.healthRecords('sanidad_seguimientos')
      .filter((r) => Number(r.datos?.['caso_id']) === row.id)
      .sort((a, b) => (b.fecha ?? '').localeCompare(a.fecha ?? '') || b.id - a.id)[0];
    const baseline = row.datos ?? {};
    const current = latest?.datos ?? {};
    const comparable =
      !!latest &&
      baseline['unidad_muestreo'] === current['unidad_muestreo'] &&
      String(baseline['metodo_muestreo']).trim().toLowerCase() ===
        String(current['metodo_muestreo']).trim().toLowerCase();
    const reduction =
      comparable && Number(baseline['incidencia_porcentaje']) > 0
        ? Math.round(
            (1 -
              Number(current['incidencia_porcentaje']) /
                Number(baseline['incidencia_porcentaje'])) *
              1000,
          ) / 10
        : null;
    return { latest, reduction, comparable };
  }
  async healthHarvests(row: Row) {
    this.farmId = row.finca_id ?? 0;
    this.plotId = row.lote_id ?? 0;
    this.cropId = row.cultivo_id ?? 0;
    await this.changeSection('cosechas');
  }
  async openHealthCase(row: Row, section: string) {
    this.farmId = row.finca_id ?? 0;
    this.plotId = row.lote_id ?? 0;
    this.cropId = row.cultivo_id ?? 0;
    await this.changeSection(section);
    this.open();
    this.model['caso_id'] = row.id;
    this.modelChanged('caso_id');
  }
  farmCrops() {
    return (this.data()?.records ?? []).filter(
      (r) =>
        r.tipo === 'cultivos' &&
        (this.showArchived || !r.deleted_at) &&
        (!this.farmId || r.finca_id === Number(this.farmId)),
    );
  }
  cycles() {
    return (
      this.data()?.records.filter(
        (r) =>
          r.tipo === 'ciclos' &&
          (this.showArchived || !r.deleted_at) &&
          (!this.farmId || r.finca_id === Number(this.farmId)) &&
          (!this.plotId || r.lote_id === Number(this.plotId)) &&
          (!this.cropId || r.cultivo_id === Number(this.cropId)),
      ) ?? []
    );
  }
  rows() {
    return (this.data()?.records ?? []).filter(
      (r) =>
        r.tipo === this.section() &&
        (this.showArchived || !r.deleted_at) &&
        (!this.farmId || r.finca_id === Number(this.farmId)) &&
        (!this.plotId || r.lote_id === Number(this.plotId)) &&
        (!this.cropId ||
          r.cultivo_id === Number(this.cropId) ||
          (r.tipo === 'cultivos' && r.id === Number(this.cropId))) &&
        (!this.cycleId ||
          r.ciclo_id === Number(this.cycleId) ||
          (r.tipo === 'ciclos' && r.id === Number(this.cycleId))) &&
        (!this.desde || (r.fecha ?? '') >= this.desde) &&
        (!this.hasta || (r.fecha ?? '') <= this.hasta) &&
        JSON.stringify([r.nombre, r.datos])
          .toLocaleLowerCase()
          .includes(this.search.toLocaleLowerCase()),
    );
  }
  visibleRows() {
    return this.rows().slice((this.page - 1) * 15, this.page * 15);
  }
  pages() {
    return Math.max(1, Math.ceil(this.rows().length / 15));
  }
  async changeSection(section: string) {
    this.section.set(section);
    this.page = 1;
    this.search = '';
    this.error.set('');
    this.area.set(this.areaFor(section));
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: {
        area: this.area(),
        seccion: section,
        nuevo: null,
        finca: this.farmId || null,
        lote: this.plotId || null,
        cultivo: this.cropId || null,
      },
      queryParamsHandling: 'merge',
    });
    if (section === 'resumen') await this.loadReport();
  }
  async filterChanged(kind = '') {
    if (kind === 'farm') {
      this.plotId = 0;
      this.cropId = 0;
      this.cycleId = 0;
    }
    if (kind === 'plot') {
      this.cropId = 0;
      this.cycleId = 0;
    }
    if (kind === 'crop') {
      this.cycleId = 0;
      const crop = this.data()?.records.find(
        (r) => r.tipo === 'cultivos' && r.id === Number(this.cropId),
      );
      if (crop) {
        this.farmId = crop.finca_id ?? this.farmId;
        this.plotId = crop.lote_id ?? 0;
      } else this.plotId = 0;
    }
    this.page = 1;
    this.context.save(this.farmId, this.plotId);
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: {
        finca: this.farmId || null,
        lote: this.plotId || null,
        cultivo: this.cropId || null,
      },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
    await this.loadReport();
  }
  params() {
    let p = new HttpParams();
    for (const [key, val] of Object.entries({
      finca_id: this.farmId,
      lote_id: this.plotId,
      cultivo_id: this.cropId,
      ciclo_id: this.cycleId,
      desde: this.desde,
      hasta: this.hasta,
      archivados: this.showArchived ? 1 : 0,
    })) {
      if (val) p = p.set(key, String(val));
    }
    return p;
  }
  async reload() {
    this.busy.set(true);
    try {
      this.data.set(
        (await firstValueFrom(this.http.get<Api<Bootstrap>>(`${this.base}/produccion/inicio`)))
          .data,
      );
      if (!this.farmId && this.data()?.fincas.length === 1) this.farmId = this.data()!.fincas[0].id;
      if (this.farmId && !this.data()?.fincas.some((f) => f.id === this.farmId)) {
        this.farmId = 0;
        this.plotId = 0;
      }
      if (
        this.plotId &&
        !this.data()?.lotes.some(
          (l) => l.id === this.plotId && (!this.farmId || l.finca_id === this.farmId),
        )
      )
        this.plotId = 0;
      if (!this.areaDefinition().sections.includes(this.section()) && this.section() !== 'repartos')
        this.area.set(this.areaFor(this.section()));
      this.openLinkedForm();
      await this.loadReport();
      const uuid = this.route.snapshot.queryParamMap.get('uuid');
      if (uuid && !this.detail()) {
        const record = this.data()?.records.find((r) => r.uuid === uuid);
        const farm = this.data()?.fincas.find((r) => r.uuid === uuid);
        const plot = this.data()?.lotes.find((r) => r.uuid === uuid);
        if (record) {
          this.section.set(record.tipo!);
          await this.history('registro', record);
        } else if (farm || plot) await this.history(farm ? 'finca' : 'lote', (farm ?? plot)!);
        else this.error.set('El código no corresponde a un registro accesible con tu cuenta.');
      }
    } catch (e) {
      this.fail(e);
    } finally {
      this.busy.set(false);
    }
  }
  private reportRequest = 0;
  async loadReport() {
    const request = ++this.reportRequest;
    this.report.set(null);
    try {
      const result = (
        await firstValueFrom(
          this.http.get<Api<Record<string, any>>>(`${this.base}/produccion/reporte`, {
            params: this.params(),
          }),
        )
      ).data;
      if (request === this.reportRequest) this.report.set(result);
    } catch (e) {
      if (request === this.reportRequest) this.fail(e);
    }
  }
  visible(field: Field) {
    if (
      this.section() === 'sanidad_tratamientos' &&
      [
        'producto',
        'ingrediente_activo',
        'registro_producto',
        'lote_insumo',
        'dosis',
        'unidad_dosis',
        'cantidad_aplicada',
        'unidad_aplicada',
        'area_tratada',
        'volumen_mezcla',
        'carencia_dias',
        'reingreso_horas',
        'equipo',
        'condiciones_clima',
      ].includes(field.key) &&
      this.model['estado'] !== 'aplicado'
    )
      return false;
    if (field.key === 'ciclo_id' && !this.model['cultivo_id']) return false;
    if (
      this.section() === 'costos' &&
      !this.detailedCosts &&
      ['cantidad', 'unidad', 'precio_unitario'].includes(field.key)
    )
      return false;
    if (
      this.section() === 'ventas' &&
      this.paymentMode !== 'parcial' &&
      ['cobrado', 'fecha_cobro'].includes(field.key)
    )
      return false;
    if (this.section() === 'labores' && field.key === 'fecha_real') return false;
    if (field.key === 'ciclo_id' && this.model['ciclo_id'] && this.relations(field).length === 1)
      return false;
    if (!field.show) return true;
    if (field.show === 'protegido')
      return ['invernadero', 'casa_malla', 'vivero', 'semillero'].includes(this.model['ambiente']);
    const [key, value] = field.show.split('=');
    return this.model[key] === value;
  }
  options(field: Field): string[] {
    return Array.isArray(field.options) ? field.options : [];
  }
  emptyRelationLabel(f: Field) {
    return f.required
      ? 'Elige una opción'
      : f.key === 'lote_id'
        ? 'Toda la finca'
        : f.key === 'cultivo_id'
          ? 'Sin asociar a un cultivo'
          : f.key === 'ciclo_id'
            ? 'Sin asignar a una siembra'
            : 'Sin asociar';
  }
  catalog(field: Field): string[] {
    const name = String(field.options);
    let defaults = this.data()?.defaults[name] ?? [];
    const parent =
      name === 'variedades'
        ? this.model['especie']
        : name === 'municipios'
          ? this.model['departamento']
          : name === 'veredas'
            ? this.model['municipio']
            : null;
    if (!Array.isArray(defaults)) defaults = defaults[parent] ?? [];
    const custom =
      this.data()
        ?.records.filter(
          (r) =>
            r.tipo === 'catalogos' &&
            r.finca_id === Number(this.model['finca_id'] ?? this.farmId) &&
            r.datos?.['catalogo'] === name &&
            (!parent || !r.datos?.['padre'] || r.datos['padre'] === parent),
        )
        .map((r) => r.nombre) ?? [];
    return [...new Set<string>([...defaults, ...custom])];
  }
  relations(field: Field): Row[] {
    const target = String(field.options);
    const source =
      target === 'lotes'
        ? this.data()?.lotes
        : target === 'actividades'
          ? this.data()?.actividades
          : this.data()?.records.filter((r) => r.tipo === target);
    return (source ?? []).filter((r) => {
      if (r.deleted_at) return false;
      const farm =
        target === 'actividades'
          ? this.data()?.lotes.find((p) => p.id === r.lote_id)?.finca_id
          : r.finca_id;
      if (farm !== Number(this.model['finca_id'])) return false;
      if (
        target !== 'lotes' &&
        this.model['lote_id'] &&
        r.lote_id &&
        r.lote_id !== Number(this.model['lote_id'])
      )
        return false;
      if (
        target !== 'cultivos' &&
        this.model['cultivo_id'] &&
        r.cultivo_id &&
        r.cultivo_id !== Number(this.model['cultivo_id'])
      )
        return false;
      if (
        target !== 'ciclos' &&
        this.model['ciclo_id'] &&
        r.ciclo_id &&
        r.ciclo_id !== Number(this.model['ciclo_id'])
      )
        return false;
      if (
        target === 'sanidad_tratamientos' &&
        this.model['caso_id'] &&
        Number(r.datos?.['caso_id']) !== Number(this.model['caso_id'])
      )
        return false;
      if (target === 'variedades') return true;
      if (target === 'costos' && (r.cultivo_id || r.ciclo_id || r.actividad_id)) return false;
      return true;
    });
  }
  modelChanged(key: string) {
    if (
      key === 'estado' &&
      !this.editing &&
      this.section() === 'sanidad_tratamientos' &&
      this.model['estado'] !== 'aplicado'
    ) {
      for (const key of [
        'producto',
        'ingrediente_activo',
        'registro_producto',
        'lote_insumo',
        'dosis',
        'unidad_dosis',
        'cantidad_aplicada',
        'unidad_aplicada',
        'area_tratada',
        'volumen_mezcla',
        'carencia_dias',
        'reingreso_horas',
        'equipo',
        'condiciones_clima',
      ])
        this.model[key] = null;
    }
    if (key === 'caso_id') {
      const parent = this.data()?.records.find(
        (r) => r.tipo === 'sanidad_casos' && r.id === Number(this.model['caso_id']),
      );
      if (parent) {
        for (const context of ['finca_id', 'lote_id', 'cultivo_id', 'ciclo_id'] as const)
          this.model[context] = parent[context] ?? null;
        this.model['tratamiento_id'] = null;
      }
    }
    if (
      ['finca_id', 'lote_id', 'cultivo_id', 'ciclo_id'].includes(key) &&
      this.section().startsWith('sanidad_')
    ) {
      this.model['caso_id'] = null;
      this.model['tratamiento_id'] = null;
    }
    if (key === 'categoria' && !this.editing)
      this.model['naturaleza'] = ['infraestructura', 'equipos'].includes(this.model['categoria'])
        ? 'inversion'
        : 'produccion';
    if (key === 'finca_id')
      for (const k of [
        'lote_id',
        'cultivo_id',
        'ciclo_id',
        'cosecha_id',
        'actividad_id',
        'costo_id',
        'venta_id',
      ])
        this.model[k] = null;
    if (key === 'lote_id')
      for (const k of ['cultivo_id', 'ciclo_id', 'actividad_id']) this.model[k] = null;
    if (key === 'cultivo_id') {
      this.model['ciclo_id'] = null;
      this.model['actividad_id'] = null;
    }
    if (key === 'especie') this.model['variedad'] = '';
    if (key === 'departamento') {
      this.model['municipio'] = '';
      this.model['vereda'] = '';
    }
    if (key === 'municipio') this.model['vereda'] = '';
    this.syncDefaults(key);
  }
  private syncDefaults(changed = '') {
    for (const key of ['lote_id', 'cultivo_id', 'ciclo_id']) {
      if (key === changed || this.model[key]) continue;
      const field = this.fields().find((f) => f.key === key);
      if (!field || (key === 'ciclo_id' && !this.model['cultivo_id'])) continue;
      if (!field.required && this.section() === 'costos') continue;
      const options = this.relations(field).filter(
        (r) => key !== 'ciclo_id' || r.estado !== 'cerrado',
      );
      if (options.length === 1) this.model[key] = options[0].id;
    }
    const plot = this.data()?.lotes.find((p) => p.id === Number(this.model['lote_id']));
    const crop = this.data()?.records.find((p) => p.id === Number(this.model['cultivo_id']));
    if (!this.editing) {
      if (this.section() === 'cultivos' && (changed === 'lote_id' || !this.model['area']))
        this.model['area'] = Number(plot?.hectareas ?? 0) || null;
      if (this.section() === 'ciclos' && crop) {
        this.model['area'] ??= Number(crop.datos?.['area']) || null;
        this.model['numero_plantas'] ??= crop.datos?.['numero_plantas'] ?? null;
      }
    }
  }
  open(row: Row | null = null) {
    this.formStep = 0;
    this.showOptional = false;
    this.editing = row;
    this.errors.set({});
    this.error.set('');
    this.model = row
      ? {
          ...row.datos,
          finca_id: row.finca_id,
          lote_id: row.lote_id,
          cultivo_id: row.cultivo_id,
          ciclo_id: row.ciclo_id,
          cosecha_id: row.cosecha_id,
          costo_id: row.costo_id,
          venta_id: row.venta_id,
          actividad_id: row.actividad_id,
          nombre: row.nombre,
          fecha: row.fecha,
          estado: row.estado,
          version: row.version,
        }
      : {
          finca_id: this.farmId || null,
          lote_id: this.plotId || null,
          cultivo_id: this.cropId || null,
          ciclo_id: this.cycleId || null,
          nombre: '',
          fecha: this.today,
          estado: this.states()[0],
          cobrado: 0,
          perdida: 0,
          presupuesto: 0,
          costo_real: 0,
          sistema: 'monocultivo',
          responsable: this.auth.currentUser()?.name ?? '',
          unidad: 'kg',
          calidad: 'Primera',
          naturaleza: 'produccion',
          cantidad: this.section() === 'costos' ? 1 : null,
        };
    if (!row && this.section() === 'labores') {
      this.model['estado'] = 'terminado';
      this.model['fecha_real'] = this.today;
    }
    if (row && this.section() === 'labores' && row.estado === 'terminado')
      this.model['fecha'] = row.datos?.['fecha_real'] ?? row.fecha;
    this.detailedCosts =
      !!row && (Number(row.datos?.['cantidad']) !== 1 || row.datos?.['unidad'] !== 'unidad');
    this.quickTotal = row ? Number(row.total) : null;
    this.paymentMode = row
      ? Number(row.datos?.['cobrado']) === Number(row.total)
        ? 'completo'
        : Number(row.datos?.['cobrado']) > 0
          ? 'parcial'
          : 'pendiente'
      : 'completo';
    this.syncDefaults();
    this.modal.set(true);
  }
  openParameters(type: string, row: Row) {
    this.formStep = 0;
    this.showOptional = false;
    this.parameterType = type;
    this.parameterId = row.id;
    this.editing = row;
    this.model = { ...row.parametros, finca_id: type === 'finca' ? row.id : row.finca_id };
    this.vertices = [];
    if (type === 'lote' && this.model['poligono']) {
      try {
        const ring = JSON.parse(this.model['poligono']).coordinates[0] as number[][];
        this.vertices = ring.slice(0, -1).map((p) => ({ longitud: p[0], latitud: p[1] }));
      } catch {
        this.vertices = [];
      }
    }
    if (type === 'finca') {
      this.model['documento_productor'] ??= row.productor?.documento;
      this.model['contacto'] ??= row.productor?.contacto;
      this.model['tipo_suelo'] = row.tipo_suelo ?? this.model['tipo_suelo'];
    }
    this.errors.set({});
    this.error.set('');
    this.modal.set(true);
  }
  updatePolygon() {
    const valid = this.vertices.filter((v) => v.latitud !== null && v.longitud !== null);
    if (!this.vertices.length) {
      this.model['poligono'] = null;
      return;
    }
    if (valid.length !== this.vertices.length || valid.length < 3) {
      this.model['poligono'] = null;
      return;
    }
    const ring = valid.map((v) => [Number(v.longitud), Number(v.latitud)]);
    this.model['poligono'] = JSON.stringify({ type: 'Polygon', coordinates: [[...ring, ring[0]]] });
  }
  addVertex() {
    this.vertices.push({ latitud: null, longitud: null });
    this.updatePolygon();
  }
  removeVertex(index: number) {
    this.vertices.splice(index, 1);
    this.updatePolygon();
  }
  contourPreview(): string {
    const points = this.vertices.filter((v) => v.latitud !== null && v.longitud !== null);
    if (!points.length) return '';
    const xs = points.map((p) => Number(p.longitud));
    const ys = points.map((p) => Number(p.latitud));
    const xmin = Math.min(...xs);
    const xmax = Math.max(...xs);
    const ymin = Math.min(...ys);
    const ymax = Math.max(...ys);
    return points
      .map(
        (p) =>
          `${10 + ((Number(p.longitud) - xmin) / (xmax - xmin || 1)) * 180},${110 - ((Number(p.latitud) - ymin) / (ymax - ymin || 1)) * 100}`,
      )
      .join(' ');
  }
  async save() {
    if (
      this.section() === 'parametros' &&
      this.parameterType === 'lote' &&
      this.vertices.length &&
      (!this.model['poligono'] ||
        this.vertices.some((v) => v.latitud === null || v.longitud === null))
    ) {
      this.error.set(
        'Completa al menos tres vértices del contorno o elimina los puntos incompletos.',
      );
      return;
    }
    this.busy.set(true);
    this.error.set('');
    this.errors.set({});
    try {
      if (this.section() === 'costos' && !this.detailedCosts) {
        if (this.quickTotal === null || this.quickTotal < 0) {
          this.error.set('Escribe el valor del gasto.');
          this.busy.set(false);
          return;
        }
        Object.assign(this.model, {
          cantidad: 1,
          unidad: 'unidad',
          precio_unitario: Number(this.quickTotal),
        });
      }
      if (this.section() === 'labores' && this.model['estado'] === 'terminado') {
        this.model['fecha_real'] = this.model['fecha'];
        this.model['fecha'] = this.editing?.fecha ?? this.model['fecha'];
      }
      if (this.section() === 'ventas') {
        const total =
          Number(this.model['cantidad'] ?? 0) * Number(this.model['precio_unitario'] ?? 0);
        if (this.paymentMode !== 'parcial')
          this.model['cobrado'] =
            this.paymentMode === 'completo' ? Math.round(total * 100) / 100 : 0;
        this.model['fecha_cobro'] =
          this.model['cobrado'] > 0 ? this.model['fecha_cobro'] || this.model['fecha'] : null;
      }
      if (this.section() === 'cosechas' && !this.model['lote_producto'])
        this.model['lote_producto'] =
          `CO-${this.model['fecha'].replaceAll('-', '')}-${crypto.randomUUID().slice(0, 6).toUpperCase()}`;
      if (!this.model['nombre'] && this.section() !== 'parametros') {
        const subject =
          this.model['problema'] ||
          this.model['accion'] ||
          this.model['resultado_observado'] ||
          this.model['especie'] ||
          this.model['recurso'] ||
          this.model['comprador'] ||
          (this.model['tipo_actividad']
            ? this.label(this.model['tipo_actividad'])
            : this.sectionLabel(this.section()));
        this.model['nombre'] = `${subject} · ${this.model['fecha']}`.slice(0, 255);
      }
      const body = Object.fromEntries(
        Object.entries(this.model).map(([key, value]) => [key, value === '' ? null : value]),
      );
      const signature = `${this.section()}:${this.editing?.id ?? this.parameterId}:${JSON.stringify(body)}`;
      if (this.lastWrite?.signature !== signature)
        this.lastWrite = { signature, key: crypto.randomUUID() };
      const headers = { 'Idempotency-Key': this.lastWrite.key };
      if (this.section() === 'parametros') {
        await firstValueFrom(
          this.http.put(
            `${this.base}/produccion/parametros/${this.parameterType}/${this.parameterId}`,
            body,
            { headers },
          ),
        );
      } else {
        const url =
          !this.editing && this.section() === 'cultivos'
            ? `${this.base}/produccion/siembras`
            : `${this.base}/produccion/registros/${this.section()}${this.editing ? '/' + this.editing.id : ''}`;
        await firstValueFrom(
          this.editing
            ? this.http.put(url, body, { headers })
            : this.http.post(url, body, { headers }),
        );
      }
      this.lastWrite = null;
      this.modal.set(false);
      if (
        this.section() !== 'parametros' &&
        (this.farmId !== Number(this.model['finca_id']) ||
          this.plotId !== Number(this.model['lote_id'] || 0))
      ) {
        this.farmId = Number(this.model['finca_id']);
        this.plotId = Number(this.model['lote_id'] || 0);
        this.cropId = 0;
        this.cycleId = 0;
        this.context.save(this.farmId, this.plotId);
        await this.router.navigate([], {
          relativeTo: this.route,
          queryParams: { finca: this.farmId, lote: this.plotId, cultivo: null },
          queryParamsHandling: 'merge',
          replaceUrl: true,
        });
      }
      this.notice.set(
        this.section() === 'cultivos' && this.model['tipo'] === 'transitorio'
          ? 'Cultivo y primera siembra guardados. Ya puedes anotar el trabajo o registrar una cosecha.'
          : 'Guardado correctamente.',
      );
      await this.reload();
    } catch (e) {
      this.fail(e);
      const firstError = Object.keys(this.errors())[0];
      const step = this.steps().findIndex((s) => s.keys.includes(firstError));
      if (step >= 0) this.formStep = step;
      else if (firstError) {
        this.showOptional = true;
        this.formStep = this.steps().length - 1;
      }
    } finally {
      this.busy.set(false);
    }
  }
  async guide(row: Row) {
    let cycle = row.tipo === 'ciclos' ? row.id : this.cycleId || null;
    if (row.tipo === 'cultivos' && row.datos?.['tipo'] === 'transitorio' && !cycle) {
      const cycles =
        this.data()?.records.filter(
          (c) => c.tipo === 'ciclos' && c.cultivo_id === row.id && c.estado === 'activo',
        ) ?? [];
      if (cycles.length === 1) cycle = cycles[0].id;
      else {
        this.error.set(
          'Crea o selecciona un ciclo para generar la guía de este cultivo transitorio.',
        );
        return;
      }
    }
    this.busy.set(true);
    try {
      await firstValueFrom(
        this.http.post(
          `${this.base}/produccion/guia`,
          { cultivo_id: row.tipo === 'cultivos' ? row.id : row.cultivo_id, ciclo_id: cycle },
          { headers: { 'Idempotency-Key': crypto.randomUUID() } },
        ),
      );
      await this.changeSection('labores');
      this.notice.set('Guía inicial creada. Ajusta responsables, fechas y presupuestos.');
      await this.reload();
    } catch (e) {
      this.fail(e);
    } finally {
      this.busy.set(false);
    }
  }
  async history(type: string, row: Row) {
    this.busy.set(true);
    this.detailType = type;
    this.detailId = row.id;
    this.archiveReason = '';
    this.note = '';
    this.file = null;
    this.gps = null;
    try {
      this.detail.set(
        (
          await firstValueFrom(
            this.http.get<Api<Record<string, any>>>(
              `${this.base}/produccion/historial/${type}/${row.id}`,
            ),
          )
        ).data,
      );
      this.evidence.set(
        (
          await firstValueFrom(
            this.http.get<Api<{ data: any[] }>>(`${this.base}/campo/evidencia/${type}/${row.id}`),
          )
        ).data.data,
      );
    } catch (e) {
      this.fail(e);
    } finally {
      this.busy.set(false);
    }
  }
  hasCostBreakdown(): boolean {
    return (
      !!this.editing?.actividad_id &&
      !!this.data()?.records.some(
        (r) =>
          r.tipo === 'costos' && !r.deleted_at && r.actividad_id === this.editing?.actividad_id,
      )
    );
  }
  historyChanges(entry: any): { label: string; before: string; after: string }[] {
    const flatten = (raw: any): Record<string, any> => {
      let data: Record<string, any>;
      try {
        data = typeof raw === 'string' ? JSON.parse(raw) : (raw ?? {});
      } catch {
        return {};
      }
      for (const key of ['datos', 'parametros']) {
        let nested = data[key];
        try {
          if (typeof nested === 'string') nested = JSON.parse(nested);
        } catch {
          nested = {};
        }
        delete data[key];
        Object.assign(data, nested ?? {});
      }
      return data;
    };
    const before = flatten(entry.before);
    const after = flatten(entry.after);
    const labels: Record<string, string> = {
      nombre: 'Nombre',
      fecha: 'Fecha',
      estado: 'Estado',
      total: 'Valor total',
      version: 'Versión',
      uuid: 'Código único',
      registrado_por: 'Registrado por',
      dispositivo: 'Dispositivo',
      observaciones: 'Observaciones',
      finca_id: 'Finca',
      user_id: 'Titular',
      latitud: 'Latitud',
      longitud: 'Longitud',
      hectareas: 'Hectáreas',
      hectareas_totales: 'Superficie total',
      deleted_at: 'Archivado el',
    };
    for (const field of this.detailFields()) labels[field.key] = field.label;
    const format = (value: any) =>
      value === null || value === undefined
        ? '—'
        : typeof value === 'boolean'
          ? value
            ? 'Sí'
            : 'No'
          : this.label(typeof value === 'object' ? JSON.stringify(value) : value);
    return [...new Set([...Object.keys(before), ...Object.keys(after)])]
      .filter((key) => JSON.stringify(before[key]) !== JSON.stringify(after[key]))
      .map((key) => ({
        label: labels[key] ?? this.label(key),
        before: format(before[key]),
        after: format(after[key]),
      }));
  }
  detailFields(): Field[] {
    const entity = this.detail()?.['entity'] as Row | undefined;
    return this.detailType === 'registro'
      ? (this.data()?.schemas[entity?.tipo ?? '']?.fields ?? [])
      : (this.data()?.parameters[this.detailType] ?? []);
  }
  detailValue(field: Field): string {
    const row = this.detail()?.['entity'] as Row;
    const value = [
      'lote_id',
      'cultivo_id',
      'ciclo_id',
      'cosecha_id',
      'costo_id',
      'venta_id',
      'actividad_id',
    ].includes(field.key)
      ? (row as any)[field.key]
      : (row.datos ?? row.parametros)?.[field.key];
    if (value === null || value === undefined || value === '') return 'Sin registrar';
    if (field.type === 'boolean') return value ? 'Sí' : 'No';
    if (field.type === 'money') return this.currency(value);
    if (field.type === 'geojson') {
      try {
        return `${JSON.parse(value).coordinates[0].length - 1} vértices registrados`;
      } catch {
        return 'Contorno registrado';
      }
    }
    if (field.type === 'relation') {
      const source =
        field.options === 'lotes'
          ? this.data()?.lotes
          : field.options === 'actividades'
            ? this.data()?.actividades
            : this.data()?.records;
      const related = source?.find((r) => r.id === Number(value));
      return related?.nombre || related?.tipo_actividad || `Registro ${value}`;
    }
    return this.label(value);
  }
  async archive() {
    const row = this.detail()?.['entity'] as Row | undefined;
    if (!row || !this.archiveReason.trim()) return;
    this.busy.set(true);
    try {
      await firstValueFrom(
        this.http.delete(`${this.base}/produccion/registros/${row.id}`, {
          body: { motivo: this.archiveReason, version: row.version },
        }),
      );
      this.detail.set(null);
      this.notice.set('Registro archivado. Su historial permanece conservado.');
      await this.reload();
    } catch (e) {
      this.fail(e);
    } finally {
      this.busy.set(false);
    }
  }
  chooseFile(event: Event) {
    this.file = (event.target as HTMLInputElement).files?.[0] ?? null;
  }
  captureGps() {
    if (!navigator.geolocation) {
      this.error.set('Este dispositivo no permite obtener la ubicación.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (p) => {
        this.gps = {
          latitud: p.coords.latitude,
          longitud: p.coords.longitude,
          gps_precision: p.coords.accuracy,
        };
      },
      () => this.error.set('No se pudo obtener la ubicación. Revisa el permiso del navegador.'),
      { enableHighAccuracy: true, timeout: 15000 },
    );
  }
  async attach() {
    if (!this.note.trim() && !this.file) return;
    this.busy.set(true);
    try {
      const form = new FormData();
      form.set('modelo_tipo', this.detailType);
      form.set('modelo_id', String(this.detailId));
      form.set('texto', this.note);
      form.set('categoria', 'seguimiento');
      form.set('captured_at', new Date().toISOString());
      if (this.gps)
        for (const [key, value] of Object.entries(this.gps)) form.set(key, String(value));
      if (this.file) form.set('archivo', this.file);
      await firstValueFrom(
        this.http.post(
          `${this.base}/${this.file ? 'campo/multimedia/archivo' : 'multimedia/subir'}`,
          form,
          { headers: { 'Idempotency-Key': crypto.randomUUID() } },
        ),
      );
      await this.history(this.detailType, this.detail()!['entity']);
      this.notice.set('Evidencia guardada.');
    } catch (e) {
      this.fail(e);
    } finally {
      this.busy.set(false);
    }
  }
  async download(id: number) {
    try {
      this.saveBlob(
        await firstValueFrom(
          this.http.get(`${this.base}/campo/archivos/${id}/descargar`, { responseType: 'blob' }),
        ),
        `evidencia-${id}`,
      );
    } catch (e) {
      this.fail(e);
    }
  }
  async export() {
    const type = this.section() === 'resumen' ? '' : '/' + this.section();
    try {
      this.saveBlob(
        await firstValueFrom(
          this.http.get(`${this.base}/produccion/exportar${type}`, {
            params: this.params(),
            responseType: 'blob',
          }),
        ),
        `aggrio-${this.section()}.csv`,
      );
    } catch (e) {
      this.fail(e);
    }
  }
  saveBlob(blob: Blob, name: string) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = name;
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }
  fail(e: any) {
    this.error.set(e?.error?.message ?? 'No se pudo completar la operación. Intenta de nuevo.');
    this.errors.set(e?.error?.errors ?? {});
  }
}
