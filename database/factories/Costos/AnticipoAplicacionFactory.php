<?php

namespace Database\Factories\Costos;

use App\Models\Costos\Anticipo;
use App\Models\Costos\AnticipoAplicacion;
use App\Models\Costos\Factura;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Costos\AnticipoAplicacion>
 */
class AnticipoAplicacionFactory extends Factory
{
    protected $model = AnticipoAplicacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'anticipo_id' => Anticipo::factory(),
            'factura_id' => Factura::factory(),
            'monto' => fake()->randomFloat(2, 100, 5000),
            'fecha' => now()->toDateString(),
        ];
    }
}
