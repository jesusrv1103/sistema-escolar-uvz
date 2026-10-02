<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Usuario administrador de desarrollo.
 *
 * - La credencial NUNCA se hardcodea: proviene de `.env`
 *   (`ADMIN_EMAIL` / `ADMIN_PASSWORD`), que no se versiona ni se documenta.
 * - Idempotente: puede ejecutarse varias veces sin duplicar usuarios.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@uvz.local');
        $password = env('ADMIN_PASSWORD');

        if (blank($password)) {
            throw new RuntimeException(
                'ADMIN_PASSWORD no está definida en .env. Configúrala (solo desarrollo) y vuelve a ejecutar el seeder.'
            );
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrador UVZ'),
                'password' => Hash::make($password),
                'rol' => RolUsuario::ADMIN,
                'activo' => true,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ],
        );

        $this->command?->info("Usuario ADMIN listo: {$email}");
    }
}
