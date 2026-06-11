<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\KilosRealesCategoria;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaEstructura;
use App\Models\Cotiz\TarjetaKilosReal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\TarjetaKilosReal>
 */
class TarjetaKilosRealFactory extends Factory
{
    protected $model = TarjetaKilosReal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tarjeta_id' => Tarjeta::factory(),
            'categoria_id' => KilosRealesCategoria::factory(),
            'estructura_id' => TarjetaEstructura::factory(),
            'kilos' => fake()->randomFloat(4, 0, 5000),
        ];
    }
}
