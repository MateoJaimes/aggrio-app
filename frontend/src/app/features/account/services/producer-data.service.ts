import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, forkJoin, map, of, switchMap } from 'rxjs';

import { environment } from '../../../../environments/environment';
import { ApiResponse } from '../../../core/models/api-response.model';
import { Actividad } from '../../activities/models/actividad.model';
import { Finca } from '../../estates/models/finca.model';
import { LecturaIot } from '../../iot/models/lectura-iot.model';
import { Lote } from '../../lots/models/lote.model';
import { ArchivoMultimedia } from '../../multimedia/models/archivo-multimedia.model';

export interface ProducerOverview {
  fincas: Finca[];
  lotes: Lote[];
  actividades: Actividad[];
  lecturas: LecturaIot[];
  archivos: ArchivoMultimedia[];
}

@Injectable({ providedIn: 'root' })
export class ProducerDataService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = environment.apiUrl;

  getOverview(): Observable<ProducerOverview> {
    return forkJoin({
      fincas: this.getList<Finca>('/mis-fincas'),
      archivos: this.getList<ArchivoMultimedia>('/mis-archivos-multimedia'),
    }).pipe(
      switchMap(({ fincas, archivos }) =>
        this.getLotsForFincas(fincas).pipe(
          switchMap((lotes) => forkJoin({
            actividades: this.getForLots<Actividad>(lotes, (id) => `/lotes/${id}/actividades`),
            lecturas: this.getForLots<LecturaIot>(lotes, (id) => `/lotes/${id}/lecturas`),
          }).pipe(
            map(({ actividades, lecturas }) => ({ fincas, lotes, actividades, lecturas, archivos })),
          )),
        ),
      ),
    );
  }

  private getLotsForFincas(fincas: Finca[]): Observable<Lote[]> {
    if (!fincas.length) {
      return of([]);
    }

    return forkJoin(fincas.map((finca) => this.getList<Lote>(`/fincas/${finca.id}/lotes`).pipe(
      map((lotes) => lotes.map((lote) => ({ ...lote, finca }))),
    ))).pipe(map((groups) => groups.flat()));
  }

  private getForLots<T>(lotes: Lote[], path: (id: number) => string): Observable<T[]> {
    if (!lotes.length) {
      return of([]);
    }

    return forkJoin(lotes.map((lote) => this.getList<T>(path(lote.id)))).pipe(
      map((groups) => groups.flat()),
    );
  }

  private getList<T>(path: string): Observable<T[]> {
    return this.http.get<ApiResponse<T[]>>(`${this.baseUrl}${path}`).pipe(
      map((response) => response.data ?? []),
    );
  }
}
