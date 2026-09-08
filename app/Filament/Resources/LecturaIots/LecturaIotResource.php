<?php

namespace App\Filament\Resources\LecturaIots;

use App\Filament\Resources\LecturaIots\Pages\CreateLecturaIot;
use App\Filament\Resources\LecturaIots\Pages\EditLecturaIot;
use App\Filament\Resources\LecturaIots\Pages\ListLecturaIots;
use App\Filament\Resources\LecturaIots\Schemas\LecturaIotForm;
use App\Filament\Resources\LecturaIots\Tables\LecturaIotsTable;
use App\Models\LecturaIot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LecturaIotResource extends Resource
{
    protected static ?string $model = LecturaIot::class;
    protected static ?string $navigationLabel = 'Telemetría IoT';
    protected static ?string $modelLabel = 'Lectura de Sensor';
    protected static ?string $pluralModelLabel = 'Lecturas IoT';


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return LecturaIotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LecturaIotsTable::configure($table);
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
            'index' => ListLecturaIots::route('/'),
            'create' => CreateLecturaIot::route('/create'),
            'edit' => EditLecturaIot::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user->isSuperAdmin()) {
            $query->whereHas('lote.finca', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return $query;
    }
}
