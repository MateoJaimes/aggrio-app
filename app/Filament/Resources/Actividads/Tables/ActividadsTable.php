<?php

namespace App\Filament\Resources\Actividads\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class ActividadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lote.nombre')
                    ->label('Lote')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('tipo_actividad')
                    ->label('Actividad')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fertilizacion'  => 'success',
                        'control_plagas' => 'danger',
                        'cosecha'        => 'warning',
                        'siembra'        => 'info',
                        'riego'          => 'primary',
                        'poda'           => 'warning',
                        'preparacion'    => 'gray',
                        'mantenimiento'  => 'gray',
                        default          => 'gray',
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('costo')
                    ->label('Costo')
                    ->money('COP') // Puedes cambiar 'COP' por 'USD' o tu moneda local
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipo_actividad')
                    ->label('Filtrar por Tipo')
                    ->options([
                        'preparacion'    => 'Preparación del Terreno',
                        'siembra'        => 'Siembra',
                        'fertilizacion'  => 'Fertilización',
                        'riego'          => 'Riego',
                        'control_plagas' => 'Control de Plagas y Enfermedades',
                        'poda'           => 'Poda',
                        'cosecha'        => 'Cosecha',
                        'mantenimiento'  => 'Mantenimiento General',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}