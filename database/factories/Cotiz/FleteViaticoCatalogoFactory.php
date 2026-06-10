<?php

namespace Database\Factories\Cotiz;

use App\Enums\Cotiz\GrupoFlete;
use App\Models\Cotiz\FleteViaticoCatalogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\FleteViaticoCatalogo>
 */
class FleteViaticoCatalogoFactory extends Factory
{
    protected $model = FleteViaticoCatalogo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grupo' => fake()->randomElement(GrupoFlete::cases()),
            'orden' => fake()->numberBetween(0, 99),
            'concepto' => fake()->words(3, true),
            'unidad' => fake()->optional()->randomElement(['viaje', 'sem', 'mes', 'pza']),
            'p_unit_default' => fake()->randomFloat(4, 0, 10000),
            'notas' => fake()->optional()->sentence(),
            'clave' => fake()->optional()->regexify('[a-z_]{5,10}'),
            'formula_cantidad' => null,
            'formula_p_unit' => null,
        ];
    }
}
