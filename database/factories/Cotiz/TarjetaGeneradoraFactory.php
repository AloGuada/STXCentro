<?php

namespace Database\Factories\Cotiz;

use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\Tarjeta;
use App\Models\Cotiz\TarjetaGeneradora;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cotiz\TarjetaGeneradora>
 */
class TarjetaGeneradoraFactory extends Factory
{
    protected $model = TarjetaGeneradora::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tarjeta_id' => Tarjeta::factory(),
            'generadora_id' => Generadora::factory(),
        ];
    }
}
