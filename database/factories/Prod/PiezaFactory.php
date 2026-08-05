<?php

namespace Database\Factories\Prod;

use App\Models\Concepto;
use App\Models\Prod\Pieza;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\Pieza>
 */
class PiezaFactory extends Factory
{
    protected $model = Pieza::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'concepto_id' => Concepto::factory(),
            // La pieza vive en el mismo catálogo que su marca; heredarlo evita
            // crear un catálogo suelto que rompería el unique (catalogo, qs).
            'catalogo_id' => fn (array $attributes) => Concepto::find($attributes['concepto_id'])?->catalogo_id,
            'qs' => (string) fake()->unique()->numberBetween(100000, 999999),
            'activo' => true,
        ];
    }
}
