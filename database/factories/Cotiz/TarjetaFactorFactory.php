<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaFactor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\TarjetaFactor>
 */
class TarjetaFactorFactory extends Factory
{
    protected $model = TarjetaFactor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tarjeta_id' => Tarjeta::factory(),
            'factor_id' => Factor::factory(),
            'validado' => false,
        ];
    }
}
