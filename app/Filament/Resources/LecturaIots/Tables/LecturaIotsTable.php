<?php

namespace App\Filament\Resources\LecturaIots\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class LecturaIotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lote.nombre')
                    ->label('Lote')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('mac_dispositivo')
                    ->label('Dispositivo')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tipo_medicion')
                    ->label('Variable')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'temperatura' => 'danger',
                        'humedad_suelo' => 'info',
                        'radiacion_solar' => 'warning',
                        'humedad_ambiente' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('valor')
                    ->label('Valor')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unidad')
                    ->label('Unidad'),
                TextColumn::make('fecha_medicion')
                    ->label('Momento de Lectura')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipo_medicion')
                    ->label('Filtrar por Variable')
                    ->options([
                        'temperatura' => 'Temperatura',
                        'humedad_suelo' => 'Humedad Suelo',
                    ]),
                SelectFilter::make('lote_id')
                    ->relationship('lote', 'nombre')
                    ->label('Filtrar por Lote'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('fecha_medicion', 'desc'); // Siempre muestra el dato más reciente arriba
    }
}