<?php

namespace Database\Factories\Sti;

use App\Models\Sti\Equipo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sti\Equipo>
 */
class EquipoFactory extends Factory
{
    protected $model = Equipo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->words(3, true),
            'serie' => fake()->unique()->regexify('[A-Z]{3}[0-9]{6}'),
            'marca' => fake()->randomElement(['Dell', 'HP', 'Lenovo', 'Apple', 'Asus', 'Acer']),
        ];
    }
}
