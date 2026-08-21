<?php

namespace Database\Factories\Qal;

use App\Models\Qal\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\ObraMontaje>
 */
class ObraMontajeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hoy = Carbon::now();

        return [
            'qal_obra_id' => Obra::factory(),
            'anio' => (int) $hoy->isoFormat('GGGG'),
            'semana' => (int) $hoy->isoFormat('W'),
            'pz_montadas' => $this->faker->numberBetween(20, 400),
            'sin_incidencias' => false,
            'notas' => null,
        ];
    }

    public function enLaSemana(int $anio, int $semana): static
    {
        return $this->state(fn (): array => ['anio' => $anio, 'semana' => $semana]);
    }

    /** La semana se revisó y no hubo hallazgos, que no es lo mismo que vacía. */
    public function revisadaSinIncidencias(): static
    {
        return $this->state(fn (): array => ['sin_incidencias' => true]);
    }
}
