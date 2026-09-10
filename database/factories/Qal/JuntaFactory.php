<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\ResultadoJunta;
use App\Enums\Qal\TipoJunta;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Junta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Junta>
 */
class JuntaFactory extends Factory
{
    protected $model = Junta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspeccion_id' => Inspeccion::factory(),
            'identificador' => 'J'.fake()->unique()->numberBetween(1, 9999),
            'tipo' => TipoJunta::Filete,
            'intento' => 1,
            'resultado' => ResultadoJunta::Correcta,
        ];
    }
}
