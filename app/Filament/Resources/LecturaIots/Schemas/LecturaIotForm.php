<?php

namespace App\Filament\Resources\LecturaIots\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;

class LecturaIotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('finca_id')
                    ->label('Finca')
                    ->options(function () {
                        $query = \App\Models\Finca::query();

                        //CORRECCIÓN: Exigir que la finca esté aprobada
                        $query->where('estado', 'aprobado');
                        
                        /** @var \App\Models\User $user */
                        $user = auth()->user();
                        
                        // Candado de seguridad: solo sus fincas
                        if (! $user->isSuperAdmin()) {
                            $query->where('user_id', $user->id);
                        }
                        
                        return $query->pluck('nombre', 'id');
                    })
                    ->live() // Notifica al formulario cada vez que cambia la finca
                    ->dehydrated(false) // Evita que Filament intente guardar este campo en la tabla 'lecturas_iot'
                    ->afterStateHydrated(function (Select $component, $record) {
                        // Al editar un registro existente, precarga la finca dueña de ese lote
                        if ($record && $record->lote) {
                            $component->state($record->lote->finca_id);
                        }
                    })
                    ->required(),
                Select::make('lote_id')
                    ->label('Lote de Cultivo')
                    ->options(function (Get $get) {
                        $fincaId = $get('finca_id');
                        
                        // Si aún no se selecciona ninguna finca, el campo permanece vacío y bloqueado
                        if (! $fincaId) {
                            return [];
                        }
                        
                        // Trae única y exclusivamente los lotes que pertenecen a la finca elegida
                        return \App\Models\Lote::where('finca_id', $fincaId)
                            ->pluck('nombre', 'id')
                            ->toArray();
                    })
                    ->required(),
                TextInput::make('mac_dispositivo')
                    ->label('MAC del Dispositivo (Opcional)')
                    ->maxLength(50),
                Select::make('tipo_medicion')
                    ->label('Variable Medida')
                    ->options([
                        'temperatura' => 'Temperatura',
                        'humedad_suelo' => 'Humedad del Suelo',
                        'radiacion_solar' => 'Radiación Solar',
                        'humedad_ambiente' => 'Humedad Ambiente',
                    ])
                    ->required()
                    ->live(),
                TextInput::make('valor')
                    ->label('Valor Registrado')
                    ->numeric()
                    ->required(),
                Select::make('unidad')
                    ->label('Unidad de Medida')
                    ->options(fn (Get $get): array => match ($get('tipo_medicion')) {
                        'temperatura' => [
                            '°C' => 'Grados Celsius (°C)',
                            '°F' => 'Grados Fahrenheit (°F)',
                        ],
                        'humedad_suelo', 'humedad_ambiente' => [
                            '%' => 'Porcentaje (%)',
                        ],
                        'radiacion_solar' => [
                            'W/m²' => 'Vatios por metro cuadrado (W/m²)',
                            'lux' => 'Lux',
                        ],
                        default => [], // Si no hay nada seleccionado, el menú de unidades estará vacío
                    })
                    ->required(),
                DateTimePicker::make('fecha_medicion')
                    ->label('Fecha y Hora Exacta')
                    ->default(now())
                    ->required(),
            ]);
    }
}