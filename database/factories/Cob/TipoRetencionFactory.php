<?php

namespace Database\Factories\Cob;

use App\Models\Cob\TipoRetencion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TipoRetencion> */
class TipoRetencionFactory extends Factory
{
    protected $model = TipoRetencion::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->words(2, true),
            'descripcion' => fake()->optional()->sentence(),
        ];
    }
}
