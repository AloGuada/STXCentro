<?php

namespace Database\Factories\Cob;

use App\Models\Cob\Comparativo;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Comparativo> */
class ComparativoFactory extends Factory
{
    protected $model = Comparativo::class;

    public function definition(): array
    {
        return [
            'proyecto_id' => Proyecto::factory(),
            'descripcion' => fake()->paragraph(),
            'monto_impacto' => fake()->randomFloat(2, 10000, 1000000),
            'fecha_identificacion' => fake()->optional()->date(),
            'estado' => 'analisis',
        ];
    }
}
