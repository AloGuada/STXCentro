<?php

namespace Database\Factories\Qal;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\DossierPlantilla>
 */
class DossierPlantillaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Plantilla '.$this->faker->unique()->words(2, true),
            'descripcion' => $this->faker->sentence(),
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
