<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Merma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\Merma>
 */
class MermaFactory extends Factory
{
    protected $model = Merma::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->unique()->words(2, true),
            'formula' => 'kilos_reales * 1.03',
        ];
    }
}
