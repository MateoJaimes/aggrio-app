<?php

namespace App\Filament\Resources\Fincas;

use App\Filament\Resources\Fincas\Pages\CreateFinca;
use App\Filament\Resources\Fincas\Pages\EditFinca;
use App\Filament\Resources\Fincas\Pages\ListFincas;
use App\Filament\Resources\Fincas\Schemas\FincaForm;
use App\Filament\Resources\Fincas\Tables\FincasTable;
use App\Models\Finca;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;


class FincaResource extends Resource
{
    protected static ?string $model = Finca::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function form(Schema $schema): Schema
    {
        return FincaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FincasTable::configure($table);
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
            'index' => ListFincas::route('/'),
            'create' => CreateFinca::route('/create'),
            'edit' => EditFinca::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // Si NO es el superadmin, solo ve sus propias fincas
        if (! $user->isSuperAdmin()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }
}
