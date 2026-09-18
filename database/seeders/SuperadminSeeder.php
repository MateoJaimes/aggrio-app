<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Crea (o promueve) la cuenta de Superadmin.
 *
 * Credenciales configurables por entorno:
 *   SUPERADMIN_NAME, SUPERADMIN_EMAIL, SUPERADMIN_PASSWORD
 *
 * El doble factor no se configura aquí: el Superadmin escanea su QR la primera
 * vez que inicia sesión en el panel (RF003).
 */
class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL');
        $password = env('SUPERADMIN_PASSWORD');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! is_string($password) || $password === '') {
            throw new RuntimeException(
                'Define SUPERADMIN_EMAIL y SUPERADMIN_PASSWORD antes de ejecutar el seeder.',
            );
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->forceFill(['role' => UserRole::Superadmin])->save();

            $this->command?->info("Superadmin existente promovido: {$email}");

            return;
        }

        User::create([
            'name' => env('SUPERADMIN_NAME', 'Superadmin Aggrio'),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Superadmin,
        ]);

        $this->command?->info("Superadmin creado: {$email}");
        $this->command?->warn('Cambia la contraseña inicial después del primer acceso.');
    }
}
