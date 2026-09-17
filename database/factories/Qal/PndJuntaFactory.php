<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\ResultadoPnd;
use App\Models\Qal\PndReporte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\PndJunta>
 */
class PndJuntaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qal_pnd_reporte_id' => PndReporte::factory(),
            'concepto_id' => null,
            'marca' => mb_strtoupper($this->faker->bothify('TP##-#')),
            'junta' => $this->faker->numberBetween(1, 40).'-'.$this->faker->numberBetween(1, 4),
            'modulo' => (string) $this->faker->numberBetween(1, 12),
            'spot' => $this->faker->numberBetween(1, 3),
            'resultado' => ResultadoPnd::Aceptada,
            'espesor' => $this->faker->randomFloat(2, 6, 32),
        ];
    }

    public function rechazada(): static
    {
        return $this->state(fn (): array => [
            'resultado' => ResultadoPnd::Rechazada,
            'discontinuidad' => $this->faker->randomElement(['Porosidad', 'Falta de fusión', 'Socavado']),
            'longitud_discontinuidad' => $this->faker->randomFloat(2, 1, 25),
        ]);
    }
}
