<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\CentroCosto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\CentroCosto>
 */
class CentroCostoFactory extends Factory
{
    protected $model = CentroCosto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cod_coste' => fake()->unique()->regexify('[A-Z]{2}[0-9]{3}'),
            'concepto' => fake()->words(2, true),
        ];
    }
}
