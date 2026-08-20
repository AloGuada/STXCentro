<?php

namespace Database\Factories\Qal;

use App\Models\Qal\PndReporte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\PndFoto>
 */
class PndFotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qal_pnd_reporte_id' => PndReporte::factory(),
            'ruta' => 'qal/pnd/'.$this->faker->uuid().'.jpg',
            'nombre' => $this->faker->word().'.jpg',
        ];
    }
}
