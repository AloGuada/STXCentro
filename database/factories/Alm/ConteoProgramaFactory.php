<?php

namespace Database\Factories\Alm;

use App\Models\Alm\Almacen;
use App\Models\Alm\ConteoPrograma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConteoPrograma>
 */
class ConteoProgramaFactory extends Factory
{
    protected $model = ConteoPrograma::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'almacen_id' => Almacen::factory(),
            'fecha_inicio' => today()->toDateString(),
            'fecha_fin' => today()->addWeek()->toDateString(),
            'dias_semana' => [1, 2, 3, 4, 5],
            'articulos_por_dia' => 10,
            'articulos_programados' => 0,
            'creado_por' => null,
        ];
    }
}
