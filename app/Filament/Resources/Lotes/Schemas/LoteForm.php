<?php

namespace App\Filament\Resources\Lotes\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class LoteForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $schema
            ->components([
                Select::make('finca_id')
                    ->relationship(
                        name: 'finca',
                        titleAttribute: 'nombre',
                        modifyQueryUsing: function (Builder $query) {
                            /** @var \App\Models\User $user */
                            $user = auth()->user();

                            $query->where('estado', 'aprobado');

                            if (! $user->isSuperAdmin()) {
                                $query->where('user_id', $user->id);
                            }
                        }
                    )
                    ->label('Finca a la que pertenece')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->columnSpanFull(),

                TextInput::make('nombre')
                    ->label('Nombre del Lote / Sector')
                    ->placeholder('Ej: Lote El Mirador')
                    ->required()
                    ->maxLength(255),

                Select::make('tipo_cultivo')
                    ->label('Tipo de Cultivo')
                    ->options([
                        'Cacao' => 'Cacao',
                        'Café' => 'Café',
                        'Aguacate' => 'Aguacate',
                        'Maíz' => 'Maíz',
                        'Otro' => 'Otro',
                    ])
                    ->required()
                    ->searchable(),

                TextInput::make('variedad')
                    ->label('Variedad (Opcional)')
                    ->placeholder('Ej: CCN51, Hass, Caturra')
                    ->maxLength(255),

                TextInput::make('hectareas')
                    ->label('Hectáreas')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->rules([
                        function (callable $get, $record) {
                            return function (string $attribute, $value, callable $fail) use ($get, $record) {
                                $fincaId = $get('finca_id');
                                if (!$fincaId) {
                                    $fail('Selecciona una finca primero.');
                                    return;
                                }

                                $finca = \App\Models\Finca::find($fincaId);
                                if (!$finca || $finca->hectareas_totales === null) {
                                    $fail('La finca no tiene hectáreas totales definidas.');
                                    return;
                                }

                                $excludeId = $record?->id;
                                if (!$finca->tieneEspacioDisponible((float) $value, $excludeId)) {
                                    $disponible = number_format($finca->hectareasDisponibles($excludeId), 2);
                                    $fail("Solo quedan {$disponible} hectáreas disponibles en la finca.");
                                }
                            };
                        },
                    ]),

                DatePicker::make('fecha_siembra')
                    ->label('Fecha de Siembra')
                    ->maxDate(now()),

                Fieldset::make('Coordenadas GPS (Opcional)')
                    ->schema([
                        TextInput::make('latitud')
                            ->numeric()
                            ->label('Latitud (Decimal)'),
                        TextInput::make('longitud')
                            ->numeric()
                            ->label('Longitud (Decimal)'),
                    ])->columns(2),

                Select::make('estado')
                    ->options([
                        'disponible'    => 'Disponible',
                        'en_uso'        => 'En uso',
                        'no_disponible' => 'No disponible',
                    ])
                    ->default('disponible')
                    ->label('Estado'),
            ]);
    }
}
