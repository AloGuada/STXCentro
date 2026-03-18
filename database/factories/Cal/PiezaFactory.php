<?php

namespace Database\Factories\Cal;

use App\Models\Cal\Etapa;
use App\Models\Cal\Pieza;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cal\Pieza>
 */
class PiezaFactory extends Factory
{
    protected $model = Pieza::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'marca' => fake()->bothify('MK-####'),
            'cantidad' => fake()->numberBetween(1, 10),
            'etapa_id' => Etapa::factory(),
        ];
    }
}
