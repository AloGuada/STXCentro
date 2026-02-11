<?php

namespace Database\Factories;

use App\Models\Concepto;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Concepto>
 */
class ConceptoFactory extends Factory
{
    protected $model = Concepto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'marca' => fake()->unique()->regexify('[A-Z]{2}-[0-9]{3}'),
            'descripcion' => fake()->words(3, true),
            'peso_unitario' => fake()->randomFloat(3, 5, 500),
            'version' => 1,
            'activo' => true,
        ];
    }
}
