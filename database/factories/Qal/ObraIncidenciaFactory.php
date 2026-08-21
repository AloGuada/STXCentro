<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\AreaIncidencia;
use App\Enums\Qal\DepartamentoIncidencia;
use App\Models\Qal\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\ObraIncidencia>
 */
class ObraIncidenciaFactory extends Factory
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
            'fecha' => $hoy->toDateString(),
            'area' => $this->faker->randomElement(AreaIncidencia::cases()),
            'departamento' => $this->faker->randomElement(DepartamentoIncidencia::cases()),
            'pz_defecto' => $this->faker->numberBetween(1, 6),
            'folio' => mb_strtoupper($this->faker->bothify('NC-####')),
            'descripcion' => $this->faker->sentence(),
            'cerrada_en' => null,
        ];
    }

    public function enLaSemana(int $anio, int $semana): static
    {
        return $this->state(fn (): array => ['anio' => $anio, 'semana' => $semana]);
    }

    public function de(AreaIncidencia $area, DepartamentoIncidencia $departamento): static
    {
        return $this->state(fn (): array => ['area' => $area, 'departamento' => $departamento]);
    }

    public function cerrada(): static
    {
        return $this->state(fn (): array => ['cerrada_en' => Carbon::now()]);
    }
}
