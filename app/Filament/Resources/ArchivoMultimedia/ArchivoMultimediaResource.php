<?php

namespace App\Filament\Resources\ArchivoMultimedia;

use App\Filament\Resources\ArchivoMultimedia\Pages\CreateArchivoMultimedia;
use App\Filament\Resources\ArchivoMultimedia\Pages\EditArchivoMultimedia;
use App\Filament\Resources\ArchivoMultimedia\Pages\ListArchivoMultimedia;
use App\Filament\Resources\ArchivoMultimedia\Schemas\ArchivoMultimediaForm;
use App\Filament\Resources\ArchivoMultimedia\Tables\ArchivoMultimediaTable;
use App\Models\Actividad;
use App\Models\ArchivoMultimedia;
use App\Models\Finca;
use App\Models\Lote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArchivoMultimediaResource extends Resource
{
    protected static ?string $model = ArchivoMultimedia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Archivos Multimedia';

    protected static ?string $pluralModelLabel = 'Archivos Multimedia';

    protected static ?string $modelLabel = 'Archivo Multimedia';

    public static function form(Schema $schema): Schema
    {
        return ArchivoMultimediaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArchivoMultimediaTable::configure($table);
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
            'index' => ListArchivoMultimedia::route('/'),
            'create' => CreateArchivoMultimedia::route('/create'),
            'edit' => EditArchivoMultimedia::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user?->isSuperAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where(function ($sub) use ($user) {
                    $sub->where('fileable_type', 'App\Models\Finca')
                        ->whereHasMorph('fileable', [Finca::class], fn ($q2) => $q2->where('user_id', $user->id));
                })
                ->orWhere(function ($sub) use ($user) {
                    $sub->where('fileable_type', 'App\Models\Lote')
                        ->whereHasMorph('fileable', [Lote::class], fn ($q2) => $q2->whereHas('finca', fn ($q3) => $q3->where('user_id', $user->id)));
                })
                ->orWhere(function ($sub) use ($user) {
                    $sub->where('fileable_type', 'App\Models\Actividad')
                        ->whereHasMorph('fileable', [Actividad::class], fn ($q2) => $q2->whereHas('lote.finca', fn ($q3) => $q3->where('user_id', $user->id)));
                });
            });
        }

        return $query;
    }
}
