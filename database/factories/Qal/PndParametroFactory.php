<?php

namespace Database\Factories\Qal;

use App\Models\Qal\PndReporte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\PndParametro>
 */
class PndParametroFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qal_pnd_reporte_id' => PndReporte::factory(),
            'clave' => $this->faker->unique()->randomElement(['Frecuencia', 'Palpador', 'Acoplante', 'Penetrante']),
            'valor' => $this->faker->word(),
        ];
    }
}
