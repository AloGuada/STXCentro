<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Unidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\Unidad>
 */
class UnidadFactory extends Factory
{
    protected $model = Unidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->unique()->randomElement(['pza', 'kg', 'm2', 'ml', 'lt', 'cil', 'tubo', 'lote']),
        ];
    }
}
