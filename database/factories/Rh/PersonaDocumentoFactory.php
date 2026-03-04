<?php

namespace Database\Factories\Rh;

use App\Models\Media;
use App\Models\Rh\Persona;
use App\Models\Rh\PersonaDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rh\PersonaDocumento>
 */
class PersonaDocumentoFactory extends Factory
{
    protected $model = PersonaDocumento::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'persona_id' => Persona::factory(),
            'media_id' => Media::factory(),
            'tipo_documento' => fake()->randomElement(['ine', 'curp', 'rfc', 'comprobante_domicilio', 'acta_nacimiento']),
        ];
    }
}
