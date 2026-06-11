<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaInsumoPrecio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\TarjetaInsumoPrecio>
 */
class TarjetaInsumoPrecioFactory extends Factory
{
    protected $model = TarjetaInsumoPrecio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tarjeta_id' => Tarjeta::factory(),
            'insumo_id' => Insumo::factory(),
            'precio_unitario' => fake()->randomFloat(4, 1, 1000),
        ];
    }
}
