<?php

namespace Database\Factories\Cob;

use App\Models\Cob\DocumentoSeccion;
use App\Models\Cob\DocumentoSeccionProyecto;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentoSeccionProyecto> */
class DocumentoSeccionProyectoFactory extends Factory
{
    protected $model = DocumentoSeccionProyecto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proyecto_id' => Proyecto::factory(),
            'seccion_id' => DocumentoSeccion::factory(),
            'estatus' => fake()->randomElement(['pendiente', 'completado']),
        ];
    }
}
