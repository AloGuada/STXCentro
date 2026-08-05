<?php

namespace Database\Factories\Prod;

use App\Models\Prod\Proceso;
use App\Models\Prod\ProcesoEvento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\ProcesoEvento>
 */
class ProcesoEventoFactory extends Factory
{
    protected $model = ProcesoEvento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proceso_id' => Proceso::factory(),
            'evento' => (string) fake()->unique()->numberBetween(100, 999),
            'descripcion' => fake()->words(2, true),
        ];
    }
}
