<?php

namespace Database\Factories\Qal;

use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Pieza;
use App\Models\Qal\Programacion;
use App\Models\Qal\ProgramacionPieza;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\ProgramacionPieza>
 */
class ProgramacionPiezaFactory extends Factory
{
    protected $model = ProgramacionPieza::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'programacion_id' => Programacion::factory(),
            'marca' => fake()->regexify('[A-Z]{3}-[A-Z]{2}[0-9]-[0-9]{1,2}'),
            'qr' => fake()->unique()->numerify('######'),
            'grupo_trabajo_id' => GrupoTrabajo::factory(),
        ];
    }

    /** El renglón de una pieza del catálogo, con lo suyo congelado. */
    public function dePieza(Pieza $pieza): static
    {
        return $this->state(fn (): array => [
            'pieza_id' => $pieza->id,
            'concepto_id' => $pieza->concepto_id,
            'marca' => $pieza->marca?->marca,
            'lote' => $pieza->marca?->lote,
            'qr' => $pieza->qr,
            'qs' => $pieza->qs,
        ]);
    }
}
