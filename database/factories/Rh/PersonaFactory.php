<?php

namespace Database\Factories\Rh;

use App\Models\Rh\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\Persona>
 */
class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'telefono' => fake()->optional()->phoneNumber(),
            'fecha_nacimiento' => fake()->optional()->dateTimeBetween('-50 years', '-18 years'),
        ];
    }
}
