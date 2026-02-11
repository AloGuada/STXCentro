<?php

namespace Database\Factories\Infra;

use App\Models\Infra\Bomba;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Infra\Bomba>
 */
class BombaFactory extends Factory
{
    protected $model = Bomba::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bomba_posos_1' => fake()->boolean(),
            'bomba_posos_2' => fake()->boolean(),
            'bomba_planta_1' => fake()->boolean(),
            'bomba_planta_2' => fake()->boolean(),
            'bomba_planta_3' => fake()->boolean(),
            'nivel_salmuera' => fake()->randomFloat(2, 0, 100),
            'nivel_tinaco' => fake()->randomFloat(2, 0, 100),
            'nivel_sisterna' => fake()->randomFloat(2, 0, 100),
            'presion_tuberia' => fake()->randomFloat(2, 30, 60),
            'nivel_hipoclorito' => fake()->randomFloat(2, 0, 100),
            'nivel_anticongelante' => fake()->randomFloat(2, 0, 100),
            'aceite_del_motor' => fake()->randomFloat(2, 0, 100),
            'tanque_diesel' => fake()->randomFloat(2, 0, 100),
            'voltaje_bateria' => fake()->randomFloat(2, 10, 14),
            'bomba_jockey' => fake()->boolean(),
            'bomba_electrica' => fake()->boolean(),
            'bomba_diesel' => fake()->boolean(),
            'presion_tuberia_incendio' => fake()->randomFloat(2, 30, 60),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }
}
