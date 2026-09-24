<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\FaseTransformacion;
use App\Models\Obra as ObraDelPortal;
use App\Models\Qal\Programacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\Programacion>
 */
class ProgramacionFactory extends Factory
{
    protected $model = Programacion::class;

    /**
     * El plan de 2ª de esta semana, abierto y sin piezas.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hoy = now();

        return [
            'obra_id' => ObraDelPortal::factory(),
            'fase' => FaseTransformacion::Segunda,
            'anio' => $hoy->isoWeekYear(),
            'semana' => $hoy->isoWeek(),
            'notas' => null,
        ];
    }

    /** El plan ya comprometido: cuenta y lo ve Calidad. */
    public function cerrada(): static
    {
        return $this->state(fn (): array => ['cerrada_at' => now()]);
    }
}
