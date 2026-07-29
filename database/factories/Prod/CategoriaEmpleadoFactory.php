<?php

namespace Database\Factories\Prod;

use App\Models\Prod\CategoriaEmpleado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\CategoriaEmpleado>
 */
class CategoriaEmpleadoFactory extends Factory
{
    protected $model = CategoriaEmpleado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Categoria '.fake()->unique()->numerify('##'),
            'valor' => 1500,
            'orden' => 0,
            'activo' => true,
        ];
    }
}
