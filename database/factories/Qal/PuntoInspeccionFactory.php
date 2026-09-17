<?php

namespace Database\Factories\Qal;

use App\Enums\Qal\AmbitoPunto;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\TipoDatoPunto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Qal\PuntoInspeccion>
 */
class PuntoInspeccionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave' => 'px_'.$this->faker->unique()->lexify('??????'),
            'ambito' => AmbitoPunto::Pieza,
            'fase' => FaseTransformacion::Primera,
            'seccion' => 'Inspección visual',
            'etiqueta' => ucfirst($this->faker->words(3, true)),
            'tipo_dato' => TipoDatoPunto::Seleccion,
            'opciones' => [
                ['valor' => 'OK', 'resultado' => 'ok'],
                ['valor' => 'Con defecto', 'resultado' => 'no_ok'],
                ['valor' => 'n/a', 'resultado' => 'no_aplica'],
            ],
            'orden' => 0,
            'activo' => true,
        ];
    }
}
