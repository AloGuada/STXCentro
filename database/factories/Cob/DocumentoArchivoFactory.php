<?php

namespace Database\Factories\Cob;

use App\Models\Cob\DocumentoArchivo;
use App\Models\Cob\DocumentoSeccion;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cob\DocumentoArchivo>
 */
class DocumentoArchivoFactory extends Factory
{
    protected $model = DocumentoArchivo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'seccion_id' => DocumentoSeccion::factory(),
            'carpeta_id' => null,
            'nombre_original' => fake()->word().'.pdf',
            'path' => 'cob/documentos/test/'.fake()->uuid().'.pdf',
            'mime' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 5_000_000),
            'subido_por_id' => null,
        ];
    }
}
