<?php

namespace Database\Factories\Cob;

use App\Models\Cob\ConfiguracionDocumento;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConfiguracionDocumento> */
class ConfiguracionDocumentoFactory extends Factory
{
    protected $model = ConfiguracionDocumento::class;

    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'nombre_documento' => fake()->words(3, true),
            'descripcion' => fake()->optional()->sentence(),
            'obligatorio' => fake()->boolean(30),
        ];
    }
}
