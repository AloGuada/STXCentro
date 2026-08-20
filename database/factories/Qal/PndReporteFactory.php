<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\MetodoPnd;
use App\Models\Qal\Laboratorio;
use App\Models\Qal\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\PndReporte>
 */
class PndReporteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fecha = $this->faker->dateTimeBetween('-6 months');

        return [
            'reporte_no' => mb_strtoupper($this->faker->unique()->bothify('??-####')),
            'metodo' => $this->faker->randomElement(MetodoPnd::cases()),
            'laboratorio_id' => Laboratorio::factory(),
            'qal_obra_id' => Obra::factory(),
            'lugar' => $this->faker->city(),
            'fecha_prueba' => $fecha,
            'fecha_emision' => $fecha,
            'anio' => (int) $fecha->format('o'),
            'semana' => (int) $fecha->format('W'),
            'porcentaje_inspeccion' => $this->faker->randomElement([10, 20, 25, 100]),
            'tecnico' => $this->faker->name(),
            'material' => 'A572 Gr.50',
            'norma' => 'AWS D1.1',
        ];
    }

    public function delMetodo(MetodoPnd $metodo): static
    {
        return $this->state(fn (): array => ['metodo' => $metodo]);
    }
}
