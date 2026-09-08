<?php

namespace App\Filament\Resources\AccessRequests\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;


class AccessRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Solicitante')
                    ->columns(2)
                    ->schema([
                        TextInput::make('firstname')->label('Nombres'),
                        TextInput::make('lastname')->label('Apellidos'),
                        Select::make('id_type') // Usa el nombre exacto de tu columna en la BD
                            ->label('Tipo Doc.')
                            ->options([
                                'CC' => 'Cédula de Ciudadanía (CC)',
                                'TI' => 'Tarjeta de Identidad (TI)',
                                'Pasaporte' => 'Pasaporte',
                            ]),
                        // TextInput::make('id_type')->label('Tipo Doc.'),
                        TextInput::make('id_number')->label('Número Doc.'),
                        TextInput::make('email')->label('Correo Electrónico'),
                    ]),
                Section::make('Información de la Finca / Ubicación')
                    ->columns(2)
                    ->schema([
                        TextInput::make('landname')->label('Nombre de Finca'),
                        TextInput::make('country')->label('País'),
                        TextInput::make('department')->label('Departamento'),
                        TextInput::make('city')->label('Ciudad'),
                    ]),
            ]);
    }
}
