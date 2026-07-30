<?php

namespace Database\Factories\Prod;

use App\Models\Prod\Destajo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\Destajo>
 */
class DestajoFactory extends Factory
{
    protected $model = Destajo::class;

    /**
     * Contador de proceso para garantizar (anio, semana) unico entre creaciones.
     */
    protected static int $contador = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        self::$contador++;
        $anio = 2020 + intdiv(self::$contador - 1, 52);
        $semana = ((self::$contador - 1) % 52) + 1;

        $fechaInicio = Carbon::now()->setISODate($anio, $semana)->startOfWeek(Carbon::MONDAY);

        return [
            'anio' => $anio,
            'semana' => $semana,
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaInicio->copy()->addDays(6)->toDateString(),
            'cerrado' => false,
            'fecha_cierre' => null,
        ];
    }

    public function cerrado(): static
    {
        return $this->state(fn () => [
            'cerrado' => true,
            'fecha_cierre' => now(),
        ]);
    }
}
