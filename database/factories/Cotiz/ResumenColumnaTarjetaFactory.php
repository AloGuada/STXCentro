<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\ResumenColumna;
use App\Models\Cotiz\ResumenColumnaTarjeta;
use App\Models\Cotiz\Tarjeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\ResumenColumnaTarjeta>
 */
class ResumenColumnaTarjetaFactory extends Factory
{
    protected $model = ResumenColumnaTarjeta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'columna_id' => ResumenColumna::factory(),
            'tarjeta_id' => Tarjeta::factory(),
        ];
    }
}
