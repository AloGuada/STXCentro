<?php

namespace Database\Factories\Prod;

use App\Models\Prod\Extra;
use App\Models\Prod\Liquidacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\Extra>
 */
class ExtraFactory extends Factory
{
    protected $model = Extra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'liquidacion_id' => Liquidacion::factory(),
            'descripcion' => fake()->sentence(3),
            'monto' => fake()->randomFloat(2, 100, 5000),
        ];
    }
}
