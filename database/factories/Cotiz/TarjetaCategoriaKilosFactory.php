<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\KilosRealesCategoria;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaCategoriaKilos;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\TarjetaCategoriaKilos>
 */
class TarjetaCategoriaKilosFactory extends Factory
{
    protected $model = TarjetaCategoriaKilos::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tarjeta_id' => Tarjeta::factory(),
            'categoria_id' => KilosRealesCategoria::factory(),
            'orden' => fake()->numberBetween(0, 10),
            'porcentual' => null,
        ];
    }
}
