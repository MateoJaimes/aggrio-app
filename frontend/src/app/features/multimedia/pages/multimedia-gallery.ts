import { CommonModule, DatePipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { Actividad } from '../../activities/models/actividad.model';
import { Finca, UserSummary } from '../../estates/models/finca.model';
import { FincaAdminService } from '../../estates/services/finca-admin.service';
import { Lote } from '../../lots/models/lote.model';
import { ArchivoMultimedia, MultimediaCategoria } from '../models/archivo-multimedia.model';
import { MultimediaAdminService } from '../services/multimedia-admin.service';

@Component({
  selector: 'app-multimedia-gallery',
  standalone: true,
  imports: [CommonModule, DatePipe, ReactiveFormsModule, RouterLink],
  templateUrl: './multimedia-gallery.html',
  styleUrls: ['../../lots/pages/lots-shell.scss', '../../lots/pages/lot-list.scss', './multimedia-gallery.scss'],
})
export class MultimediaGallery implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly multimediaAdminService = inject(MultimediaAdminService);
  private readonly fincaAdminService = inject(FincaAdminService);

  readonly archivos = signal<ArchivoMultimedia[]>([]);
  readonly owners = signal<UserSummary[]>([]);
  readonly isLoading = signal(false);
  readonly isLoadingOwners = signal(false);
  readonly isSaving = signal(false);
  readonly deletingId = signal<number | null>(null);
  readonly error = signal('');
  readonly notice = signal('');
  readonly selectedOwnerId = signal<number | null>(null);
  readonly search = signal('');
  readonly selectedArchivo = signal<ArchivoMultimedia | null>(null);

  readonly metadataForm = this.fb.nonNullable.group({
    categoria: ['seguimiento' as MultimediaCategoria, [Validators.required]],
    contenido_texto: ['', [Validators.maxLength(65535)]],
  });

  readonly visibleArchivos = computed(() => {
    const term = this.search().trim().toLocaleLowerCase();

    if (!term) {
      return this.archivos();
    }

    return this.archivos().filter((archivo) => [
      this.fileTypeLabel(archivo),
      this.ownerName(archivo),
      this.contextLabel(archivo),
      archivo.contenido_texto,
      archivo.ruta_archivo,
    ].some((value) => value?.toLocaleLowerCase().includes(term)));
  });

  ngOnInit(): void {
    this.loadOwners();
    this.loadArchivos();
  }

  loadOwners(): void {
    this.isLoadingOwners.set(true);

    this.fincaAdminService.getAdminFincas().subscribe({
      next: (response) => {
        const owners = new Map<number, UserSummary>();

        for (const finca of response.data ?? []) {
          if (finca.user) {
            owners.set(finca.user.id, finca.user);
          }
        }

        this.owners.set(Array.from(owners.values()).sort((a, b) => a.name.localeCompare(b.name)));
        this.isLoadingOwners.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudieron cargar los propietarios para el filtro.'));
        this.isLoadingOwners.set(false);
      },
    });
  }

  loadArchivos(): void {
    this.isLoading.set(true);
    this.error.set('');

    this.multimediaAdminService.getArchivos(this.selectedOwnerId() ?? undefined).subscribe({
      next: (response) => {
        this.archivos.set(response.data ?? []);
        this.isLoading.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudieron cargar los archivos multimedia.'));
        this.isLoading.set(false);
      },
    });
  }

  filterByOwner(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    this.selectedOwnerId.set(value ? Number(value) : null);
    this.loadArchivos();
  }

  setSearch(event: Event): void {
    this.search.set((event.target as HTMLInputElement).value);
  }

  openDetails(archivo: ArchivoMultimedia): void {
    this.selectedArchivo.set(archivo);
    this.metadataForm.patchValue({
      categoria: archivo.categoria ?? 'seguimiento',
      contenido_texto: archivo.contenido_texto ?? '',
    });
  }

  closeDetails(): void {
    if (!this.isSaving()) {
      this.selectedArchivo.set(null);
    }
  }

  saveMetadata(): void {
    const archivo = this.selectedArchivo();

    if (!archivo || this.metadataForm.invalid) {
      this.metadataForm.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    this.error.set('');

    const raw = this.metadataForm.getRawValue();
    this.multimediaAdminService.updateArchivo(archivo.id, {
      categoria: raw.categoria,
      contenido_texto: raw.contenido_texto.trim() || null,
    }).subscribe({
      next: (response) => {
        const updated = response.data;

        if (updated) {
          this.archivos.update((archivos) => archivos.map((item) => item.id === updated.id ? updated : item));
          this.selectedArchivo.set(updated);
        }

        this.notice.set(response.message ?? 'Archivo multimedia actualizado exitosamente.');
        this.isSaving.set(false);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudieron actualizar los datos del archivo.'));
        this.isSaving.set(false);
      },
    });
  }

  deleteArchivo(archivo: ArchivoMultimedia): void {
    const confirmed = window.confirm(
      `¿Deseas eliminar este archivo de ${this.ownerName(archivo)}? Esta acción también eliminará el archivo físico cuando exista.`,
    );

    if (!confirmed) {
      return;
    }

    this.deletingId.set(archivo.id);
    this.error.set('');
    this.notice.set('');

    this.multimediaAdminService.deleteArchivo(archivo.id).subscribe({
      next: (response) => {
        this.archivos.update((archivos) => archivos.filter((item) => item.id !== archivo.id));

        if (this.selectedArchivo()?.id === archivo.id) {
          this.selectedArchivo.set(null);
        }

        this.notice.set(response.message ?? 'Archivo multimedia eliminado exitosamente.');
        this.deletingId.set(null);
      },
      error: (error) => {
        this.error.set(this.getErrorMessage(error, 'No se pudo eliminar el archivo.'));
        this.deletingId.set(null);
      },
    });
  }

  ownerName(archivo: ArchivoMultimedia): string {
    return this.ownerOf(archivo)?.name ?? 'Propietario no disponible';
  }

  contextLabel(archivo: ArchivoMultimedia): string {
    const fileable = archivo.fileable;

    if (!fileable) {
      return 'Entidad no disponible';
    }

    if (archivo.fileable_type === 'App\\Models\\Finca') {
      const finca = fileable as Finca;
      return `Finca: ${finca.nombre ?? `#${finca.id}`}`;
    }

    if (archivo.fileable_type === 'App\\Models\\Lote') {
      const lote = fileable as Lote;
      return `Lote: ${lote.nombre} · ${lote.finca?.nombre ?? `Finca #${lote.finca_id}`}`;
    }

    if (archivo.fileable_type === 'App\\Models\\Actividad') {
      const actividad = fileable as Actividad;
      return `Actividad: ${actividad.tipo_actividad} · ${actividad.lote?.nombre ?? `Lote #${actividad.lote_id}`}`;
    }

    return 'Entidad no disponible';
  }

  fileTypeLabel(archivo: ArchivoMultimedia): string {
    if (this.isTextNote(archivo)) {
      return 'Nota de texto';
    }

    if (this.isImage(archivo)) {
      return 'Imagen';
    }

    if (this.isVideo(archivo)) {
      return 'Video';
    }

    if (this.isAudio(archivo)) {
      return 'Audio';
    }

    return 'Archivo';
  }

  isTextNote(archivo: ArchivoMultimedia): boolean {
    return !archivo.ruta_archivo || (archivo.tipo_archivo ?? '').toLocaleLowerCase().includes('texto');
  }

  isImage(archivo: ArchivoMultimedia): boolean {
    return this.matchesMediaType(archivo, ['foto', 'imagen', 'image'], ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
  }

  isVideo(archivo: ArchivoMultimedia): boolean {
    return this.matchesMediaType(archivo, ['video'], ['mp4', 'avi', 'mov', 'wmv', 'mkv', 'webm']);
  }

  isAudio(archivo: ArchivoMultimedia): boolean {
    return this.matchesMediaType(archivo, ['audio'], ['mp3', 'wav', 'ogg', 'm4a', 'aac']);
  }

  formatSize(bytes: number | null | undefined): string {
    if (!bytes) {
      return '—';
    }

    if (bytes < 1024) {
      return `${bytes} B`;
    }

    if (bytes < 1024 ** 2) {
      return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
  }

  categoryLabel(category: MultimediaCategoria | null | undefined): string {
    return category === 'enfermedad' ? 'Enfermedad' : 'Seguimiento';
  }

  private ownerOf(archivo: ArchivoMultimedia): UserSummary | undefined {
    const fileable = archivo.fileable;

    if (!fileable) {
      return undefined;
    }

    if (archivo.fileable_type === 'App\\Models\\Finca') {
      return (fileable as Finca).user;
    }

    if (archivo.fileable_type === 'App\\Models\\Lote') {
      return (fileable as Lote).finca?.user;
    }

    if (archivo.fileable_type === 'App\\Models\\Actividad') {
      return (fileable as Actividad).lote?.finca?.user;
    }

    return undefined;
  }

  private matchesMediaType(archivo: ArchivoMultimedia, names: string[], extensions: string[]): boolean {
    const type = (archivo.tipo_archivo ?? '').toLocaleLowerCase();
    const path = (archivo.ruta_archivo ?? '').toLocaleLowerCase();

    return names.some((name) => type.includes(name)) || extensions.some((extension) => path.endsWith(`.${extension}`));
  }

  private getErrorMessage(error: unknown, fallback: string): string {
    const response = (error as { error?: { message?: string; errors?: Record<string, string[]> } })?.error;
    const validationError = response?.errors ? Object.values(response.errors).flat()[0] : undefined;

    return validationError ?? response?.message ?? fallback;
  }
}
