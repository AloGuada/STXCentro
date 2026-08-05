<?php

namespace Database\Factories\Prod;

use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioProceso;
use App\Models\Prod\Proceso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\GrupoPrecioProceso>
 */
class GrupoPrecioProcesoFactory extends Factory
{
    protected $model = GrupoPrecioProceso::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grupo_precio_id' => GrupoPrecio::factory(),
            'proceso_id' => Proceso::factory(),
            'precio_kilo' => fake()->randomFloat(4, 1, 50),
        ];
    }
}
