<?php

namespace Database\Factories;

use App\Models\RegimenFiscal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RegimenFiscal>
 */
class RegimenFiscalFactory extends Factory
{
    protected $model = RegimenFiscal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => fake()->unique()->numerify('6##'),
            'descripcion' => fake()->sentence(3),
            'aplica_persona_fisica' => true,
            'aplica_persona_moral' => true,
            'activo' => true,
        ];
    }
}
