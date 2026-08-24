<?php

namespace Database\Factories\Prod;

use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioSubproceso;
use App\Models\Prod\Proceso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrupoPrecioSubproceso>
 */
class GrupoPrecioSubprocesoFactory extends Factory
{
    protected $model = GrupoPrecioSubproceso::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grupo_precio_id' => GrupoPrecio::factory(),
            'proceso_id' => Proceso::factory(),
            'nombre' => fake()->unique()->word(),
            'orden' => 0,
            'precio' => fake()->randomFloat(2, 10, 500),
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn (): array => ['activo' => false]);
    }
}
