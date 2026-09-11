<?php

namespace Database\Factories\Qal;

use App\Models\Qal\Dossier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\DossierSeccion>
 */
class DossierSeccionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_id' => Dossier::factory(),
            'padre_id' => null,
            'orden' => 1,
            'titulo' => mb_strtoupper($this->faker->words(3, true)),
            'nota' => null,
        ];
    }
}
