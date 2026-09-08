<?php

namespace App\Filament\Resources\ArchivoMultimedia\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ArchivoMultimediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                //SECCIÓN 1: Trazabilidad Jerárquica
                Section::make('1. Asociación del Archivo')
                    ->description('Define el propósito de la evidencia y su ruta exacta en la finca.')
                    ->schema([
                        Radio::make('categoria')
                            ->label('Propósito de la Evidencia')
                            ->options([
                                'seguimiento' => 'Seguimiento Rutinario',
                                'enfermedad'  => 'Reporte de Enfermedad',
                            ])
                            ->default('seguimiento')
                            ->inline()
                            ->live()
                            ->required(),

                        Select::make('fileable_type')
                            ->label('¿A qué nivel deseas asociar la evidencia?')
                            ->options([
                                \App\Models\Finca::class => 'A una Finca completa',
                                \App\Models\Lote::class => 'A un Lote específico',
                                \App\Models\Actividad::class => 'A una Actividad específica',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('finca_id', null);
                                $set('lote_id', null);
                                $set('actividad_id', null);
                                $set('fileable_id', null);
                            }),

                        Select::make('finca_id')
                            ->label('Paso 1: Selecciona la Finca')
                            ->options(function () {
                                /** @var \App\Models\User $user */
                                $user = auth()->user();
                                $isSuper = $user->isSuperAdmin();

                                return \App\Models\Finca::query()
                                    ->when(! $isSuper, fn ($q) => $q->where('user_id', $user->id))
                                    ->where('estado', 'aprobado')
                                    ->pluck('nombre', 'id');
                            })
                            ->required(fn (Get $get) => filled($get('fileable_type')))
                            ->visible(fn (Get $get) => filled($get('fileable_type')))
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state, Get $get) {
                                $set('lote_id', null);
                                $set('actividad_id', null);
                                if ($get('fileable_type') === \App\Models\Finca::class) {
                                    $set('fileable_id', $state);
                                }
                            })
                            ->dehydrated(false),

                        Select::make('lote_id')
                            ->label('Paso 2: Selecciona el Lote')
                            ->options(fn (Get $get) => \App\Models\Lote::where('finca_id', $get('finca_id'))->pluck('nombre', 'id'))
                            ->required(fn (Get $get) => in_array($get('fileable_type'), [\App\Models\Lote::class, \App\Models\Actividad::class]))
                            ->visible(fn (Get $get) => in_array($get('fileable_type'), [\App\Models\Lote::class, \App\Models\Actividad::class]) && filled($get('finca_id')))
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state, Get $get) {
                                $set('actividad_id', null);
                                if ($get('fileable_type') === \App\Models\Lote::class) {
                                    $set('fileable_id', $state);
                                }
                            })
                            ->dehydrated(false),

                        Select::make('actividad_id')
                            ->label('Paso 3: Selecciona la Actividad')
                            ->options(fn (Get $get) => \App\Models\Actividad::where('lote_id', $get('lote_id'))->pluck('tipo_actividad', 'id'))
                            ->required(fn (Get $get) => $get('fileable_type') === \App\Models\Actividad::class)
                            ->visible(fn (Get $get) => $get('fileable_type') === \App\Models\Actividad::class && filled($get('lote_id')))
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state, Get $get) {
                                if ($get('fileable_type') === \App\Models\Actividad::class) {
                                    $set('fileable_id', $state);
                                }
                            })
                            ->dehydrated(false),

                        Hidden::make('fileable_id')
                            ->required(),
                    ])->columns(1),

                // SECCIÓN 2: Evidencia Multimedia o Nota
                Section::make('2. Evidencia Multimedia o Nota')
                    ->schema([
                        Radio::make('es_nota_texto')
                            ->label('Tipo de Evidencia')
                            ->options([
                                '0' => 'Subir Archivo(s) Multimedia',
                                '1' => 'Escribir una Nota de Texto',
                            ])
                            ->default('0')
                            ->inline()
                            ->live(),

                        //NUEVO: Control manual para arreglar el problema del .mp4
                        Select::make('forzar_clasificacion')
                            ->label('Clasificación en MinIO')
                            ->options([
                                'auto' => 'Automático (Según el formato del archivo)',
                                'audios' => 'Forzar TODO a Audios (Útil para audios en .mp4)',
                                'imagenes' => 'Forzar TODO a Imágenes',
                                'videos' => 'Forzar TODO a Videos',
                                'documentos_varios' => 'Forzar TODO a Documentos Varios',
                            ])
                            ->default('auto')
                            ->visible(fn (Get $get) => $get('es_nota_texto') == '0')
                            ->live()
                            ->helperText('Si subes un audio pero está en formato de video (.mp4), selecciona "Forzar a Audios".'),

                        FileUpload::make('ruta_archivo')
                            ->label('Sube tu(s) archivo(s) (Acepta Video, Audio, Imagen o Documento)')
                            ->disk('s3')
                            ->maxSize(51200)
                            ->multiple(fn (string $operation): bool => $operation === 'create')
                            ->appendFiles()
                            ->visible(fn (Get $get) => $get('es_nota_texto') == '0')
                            ->required(fn (Get $get) => $get('es_nota_texto') == '0')
                            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, Get $get): string {
                                $mime = $file->getMimeType() ?? '';
                                $extension = strtolower($file->getClientOriginalExtension());
                                
                                $userId = auth()->id() ?? 'invitado';
                                $categoria = $get('categoria') ?? 'seguimiento';
                                $forzar = $get('forzar_clasificacion') ?? 'auto';

                                //LÓGICA DE CLASIFICACIÓN CON BOTÓN DE FORZADO
                                if ($forzar !== 'auto') {
                                    // Si el usuario eligió forzar, obedecemos sin importar el formato
                                    $tipoMedia = $forzar;
                                } else {
                                    // Primero priorizamos los MIME de Audio (Por si acaso viene bien etiquetado)
                                    if (str_starts_with($mime, 'audio/') || in_array($extension, ['mp3', 'wav', 'ogg', 'm4a', 'aac'])) {
                                        $tipoMedia = 'audios';
                                    } 
                                    // Luego Imágenes
                                    elseif (str_starts_with($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                                        $tipoMedia = 'imagenes';
                                    } 
                                    // Luego Videos (Aquí caerán los audios .mp4 que no hayan sido forzados)
                                    elseif (str_starts_with($mime, 'video/') || in_array($extension, ['mp4', 'avi', 'mov', 'wmv', 'mkv'])) {
                                        $tipoMedia = 'videos';
                                    } 
                                    // Resto de documentos
                                    else {
                                        $tipoMedia = 'documentos_varios';
                                    }
                                }

                                $folder = "documentos/usuario_{$userId}/{$categoria}/{$tipoMedia}";

                                return $file->store($folder, 's3');
                            })
                            ->columnSpanFull(),

                        Textarea::make('contenido_texto')
                            ->label('Contenido de la Nota de Texto')
                            ->rows(5)
                            ->visible(fn (Get $get) => $get('es_nota_texto') == '1')
                            ->required(fn (Get $get) => $get('es_nota_texto') == '1')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}