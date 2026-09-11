<?php

namespace Database\Factories\Qal;

use App\Models\Qal\DossierSeccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\DossierArchivo>
 */
class DossierArchivoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seccion_id' => DossierSeccion::factory(),
            'orden' => 1,
            'nombre_original' => $this->faker->slug(2).'.pdf',
            'path' => 'qal/dosier/'.$this->faker->uuid().'.pdf',
            'size' => $this->faker->numberBetween(10_000, 900_000),
            'paginas' => $this->faker->numberBetween(1, 12),
            'compatible' => true,
        ];
    }

    public function incompatible(): static
    {
        return $this->state(fn (): array => ['compatible' => false, 'paginas' => null]);
    }
}
