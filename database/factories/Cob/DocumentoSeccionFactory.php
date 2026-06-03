<?php

namespace Database\Factories\Cob;

use App\Models\Cob\DocumentoSeccion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cob\DocumentoSeccion>
 */
class DocumentoSeccionFactory extends Factory
{
    protected $model = DocumentoSeccion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => strtoupper(fake()->unique()->words(2, true)),
            'orden' => fake()->numberBetween(0, 20),
            'activo' => true,
        ];
    }
}
