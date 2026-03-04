<?php

namespace Database\Factories\Rh;

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
            'tipo_documento' => fake()->randomElement(['ine', 'curp', 'rfc', 'comprobante_domicilio', 'acta_nacimiento']),
            'nombre_archivo' => fake()->word().'.pdf',
            'ruta_archivo' => 'rh/documentos/'.fake()->uuid().'.pdf',
            'extension' => 'pdf',
            'tamano' => fake()->numberBetween(10000, 5000000),
        ];
    }
}
