<?php

namespace App\Filament\Resources\Fincas\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;

class FincasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Propietario')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nombre')
                    ->label('Finca')
                    ->searchable(),

                TextColumn::make('hectareas_totales')
                    ->label('Hectáreas')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                // NUEVA COLUMNA: Etiqueta visual de estado
                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pendiente' => 'warning', // Amarillo
                        'aprobado'  => 'success', // Verde
                        'rechazado' => 'danger',  // Rojo
                        default     => 'gray',
                    })
                    ->disabled(fn () => ! auth()->user()->isSuperAdmin()),
            ])
            ->filters([
                // Aquí agregaremos filtros más adelante
            ])
            ->actions([
                // NUEVO BOTÓN: Aprobar (Solo visible si está pendiente)
                Action::make('aprobar')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation() // Pide confirmación antes de ejecutar
                    ->visible(fn ($record) => $record->estado === 'pendiente')
                    ->action(fn ($record) => $record->update(['estado' => 'aprobado']))
                    ->visible(fn () => auth()->user()->isSuperAdmin()),

                // NUEVO BOTÓN: Rechazar (Solo visible si está pendiente)
                Action::make('rechazar')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->estado === 'pendiente')
                    ->action(fn ($record) => $record->update(['estado' => 'rechazado']))
                    ->visible(fn () => auth()->user()->isSuperAdmin()),

                // Tus botones habituales
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}