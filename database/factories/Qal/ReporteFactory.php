<?php

namespace Database\Factories\Qal;

use App\Models\Qal\PiezaPlano;
use App\Models\Qal\Reporte;
use App\Models\Qal\Soldador;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Reporte>
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
            // folio se autogenera en Reporte::booted() para no-plantillas
            'folio' => null,
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
