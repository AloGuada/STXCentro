<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaEstructura;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\TarjetaEstructura>
 */
class TarjetaEstructuraFactory extends Factory
{
    protected $model = TarjetaEstructura::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tarjeta_id' => Tarjeta::factory(),
            'nombre' => fake()->randomElement(['NAVE', 'NAVE B', 'MEZZANINE', 'ANEXO']),
            'orden' => fake()->numberBetween(0, 10),
        ];
    }
}
