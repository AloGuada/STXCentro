<?php

namespace Database\Factories\Prod;

use App\Models\Prod\Grupo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prod\Grupo>
 */
class GrupoFactory extends Factory
{
    protected $model = Grupo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => 'Grupo '.fake()->unique()->word(),
        ];
    }
}
