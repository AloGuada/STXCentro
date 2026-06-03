<?php

namespace Database\Factories\Cob;

use App\Models\Cob\DocumentoCarpeta;
use App\Models\Cob\DocumentoSeccion;
use App\Models\Obra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cob\DocumentoCarpeta>
 */
class DocumentoCarpetaFactory extends Factory
{
    protected $model = DocumentoCarpeta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'obra_id' => Obra::factory(),
            'seccion_id' => DocumentoSeccion::factory(),
            'parent_id' => null,
            'nombre' => fake()->words(2, true),
            'orden' => 0,
        ];
    }
}
