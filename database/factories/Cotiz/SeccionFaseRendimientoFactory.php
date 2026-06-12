<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\FaseMontaje;
use App\Models\Cotiz\SeccionFaseRendimiento;
use App\Models\Cotiz\SeccionMontaje;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\SeccionFaseRendimiento>
 */
class SeccionFaseRendimientoFactory extends Factory
{
    protected $model = SeccionFaseRendimiento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seccion_id' => SeccionMontaje::factory(),
            'fase_id' => FaseMontaje::factory(),
            'concepto' => fake()->words(3, true),
            'largo_pza' => fake()->optional()->numerify('## m'),
            'cantidad' => fake()->randomFloat(2, 1, 100),
            'rendimiento' => fake()->randomFloat(2, 1, 20),
            'jornales' => null,
        ];
    }
}
