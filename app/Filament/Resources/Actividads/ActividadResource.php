<?php

namespace App\Filament\Resources\Actividads;

use App\Filament\Resources\Actividads\Pages\CreateActividad;
use App\Filament\Resources\Actividads\Pages\EditActividad;
use App\Filament\Resources\Actividads\Pages\ListActividads;
use App\Filament\Resources\Actividads\Schemas\ActividadForm;
use App\Filament\Resources\Actividads\Tables\ActividadsTable;
use App\Models\Actividad;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ActividadResource extends Resource
{
    protected static ?string $model = Actividad::class;

    protected static ?string $navigationLabel = 'Actividades'; // Nombre en el menú lateral
    protected static ?string $modelLabel = 'Actividad';         // Nombre en singular (ej: "Crear Actividad")
    protected static ?string $pluralModelLabel = 'Actividades';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'tipo_actividad';

    public static function form(Schema $schema): Schema
    {
        return ActividadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActividadsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActividads::route('/'),
            'create' => CreateActividad::route('/create'),
            'edit' => EditActividad::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->isSuperAdmin()) {
            // Rastreo profundo: Actividad -> Lote -> Finca -> Usuario
            $query->whereHas('lote.finca', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return $query;
    }
}
