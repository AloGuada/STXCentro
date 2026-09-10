<?php

namespace Database\Factories\Drive;

use App\Models\Drive\Archivo;
use App\Models\Drive\Carpeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Drive\Archivo>
 */
class ArchivoFactory extends Factory
{
    protected $model = Archivo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'carpeta_id' => Carpeta::factory(),
            'nombre_original' => fake()->word().'.pdf',
            'path' => 'drive/'.fake()->uuid().'.pdf',
            'mime' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 5242880),
            'descripcion' => fake()->optional()->sentence(),
            'subido_por_type' => 'externo',
            'subido_por_id' => '1',
        ];
    }
}
