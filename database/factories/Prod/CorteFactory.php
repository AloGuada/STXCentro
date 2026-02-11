<?php

namespace Database\Factories\Prod;

use App\Models\Prod\Corte;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\Corte>
 */
class CorteFactory extends Factory
{
    protected $model = Corte::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fechaInicio = fake()->date();

        return [
            'semana' => fake()->numberBetween(1, 52),
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => Carbon::parse($fechaInicio)->addDays(6)->toDateString(),
            'cerrado' => false,
            'fecha_cierre' => null,
        ];
    }

    public function cerrado(): static
    {
        return $this->state(fn () => [
            'cerrado' => true,
            'fecha_cierre' => now(),
        ]);
    }
}
