<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\ObraVersion>
 */
class ObraVersionFactory extends Factory
{
    protected $model = ObraVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'nombre' => fake()->words(2, true),
            'nota' => null,
            'auto' => false,
            'creado_por' => null,
            'snapshot' => ['obra' => [], 'generadoras' => [], 'tarjetas' => []],
        ];
    }
}
