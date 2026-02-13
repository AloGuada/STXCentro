<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Rubro;
use App\Models\Costos\TipoRubro;
use App\Models\Departamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\Rubro>
 */
class RubroFactory extends Factory
{
    protected $model = Rubro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->regexify('[A-Z]{2}[0-9]{3}'),
            'descripcion' => fake()->words(3, true),
            'tipo_rubro_id' => TipoRubro::factory(),
            'departamento_id' => Departamento::factory(),
        ];
    }
}
