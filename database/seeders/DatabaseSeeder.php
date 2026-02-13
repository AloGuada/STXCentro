<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Primero ejecutar los seeders de catálogos
        $this->call([
            RolesAndPermissionsSeeder::class,
            StiStatusSeeder::class,
        ]);

        // Crear usuario de prueba con rol super-admin
        $user = User::firstOrCreate(
            ['email' => 'sistemas@steelex.com.mx'],
            [
                'name' => 'Sistemas STEELEX',
                'password' => Hash::make('todoesacero'),
            ],
        );

        $user->assignRole('super-admin');
    }
}
