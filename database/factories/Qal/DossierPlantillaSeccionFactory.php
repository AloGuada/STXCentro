<?php

namespace Database\Factories\Qal;

use App\Models\Qal\DossierPlantilla;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\DossierPlantillaSeccion>
 */
class DossierPlantillaSeccionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plantilla_id' => DossierPlantilla::factory(),
            'padre_id' => null,
            'orden' => 1,
            'titulo' => mb_strtoupper($this->faker->words(3, true)),
            'nota' => null,
        ];
    }
}
