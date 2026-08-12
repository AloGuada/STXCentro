<?php

namespace Database\Factories\Qal;

use App\Models\Qal\Etapa;
use App\Models\Qal\Pieza;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Pieza>
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
