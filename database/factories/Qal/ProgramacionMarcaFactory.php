<?php

namespace Database\Factories\Qal;

use App\Models\Qal\Programacion;
use App\Models\Qal\ProgramacionMarca;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\ProgramacionMarca>
 */
class ProgramacionMarcaFactory extends Factory
{
    protected $model = ProgramacionMarca::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'programacion_id' => Programacion::factory(),
            'marca' => fake()->regexify('[A-Z]{3}-[A-Z]{2}[0-9]-[0-9]{1,2}'),
            'cantidad' => 1,
            'es_baja' => false,
        ];
    }

    public function baja(string $motivo = 'Cambio de ingeniería'): static
    {
        return $this->state(fn (): array => ['es_baja' => true, 'motivo_baja' => $motivo]);
    }
}
