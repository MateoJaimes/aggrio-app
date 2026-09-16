import { Actividad } from '../../activities/models/actividad.model';
import { Finca } from '../../estates/models/finca.model';
import { Lote } from '../../lots/models/lote.model';

export type MultimediaCategoria = 'seguimiento' | 'enfermedad';
export type MultimediaFileable = Finca | Lote | Actividad;

export interface ArchivoMultimedia {
  id: number;
  fileable_type: string;
  fileable_id: number;
  fileable?: MultimediaFileable | null;
  ruta_archivo?: string | null;
  url_archivo?: string | null;
  tipo_archivo?: string | null;
  peso_bytes?: number | null;
  categoria?: MultimediaCategoria | null;
  contenido_texto?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface ArchivoMultimediaPayload {
  categoria: MultimediaCategoria;
  contenido_texto?: string | null;
}
