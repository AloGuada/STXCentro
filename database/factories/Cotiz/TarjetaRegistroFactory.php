<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Insumo;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaRegistro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por defecto crea un registro MANUAL (insumo + cantidad). Para uno de generadora,
 * usar el estado `deGeneradora()`.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\TarjetaRegistro>
 */
class TarjetaRegistroFactory extends Factory
{
    protected $model = TarjetaRegistro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tarjeta_id' => Tarjeta::factory(),
            'generadora_registro_id' => null,
            'insumo_id' => Insumo::factory(),
            'cantidad' => fake()->randomFloat(4, 1, 500),
            'validado' => false,
            'tipo_pintura' => 'auto',
        ];
    }
}
