<?php

namespace App\Filament\Resources\AccessRequests;

use App\Filament\Resources\AccessRequests\Pages;
use App\Filament\Resources\AccessRequests\Schemas\AccessRequestForm;
use App\Filament\Resources\AccessRequests\Tables\AccessRequestsTable;
use App\Models\AccessRequest;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AccessRequestResource extends Resource
{
    protected static ?string $model = AccessRequest::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'Solicitudes de Acceso';
    protected static ?string $modelLabel = 'Solicitud';
    protected static ?string $pluralModelLabel = 'Solicitudes';

    public static function canCreate(): bool { return false; }

    // Conectamos el archivo del esquema (Formulario / Vista detallada)
    public static function form(Schema $schema): Schema
    {
        return AccessRequestForm::configure($schema);
    }

    // Conectamos el archivo de la tabla (Columnas y Botones)
    public static function table(Table $table): Table
    {
        return AccessRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccessRequests::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        // Solo retorna TRUE (es decir, muestra el menú) si el usuario es el Superadmin
        return $user->isSuperAdmin();
    }
}