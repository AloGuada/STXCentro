<?php

namespace Database\Factories\Sti;

use App\Models\Sti\Tecnico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sti\Tecnico>
 */
class TecnicoFactory extends Factory
{
    protected $model = Tecnico::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descripcion' => fake()->name(),
        ];
    }
}
