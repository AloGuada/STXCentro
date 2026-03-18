<?php

namespace Database\Factories\Cal;

use App\Models\Cal\PiezaPlano;
use App\Models\Cal\Reporte;
use App\Models\Cal\Soldador;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cal\Reporte>
 */
class ReporteFactory extends Factory
{
    protected $model = Reporte::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plano_id' => PiezaPlano::factory(),
            'strumis_id' => fake()->optional()->bothify('STR-####'),
            'consecutivo' => (string) fake()->numberBetween(1, 100),
            'inspector_id' => Usuario::factory(),
            'es_plantilla' => false,
            'linea' => fake()->optional()->numberBetween(1, 20),
            'modulo' => fake()->optional()->numberBetween(1, 10),
            'comentario' => fake()->optional()->sentence(),
            'folio' => fake()->optional()->bothify('FOL-####'),
            'soldador_id' => Soldador::factory(),
        ];
    }

    public function plantilla(): static
    {
        return $this->state(fn (array $attributes) => [
            'es_plantilla' => true,
        ]);
    }
}
