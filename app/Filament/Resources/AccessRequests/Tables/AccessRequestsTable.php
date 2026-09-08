<?php

namespace App\Filament\Resources\AccessRequests\Tables;

use App\Models\AccessRequest;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccessRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('firstname')->label('Nombre')->searchable(),
                TextColumn::make('lastname')->label('Apellido')->searchable(),
                TextColumn::make('landname')->label('Finca'),
                TextColumn::make('email')->label('Correo'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'waitlisted' => 'info',
                        'denied' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'waitlisted' => 'En Espera',
                        'denied' => 'Rechazado',
                    }),
                TextColumn::make('created_at')->dateTime()->label('Fecha')->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // Botón para ver detalles
                ViewAction::make(),

                // Botón Permitir
                Action::make('approve')
                    ->label('Permitir')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->hidden(fn (AccessRequest $record) => $record->status === 'approved')
                    ->action(function (AccessRequest $record) {
                        $password = Str::random(8);

                        $user = User::updateOrCreate(
                            ['email' => $record->email], // Condición de búsqueda (El ID único del usuario)
                            [
                                'name' => $record->firstname . ' ' . $record->lastname,
                                'password' => Hash::make($password) // Genera o rota la credencial al instante
                            ]
                        );

                        //MEDIDA DE SEGURIDAD: Revocar todos los tokens viejos si el usuario ya existía
                        // Esto obliga a Flutter a pedir el nuevo login con la nueva contraseña
                        $user->tokens()->delete();

                        // Crear la finca con el nombre proporcionado por el solicitante
                        // Nace en estado 'pendiente' para que el usuario la complete y el admin la apruebe
                        \App\Models\Finca::create([
                            'user_id' => $user->id,
                            'nombre'  => $record->landname,
                            'estado'  => 'pendiente',
                        ]);

                        // $record->update(['status' => 'approved']);

                        // 3. Actualizamos el estado de la solicitud
                        $record->update(['status' => 'approved']);

                        // 4. Mostramos la notificación con el resumen completo
                        Notification::make()
                            ->title('Acceso Permitido Exitosamente')
                            ->body("El usuario <strong>{$record->firstname} {$record->lastname}</strong> fue creado. Se creó la finca <strong>{$record->landname}</strong> en estado pendiente. Contraseña para Flutter: <strong>{$password}</strong>")
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                // Botón Espera
                Action::make('waitlist')
                    ->label('Espera')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->requiresConfirmation()
                    ->hidden(fn (AccessRequest $record) => $record->status === 'waitlisted')
                    ->action(function (AccessRequest $record) {
                        $record->update(['status' => 'waitlisted']);

                        Notification::make()
                            ->title('Solicitud en espera')
                            ->info()
                            ->send();
                    }),

                // Botón Negar
                Action::make('deny')
                    ->label('Negar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->hidden(fn (AccessRequest $record) => $record->status === 'denied')
                    ->action(function (AccessRequest $record) {
                        $record->update(['status' => 'denied']);

                        // 🔒 MEDIDA DE SEGURIDAD: Expulsar inmediatamente al usuario de la App Móvil
                        $user = User::where('email', $record->email)->first();
                        if ($user) {
                            $user->tokens()->delete(); // Borra todos sus tokens de acceso activos
                        }

                        Notification::make()
                            ->title('Solicitud denegada')
                            ->danger()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}