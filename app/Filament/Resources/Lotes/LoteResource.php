<?php

namespace App\Filament\Resources\Lotes;

use App\Filament\Resources\Lotes\Pages\CreateLote;
use App\Filament\Resources\Lotes\Pages\EditLote;
use App\Filament\Resources\Lotes\Pages\ListLotes;
use App\Filament\Resources\Lotes\Schemas\LoteForm;
use App\Filament\Resources\Lotes\Tables\LotesTable;
use App\Models\Lote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LoteResource extends Resource
{
    protected static ?string $model = Lote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'tipo_cultivo';

    public static function form(Schema $schema): Schema
    {
        return LoteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LotesTable::configure($table);
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
            'index' => ListLotes::route('/'),
            'create' => CreateLote::route('/create'),
            'edit' => EditLote::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user?->isSuperAdmin()) {
            // Entra al Lote -> Finca -> verifica el dueño
            $query->whereHas('finca', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return $query;
    }
}
