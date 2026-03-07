<?php

namespace Database\Factories\Rh;

use App\Models\Rh\DatosExtra;
use App\Models\Rh\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\DatosExtra>
 */
class DatosExtraFactory extends Factory
{
    protected $model = DatosExtra::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'persona_id' => Persona::factory(),
            'estado_civil' => fake()->optional()->randomElement(['soltero', 'casado', 'divorciado', 'viudo']),
            'hijos' => fake()->optional()->numberBetween(0, 5),
            'localidad' => fake()->optional()->city(),
            'domicilio' => fake()->optional()->address(),
            'cp' => fake()->optional()->postcode(),
            'curp' => fake()->optional()->regexify('[A-Z]{4}[0-9]{6}[A-Z]{6}[0-9]{2}'),
            'rfc' => fake()->optional()->regexify('[A-Z]{4}[0-9]{6}[A-Z0-9]{3}'),
            'numero_ine' => fake()->optional()->numerify('#############'),
        ];
    }
}
