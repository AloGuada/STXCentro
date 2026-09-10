<?php

namespace Database\Factories\Cob;

use App\Models\Cob\IcsoeMes;
use App\Models\Cob\IcsoeSeguimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IcsoeMes> */
class IcsoeMesFactory extends Factory
{
    protected $model = IcsoeMes::class;

    public function definition(): array
    {
        return [
            'seguimiento_id' => IcsoeSeguimiento::factory(),
            'anio' => 2026,
            'mes' => fake()->numberBetween(1, 12),
            'dias_proyecto' => 30,
            'sbc' => 290.00,
            'sbc_aplicado' => 290.00,
            'mo_estimada' => 0,
            'dias_cotizados' => 0,
            'mo_real' => 0,
            'fuera_de_rango' => false,
        ];
    }
}
