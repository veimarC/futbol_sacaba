<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DeveloperUserSeeder extends Seeder
{
    /**
     * Crear un usuario desarrollador (rol: desarrollador).
     * Variables opcionales en .env: DEV_EMAIL, DEV_PASSWORD
     */
    public function run(): void
    {
        $email = env('DEV_EMAIL');
        $password = env('DEV_PASSWORD');

        if (! $email || ! $password || strlen($password) < 12) {
            $this->command->warn('No se creó el desarrollador: configura DEV_EMAIL y DEV_PASSWORD (mínimo 12 caracteres).');
            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Developer',
                'password' => Hash::make($password),
                'rol' => User::ROLE_DESARROLLADOR,
            ]
        );

        // Forzar rol por si ya existía con otro valor.
        $user->rol = User::ROLE_DESARROLLADOR;
        $user->save();

        $this->command->info("Usuario desarrollador creado/asegurado: {$email}");
    }
}
