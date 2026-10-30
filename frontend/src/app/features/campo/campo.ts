import { CommonModule } from '@angular/common';
import { Component, ElementRef, OnDestroy, OnInit, ViewChild, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink, ActivatedRoute, Router } from '@angular/router';
import { Title } from '@angular/platform-browser';
import { Observable } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { AppSidebar } from '../../shared/app-sidebar';
import { FarmContext } from '../../shared/farm-context';
import {
  Activity,
  CampoApi,
  Evidence,
  Farm,
  Page,
  Plot,
  Reading,
  Summary,
  ProducerProfile,
} from './campo-api';

@Component({
  selector: 'app-campo',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, AppSidebar],
  templateUrl: './campo.html',
  styleUrl: './campo.scss',
})
export class Campo implements OnInit, OnDestroy {
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private context = inject(FarmContext);
  @ViewChild('evidenceFile') private evidenceInput?: ElementRef<HTMLInputElement>;
  constructor() {
    inject(Title).setTitle('Aggrio | Mi campo');
  }
  readonly auth = inject(AuthService);
  private api = inject(CampoApi);
  readonly tabs = [
    ['resumen', 'Inicio'],
    ['fincas', 'Mi finca'],
    ['lotes', 'Mis lotes'],
    ['iot', 'Mediciones del lote'],
    ['evidencia', 'Fotos y notas'],
    ['perfil', 'Mi cuenta'],
  ];
  readonly tab = signal('resumen');
  readonly farms = signal<Farm[]>([]);
  readonly profile = signal<ProducerProfile | null>(null);
  readonly plots = signal<Plot[]>([]);
  readonly activities = signal<Page<Activity> | null>(null);
  readonly readings = signal<Page<Reading> | null>(null);
  readonly evidence = signal<Page<Evidence> | null>(null);
  readonly summary = signal<Summary | null>(null);
  readonly busy = signal(false);
  readonly loading = signal(false);
  readonly error = signal('');
  readonly notice = signal('');
  readonly preview = signal('');
  readonly previewType = signal('');
  farmId = 0;
  plotId = 0;
  showArchived = false;
  farmEditorOpen = false;
  plotEditorOpen = false;
  requestFarmOpen = false;
  farmSetupStep = 0;
  newFarm = '';
  profileName = this.auth.currentUser()?.name ?? '';
  farmForm = {
    nombre: '',
    latitud: null as number | null,
    longitud: null as number | null,
    hectareas_totales: null as number | null,
    tipo_suelo: '',
  };
  plotForm = {
    nombre: '',
    hectareas: null as number | null,
    tipo_cultivo: 'Sin cultivo',
    variedad: '',
    fecha_siembra: '',
    estado: 'disponible',
  };
  editPlotId = 0;
  activityForm = {
    tipo_actividad: 'riego',
    fecha: new Date().toLocaleDateString('en-CA', { timeZone: 'America/Bogota' }),
    costo: 0,
    observaciones: '',
  };
  readingForm = {
    tipo_medicion: 'temperatura',
    valor: null as number | null,
    unidad: '°C',
    fecha_medicion: '',
    motivo: '',
  };
  mediaForm = {
    modelo_tipo: 'lote',
    modelo_id: 0,
    categoria: 'seguimiento',
    texto: '',
    latitud: null as number | null,
    longitud: null as number | null,
    gps_precision: null as number | null,
  };
  filters = { desde: '', hasta: '', tipo_medicion: '' };
  file: File | null = null;
  readonly activityTypes = [
    'preparacion',
    'siembra',
    'fertilizacion',
    'riego',
    'control_plagas',
    'poda',
    'cosecha',
    'mantenimiento',
  ];
  readonly measurementTypes = [
    'temperatura',
    'humedad_suelo',
    'humedad_ambiente',
    'radiacion_solar',
  ];
  private draftKey = `aggrio:draft:${this.auth.currentUser()?.id}:campo`;
  profilePhone = this.auth.currentUser()?.phone ?? '';
  private operations: Record<string, { hash: string; key: string }> = {};
  private lotEpoch = 0;
  private detailEpoch = 0;
  private mediaEpoch = 0;
  private restoredPlotId = 0;
  private restoredContext = '';
  private activeContext = '';

  ngOnInit() {
    const section = this.route.snapshot.queryParamMap.get('seccion');
    if (section && this.tabs.some((t) => t[0] === section)) this.tab.set(section);
    if (section === 'actividades') this.goProduction('labores');
    try {
      const d = JSON.parse(sessionStorage.getItem(this.draftKey) ?? '{}');
      if (d.activity) Object.assign(this.activityForm, d.activity);
      if (d.media) Object.assign(this.mediaForm, d.media);
      this.operations = d.operations ?? {};
      this.farmId = Number(d.farmId) || 0;
      this.restoredPlotId = Number(d.plotId) || 0;
      this.restoredContext = `${this.farmId}/${this.restoredPlotId}`;
    } catch {
      /* A damaged draft must not prevent access. */
    }
    const context = this.context.read();
    this.farmId =
      Number(this.route.snapshot.queryParamMap.get('finca')) || context.farmId || this.farmId;
    this.restoredPlotId =
      Number(this.route.snapshot.queryParamMap.get('lote')) ||
      context.plotId ||
      this.restoredPlotId;
    this.loadFarms();
    this.loadSummary();
    this.api.get<ProducerProfile>('/campo/perfil').subscribe({
      next: (p) => {
        this.profile.set(p);
        this.profileName = p.name;
        this.profilePhone = p.phone ?? '';
      },
      error: (e) => this.fail(e),
    });
  }
  ngOnDestroy() {
    this.closePreview();
  }
  label(value: string) {
    return (
      (
        {
          aprobado: 'Aprobada',
          pendiente: 'Pendiente de revisión',
          rechazado: 'Rechazada',
          en_uso: 'En uso',
          no_disponible: 'No disponible',
          disponible: 'Disponible',
        } as Record<string, string>
      )[value] ?? value.replaceAll('_', ' ')
    );
  }
  chooseFarm(f: Farm) {
    this.farmId = f.id;
    this.changeFarm();
    this.selectTab('fincas');
  }
  title() {
    return this.tabs.find((t) => t[0] === this.tab())?.[1] ?? 'Mi campo';
  }
  max<T>(rows: T[], field: keyof T) {
    return Math.max(1, ...rows.map((r) => Number(r[field])));
  }
  sensorMax(rows: Summary['sensores_siete_dias'], type: string, unit: string) {
    return Math.max(
      1,
      ...rows
        .filter((r) => r.tipo_medicion === type && r.unidad === unit)
        .map((r) => Math.abs(Number(r.promedio))),
    );
  }
  sensorMin(rows: Summary['sensores_siete_dias'], type: string, unit: string) {
    return Math.min(
      0,
      ...rows
        .filter((r) => r.tipo_medicion === type && r.unidad === unit)
        .map((r) => Number(r.promedio)),
    );
  }
  recent(when: string) {
    const delta = Date.now() - new Date(when).getTime();
    return delta >= 0 && delta <= 45 * 60000;
  }
  recentCount(rows: Summary['estado_sensores']) {
    return rows.filter((r) => this.recent(r.ultima_lectura)).length;
  }
  farm() {
    return this.farms().find((f) => f.id === Number(this.farmId));
  }
  plot() {
    return this.plots().find((l) => l.id === Number(this.plotId));
  }
  operational() {
    const f = this.farm();
    return f?.estado === 'aprobado' && f.perfil_completo;
  }
  availablePlot() {
    const l = this.plot();
    return this.operational() && !!l && !l.deleted_at && l.estado !== 'no_disponible';
  }
  selectTab(value: string) {
    if (value === 'actividades') {
      this.goProduction('labores');
      return;
    }
    this.tab.set(value);
    void this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { seccion: value },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
    this.error.set('');
    this.notice.set('');
  }
  goProduction(section: string, create = false) {
    const area = ['cultivos', 'ciclos'].includes(section)
      ? 'cultivos'
      : ['labores', 'cosechas', 'transportes'].includes(section)
        ? 'trabajo'
        : ['parametros', 'analisis', 'certificaciones', 'catalogos'].includes(section)
          ? 'detalles'
          : 'dinero';
    void this.router.navigate(['/produccion'], {
      queryParams: {
        area,
        seccion: section,
        finca: this.farmId || null,
        lote: this.plotId || null,
        nuevo: create ? 1 : null,
      },
    });
  }
  activePlots() {
    return this.plots().filter((p) => !p.deleted_at);
  }
  contextForPlot(id: number) {
    this.plotId = id;
    this.context.save(this.farmId, id);
  }
  startPlot() {
    this.resetPlot();
    this.plotEditorOpen = true;
    this.selectTab('fincas');
  }
  captureFarmLocation() {
    if (!navigator.geolocation) {
      this.error.set(
        'Este navegador no permite obtener ubicación. Puedes escribir las coordenadas.',
      );
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (p) => {
        this.farmForm.latitud = p.coords.latitude;
        this.farmForm.longitud = p.coords.longitude;
        this.notice.set('Ubicación añadida. Revisa que corresponda a la entrada de tu finca.');
      },
      () =>
        this.error.set(
          'No se pudo obtener ubicación. Revisa el permiso del navegador o escribe las coordenadas.',
        ),
      { enableHighAccuracy: true, timeout: 15000 },
    );
  }
  loadFarms() {
    this.loading.set(true);
    this.api.get<Farm[]>('/campo/fincas').subscribe({
      next: (rows) => {
        this.farms.set(rows);
        this.loading.set(false);
        if (!rows.some((f) => f.id === Number(this.farmId)))
          this.farmId = rows.find((f) => f.estado === 'aprobado')?.id ?? rows[0]?.id ?? 0;
        this.changeFarm();
      },
      error: (e) => {
        this.loading.set(false);
        this.fail(e);
      },
    });
  }
  changeFarm() {
    this.context.save(this.farmId, 0);
    const epoch = ++this.lotEpoch;
    const previousPlot = this.plotId || this.restoredPlotId;
    this.plots.set([]);
    this.plotId = 0;
    this.clearDetails();
    this.editPlotId = 0;
    const f = this.farm();
    if (!f) return;
    this.farmForm = {
      nombre: f.nombre,
      latitud: f.latitud,
      longitud: f.longitud,
      hectareas_totales: f.hectareas_totales,
      tipo_suelo: f.tipo_suelo ?? '',
    };
    this.api
      .get<Plot[]>(`/campo/fincas/${f.id}/lotes?archivados=${this.showArchived ? 1 : 0}`)
      .subscribe({
        next: (rows) => {
          if (epoch !== this.lotEpoch) return;
          this.plots.set(rows);
          this.plotId =
            rows.find((l) => l.id === Number(previousPlot))?.id ??
            rows.find((l) => !l.deleted_at)?.id ??
            rows[0]?.id ??
            0;
          this.changePlot();
        },
        error: (e) => {
          if (epoch === this.lotEpoch) this.fail(e);
        },
      });
  }
  changePlot() {
    this.context.save(this.farmId, this.plotId);
    const context = `${this.farmId}/${this.plotId}`;
    if (context !== this.activeContext && context !== this.restoredContext) {
      this.activityForm.observaciones = '';
      this.mediaForm.texto = '';
      this.mediaForm.latitud = null;
      this.mediaForm.longitud = null;
      this.mediaForm.gps_precision = null;
      this.file = null;
      if (this.evidenceInput) this.evidenceInput.nativeElement.value = '';
    }
    this.activeContext = context;
    this.restoredContext = '';
    this.restoredPlotId = 0;
    this.clearDetails();
    this.mediaForm.modelo_tipo = 'lote';
    this.mediaForm.modelo_id = Number(this.plotId);
    this.loadDetails();
  }
  private clearDetails() {
    ++this.detailEpoch;
    ++this.mediaEpoch;
    this.activities.set(null);
    this.readings.set(null);
    this.evidence.set(null);
    this.closePreview();
  }
  loadDetails(activityPage = 1, readingPage = 1) {
    if (!this.plotId) return;
    const id = this.plotId,
      epoch = ++this.detailEpoch;
    this.api.get<Page<Activity>>(`/campo/lotes/${id}/actividades?page=${activityPage}`).subscribe({
      next: (r) => {
        if (epoch === this.detailEpoch) this.activities.set(r);
      },
      error: (e) => this.fail(e),
    });
    const query = new URLSearchParams({ page: String(readingPage) });
    for (const [k, v] of Object.entries(this.filters)) if (v) query.set(k, v);
    this.api.get<Page<Reading>>(`/campo/lotes/${id}/lecturas?${query}`).subscribe({
      next: (r) => {
        if (epoch === this.detailEpoch) this.readings.set(r);
      },
      error: (e) => this.fail(e),
    });
    this.loadEvidence();
  }
  loadEvidence(page = 1) {
    if (!this.mediaForm.modelo_id) {
      this.evidence.set(null);
      return;
    }
    const epoch = ++this.mediaEpoch;
    this.api
      .get<Page<Evidence>>(
        `/campo/evidencia/${this.mediaForm.modelo_tipo}/${this.mediaForm.modelo_id}?page=${page}`,
      )
      .subscribe({
        next: (r) => {
          if (epoch === this.mediaEpoch) this.evidence.set(r);
        },
        error: (e) => this.fail(e),
      });
  }
  loadSummary() {
    this.api
      .get<Summary>('/campo/dashboard')
      .subscribe({ next: (r) => this.summary.set(r), error: (e) => this.fail(e) });
  }
  private key(scope: string, body: unknown) {
    const hash = JSON.stringify(body);
    if (this.operations[scope]?.hash !== hash)
      this.operations[scope] = { hash, key: crypto.randomUUID() };
    this.saveDraft();
    return this.operations[scope].key;
  }
  private save<T>(
    scope: string,
    body: unknown,
    method: 'POST' | 'PUT' | 'PATCH' | 'DELETE',
    path: string,
    done: (value: T) => void,
  ) {
    if (this.busy()) return;
    this.busy.set(true);
    this.error.set('');
    this.notice.set('');
    this.api.write<T>(method, path, body, this.key(scope, body)).subscribe({
      next: (response) => {
        this.busy.set(false);
        delete this.operations[scope];
        done(response);
        this.saveDraft();
        this.notice.set('Guardado correctamente.');
        this.loadSummary();
      },
      error: (e) => {
        this.busy.set(false);
        this.fail(e);
      },
    });
  }
  requestFarm() {
    this.save('requestFarm', { nombre: this.newFarm }, 'POST', '/fincas/solicitar', () => {
      this.newFarm = '';
      this.requestFarmOpen = false;
      this.loadFarms();
    });
  }
  saveFarm() {
    this.save(
      'farm:' + this.farmId,
      this.farmForm,
      'PUT',
      `/fincas/${this.farmId}/completar`,
      () => {
        this.farmEditorOpen = false;
        this.loadFarms();
      },
    );
  }
  savePlot() {
    const body = { ...this.plotForm, fecha_siembra: this.plotForm.fecha_siembra || null };
    this.save<Plot>(
      'plot:' + this.farmId + ':' + this.editPlotId,
      body,
      this.editPlotId ? 'PUT' : 'POST',
      `/campo/fincas/${this.farmId}/lotes${this.editPlotId ? '/' + this.editPlotId : ''}`,
      (plot) => {
        this.resetPlot();
        this.plotEditorOpen = false;
        this.plotId = plot.id;
        this.changeFarm();
      },
    );
  }
  editPlot(l: Plot) {
    this.plotEditorOpen = true;
    this.editPlotId = l.id;
    this.plotForm = {
      nombre: l.nombre,
      hectareas: l.hectareas,
      tipo_cultivo: l.tipo_cultivo,
      variedad: l.variedad ?? '',
      fecha_siembra: l.fecha_siembra ?? '',
      estado: l.estado,
    };
  }
  resetPlot() {
    this.editPlotId = 0;
    this.plotForm = {
      nombre: '',
      hectareas: null,
      tipo_cultivo: 'Sin cultivo',
      variedad: '',
      fecha_siembra: '',
      estado: 'disponible',
    };
  }
  archivePlot(l: Plot) {
    const motivo = window.prompt(
      `Motivo para archivar «${l.nombre}». Se conserva su historial y su superficie asignada.`,
    );
    if (!motivo?.trim()) return;
    this.save('archive:' + l.id, { motivo }, 'DELETE', `/campo/lotes/${l.id}`, () =>
      this.changeFarm(),
    );
  }
  saveActivity() {
    this.save(
      'activity:' + this.plotId,
      this.activityForm,
      'POST',
      `/campo/lotes/${this.plotId}/actividades`,
      () => {
        this.activityForm.observaciones = '';
        this.loadDetails();
      },
    );
  }
  readingUnit() {
    this.readingForm.unidad =
      this.readingForm.tipo_medicion === 'temperatura'
        ? '°C'
        : this.readingForm.tipo_medicion === 'radiacion_solar'
          ? 'W/m2'
          : '%';
  }
  saveReading() {
    const body = {
      ...this.readingForm,
      fecha_medicion: new Date(this.readingForm.fecha_medicion).toISOString(),
    };
    this.save(
      'reading:' + this.plotId,
      body,
      'POST',
      `/campo/lotes/${this.plotId}/lecturas`,
      () => {
        this.readingForm.valor = null;
        this.readingForm.motivo = '';
        this.loadDetails();
      },
    );
  }
  chooseFile(e: Event) {
    const input = e.target as HTMLInputElement;
    const f = input.files?.[0] ?? null;
    if (f && f.size > (f.type.startsWith('video/') ? 50 : 10) * 1024 * 1024) {
      this.error.set('Máximo 10 MiB por imagen, audio o PDF; 50 MiB por video.');
      input.value = '';
      this.file = null;
      return;
    }
    this.file = f;
  }
  mediaTarget() {
    this.mediaForm.modelo_id =
      this.mediaForm.modelo_tipo === 'finca'
        ? Number(this.farmId)
        : this.mediaForm.modelo_tipo === 'lote'
          ? Number(this.plotId)
          : (this.activities()?.data[0]?.id ?? 0);
    this.loadEvidence();
  }
  saveEvidence() {
    if (this.busy()) return;
    if (!this.file) {
      this.save('media', this.mediaForm, 'POST', '/multimedia/subir', () => {
        this.mediaForm.texto = '';
        this.loadEvidence();
      });
      return;
    }
    const form = new FormData();
    for (const [k, v] of Object.entries(this.mediaForm)) if (v !== null) form.append(k, String(v));
    form.append('archivo', this.file);
    const key = this.key('upload', {
      ...this.mediaForm,
      file: this.file.name,
      size: this.file.size,
      lastModified: this.file.lastModified,
    });
    this.busy.set(true);
    this.error.set('');
    this.api.write('POST', '/campo/multimedia/archivo', form, key).subscribe({
      next: () => {
        this.busy.set(false);
        delete this.operations['upload'];
        this.file = null;
        if (this.evidenceInput) this.evidenceInput.nativeElement.value = '';
        this.mediaForm.texto = '';
        this.saveDraft();
        this.loadEvidence();
        this.notice.set('Evidencia guardada.');
      },
      error: (e) => {
        this.busy.set(false);
        this.fail(e);
      },
    });
  }
  openEvidence(a: Evidence) {
    this.closePreview();
    if (!a.ruta_archivo) return;
    this.api.download(a.id).subscribe({
      next: (blob) => {
        this.closePreview();
        this.previewType.set(blob.type);
        this.preview.set(URL.createObjectURL(blob));
      },
      error: (e) => this.fail(e),
    });
  }
  closePreview() {
    const url = this.preview();
    if (url) URL.revokeObjectURL(url);
    this.preview.set('');
  }
  saveProfile() {
    this.save(
      'profile',
      { name: this.profileName, phone: this.profilePhone || null },
      'PUT',
      '/campo/perfil',
      () => {
        this.auth.updateContact(this.profileName.trim(), this.profilePhone.trim());
        this.profile.update((p) =>
          p ? { ...p, name: this.profileName.trim(), phone: this.profilePhone.trim() } : p,
        );
      },
    );
  }
  saveDraft() {
    try {
      sessionStorage.setItem(
        this.draftKey,
        JSON.stringify({
          farmId: this.farmId,
          plotId: this.plotId,
          activity: this.activityForm,
          media: this.mediaForm,
          operations: this.operations,
        }),
      );
    } catch {
      /*The form remains usable if storage is blocked.*/
    }
  }
  captureLocation() {
    if (!navigator.geolocation) {
      this.notice.set('La ubicación no está disponible. Puedes guardar sin GPS.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (p) => {
        this.mediaForm.latitud = p.coords.latitude;
        this.mediaForm.longitud = p.coords.longitude;
        this.mediaForm.gps_precision = p.coords.accuracy;
        this.saveDraft();
        this.notice.set('Ubicación añadida a esta evidencia.');
      },
      () => this.notice.set('No se obtuvo ubicación. Puedes guardar sin GPS.'),
      { timeout: 10000, maximumAge: 0 },
    );
  }
  private fail(e: unknown) {
    const v = e as {
      status?: number;
      error?: { message?: string; errors?: Record<string, string[]> };
    };
    this.error.set(
      v.error?.errors
        ? Object.values(v.error.errors).flat().join(' ')
        : (v.error?.message ??
            (v.status === 0
              ? 'Sin conexión. Conservamos el formulario; reintenta al recuperar la red.'
              : 'No fue posible completar la operación.')),
    );
  }
}
