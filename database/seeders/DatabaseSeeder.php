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
        // Primero ejecutar el seeder de roles y permisos
        $this->call(RolesAndPermissionsSeeder::class);

        // Crear usuario de prueba con rol super-admin
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'admin@steelex.com',
            'password' => Hash::make('todoesacero'),
        ]);

        $user->assignRole('super-admin');
    }
}
