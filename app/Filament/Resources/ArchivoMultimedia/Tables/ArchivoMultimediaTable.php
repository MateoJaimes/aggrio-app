<?php

namespace App\Filament\Resources\ArchivoMultimedia\Tables;

use App\Models\Actividad;
use App\Models\Finca;
use App\Models\Lote;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class ArchivoMultimediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('tipo_archivo')
                    ->label('Tipo')
                    ->icon(fn (string $state): string => match (true) {
                        str_contains($state, 'foto') || str_contains($state, 'imagen') => 'heroicon-o-photo',
                        str_contains($state, 'video') => 'heroicon-o-video-camera',
                        str_contains($state, 'audio') => 'heroicon-o-microphone',
                        str_contains($state, 'texto') || str_contains($state, 'nota') => 'heroicon-o-document-text',
                        default => 'heroicon-o-paper-clip',
                    })
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'foto') || str_contains($state, 'imagen') => 'success',
                        str_contains($state, 'video') => 'warning',
                        str_contains($state, 'audio') => 'info',
                        str_contains($state, 'texto') || str_contains($state, 'nota') => 'gray',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match (true) {
                        str_contains($state, 'foto') || str_contains($state, 'imagen') => 'Foto',
                        str_contains($state, 'video') => 'Video',
                        str_contains($state, 'audio') => 'Audio',
                        str_contains($state, 'texto') || str_contains($state, 'nota') => 'Nota de texto',
                        default => 'Archivo',
                    }),

                TextColumn::make('fileable_type')
                    ->label('Entidad')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->badge()
                    ->color('info'),

                TextColumn::make('fileable.nombre')
                    ->label('Nombre')
                    ->default(fn ($record) => $record->fileable?->tipo_actividad ?? 'N/A')
                    ->searchable(),

                TextColumn::make('categoria')
                    ->label('Categoría')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'seguimiento' => 'success',
                        'enfermedad' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'seguimiento' => 'Seguimiento',
                        'enfermedad' => 'Enfermedad',
                        default => ucfirst($state),
                    })
                    ->default('N/A'),

                TextColumn::make('ruta_archivo')
                    ->label('Ruta MinIO')
                    ->limit(40)
                    ->tooltip(fn (string $state): string => $state)
                    ->searchable(),

                TextColumn::make('contenido_texto')
                    ->label('Transcripción / Nota')
                    ->limit(50)
                    ->placeholder('Sin texto')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('peso_bytes')
                    ->label('Peso')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 1024 / 1024, 2) . ' MB' : '-')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Fecha de Subida')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('categoria')
                    ->label('Categoría')
                    ->options([
                        'seguimiento' => 'Seguimiento',
                        'enfermedad' => 'Enfermedad',
                    ]),

                SelectFilter::make('tipo_archivo')
                    ->label('Tipo de archivo')
                    ->options([
                        'foto_campo' => 'Foto',
                        'video_campo' => 'Video',
                        'nota_audio' => 'Audio',
                        'nota_texto' => 'Nota de texto',
                        'archivo_campo' => 'Archivo',
                    ]),

                SelectFilter::make('finca_user')
                    ->label('Usuario (Finca)')
                    ->options(fn (): array => User::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)
                    ->query(function ($query, array $data) {
                        if (!$data['value']) return $query;
                        return $query->where(function ($q) use ($data) {
                            $q->where(function ($sub) use ($data) {
                                $sub->where('fileable_type', 'App\Models\Finca')
                                    ->whereHasMorph('fileable', [Finca::class], function ($q2) use ($data) {
                                        $q2->where('user_id', $data['value']);
                                    });
                            })
                            ->orWhere(function ($sub) use ($data) {
                                $sub->where('fileable_type', 'App\Models\Lote')
                                    ->whereHasMorph('fileable', [Lote::class], function ($q2) use ($data) {
                                        $q2->whereHas('finca', function ($q3) use ($data) {
                                            $q3->where('user_id', $data['value']);
                                        });
                                    });
                            })
                            ->orWhere(function ($sub) use ($data) {
                                $sub->where('fileable_type', 'App\Models\Actividad')
                                    ->whereHasMorph('fileable', [Actividad::class], function ($q2) use ($data) {
                                        $q2->whereHas('lote.finca', function ($q3) use ($data) {
                                            $q3->where('user_id', $data['value']);
                                        });
                                    });
                            });
                        });
                    }),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(function ($query, array $data): void {
                        $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
