<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\AmbitoDefecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Defecto>
 */
class DefectoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ambito' => AmbitoDefecto::Soldadura,
            'nombre' => ucfirst($this->faker->unique()->words(2, true)),
            'activo' => true,
        ];
    }

    public function deAmbito(AmbitoDefecto $ambito): static
    {
        return $this->state(fn (): array => ['ambito' => $ambito]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
