<?php

namespace Database\Factories\Cob;

use App\Models\Cob\Adenda;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Adenda> */
class AdendaFactory extends Factory
{
    protected $model = Adenda::class;

    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'tipo' => fake()->randomElement(['aumento', 'reduccion', 'cambio_especificacion', 'ampliacion_plazo']),
            'descripcion' => fake()->paragraph(),
            'monto_modificacion' => fake()->randomFloat(2, 5000, 200000),
            'fecha' => fake()->optional()->date(),
            'estado' => 'borrador',
        ];
    }
}
