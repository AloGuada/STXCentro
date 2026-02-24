<?php

namespace Database\Factories\Cob;

use App\Models\Cliente;
use App\Models\Cob\Contacto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contacto> */
class ContactoFactory extends Factory
{
    protected $model = Contacto::class;

    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'nombre' => fake()->name(),
            'email' => fake()->safeEmail(),
            'telefono' => fake()->phoneNumber(),
            'cargo' => fake()->jobTitle(),
            'activo' => true,
        ];
    }
}
