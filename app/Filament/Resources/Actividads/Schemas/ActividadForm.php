<?php

namespace App\Filament\Resources\Actividads\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;

class ActividadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('finca_id')
                    ->label('Finca')
                    ->options(function () {
                        $query = \App\Models\Finca::query();

                        // CORRECCIÓN: Exigir que la finca esté aprobada
                        $query->where('estado', 'aprobado');
                        
                        /** @var \App\Models\User $user */
                        $user = auth()->user();
                        
                        // Candado de seguridad: solo sus fincas
                        if (! $user->isSuperAdmin()) {
                            $query->where('user_id', $user->id);
                        }
                        
                        return $query->pluck('nombre', 'id');
                    })
                    ->live() // Hace que el formulario reaccione al instante
                    ->dehydrated(false) // Evita que intente guardarlo en la tabla actividades
                    ->afterStateHydrated(function (Select $component, $record) {
                        // Si estamos editando, autocompleta la finca basándose en el lote
                        if ($record && $record->lote) {
                            $component->state($record->lote->finca_id);
                        }
                    })
                    ->required(),
                Select::make('lote_id')
                    ->label('Lote donde se realizó')
                    ->options(function (Get $get) {
                        $fincaId = $get('finca_id');
                        
                        // Si no hay finca seleccionada, el select de lotes estará vacío
                        if (! $fincaId) {
                            return [];
                        }
                        
                        // Busca los lotes disponibles o en uso que pertenezcan a esa finca
                        return \App\Models\Lote::where('finca_id', $fincaId)
                            ->whereIn('estado', ['disponible', 'en_uso'])
                            ->pluck('nombre', 'id')
                            ->toArray();
                    })
                    ->required(),

                // Los demás campos de tu captura de pantalla
                Select::make('tipo_actividad')
                    ->label('Tipo de Actividad')
                    ->options([
                        'preparacion' => 'Preparación del Terreno',
                        'siembra' => 'Siembra',
                        'fertilizacion' => 'Fertilización',
                        'riego' => 'Riego',
                        'control_plagas' => 'Control de Plagas y Enfermedades',
                        'poda' => 'Poda',
                        'cosecha' => 'Cosecha',
                        'mantenimiento' => 'Mantenimiento General',
                    ])
                    ->required(),

                DatePicker::make('fecha')
                    ->label('Fecha de la Actividad')
                    ->default(now())
                    ->required(),

                TextInput::make('costo')
                    ->label('Costo de la Actividad ($)')
                    ->numeric()
                    ->prefix('$')
                    ->required(),

                Textarea::make('observaciones')
                    ->label('Observaciones Técnicas')
                    ->columnSpanFull(),
            ]);
    }
}