<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\FaseMontaje;
use App\Models\Cotiz\PersonalCategoria;
use App\Models\Cotiz\SeccionMontaje;
use App\Models\Cotiz\SeccionPersonal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\SeccionPersonal>
 */
class SeccionPersonalFactory extends Factory
{
    protected $model = SeccionPersonal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seccion_id' => SeccionMontaje::factory(),
            'fase_id' => FaseMontaje::factory(),
            'categoria_id' => PersonalCategoria::factory(),
            'cantidad' => fake()->numberBetween(0, 10),
        ];
    }
}
