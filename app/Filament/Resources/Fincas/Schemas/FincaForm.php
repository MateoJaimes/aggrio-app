<?php

namespace App\Filament\Resources\Fincas\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

class FincaForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $schema
            ->components([
                // Relación con el Usuario (Propietario)
                //CORRECCIÓN: Condicional para el propietario
                $user->isSuperAdmin() 
                    ? Select::make('user_id')
                        ->relationship('user', 'name') 
                        ->required()
                        ->label('Propietario (Agricultor)')
                    : Hidden::make('user_id')
                        ->default($user->id), // Asigna automáticamente al usuario logueado

                // Datos de la Finca
                TextInput::make('nombre')
                    ->required()
                    ->maxLength(255)
                    ->label('Nombre de la Finca'),

                // Coordenadas GPS con captura automática
                Grid::make(2)->schema([
                    TextInput::make('latitud')
                        ->numeric()
                        ->label('Latitud')
                        ->placeholder('Automático'),
                        
                    TextInput::make('longitud')
                        ->numeric()
                        ->label('Longitud')
                        ->placeholder('Automático'),
                ]),

                // Botón para obtener ubicación GPS
                \Filament\Forms\Components\ViewField::make('gps_button')
                    ->view('filament.forms.components.gps-button')
                    ->dehydrated(false),

                TextInput::make('hectareas_totales')
                    ->numeric()
                    ->label('Hectáreas Totales')
                    ->placeholder('Ej: 5.5'),

                TextInput::make('tipo_suelo')
                    ->maxLength(255)
                    ->label('Tipo de Suelo (Opcional)'),
            ]);
    }
}
